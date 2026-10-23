<?php

namespace App\Console\Commands;

use App\Models\Camion;
use App\Models\SerNormeRefHistorique;
use App\Models\RegistreMesure;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Revolution\Google\Sheets\Facades\Sheets;

class SyncRegistreSheets extends Command
{
    protected $signature = 'sheets:sync-registre {--mois=3 : Nombre de mois récents (par site) pour lesquels récupérer % SER/usage et P.E précis depuis les fichiers norme interne mensuels individuels — mettre un grand nombre (ex: 999) pour un backfill complet de l\'historique, une fois}';

    protected $description = "Synchronise registre_mesures depuis l'onglet DATA du registre tableur (Google Sheets), "
        . "en remplacement de l'export CSV manuel (registre:import). Idempotent (updateOrCreate camion_id+date). "
        . "Enrichit aussi les mois récents avec % SER/usage et P.E (l/h) précis, lus directement dans les fichiers "
        . "norme interne mensuels individuels (cf. synchroniserColonnesRecapDataStudio()).";

    // Positions FIXES des colonnes dans l'onglet DATA — pas d'en-tête à lire.
    private const COL_USAGE = 0;
    private const COL_DATE = 1;
    private const COL_ENGIN = 2; // ignoré
    private const COL_MATRICULE = 3;
    private const COL_DESIGNATION = 4; // ignoré
    private const COL_SER = 5;
    private const COL_SER_1X = 6;
    private const COL_CIBLE = 7;
    private const COL_KM = 8;
    private const COL_HEURES = 9;
    private const COL_LITRES = 10;
    private const COL_PE_LH = 11;
    private const COL_PE_L100 = 12;
    private const COL_SITE = 13;

    private const NB_COLONNES_MIN = 14;

    // Champs numériques comparés à la valeur déjà en base avant écrasement (cf. garde de
    // divergence) — usage/site sont du texte, pas concernés par un seuil en %.
    private const CHAMPS_NUMERIQUES = ['ser', 'ser_1x', 'cible', 'km', 'heures', 'litres', 'pe_lh', 'pe_l100'];

    // Seuil au-delà duquel un écart camion_id+date+champ est signalé avant écrasement (non
    // bloquant — juste une trace explicite, cf. cas DEMO-006/mars 2026 : litres=131 (CSV, une
    // erreur de frappe confirmée) vs 1031 (Sheet, la vraie valeur) repéré uniquement en comparant
    // manuellement à un vieux CSV. Ce garde-fou rend ce genre de cas visible sans ce travail manuel.
    private const SEUIL_DIVERGENCE_PCT = 0.20;

    // % SER/usage et P.E précis — lus directement dans les fichiers norme interne mensuels
    // individuels, PAS dans l'onglet DATA (cf. migration dédiée : son ARRAYFORMULA n'importe que
    // "Copy of Récap data studio!A3:N", jamais les colonnes O à U)

    // Onglets INDEX du registre (un par site, PAS "Correct DATA" qui mélange les deux sans
    // colonne "site" exploitable — l'ordre SiteB-puis-SiteA de sa propre formule serait fragile à
    // décoder). Chaque ligne : Mois (nom français) | Année | Lien | Status ("OK" si le fichier a
    // été validé comme un vrai rapport norme interne, cf. formule IMPORTRANGE déjà documentée).
    private const ONGLETS_INDEX_PAR_SITE = ['Site A' => 'SITE A', 'Site B' => 'SITE B'];
    private const COL_INDEX_MOIS = 0;
    private const COL_INDEX_ANNEE = 1;
    private const COL_INDEX_LIEN = 2;
    private const COL_INDEX_STATUT = 3;
    private const LIGNE_INDEX_DEBUT = 2; // 0-based : les 2 premières lignes sont des en-têtes

    private const MOIS_FR = [
        'janvier' => 1, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'août' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12,
    ];

    // Onglet du fichier mensuel individuel (PAS "Camions", qui ne couvre que les camions — "Copy
    // of Récap data studio" couvre tous les équipements et porte les 2 colonnes recherchées).
    private const ONGLET_RECAP_DATA_STUDIO = 'Copy of Récap data studio';
    private const COL_RECAP_MATRICULE = 3;
    private const COL_RECAP_PCT_SER_USAGE = 14;
    private const COL_RECAP_ALL_PE = 20;
    private const LIGNE_RECAP_DEBUT = 2; // 0-based : ligne 1 = en-têtes, ligne 2 = vide

