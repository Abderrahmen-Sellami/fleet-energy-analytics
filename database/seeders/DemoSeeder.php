<?php

namespace Database\Seeders;

use App\Console\Commands\Concerns\VehiculesSourceB;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration ENTIÈREMENT FICTIVES.
 */
class DemoSeeder extends Seeder
{
    private const NB_MOIS = 18;

    /**
     * plaque => [site, marque, usage, SER, incertitude, ratio IPE/SER des 2 derniers mois, mois d'activation source A]
     * Le ratio pilote le statut de la norme de référence : < 0,98 vert foncé, 0,98-1 vert clair,
     * 1 à 1+incertitude orange, au-delà rouge.
     */
    private const FLOTTE = [
        'DEMO-001' => ['SITE A', 'Marque A', 'Transport — catégorie 2', 45, 0.15, 0.99, 0],
        'DEMO-002' => ['SITE A', 'Marque A', 'Transport — catégorie 1', 50, 0.15, 1.22, 0],
        'DEMO-003' => ['SITE A', 'Marque A', 'Transport — catégorie 2', 35, 0.15, 0.90, 0],
        'DEMO-004' => ['SITE A', 'Marque A', 'Transport — catégorie 2', 35, 0.15, 1.06, 0],
        'DEMO-005' => ['SITE A', 'Marque A', 'Transport — catégorie 1', 45, 0.25, 0.92, 0],
        'DEMO-006' => ['SITE A', 'Marque A', 'Transport — catégorie 1', 45, 0.25, 0.88, 0],
        'DEMO-007' => ['SITE B', 'Marque A', 'Transport — catégorie 1', 45, 0.25, 1.32, 0],
        'DEMO-008' => ['SITE B', 'Marque A', 'Transport — catégorie 1', 45, 0.25, 0.93, 0],
        'DEMO-009' => ['SITE B', 'Marque A', 'Transport — catégorie 1', 45, 0.25, 1.12, 6],
        'DEMO-010' => ['SITE B', 'Marque A', 'Transport — catégorie 2', 45, 0.15, 0.85, 6],
        'DEMO-011' => ['SITE B', 'Marque B', 'Transport — catégorie 2', 40, 0.15, 0.985, 0],
        'DEMO-012' => ['SITE B', 'Marque B', 'Transport — catégorie 1', 45, 0.25, 0.90, 16],
    ];

    /** Coordonnées fictives des deux sites (cohérentes avec WeatherService). */
    private const COORD = ['SITE A' => [45.7000, 4.9000], 'SITE B' => [44.9000, 4.8800]];

    public function run(): void
    {
        mt_srand(20260701);
        $maintenant = Carbon::now();
        $moisCourant = $maintenant->copy()->startOfMonth();
        $premierMois = $moisCourant->copy()->subMonths(self::NB_MOIS - 1);

        $this->comptes();
        $ids = $this->camions($premierMois);
        $this->mesuresSourceA($ids, $premierMois, $moisCourant, $maintenant);
        $this->registre($ids, $premierMois, $moisCourant);
        $this->sourceB($ids, $maintenant);
        $this->positions($ids, $maintenant);
        $this->referentiels($ids, $premierMois, $maintenant);

        // Alertes et suggestions : calculées par les vrais services à partir des données ci-dessus.
        Artisan::call('alertes:detecter');
        Artisan::call('suggestions:detecter');

        $this->command?->info('Démonstration prête : 12 camions fictifs. Connexion : demo / demo (Admin) ou consultant / consultant.');
    }

