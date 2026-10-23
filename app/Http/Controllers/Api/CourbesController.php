<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\Concerns\VehiculesSourceB;
use App\Http\Controllers\Controller;
use App\Models\Camion;
use App\Models\ConsommationSourceB;
use App\Models\Mesure;
use App\Models\MesureSourceB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CourbesController extends Controller
{
    // Source unique des métriques exposables en courbes : whitelist de champs + libellé FR + unité
    // réelle, utilisée à la fois pour le sélecteur du frontend (metriquesDisponibles) et pour
    // valider toute métrique reçue en paramètre d'URL (resoudreSource) avant de l'utiliser en SQL.
    private const LABELS = [
        'source_a' => [
            'consommation_l100' => ['label' => 'Consommation (L/100km)', 'unite' => 'L/100km'],
            'conso_horaire' => ['label' => 'Consommation (L/h)', 'unite' => 'L/h'],
            // Litres bruts consommés — pas une colonne stockée : consommation_l100 (L/100km) ×
            // km_parcourus (km) / 100.
            'litres_consommes' => ['label' => 'Consommation (L)', 'unite' => 'L'],
            'km_parcourus' => ['label' => 'Kilomètres parcourus (km)', 'unite' => 'km'],
            'heures_moteur' => ['label' => 'Heures moteur (h)', 'unite' => 'h'],
            'ecoscore' => ['label' => 'Eco Score (/10)', 'unite' => '/10'],
            'anticipation' => ['label' => 'Anticipation (/10)', 'unite' => '/10'],
            'utilisation_abusive' => ['label' => 'Utilisation abusive (/10)', 'unite' => '/10'],
            'utilisation_optimale' => ['label' => 'Utilisation optimale (/10)', 'unite' => '/10'],
            'arret' => ['label' => 'Arrêt / Ralenti (/10)', 'unite' => '/10'],
            'vitesse_moyenne' => ['label' => 'Vitesse moyenne (km/h)', 'unite' => 'km/h'],
            'nb_arrets' => ['label' => "Nombre d'arrêts (arrêts/100km)", 'unite' => '/100km'],
            // Indicateurs bruts (indicateurBrut(), pas scoreSur10())
            'usage_mode_puissance' => ['label' => 'Usage mode Puissance (indice)', 'unite' => 'indice brut'],
            'engine_overrev' => ['label' => 'Sur-régime moteur (indice)', 'unite' => 'indice brut'],
        ],
        'registre' => [
            'pe_l100' => ['label' => 'Consommation (L/100km)', 'unite' => 'L/100km'],
            'pe_lh' => ['label' => 'Consommation (L/h)', 'unite' => 'L/h'],
            'km' => ['label' => 'Kilomètres parcourus (km)', 'unite' => 'km'],
            'litres' => ['label' => 'Consommation (L)', 'unite' => 'L'],
            'heures' => ['label' => 'Heures (h)', 'unite' => 'h'],
            // Unité de la SER : L/100km pour les usages Transport.
            'ser' => ['label' => 'SER (L/100km)', 'unite' => 'L/100km', 'info' => 'Usages Transport uniquement — SER exprimée en L/h pour les autres usages (toupies, engins...), non représentée sur cet axe.'],
            'ser_1x' => ['label' => 'Seuil SER×1,x% (L/100km)', 'unite' => 'L/100km', 'info' => 'Usages Transport uniquement — voir la note sur SER ci-dessus.'],
            'cible' => ['label' => 'Cible norme interne (L/100km)', 'unite' => 'L/100km', 'info' => 'Usages Transport uniquement — voir la note sur SER ci-dessus.'],
        ],
        // 3e groupe — distance (mesures_source_b, mileage, agrégée par mois) et consommation.
        'source_b' => [
            'distance_km' => ['label' => 'Distance (km)', 'unite' => 'km'],
            'consommation_l100km' => ['label' => 'Consommation (L/100km)', 'unite' => 'L/100km'],
            // Litres bruts consommés — somme des volumes de plein
            // (consommations_source_b.volume_l) par mois, cf. litresSourceBParMois() ci-dessous.
            'litres_source_b' => ['label' => 'Consommation (L)', 'unite' => 'L'],
        ],
    ];

    // Affichage FR du groupe, pour désambiguïser un export CSV multi-métriques quand deux clés de
    // groupes différents partagent le même libellé (cf. commentaire de LABELS ci-dessus) — jamais
    // affiché dans le sélecteur/la légende du dashboard, où le regroupement visuel suffit déjà.
    private const GROUPES_AFFICHES = ['source_a' => 'Télématique A', 'registre' => 'Registre', 'source_b' => 'Télématique B'];

    // Accesseur public sur GROUPES_AFFICHES, pour ExportCsvController — cf. LABELS ci-dessus.
    public static function groupePour(string $champ): ?string
    {
        foreach (self::LABELS as $groupe => $metriques) {
            if (array_key_exists($champ, $metriques)) {
                return self::GROUPES_AFFICHES[$groupe];
            }
        }

        return null;
    }

    // Ces champs stockent 0 (au lieu de NULL) pour les mois sans données Télématique A — même
    // convention déjà appliquée ailleurs dans l'app (ex. sous-scores de la fiche camion,
    // moyennes eco_score). On la reproduit ici pour ne pas tracer un "0" comme une vraie mesure.
    private const ZERO_SIGNIFIE_ABSENCE = ['ecoscore', 'anticipation', 'utilisation_abusive', 'utilisation_optimale', 'arret', 'consommation_l100'];

    // Bornes hautes de plausibilité, par champ — au-delà.
    private const BORNES_HAUTES = [
        'consommation_l100' => Mesure::CONSO_L100_MAX_PLAUSIBLE,
        'vitesse_moyenne' => 130.0,
        'nb_arrets' => 400.0,
    ];

    // Bornes basses de plausibilité : une valeur strictement négative est physiquement impossible
    // pour ces deux champs (on ne roule pas à une vitesse négative, on ne compte pas un nombre
    // d'arrêts négatif) — distinct de ZERO_SIGNIFIE_ABSENCE ci-dessus, qui documente que 0 est un
    // sentinelle Télématique A volontaire pour "pas de donnée ce mois-ci" sur d'autres champs ; ici on
    // n'a aucune preuve que 0 ait ce même sens pour vitesse_moyenne/nb_arrets (jamais observé en
    // base), donc on exclut uniquement le strictement négatif, sans toucher à un éventuel futur 0
    // légitime.
    private const BORNES_BASSES = [
        'vitesse_moyenne' => 0.0,
        'nb_arrets' => 0.0,
    ];

    // Accesseur public sur LABELS (resté private) pour ExportCsvController — juste un libellé FR,
    // pas de logique de validation à dupliquer/réexposer.
    public static function labelPour(string $champ): ?string
    {
        return self::LABELS['source_a'][$champ]['label'] ?? self::LABELS['registre'][$champ]['label'] ?? self::LABELS['source_b'][$champ]['label'] ?? null;
    }

    // Liste toutes les métriques disponibles, pour que le frontend propose un sélecteur complet.
    // 'info' (optionnel, cf. LABELS['registre']['ser'/'ser_1x'/'cible']) : texte d'une info-bulle à
    // afficher à côté de la case à cocher, absent (null) pour toutes les autres métriques.
    public function metriquesDisponibles()
    {
        $mappe = fn ($groupe) => collect(self::LABELS[$groupe])
            ->map(fn ($info, $champ) => ['champ' => $champ, 'label' => $info['label'], 'unite' => $info['unite'], 'info' => $info['info'] ?? null])
            ->values();

        return response()->json([
            'source_a' => $mappe('source_a'),
            'registre' => $mappe('registre'),
            'source_b' => $mappe('source_b'),
        ]);
    }

    public function mensuel(int $id, string $metrique)
    {
        $camion = Camion::findOrFail($id);
        $resolu = $this->resoudreSource($metrique);
        if ($resolu instanceof JsonResponse) {
            return $resolu;
        }
        [$source, $champ] = $resolu;

        if ($source === 'source_b') {
            return response()->json($this->donneesSourceBMensuelles($camion, $champ));
        }

        if ($source === 'source_a' && $champ === 'litres_consommes') {
            return response()->json($this->litresSourceAMensuel($camion));
        }

        // est_partielle n'existe que côté Télématique A (mesures) — registre manuel n'a pas ce
        // concept, le registre norme interne n'étant jamais alimenté en quasi temps réel.
        $donnees = $source === 'source_a'
            ? $camion->mesures()->orderBy('date')->get(['date', $champ, 'est_partielle'])
            : $camion->registreMesures()->orderBy('date')->get(['date', $champ]);

        $zeroAbsent = in_array($champ, self::ZERO_SIGNIFIE_ABSENCE, true);
        $borneHaute = self::BORNES_HAUTES[$champ] ?? null;
        $borneBasse = self::BORNES_BASSES[$champ] ?? null;

        // Le mois en cours (est_partielle) reste dans la liste — contrairement aux
        // moyennes/statuts (scopeExploitable, ProjectionController, annuel() ci-dessous...), une
        // courbe est un historique VISUEL : le point du mois en cours doit apparaître, marqué
        // distinctement côté frontend.
        return response()->json($donnees->map(function ($m) use ($champ, $zeroAbsent, $borneHaute, $borneBasse, $source) {
            $valeur = $m->$champ;
            if ($zeroAbsent && $valeur !== null && (float) $valeur <= 0) {
                $valeur = null;
            }
            if ($borneHaute !== null && $valeur !== null && (float) $valeur > $borneHaute) {
                $valeur = null;
            }
            if ($borneBasse !== null && $valeur !== null && (float) $valeur < $borneBasse) {
                $valeur = null;
            }
            return [
                'mois' => \Carbon\Carbon::parse($m->date)->format('Y-m'),
                'valeur' => $valeur,
                'est_partielle' => $source === 'source_a' ? (bool) $m->est_partielle : false,
            ];
        }));
    }

    public function annuel(int $id, string $metrique)
    {
        $camion = Camion::findOrFail($id);
        $resolu = $this->resoudreSource($metrique);
        if ($resolu instanceof JsonResponse) {
            return $resolu;
        }
        [$source, $champ] = $resolu;

        if ($source === 'source_b') {
            return response()->json($this->donneesSourceBAnnuelles($camion, $champ));
        }

        if ($source === 'source_a' && $champ === 'litres_consommes') {
            return response()->json($this->litresSourceAAnnuel($camion));
        }

        $table = $source === 'source_a' ? 'mesures' : 'registre_mesures';

        $query = DB::table($table)
            ->select(DB::raw("YEAR(date) as annee"), DB::raw("AVG($champ) as moyenne"), DB::raw("SUM($champ) as total"))
            ->where('camion_id', $camion->id)
            ->whereNotNull($champ);

        // Une moyenne/somme annuelle ne doit jamais mélanger un mois complet et le mois en cours —
        // est_partielle n'existe que sur mesures (Télématique A)
        if ($table === 'mesures') {
            $query->where('est_partielle', false);
        }

        if (in_array($champ, self::ZERO_SIGNIFIE_ABSENCE, true)) {
            $query->where($champ, '>', 0);
        }
        if (isset(self::BORNES_HAUTES[$champ])) {
            $query->where($champ, '<=', self::BORNES_HAUTES[$champ]);
        }
        if (isset(self::BORNES_BASSES[$champ])) {
            $query->where($champ, '>=', self::BORNES_BASSES[$champ]);
        }

        $donnees = $query->groupBy(DB::raw("YEAR(date)"))->orderBy('annee')->get();

        return response()->json($donnees);
    }

    // "Consommation (L)" — pas une colonne stockée : consommation_l100 (L/100km) × km_parcourus
    // (km) / 100.
    private function litresSourceAMensuel(Camion $camion): array
    {
        $donnees = $camion->mesures()->orderBy('date')->get(['date', 'consommation_l100', 'km_parcourus', 'est_partielle']);

        return $donnees->map(function ($m) {
            $valeur = null;
            if (Mesure::estValeurConsoExploitable($m->consommation_l100) && $m->km_parcourus !== null) {
                $valeur = round((float) $m->consommation_l100 * (float) $m->km_parcourus / 100, 1);
            }

            return [
                'mois' => \Carbon\Carbon::parse($m->date)->format('Y-m'),
                'valeur' => $valeur,
                'est_partielle' => (bool) $m->est_partielle,
            ];
        })->values()->all();
    }

    // Même règle que le reste du projet : jamais un mois en cours (est_partielle) dans une
    // moyenne/ somme annuelle.
    private function litresSourceAAnnuel(Camion $camion): array
    {
        $donnees = $camion->mesures()
            ->where('est_partielle', false)
            ->orderBy('date')
            ->get(['date', 'consommation_l100', 'km_parcourus']);

        return $donnees
            ->filter(fn ($m) => Mesure::estValeurConsoExploitable($m->consommation_l100) && $m->km_parcourus !== null)
            ->map(fn ($m) => [
                'annee' => \Carbon\Carbon::parse($m->date)->year,
                'litres' => (float) $m->consommation_l100 * (float) $m->km_parcourus / 100,
            ])
            ->groupBy('annee')
            ->map(fn ($lignes, $annee) => [
                'annee' => (int) $annee,
                'moyenne' => round($lignes->avg('litres'), 2),
                'total' => round($lignes->sum('litres'), 2),
            ])
            ->sortBy('annee')
            ->values()
            ->all();
    }

    private function resoudreSource(string $metrique): array|JsonResponse
    {
        if (array_key_exists($metrique, self::LABELS['source_a'])) {
            return ['source_a', $metrique];
        }

        if (array_key_exists($metrique, self::LABELS['registre'])) {
            return ['registre', $metrique];
        }

        if (array_key_exists($metrique, self::LABELS['source_b'])) {
            return ['source_b', $metrique];
        }

        return response()->json(['message' => "Métrique inconnue : {$metrique}"], 422);
    }

    // Mois calendaire en cours (Etc/GMT-1) — Télématique B n'a pas de flag est_partielle stocké
    // comme Télématique A, mais la même règle de fiabilité s'applique : un mois qui n'est pas
    // encore terminé ne doit jamais être traité comme un mois complet.
    private function sourceBMoisCourant(): string
    {
        return Carbon::now('Etc/GMT-1')->format('Y-m');
    }

    // Distance Télématique B agrégée par mois (mesures_source_b, source=mileage, une ligne par
    // jour)
    private function distanceSourceBParMois(Camion $camion): \Illuminate\Support\Collection
    {
        return MesureSourceB::where('camion_id', $camion->id)
            ->where('source', 'mileage')
            ->get(['horodatage_record', 'distance_km'])
            ->groupBy(fn ($m) => $m->horodatage_record->format('Y-m'))
            ->map(fn ($jours) => round((float) $jours->sum('distance_km'), 1));
    }

    // Consommation Télématique B agrégée par mois (consommations_source_b, méthode plein-à-plein)
    // — moyenne PONDÉRÉE (somme volume / somme distance), jamais une moyenne simple d'intervalles,
    // même principe que
    // ConsommationSourceB::resumer()/ComparateurRegistreController::sourceBParMois()
    private function consommationSourceBParMois(Camion $camion): \Illuminate\Support\Collection
    {
        return ConsommationSourceB::where('camion_id', $camion->id)
            ->whereNotNull('consommation_l100km')
            ->whereNotNull('plein_suivant_date')
            ->get()
            ->groupBy(fn ($c) => $c->plein_suivant_date->format('Y-m'))
            ->map(function ($intervalles) {
                $sommeVolume = $intervalles->sum('volume_l');
                $sommeDistance = $intervalles->sum('distance_km');

                return $sommeDistance > 0 ? round($sommeVolume / $sommeDistance * 100, 2) : null;
            })
            ->filter(fn ($v) => $v !== null);
    }

    // Litres bruts consommés Télématique B agrégés par mois — somme des volumes de plein
    // (consommations_source_b.volume_l) par mois, bucketés sur plein_suivant_date comme
    // consommationSourceBParMois() ci-dessus.
    private function litresSourceBParMois(Camion $camion): \Illuminate\Support\Collection
    {
        return ConsommationSourceB::where('camion_id', $camion->id)
            ->whereNotNull('volume_l')
            ->whereNotNull('plein_suivant_date')
            ->get(['plein_suivant_date', 'volume_l'])
            ->groupBy(fn ($c) => $c->plein_suivant_date->format('Y-m'))
            ->map(fn ($intervalles) => round((float) $intervalles->sum('volume_l'), 1));
    }

    // Gate commune aux deux métriques Télématique B de Courbes complètes.
    private function sourceBDisponiblePour(Camion $camion): bool
    {
        return array_key_exists($camion->immatriculation, VehiculesSourceB::DEVICE_PAR_PLAQUE);
    }

    private function donneesSourceBMensuelles(Camion $camion, string $champ): array
    {
        if (!$this->sourceBDisponiblePour($camion)) {
            return [];
        }

        $parMois = match ($champ) {
            'distance_km' => $this->distanceSourceBParMois($camion),
            'litres_source_b' => $this->litresSourceBParMois($camion),
            default => $this->consommationSourceBParMois($camion),
        };
        $moisCourant = $this->sourceBMoisCourant();

        return $parMois->map(fn ($valeur, $mois) => [
            'mois' => $mois,
            'valeur' => $valeur,
            'est_partielle' => $mois === $moisCourant,
        ])->sortBy('mois')->values()->all();
    }

    private function donneesSourceBAnnuelles(Camion $camion, string $champ): array
    {
        if (!$this->sourceBDisponiblePour($camion)) {
            return [];
        }

        $parMois = match ($champ) {
            'distance_km' => $this->distanceSourceBParMois($camion),
            'litres_source_b' => $this->litresSourceBParMois($camion),
            default => $this->consommationSourceBParMois($camion),
        };
        $moisCourant = $this->sourceBMoisCourant();

        // Même règle que le reste du projet : une moyenne annuelle ne mélange jamais un mois
        // complet et le mois en cours.
        return $parMois
            ->reject(fn ($valeur, $mois) => $mois === $moisCourant)
            ->groupBy(fn ($valeur, $mois) => substr($mois, 0, 4))
            ->map(fn ($valeursAnnee, $annee) => [
                'annee' => (int) $annee,
                'moyenne' => round($valeursAnnee->avg(), 2),
                'total' => round($valeursAnnee->sum(), 2),
            ])
            ->sortBy('annee')
            ->values()
            ->all();
    }
}