    // Un délai de 1,1s entre deux fichiers plafonne à ~54 requêtes/min, sous la limite, avec de la
    // marge pour les 2 lectures d'onglet index.
    private const DELAI_ENTRE_FICHIERS_MS = 1100;

    public function handle(): int
    {
        $spreadsheetId = config('services.google_sheets.registre_id');
        if (empty($spreadsheetId)) {
            $this->error("GOOGLE_SHEETS_REGISTRE_ID n'est pas configuré dans .env — impossible de savoir quel registre lire.");
            return Command::FAILURE;
        }

        try {
            $lignesBrutes = Sheets::spreadsheet($spreadsheetId)->sheet('DATA')->get();
        } catch (\Throwable $e) {
            $this->error("Échec de connexion au registre Google Sheets : {$e->getMessage()}");
            $this->error("Vérifier : le fichier de compte de service existe (GOOGLE_SERVICE_ACCOUNT_JSON_LOCATION), "
                . "le registre est bien partagé avec l'email du compte de service (rôle Lecteur suffit), "
                . "et GOOGLE_SHEETS_REGISTRE_ID correspond bien à l'ID dans l'URL du registre.");
            return Command::FAILURE;
        }

        if ($lignesBrutes->isEmpty()) {
            $this->error("L'onglet DATA du registre est vide ou introuvable.");
            return Command::FAILURE;
        }

        if (!$this->verifierStructure($lignesBrutes)) {
            return Command::FAILURE;
        }

        // Rapprochement sur la plaque NORMALISÉE (majuscules, tous espaces retirés) : le registre écrit
        // parfois « DEMO-007 » au lieu de « DEMO-007 » — sans cela ces mois étaient ignorés comme
        // « matricule hors périmètre ».
        $camionsParImmat = Camion::pluck('id', 'immatriculation')
            ->mapWithKeys(fn ($id, $immat) => [self::normaliserPlaque($immat) => $id]);

        $traitees = 0;
        $nouvelles = 0;
        $misesAJour = 0;
        $divergences = 0;
        $ignoreesCamionInconnu = [];
        $ignoreesDateInvalide = [];

        foreach ($lignesBrutes as $ligne) {
            $matricule = self::normaliserPlaque((string) ($ligne[self::COL_MATRICULE] ?? ''));
            if ($matricule === '') {
                continue; // ligne vide (ex: la toute première ligne du registre), pas une anomalie
            }

            if (!isset($camionsParImmat[$matricule])) {
                $ignoreesCamionInconnu[$matricule] = true;
                continue;
            }

            $dateBrute = trim((string) ($ligne[self::COL_DATE] ?? ''));
            try {
                $date = Carbon::createFromFormat('d/m/Y', $dateBrute)->format('Y-m-d');
            } catch (\Throwable $e) {
                $ignoreesDateInvalide[] = "{$matricule} / " . ($dateBrute !== '' ? $dateBrute : '(vide)');
                continue;
            }

            $nouvellesValeurs = [
                'usage' => $this->valeurTexte($ligne, self::COL_USAGE),
                'ser' => $this->valeurDecimale($ligne, self::COL_SER),
                'ser_1x' => $this->valeurDecimale($ligne, self::COL_SER_1X),
                'cible' => $this->valeurDecimale($ligne, self::COL_CIBLE),
                'km' => $this->valeurDecimale($ligne, self::COL_KM),
                'heures' => $this->valeurDecimale($ligne, self::COL_HEURES),
                'litres' => $this->valeurDecimale($ligne, self::COL_LITRES),
                'pe_lh' => $this->valeurDecimale($ligne, self::COL_PE_LH),
                'pe_l100' => $this->valeurDecimale($ligne, self::COL_PE_L100),
                'site' => $this->valeurTexte($ligne, self::COL_SITE),
            ];

            $existante = RegistreMesure::where(['camion_id' => $camionsParImmat[$matricule], 'date' => $date])->first();
            if ($existante && $this->signalerDivergences($existante, $nouvellesValeurs, $matricule, $date)) {
                $divergences++;
            }

            $mesure = RegistreMesure::updateOrCreate(
                ['camion_id' => $camionsParImmat[$matricule], 'date' => $date],
                $nouvellesValeurs
            );

            $traitees++;
            if ($mesure->wasRecentlyCreated) {
                $nouvelles++;
            } else {
                $misesAJour++;
            }
        }

        $resultatSer = $this->synchroniserSerNormeRefHistorique();
        $changementsSer = $resultatSer['changements'];
        $amorcagesNonVerifies = $resultatSer['amorcages_non_verifies'];

        $resultatRecap = $this->synchroniserColonnesRecapDataStudio($camionsParImmat, (int) $this->option('mois'));

        $resume = "sheets:sync-registre : {$traitees} ligne(s) traitée(s), {$nouvelles} nouvelle(s), {$misesAJour} mise(s) à jour.";
        if ($divergences > 0) {
            $resume .= " ⚠ {$divergences} ligne(s) avec un écart >" . (self::SEUIL_DIVERGENCE_PCT * 100) . '% sur au moins un champ (voir avertissements ci-dessus/logs).';
        }
        if (!empty($ignoreesCamionInconnu)) {
            $resume .= ' ' . count($ignoreesCamionInconnu) . ' matricule(s) distinct(s) ignoré(s) (hors du périmètre des camions suivis).';
        }
        if (!empty($ignoreesDateInvalide)) {
            $resume .= ' ' . count($ignoreesDateInvalide) . ' ligne(s) ignorée(s) pour date invalide.';
        }
        if (!empty($changementsSer)) {
            $resume .= ' ' . count($changementsSer) . ' changement(s) de SER norme interne historisé(s).';
        }
        if (!empty($amorcagesNonVerifies)) {
            $resume .= ' ⚠ ' . count($amorcagesNonVerifies) . ' SER amorcée(s) automatiquement sans SER officielle pré-existante — JAMAIS vérifiée(s) humainement (voir avertissements ci-dessous/logs).';
        }
        $resume .= " % SER/usage + P.E précis : {$resultatRecap['fichiers_lus']} fichier(s) norme interne mensuel(s) lu(s)"
            . ($resultatRecap['fichiers_echec'] > 0 ? ", {$resultatRecap['fichiers_echec']} échec(s)" : '')
            . ", {$resultatRecap['lignes_mises_a_jour']} ligne(s) enrichie(s).";

        $this->info($resume);
        Log::info($resume, [
            'traitees' => $traitees,
            'nouvelles' => $nouvelles,
            'mises_a_jour' => $misesAJour,
            'divergences' => $divergences,
            'matricules_inconnus' => array_keys($ignoreesCamionInconnu),
            'dates_invalides' => $ignoreesDateInvalide,
            'changements_ser_norme_ref' => $changementsSer,
            'amorcages_ser_norme_ref_non_verifies' => $amorcagesNonVerifies,
            'recap_data_studio' => $resultatRecap,
        ]);

        if (!empty($ignoreesDateInvalide)) {
            $this->warn('Dates invalides (' . count($ignoreesDateInvalide) . ') : ' . implode(' | ', array_slice($ignoreesDateInvalide, 0, 20)));
        }
        if (!empty($changementsSer)) {
            $this->warn('Changements de SER norme interne historisés : ' . implode(' | ', $changementsSer));
        }
        if (!empty($amorcagesNonVerifies)) {
            $this->warn('SER amorcées automatiquement depuis le registre (aucune SER officielle pré-existante sur la fiche) : ' . implode(' | ', $amorcagesNonVerifies));
        }
        if (!empty($resultatRecap['erreurs'])) {
            $this->warn('Fichiers norme interne mensuels inaccessibles (% SER/usage et P.E précis non mis à jour pour ces mois) : ' . implode(' | ', $resultatRecap['erreurs']));
        }

        return Command::SUCCESS;
    }