    private function comptes(): void
    {
        foreach ([['demo', 'demo', 'admin'], ['consultant', 'consultant', 'consultant']] as [$id, $mdp, $role]) {
            DB::table('users')->updateOrInsert(['identifiant' => $id], [
                'mot_de_passe' => Hash::make($mdp), 'role' => $role, 'actif' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function camions(Carbon $premierMois): array
    {
        $ids = [];
        $i = 0;
        foreach (self::FLOTTE as $plaque => [$site, $marque, $usage, $ser, $inc, , $decalage]) {
            $i++;
            $ids[$plaque] = DB::table('camions')->insertGetId([
                'vpi' => strtoupper(md5('demo-vpi-' . $plaque)),
                'vin' => sprintf('DEMOVIN%010d', $i),
                'immatriculation' => $plaque,
                'groupe' => $site,
                'marque' => $marque,
                'modele' => $marque === 'Marque A' ? 'Tracteur 4x2' : 'Porteur 6x4',
                'euro_class' => 'EURO_6',
                'objectif_conso' => 35,
                'ser_norme_ref' => $ser,
                'ser_norme_ref_incertitude' => $inc,
                'ser_norme_ref_cible' => round($ser * 0.98, 2),
                'usage_norme_ref' => $usage,
                'source_a_actif_depuis' => $premierMois->copy()->addMonths($decalage)->addDays(12)->toDateString(),
                'source_principale' => 'SOURCE_A',
                'conducteur_source_b' => $i <= 6 ? sprintf('Conducteur fictif %02d', $i) : null,
                'actif' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function bruit(float $amplitude): float
    {
        return 1 + (mt_rand(-1000, 1000) / 1000) * $amplitude;
    }

    /** Ratio IPE/SER d'un camion pour un mois donné (les 2 derniers mois clos portent le statut voulu). */
    private function ratio(string $plaque, int $moisAvantFin): float
    {
        $cible = self::FLOTTE[$plaque][5];
        return $moisAvantFin <= 2 ? $cible * $this->bruit(0.005) : 0.94 * $this->bruit(0.05);
    }

    private function mesuresSourceA(array $ids, Carbon $premierMois, Carbon $moisCourant, Carbon $maintenant): void
    {
        $lignes = [];
        foreach (self::FLOTTE as $plaque => [$site, , , $ser, , , $decalage]) {
            for ($m = 0; $m < self::NB_MOIS; $m++) {
                $date = $premierMois->copy()->addMonths($m);
                $partiel = $date->equalTo($moisCourant);
                $avantFin = self::NB_MOIS - 1 - $m; // 0 = mois en cours
                $base = ['camion_id' => $ids[$plaque], 'date' => $date->toDateString(), 'est_partielle' => $partiel,
                    'created_at' => now(), 'updated_at' => now()];

                if ($m < $decalage) {
                    // Avant l'activation de l'abonnement : ligne vide, affichée « — ».
                    $lignes[] = $base + array_fill_keys($this->colonnesMesure(), null);
                    continue;
                }

                $fraction = $partiel ? max(0.1, $maintenant->day / $date->daysInMonth) : 1;
                $km = (int) round(mt_rand(5200, 8600) * $fraction);
                $conso = round($ser * $this->ratio($plaque, $avantFin) * $this->bruit(0.03), 2);
                $vitesse = round(mt_rand(380, 560) / 10, 1);
                $heures = (int) round($km / $vitesse * 1.15);
                $eco = round(mt_rand(640, 920) / 100, 2);

                $ligne = $base + [
                    'consommation_l100' => $conso,
                    'km_parcourus' => $km,
                    'heures_moteur' => $heures,
                    'ecoscore' => $eco,
                    'anticipation' => round(min(10, $eco * $this->bruit(0.08)), 2),
                    'utilisation_abusive' => round(min(10, $eco * $this->bruit(0.10)), 2),
                    'utilisation_optimale' => round(min(10, $eco * $this->bruit(0.08)), 2),
                    'arret' => round(min(10, $eco * $this->bruit(0.12)), 2),
                    'vitesse_moyenne' => $vitesse,
                    'nb_arrets' => round(mt_rand(800, 2600) / 10, 2),
                    'conso_horaire' => $heures > 0 ? round($conso * $km / 100 / $heures, 2) : null,
                    'usage_mode_puissance' => round(-mt_rand(0, 300) / 100, 2),
                    'engine_overrev' => round(-mt_rand(0, 200) / 100, 2),
                    'taux_ralenti' => null,
                ];

                // Anomalies volontaires (badges et exclusions des moyennes).
                if ($plaque === 'DEMO-005' && $avantFin === 10) {
                    $ligne['consommation_l100'] = 0;        // « 0 » renvoyé par la source = absence de donnée
                }
                if ($plaque === 'DEMO-008' && $avantFin === 8) {
                    $ligne['consommation_l100'] = 176.42;   // hors bornes plausibles (> 100 L/100km)
                }
                if ($plaque === 'DEMO-003' && $avantFin === 5) {
                    $ligne['vitesse_moyenne'] = 1987.6;    // vitesse physiquement impossible
                }
                if ($plaque === 'DEMO-006' && $avantFin === 12) {
                    $ligne = $base + array_fill_keys($this->colonnesMesure(), null); // mois sans aucune donnée
                }
                $lignes[] = $ligne;
            }
        }
        DB::table('mesures')->insert($lignes);
    }

    private function colonnesMesure(): array
    {
        return ['consommation_l100', 'km_parcourus', 'heures_moteur', 'ecoscore', 'anticipation',
            'utilisation_abusive', 'utilisation_optimale', 'arret', 'vitesse_moyenne', 'nb_arrets',
            'conso_horaire', 'usage_mode_puissance', 'engine_overrev', 'taux_ralenti'];
    }

    /** Registre mensuel saisi à M+1 : le mois en cours n'y figure jamais (délai de saisie normal). */
    private function registre(array $ids, Carbon $premierMois, Carbon $moisCourant): void
    {
        $lignes = [];
        foreach (self::FLOTTE as $plaque => [$site, , $usage, $ser, $inc]) {
            for ($m = 0; $m < self::NB_MOIS - 1; $m++) {
                $date = $premierMois->copy()->addMonths($m);
                $avantFin = self::NB_MOIS - 1 - $m;
                if ($plaque === 'DEMO-011' && $avantFin === 4) {
                    continue; // mois manquant dans le registre
                }
                if ($plaque === 'DEMO-012') {
                    continue; // véhicule récent pas encore saisi au registre : statut « Donnée manquante »
                }
                $km = round(mt_rand(5300, 8500));
                $pe = $ser * $this->ratio($plaque, $avantFin);
                if ($plaque === 'DEMO-004' && in_array($avantFin, [6, 7], true)) {
                    $pe *= 1.45; // divergence registre / source A (> 25 %) pendant 2 mois
                }
                $litres = round($km * $pe / 100);
                $heures = ($plaque === 'DEMO-010' && $avantFin <= 3) ? null : round($km / 45);
                $lignes[] = [
                    'camion_id' => $ids[$plaque],
                    'date' => $date->toDateString(),
                    'usage' => $usage,
                    'ser' => $ser,
                    'ser_1x' => round($ser * (1 + $inc), 2),
                    'cible' => round($ser * 0.98, 2),
                    'km' => $km,
                    'heures' => $heures,
                    'litres' => $litres,
                    'pe_lh' => $heures ? round($litres / $heures, 2) : null,
                    'pe_l100' => round($litres / $km * 100, 2),
                    'pct_ser_usage' => round(mt_rand(80, 220) / 1000, 4),
                    'pe_lh_precis' => $heures ? round($litres / $heures, 6) : null,
                    'site' => $site,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            DB::table('ser_norme_ref_historique')->insert([
                'camion_id' => $ids[$plaque], 'date_debut' => $premierMois->toDateString(), 'date_fin' => null,
                'ser' => $ser, 'incertitude' => $inc, 'cible' => round($ser * 0.98, 2), 'usage' => $usage,
                'source' => 'Données de démonstration', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('registre_mesures')->insert($lignes);
    }

    /**
     * Source télématique B pour les 6 camions du SITE A équipés d'un boîtier (cf. VehiculesSourceB) :
     * kilométrage journalier, intervalles plein-à-plein et positions instantanées.
     */
    private function sourceB(array $ids, Carbon $maintenant): void
    {
        // conso plein-à-plein par plaque (null = aucun plein détecté ; DEMO-002 est exclu du calcul)
        $consoParPlaque = ['DEMO-001' => 52, 'DEMO-002' => null, 'DEMO-003' => 38, 'DEMO-004' => 40,
            'DEMO-005' => null, 'DEMO-006' => 92];
        $debut = $maintenant->copy()->startOfDay()->subDays(180);
        $mileage = [];
        $intervalles = [];
        $tempsReel = [];

        foreach ($consoParPlaque as $plaque => $conso) {
            $device = VehiculesSourceB::DEVICE_PAR_PLAQUE[$plaque];
            $odo = 120000 + mt_rand(0, 80000);
            $distances = [];
            for ($j = 0; $j < 180; $j++) {
                $jour = $debut->copy()->addDays($j);
                $km = (mt_rand(1, 7) === 7) ? 0.0 : round(mt_rand(150, 330) * 1.0, 1);
                if ($plaque === 'DEMO-001' && $j === 150) {
                    $km = 154321.4; // jour de kilométrage corrompu (compteur du boîtier faussé)
                }
                $distances[$jour->toDateString()] = $km;
                $mileage[] = ['device_id' => $device, 'camion_id' => $ids[$plaque], 'horodatage_record' => $jour,
                    'distance_km' => $km, 'duree_secondes' => (int) ($km < 2000 ? $km / 48 * 3600 : 36000),
                    'odo' => $odo, 'accum_odo' => $odo + min($km, 2000), 'source' => 'mileage',
                    'created_at' => now(), 'updated_at' => now()];
                $odo += min($km, 2000);
            }

            if ($conso !== null) {
                $precedent = $debut->copy()->addHours(9);
                while (true) {
                    $suivant = $precedent->copy()->addDays(mt_rand(4, 7))->setTime(mt_rand(7, 17), mt_rand(0, 59));
                    if ($suivant->greaterThan($maintenant)) {
                        break;
                    }
                    $dist = 0;
                    for ($d = $precedent->copy()->startOfDay(); $d->lessThanOrEqualTo($suivant->copy()->startOfDay()); $d->addDay()) {
                        $dist += $distances[$d->toDateString()] ?? 0;
                    }
                    $normal = min($dist, 3000); // volume réaliste même si la distance contient le jour corrompu
                    $volume = round($normal * $conso * (1 + mt_rand(-60, 60) / 1000) / 100, 1);
                    $sansDistance = $plaque === 'DEMO-004' && count($intervalles) % 9 === 4;
                    $intervalles[] = ['device_id' => $device, 'camion_id' => $ids[$plaque],
                        'plein_precedent_date' => $precedent, 'plein_suivant_date' => $suivant, 'volume_l' => $volume,
                        'distance_km' => $sansDistance || $dist <= 0 ? null : $dist,
                        'consommation_l100km' => $sansDistance || $dist <= 0 ? null : round($volume / $dist * 100, 2),
                        'est_partielle' => $suivant->isSameMonth($maintenant),
                        'created_at' => now(), 'updated_at' => now()];
                    $precedent = $suivant;
                }
            }

            [$lat, $lon] = self::COORD['SITE A'];
            $age = ['DEMO-005' => 60 * 24 * 10, 'DEMO-006' => 60 * 24 * 2][$plaque] ?? mt_rand(2, 20);
            $tempsReel[] = ['device_id' => $device, 'camion_id' => $ids[$plaque],
                'horodatage_record' => $maintenant->copy()->subMinutes($age),
                'latitude' => $lat + mt_rand(-300, 300) / 10000, 'longitude' => $lon + mt_rand(-300, 300) / 10000,
                'vitesse' => mt_rand(0, 80), 'fuel' => mt_rand(20, 95), 'rpm' => mt_rand(600, 1600), 'ignition' => true,
                'power' => 1, 'realtime_record_status' => 'VALID', 'source' => 'realtime',
                'donnees_brutes' => json_encode(['address' => 'Adresse fictive, ' . self::FLOTTE[$plaque][0]]),
                'created_at' => now(), 'updated_at' => now()];
        }

        foreach (array_chunk($mileage, 500) as $lot) {
            DB::table('mesures_source_b')->insert($lot);
        }
        DB::table('mesures_source_b')->insert($tempsReel);
        DB::table('consommations_source_b')->insert($intervalles);
    }

    /** Trajets GPS de la journée (source A) : un point toutes les 15 minutes sur les 6 dernières heures. */
    private function positions(array $ids, Carbon $maintenant): void
    {
        $lignes = [];
        foreach (self::FLOTTE as $plaque => [$site]) {
            [$lat, $lon] = self::COORD[$site];
            $lat += mt_rand(-400, 400) / 10000;
            $lon += mt_rand(-400, 400) / 10000;
            $cap = mt_rand(0, 359);
            for ($k = 24; $k >= 0; $k--) {
                $lat += cos(deg2rad($cap)) * 0.004;
                $lon += sin(deg2rad($cap)) * 0.004;
                $cap = ($cap + mt_rand(-25, 25) + 360) % 360;
                $lignes[] = ['camion_id' => $ids[$plaque], 'latitude' => round($lat, 6), 'longitude' => round($lon, 6),
                    'vitesse' => mt_rand(0, 85), 'cap' => $cap, 'altitude' => mt_rand(150, 400),
                    'horodatage_position' => $maintenant->copy()->subMinutes($k * 15), 'created_at' => now()];
            }
        }
        DB::table('positions_historique')->insert($lignes);
    }

    private function referentiels(array $ids, Carbon $premierMois, Carbon $maintenant): void
    {
        DB::table('prix_carburant_historique')->insert([
            ['date_debut' => $premierMois->copy()->subYear()->toDateString(),
                'date_fin' => $premierMois->copy()->addMonths(8)->subDay()->toDateString(), 'prix_ht' => 1.900,
                'created_at' => now(), 'updated_at' => now()],
            ['date_debut' => $premierMois->copy()->addMonths(8)->toDateString(), 'date_fin' => null, 'prix_ht' => 1.985,
                'created_at' => now(), 'updated_at' => now()],
        ]);

        $meteo = [];
        for ($j = 60; $j >= 1; $j--) {
            foreach (['SITE A' => 0, 'SITE B' => 2] as $site => $ecart) {
                $meteo[] = ['site' => $site, 'date' => $maintenant->copy()->subDays($j)->toDateString(),
                    'temperature' => round(14 + $ecart + 6 * sin($j / 9) + mt_rand(-20, 20) / 10, 1),
                    'humidite' => mt_rand(40, 85), 'vent_kmh' => mt_rand(5, 35), 'description' => 'donnée fictive',
                    'created_at' => now(), 'updated_at' => now()];
            }
        }
        DB::table('meteo_historique')->insert($meteo);

        $scores = [];
        foreach ($ids as $id) {
            for ($s = 2; $s >= 1; $s--) {
                $lundi = $maintenant->copy()->startOfWeek()->subWeeks($s);
                $scores[] = ['camion_id' => $id, 'semaine_debut' => $lundi->toDateString(),
                    'semaine_fin' => $lundi->copy()->addDays(6)->toDateString(), 'score_total' => mt_rand(60, 92),
                    'anticipation' => mt_rand(55, 95), 'braking' => mt_rand(55, 95), 'coasting' => mt_rand(40, 90),
                    'total_distance' => mt_rand(1200, 2100), 'total_time' => mt_rand(100000, 160000),
                    'avg_fuel_consumption' => mt_rand(320, 480) / 10, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        DB::table('constructeur_scores_historique')->insert($scores);

        DB::table('analyses_correctives')->insert([
            ['camion_id' => $ids['DEMO-002'], 'mois' => $maintenant->copy()->subMonths(2)->startOfMonth()->toDateString(),
                'cause' => 'Exemple fictif : chantier en forte pente et charge maximale pendant tout le mois.',
                'action' => 'Exemple fictif : formation éco-conduite du conducteur et suivi renforcé.',
                'responsable' => 'Responsable énergie (fictif)', 'statut_suivi' => 'En cours',
                'source' => 'Données de démonstration', 'created_at' => now(), 'updated_at' => now()],
            ['camion_id' => $ids['DEMO-007'], 'mois' => $maintenant->copy()->subMonths(1)->startOfMonth()->toDateString(),
                'cause' => 'Exemple fictif : défaut d\'injecteur détecté en atelier.',
                'action' => 'Exemple fictif : remplacement de l\'injecteur, contrôle du mois suivant.',
                'responsable' => 'Atelier (fictif)', 'statut_suivi' => 'Réalisé',
                'source' => 'Données de démonstration', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Santé des automatisations : exécutions récentes fictives (aucun retard affiché).
        foreach (array_keys(\App\Services\SanteAutomatisationsService::FREQUENCES_MINUTES) as $tache) {
            DB::table('automatisations_sante')->insert(['tache' => $tache,
                'derniere_execution_reussie' => $maintenant->copy()->subMinutes(5),
                'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
