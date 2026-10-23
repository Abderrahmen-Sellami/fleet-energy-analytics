<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use League\Csv\ByteSequence;
use League\Csv\Writer;

// Export CSV de données brutes, en complément du rapport PDF (window.print() côté client, aucune
// génération serveur) — league/csv plutôt qu'une lib Excel : un simple fichier texte, pas de mise en
// forme/formules à gérer, largement suffisant pour un premier export tableur.
//
// Délimiteur ';' + décimales en virgule française (cohérent avec fmt() côté JS et les exports CSV
// client-side déjà existants du dashboard, ex. btnExport) : c'est la convention CSV attendue par
// Excel en locale FR (','  est le séparateur décimal par défaut) — un export en '.'/',' s'ouvrirait
// avec toutes les colonnes numériques fusionnées en une seule. BOM UTF-8 ajouté explicitement : sans
// lui, Excel (contrairement à Google Sheets, qui détecte l'UTF-8 correctement dans les deux cas)
// interprète les caractères accentués comme du Latin-1 et les affiche corrompus.
class ExportCsvController extends Controller
{
    private const DELIMITER = ';';

    // Réutilise CamionController::mesures() (source unique déjà utilisée par la Fiche camion à
    // l'écran) plutôt que de requêter mesures directement : si son filtrage/sa logique évolue un
    // jour, cet export suit automatiquement sans double maintenance. Un id non numérique/inexistant
    // lève la même ModelNotFoundException que l'appel direct de la route, remontée telle quelle
    // jusqu'au handler global (bootstrap/app.php) — même 404 JSON propre que le reste de l'API.
    public function ficheCamion(int $id): Response
    {
        $reponse = app(CamionController::class)->mesures($id);
        $data = $reponse->getData(true);
        $camion = $data['camion'];

        // Même filtre que mesuresFiltrees() côté frontend (dashboard.blade.php) en mode "tout
        // l'historique" : exclut le mois en cours (est_partielle) et les mesures non exploitables
        // — jamais mélanger un mois complet et un mois entamé dans une donnée exportée pour
        // analyse.
        $mesures = array_values(array_filter(
            $data['mesures'],
            fn (array $m) => ($m['conso_exploitable'] ?? false) && !($m['est_partielle'] ?? false)
        ));

        $csv = $this->nouveauCsv();
        $csv->insertOne(['Mois', 'Conso (L/100km)', 'L/Heure', 'Km parcourus', 'Heures moteur', 'Eco Score']);

        foreach ($mesures as $m) {
            $litresMois = $m['consommation_l100'] !== null && $m['km_parcourus'] !== null
                ? $m['consommation_l100'] * $m['km_parcourus'] / 100
                : null;
            $lHeure = $litresMois !== null && ($m['heures_moteur'] ?? 0) > 0
                ? $litresMois / $m['heures_moteur']
                : null;

            $csv->insertOne([
                substr($m['date'], 0, 7),
                $this->nombre($m['consommation_l100']),
                $this->nombre($lHeure, 1),
                $this->nombre($m['km_parcourus'], 0),
                $this->nombre($m['heures_moteur'], 0),
                $this->nombre($m['ecoscore']),
            ]);
        }

        return $this->telecharger($csv, "fiche_camion_{$camion['immatriculation']}");
    }