    /**
     * Tient camions.ser_norme_ref à jour à partir de la dernière valeur SER observée dans le registre
     * (registre_mesures.ser), sans jamais l'écraser silencieusement : chaque changement réel ferme
     * la période active de ser_norme_ref_historique et en ouvre une nouvelle — même logique que
     * carburant:prix (GererPrixCarburant) pour prix_carburant_historique. Un véhicule sans période
     * active est amorcé avec la valeur officielle déjà en vigueur sur sa fiche (ser_norme_ref /
     * ser_norme_ref_incertitude / ser_norme_ref_cible / usage_norme_ref), jamais avec une ligne brute du
     * registre non validée par le responsable métier.
     *
     * @return string[] libellés "IMMAT : ancienne → nouvelle (effectif AAAA-MM-JJ)" des changements détectés
     */
    private function synchroniserSerNormeRefHistorique(): array
    {
        $changements = [];
        $amorcagesNonVerifies = [];

        foreach (Camion::all() as $camion) {
            $observee = $this->dernierePeriodeSerObservee($camion->id);
            if (!$observee) {
                continue; // aucune ligne de registre avec SER renseignée pour ce véhicule
            }

            $periodeActive = SerNormeRefHistorique::where('camion_id', $camion->id)
                ->whereNull('date_fin')
                ->orderByDesc('date_debut')
                ->first();

            if (!$periodeActive) {
                if ($camion->ser_norme_ref === null) {
                    // Camion sans AUCUNE SER officielle pré-existante —: ce cas restait bloqué
                    // indéfiniment (continue), alors que le registre contient bien une SER
                    // exploitable.
                    $periodeActive = SerNormeRefHistorique::updateOrCreate(
                        ['camion_id' => $camion->id, 'date_debut' => $observee['depuis']],
                        [
                            'date_fin' => null,
                            'ser' => $observee['ser'],
                            'incertitude' => $observee['incertitude'],
                            'cible' => $observee['cible'],
                            'usage' => $observee['usage'],
                            'source' => 'Amorçage automatique depuis le registre — aucune SER officielle pré-existante, jamais vérifiée humainement',
                        ]
                    );

                    $camion->update([
                        'ser_norme_ref' => $observee['ser'],
                        'ser_norme_ref_incertitude' => $observee['incertitude'],
                        'ser_norme_ref_cible' => $observee['cible'],
                        'usage_norme_ref' => $observee['usage'],
                    ]);

                    $amorcagesNonVerifies[] = "{$camion->immatriculation} : SER amorcée depuis le registre (JAMAIS VÉRIFIÉE) = {$observee['ser']} (effectif {$observee['depuis']})";

                    continue; // déjà à jour avec $observee, rien de plus à comparer pour ce camion ici
                }

                $premiereDate = RegistreMesure::where('camion_id', $camion->id)
                    ->whereNotNull('ser')
                    ->orderBy('date')
                    ->value('date');

                $periodeActive = SerNormeRefHistorique::updateOrCreate(
                    [
                        'camion_id' => $camion->id,
                        'date_debut' => Carbon::parse($premiereDate)->startOfMonth()->toDateString(),
                    ],
                    [
                        'date_fin' => null,
                        'ser' => $camion->ser_norme_ref,
                        'incertitude' => $camion->ser_norme_ref_incertitude,
                        'cible' => $camion->ser_norme_ref_cible,
                        'usage' => $camion->usage_norme_ref,
                        'source' => 'Amorçage automatique — valeur officielle en vigueur à la première synchronisation historisée',
                    ]
                );
            }

            $ancienneSer = (float) $periodeActive->ser;
            if (abs($ancienneSer - $observee['ser']) < 0.005 || $observee['depuis'] <= $periodeActive->date_debut->toDateString()) {
                continue; // pas de changement, ou changement antérieur à la période déjà active (rien à faire)
            }

            $finPrecedente = Carbon::parse($observee['depuis'])->subDay()->toDateString();
            $periodeActive->update(['date_fin' => $finPrecedente]);

            SerNormeRefHistorique::updateOrCreate(
                ['camion_id' => $camion->id, 'date_debut' => $observee['depuis']],
                [
                    'date_fin' => null,
                    'ser' => $observee['ser'],
                    'incertitude' => $observee['incertitude'],
                    'cible' => $observee['cible'],
                    'usage' => $observee['usage'],
                    'source' => 'sheets:sync-registre',
                ]
            );

            $camion->update([
                'ser_norme_ref' => $observee['ser'],
                'ser_norme_ref_incertitude' => $observee['incertitude'],
                'ser_norme_ref_cible' => $observee['cible'],
                'usage_norme_ref' => $observee['usage'],
            ]);

            $changements[] = "{$camion->immatriculation} : SER {$ancienneSer} → {$observee['ser']} (effectif {$observee['depuis']})";
        }

        return ['changements' => $changements, 'amorcages_non_verifies' => $amorcagesNonVerifies];
    }