    // Mêmes garanties que la courbe à l'écran : whitelist de métrique + bornes de plausibilité +
    // est_partielle proviennent de CourbesController::mensuel()/annuel(), jamais dupliqués ici — une
    // métrique inconnue renvoie exactement le même 422 JSON que /api/courbes/{id}/mensuel/{metrique}
    // (propagé tel quel, cf. la boucle ci-dessous), un id invalide le même 404 JSON.
    public function courbes(int $id, Request $request): Response|JsonResponse
    {
        $mode = $request->query('mode', 'mensuel');
        if (!in_array($mode, ['mensuel', 'annuel'], true)) {
            return response()->json(['message' => "Mode inconnu : {$mode}. Valeurs acceptées : mensuel, annuel"], 422);
        }

        $metriques = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('metriques', '')))));
        if (!$metriques) {
            return response()->json(['message' => 'Paramètre metriques requis (liste séparée par des virgules).'], 422);
        }

        $courbesController = app(CourbesController::class);
        $camion = Camion::findOrFail($id);

        // parPeriode[metrique][periode] = ['valeur' => ..., 'est_partielle' => ...]
        $parMetrique = [];
        foreach ($metriques as $metrique) {
            $reponse = $mode === 'mensuel'
                ? $courbesController->mensuel($id, $metrique)
                : $courbesController->annuel($id, $metrique);

            if ($reponse instanceof JsonResponse && $reponse->getStatusCode() >= 400) {
                return $reponse;
            }

            $lignes = $reponse->getData(true);
            $parPeriode = [];
            foreach ($lignes as $ligne) {
                $periode = $mode === 'mensuel' ? $ligne['mois'] : (string) $ligne['annee'];
                $parPeriode[$periode] = $mode === 'mensuel'
                    ? ['valeur' => $ligne['valeur'], 'est_partielle' => $ligne['est_partielle']]
                    : ['valeur' => $ligne['moyenne'], 'est_partielle' => false];
            }
            $parMetrique[$metrique] = $parPeriode;
        }

        // array_values() avant le spread : $parMetrique est indexé par nom de métrique (clés
        // textuelles) — sans ça, `...` le déballe en arguments nommés (PHP 8.1+) plutôt qu'en
        // arguments positionnels, qu'array_merge() rejette avec ArgumentCountError.
        $periodesUnion = array_unique(array_merge(...array_values(array_map('array_keys', $parMetrique))));
        sort($periodesUnion);

        $csv = $this->nouveauCsv();

        // Désambiguïsation par groupe — depuis que les libellés ne mentionnent plus leur source
        // (cf. CourbesController::LABELS), deux métriques de groupes différents peuvent partager
        // le même texte (ex. "Consommation (L/100km)" existe côté Télématique A, Registre et
        // Télématique B)
        $labels = array_map(fn ($metrique) => CourbesController::labelPour($metrique) ?? $metrique, $metriques);
        $occurrences = array_count_values($labels);

        $entete = [$mode === 'mensuel' ? 'Mois' : 'Année'];
        foreach ($metriques as $i => $metrique) {
            $label = $labels[$i];
            if (($occurrences[$label] ?? 0) > 1 && ($groupe = CourbesController::groupePour($metrique)) !== null) {
                $label .= " — {$groupe}";
            }
            $entete[] = $label;
        }
        if ($mode === 'mensuel') {
            $entete[] = 'Mois en cours (provisoire)';
        }
        $csv->insertOne($entete);

        foreach ($periodesUnion as $periode) {
            $ligne = [$periode];
            $partielle = false;
            foreach ($metriques as $metrique) {
                $point = $parMetrique[$metrique][$periode] ?? null;
                $ligne[] = $this->nombre($point['valeur'] ?? null);
                $partielle = $partielle || ($point['est_partielle'] ?? false);
            }
            if ($mode === 'mensuel') {
                $ligne[] = $partielle ? 'oui' : '';
            }
            $csv->insertOne($ligne);
        }

        return $this->telecharger($csv, "courbes_{$camion->immatriculation}_{$mode}");
    }

    // \r\n explicite : league/csv écrit du \n seul par défaut (vérifié), pas RFC 4180-conforme et
    // Excel-Windows (contrairement à Google Sheets et aux Excel récents, plus tolérants) peut afficher
    // tout le fichier sur une seule ligne visuelle sans le \r.
    private function nouveauCsv(): Writer
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter(self::DELIMITER);
        $csv->setOutputBOM(ByteSequence::BOM_UTF8);
        $csv->setEndOfLine("\r\n");

        return $csv;
    }

    // int|float|string : CourbesController::annuel() vient d'une requête DB::table() brute
    // (AVG/SUM en SQL), jamais castée par Eloquent contrairement à mensuel() — MySQL/Laravel renvoie
    // ces agrégats en string, pas en float, d'où le cast explicite ici plutôt qu'un type strict.
    private function nombre(int|float|string|null $valeur, int $decimales = 1): string
    {
        return $valeur === null ? '' : number_format((float) $valeur, $decimales, ',', '');
    }

    private function telecharger(Writer $csv, string $prefixeNom): Response
    {
        $nomFichier = $prefixeNom.'_'.now()->format('Y-m-d').'.csv';

        return response($csv->toString(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nomFichier}\"",
        ]);
    }
}