    /**
     * Remonte l'historique du registre (registre_mesures, le plus récent d'abord) tant que le SER
     * reste égal à celui de la ligne la plus récente, pour trouver la date de DÉBUT réelle de la
     * valeur actuellement en vigueur — pas seulement la date de la dernière ligne. Nécessaire car
     * une synchronisation peut être lancée alors que le changement a déjà commencé plusieurs mois
     * plus tôt (ex: SER passé à une nouvelle valeur depuis 2 mois, détecté seulement aujourd'hui).
     */
    private function dernierePeriodeSerObservee(int $camionId): ?array
    {
        $lignes = RegistreMesure::where('camion_id', $camionId)
            ->whereNotNull('ser')
            ->orderByDesc('date')
            ->get(['date', 'ser', 'ser_1x', 'cible', 'usage']);

        if ($lignes->isEmpty()) {
            return null;
        }

        $derniere = $lignes->first();
        $valeurActuelle = (float) $derniere->ser;
        $ligneDepart = $derniere;

        foreach ($lignes as $ligne) {
            if ((float) $ligne->ser !== $valeurActuelle) {
                break;
            }
            $ligneDepart = $ligne;
        }

        $incertitude = ($derniere->ser_1x !== null && $valeurActuelle > 0)
            ? round(((float) $derniere->ser_1x / $valeurActuelle) - 1, 4)
            : null;

        return [
            'depuis' => Carbon::parse($ligneDepart->date)->startOfMonth()->toDateString(),
            'ser' => $valeurActuelle,
            'incertitude' => $incertitude,
            'cible' => $derniere->cible !== null ? (float) $derniere->cible : null,
            'usage' => $derniere->usage,
        ];
    }

    /**
     * % SER/usage et P.E (l/h) précis — lus directement dans les fichiers norme interne mensuels
     * individuels ("Copy of Récap data studio"), colonnes O et U, JAMAIS dans l'onglet DATA du
     * registre (cf. commentaire de la migration dédiée : sa formule racine n'importe que "Copy of
     * Récap data studio!A3:N".
     *
     * @param  \Illuminate\Support\Collection<string,int>  $camionsParImmat  plaque normalisée => camion_id
     * @return array{fichiers_lus:int,fichiers_echec:int,lignes_mises_a_jour:int,erreurs:string[]}
     */
    private function synchroniserColonnesRecapDataStudio($camionsParImmat, int $nbMoisRecents): array
    {
        $spreadsheetId = config('services.google_sheets.registre_id');
        $fichiersLus = 0;
        $fichiersEchec = 0;
        $lignesMisesAJour = 0;
        $erreurs = [];

        foreach (self::ONGLETS_INDEX_PAR_SITE as $ongletIndex => $site) {
            try {
                $lignesIndex = Sheets::spreadsheet($spreadsheetId)->sheet($ongletIndex)->get();
            } catch (\Throwable $e) {
                $erreurs[] = "onglet index '{$ongletIndex}' illisible : {$e->getMessage()}";
                continue;
            }

            // Entrées valides du site (mois/année reconnus, lien présent, Statut="OK") — d'abord
            // collectées en entier pour ancrer la fenêtre glissante sur le DERNIER MOIS RÉELLEMENT
            // LIÉ de CE site, jamais sur la date du jour : le délai de saisie M+1 fait que le mois
            // calendaire courant n'a généralement pas encore de fichier lié.
            $entrees = [];
            foreach ($lignesIndex->skip(self::LIGNE_INDEX_DEBUT) as $ligneIndex) {
                $moisNom = strtolower(trim((string) ($ligneIndex[self::COL_INDEX_MOIS] ?? '')));
                $annee = trim((string) ($ligneIndex[self::COL_INDEX_ANNEE] ?? ''));
                $lien = trim((string) ($ligneIndex[self::COL_INDEX_LIEN] ?? ''));
                $statut = trim((string) ($ligneIndex[self::COL_INDEX_STATUT] ?? ''));

                if ($statut !== 'OK' || $lien === '' || !isset(self::MOIS_FR[$moisNom]) || $annee === '') {
                    continue; // mois futur/non encore lié, ligne vide, ou statut jamais confirmé "OK"
                }

                $entrees[] = ['date' => Carbon::createFromDate((int) $annee, self::MOIS_FR[$moisNom], 1), 'lien' => $lien];
            }

            if (empty($entrees)) {
                continue;
            }

            $dernierMoisLie = collect($entrees)->max('date');
            $depuisFenetre = $dernierMoisLie->copy()->startOfMonth()->subMonths(max(0, $nbMoisRecents - 1));

            foreach ($entrees as $entree) {
                $dateFichier = $entree['date'];
                $lien = $entree['lien'];

                if ($dateFichier->lt($depuisFenetre)) {
                    continue; // hors de la fenêtre glissante demandée — jamais refetché à chaque run
                }

                if (!preg_match('#/d/([a-zA-Z0-9_-]+)#', $lien, $m)) {
                    $erreurs[] = "{$site} {$moisNom} {$annee} : lien sans identifiant de fichier exploitable";
                    continue;
                }
                $fichierId = $m[1];

                if (!app()->runningUnitTests()) {
                    usleep(self::DELAI_ENTRE_FICHIERS_MS * 1000);
                }

                try {
                    $lignesRecap = Sheets::spreadsheet($fichierId)->sheet(self::ONGLET_RECAP_DATA_STUDIO)->get();
                } catch (\Throwable $e) {
                    $fichiersEchec++;
                    $erreurs[] = "{$site} {$moisNom} {$annee} : {$e->getMessage()}";
                    continue;
                }

                $fichiersLus++;
                $dateStr = $dateFichier->toDateString();

                foreach ($lignesRecap->skip(self::LIGNE_RECAP_DEBUT) as $ligneRecap) {
                    $matricule = self::normaliserPlaque((string) ($ligneRecap[self::COL_RECAP_MATRICULE] ?? ''));
                    if ($matricule === '' || !isset($camionsParImmat[$matricule])) {
                        continue;
                    }

                    $pctSerUsage = $this->valeurPourcentage($ligneRecap, self::COL_RECAP_PCT_SER_USAGE);
                    $allPe = $this->valeurDecimale($ligneRecap, self::COL_RECAP_ALL_PE);
                    if ($pctSerUsage === null && $allPe === null) {
                        continue; // rien à écrire (mois pas encore saisi pour ce véhicule, cf. délai M+1)
                    }

                    // update() seul (jamais updateOrCreate) : n'enrichit qu'une ligne DÉJÀ créée par
                    // la synchronisation DATA ci-dessus, ne crée jamais de ligne registre_mesures
                    // partielle si ce camion+mois n'y existe pas encore.
                    $lignesMisesAJour += RegistreMesure::where(['camion_id' => $camionsParImmat[$matricule], 'date' => $dateStr])
                        ->update(['pct_ser_usage' => $pctSerUsage, 'pe_lh_precis' => $allPe]);
                }
            }
        }

        return ['fichiers_lus' => $fichiersLus, 'fichiers_echec' => $fichiersEchec, 'lignes_mises_a_jour' => $lignesMisesAJour, 'erreurs' => $erreurs];
    }

    /**
     * Pas d'en-tête à valider dans ce registre (cf. commentaire de classe) — à la place, on
     * vérifie que les N premières lignes non vides ont bien une date au format d/m/Y et un
     * nombre de colonnes suffisant à la position attendue. Si la structure a changé (colonnes
     * réordonnées, ligne d'en-tête ajoutée...), on s'arrête net plutôt que d'importer des
     * données mal alignées silencieusement.
     */
    private function verifierStructure($lignesBrutes): bool
    {
        $echantillon = $lignesBrutes->filter(fn ($ligne) => trim((string) ($ligne[self::COL_MATRICULE] ?? '')) !== '')->take(20);

        if ($echantillon->isEmpty()) {
            $this->error("Aucune ligne avec un matricule non vide trouvée dans les 20 premières lignes de données — structure du registre à vérifier manuellement.");
            return false;
        }

        $datesValides = 0;
        foreach ($echantillon as $ligne) {
            if (count($ligne) < self::NB_COLONNES_MIN) {
                continue;
            }
            $date = trim((string) ($ligne[self::COL_DATE] ?? ''));
            if ($date === '') {
                continue;
            }
            try {
                Carbon::createFromFormat('d/m/Y', $date);
                $datesValides++;
            } catch (\Throwable $e) {
                // ligne comptée comme non valide, cf. tolérance de 80% plus bas
            }
        }

        // Tolérance : au moins 80% de l'échantillon doit être structurellement cohérent (quelques
        // lignes ponctuellement malformées dans un registre à saisie manuelle ne doivent pas
        // bloquer toute la synchronisation, mais une majorité invalide signale un vrai problème).
        if ($datesValides / $echantillon->count() < 0.8) {
            $this->error("La structure du registre ne correspond plus à celle attendue (colonne Date en position "
                . self::COL_DATE . ", format d/m/Y) — seulement {$datesValides}/{$echantillon->count()} lignes "
                . "de l'échantillon sont cohérentes. Vérifier si l'ordre des colonnes de l'onglet DATA a changé.");
            return false;
        }

        return true;
    }

    /**
     * Compare chaque champ numérique de la ligne déjà en base à la nouvelle valeur venant du
     * Sheet, AVANT que updateOrCreate() n'écrase.
     *
     * @return bool true si au moins une divergence a été signalée pour cette ligne
     */
    private function signalerDivergences(RegistreMesure $existante, array $nouvellesValeurs, string $matricule, string $date): bool
    {
        $aDivergence = false;

        foreach (self::CHAMPS_NUMERIQUES as $champ) {
            $ancienne = $existante->$champ;
            $nouvelle = $nouvellesValeurs[$champ];

            if ($ancienne === null || $nouvelle === null || (float) $ancienne === 0.0) {
                continue;
            }

            $ecartPct = abs(($nouvelle - $ancienne) / $ancienne);
            if ($ecartPct > self::SEUIL_DIVERGENCE_PCT) {
                $aDivergence = true;
                $message = "sheets:sync-registre — ÉCART {$matricule} {$date} champ '{$champ}' : "
                    . "ancienne valeur={$ancienne} -> nouvelle valeur={$nouvelle} (" . round($ecartPct * 100, 1) . "% d'écart)";
                $this->warn($message);
                Log::warning($message, [
                    'matricule' => $matricule,
                    'date' => $date,
                    'champ' => $champ,
                    'ancienne_valeur' => $ancienne,
                    'nouvelle_valeur' => $nouvelle,
                    'ecart_pct' => round($ecartPct * 100, 1),
                ]);
            }
        }

        return $aDivergence;
    }

    /**
     * Forme canonique d'une plaque pour le rapprochement : majuscules, tous les espaces retirés
     * (y compris insécables, fréquents dans un copier-coller depuis un tableur).
     */
    public static function normaliserPlaque(string $plaque): string
    {
        return strtoupper(preg_replace('/[\s\x{00A0}]+/u', '', $plaque) ?? '');
    }

    private function valeurTexte(array $ligne, int $index): ?string
    {
        $valeur = trim((string) ($ligne[$index] ?? ''));
        return $valeur !== '' ? $valeur : null;
    }

    private function valeurDecimale(array $ligne, int $index): ?float
    {
        $valeur = trim((string) ($ligne[$index] ?? ''));
        return $valeur !== '' ? (float) str_replace(',', '.', $valeur) : null;
    }

    /**
     * "63,85760988%" -> 0.6385760988 (fraction, même convention que camions.ser_norme_ref_incertitude
     * — jamais le nombre de pourcentage brut 63,86). Retiré le signe "%" avant la conversion
     * décimale déjà faite par valeurDecimale().
     */
    private function valeurPourcentage(array $ligne, int $index): ?float
    {
        $valeur = trim((string) ($ligne[$index] ?? ''));
        if ($valeur === '') {
            return null;
        }
        $nombre = (float) str_replace(',', '.', rtrim($valeur, '% '));
        return $nombre / 100;
    }
}
