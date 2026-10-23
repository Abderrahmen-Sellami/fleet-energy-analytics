<?php

namespace Tests\Unit;

use App\Models\Camion;
use App\Models\RegistreMesure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Revolution\Google\Sheets\Facades\Sheets;
use Tests\TestCase;

/**
 * Teste sheets:sync-registre (App\Console\Commands\SyncRegistreSheets) SANS jamais appeler Google
 * réellement : la façade Sheets est mockée (Mockery via shouldReceive), le résultat de ->get() est
 * un Collection de lignes construites à la main.
 */
class SyncRegistreSheetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_sheets.registre_id' => 'test-spreadsheet-id']);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    // Ordre réel des colonnes de l'onglet DATA (pas d'en-tête) : Usage, Date, Engin, Matricule,
    // Désignation, SER, SER_1X, Cible, Kilométrage, Heures, Litres, PE_lh, PE_l100, Site.
    private function ligne(
        string $matricule,
        string $date,
        float $ser = 45.0,
        float $ser1x = 56.25,
        float $cible = 44.1,
        float $km = 5000,
        float $heures = 150,
        float $litres = 2000,
        string $usage = 'Transport — catégorie 2',
        string $site = 'SITE A',
    ): array {
        return [$usage, $date, 'ENGIN1', $matricule, 'Désignation', $ser, $ser1x, $cible, $km, $heures, $litres, null, null, $site];
    }

    // Depuis, handle() lit systématiquement 2 onglets de plus (Site A/Site B, pour % SER/usage et
    // P.E précis, cf. synchroniserColonnesRecapDataStudio()) — Site A/Site B vides ici (aucune
    // ligne exploitable) pour que ces tests, centrés sur l'onglet DATA.
    private function mockSheets(array $lignes, string $spreadsheetId = 'test-spreadsheet-id'): void
    {
        $this->mockSheetsMulti([
            $spreadsheetId => ['DATA' => $lignes, 'Site A' => [], 'Site B' => []],
        ]);
    }

    public function test_synchronise_une_ligne_valide_et_cree_la_mesure(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-007']);
        $this->mockSheets([$this->ligne('DEMO-007', '15/06/2026', litres: 1800)]);

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        $mesure = RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-06-15')->first();
        $this->assertNotNull($mesure);
        $this->assertSame(1800.0, $mesure->litres);
    }

    public function test_plaque_avec_espaces_ou_casse_differente_est_rapprochee_du_camion(): void
    {
        // Cas rencontré : le registre écrit parfois la plaque avec des espaces (y compris insécables) ou en minuscules.
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-007']);
        $this->mockSheets([
            $this->ligne(' DEMO-007', '01/07/2026', litres: 2603),
            $this->ligne("demo\u{00A0}-\u{00A0}007", '01/08/2026', litres: 1582),
        ]);

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        $this->assertSame(2, RegistreMesure::where('camion_id', $camion->id)->count());
        $this->assertSame(2603.0, RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-07-01')->value('litres'));
        $this->assertSame(1582.0, RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-08-01')->value('litres'));
    }

    public function test_normalisation_ne_rapproche_pas_deux_plaques_differentes(): void
    {
        Camion::factory()->create(['immatriculation' => 'DEMO-007']);
        $this->mockSheets([$this->ligne('DEMO-008', '01/07/2026')]);

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        $this->assertSame(0, RegistreMesure::count());
    }

    public function test_ligne_avec_matricule_hors_perimetre_est_ignoree_sans_erreur(): void
    {
        Camion::factory()->create(['immatriculation' => 'DEMO-007']);
        $this->mockSheets([$this->ligne('AUTRE-MATRICULE-999', '15/06/2026')]);

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        $this->assertSame(0, RegistreMesure::count());
    }

    public function test_ecart_superieur_a_20_pourcent_est_logue_mais_la_valeur_est_ecrasee_quand_meme(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-007']);
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id,
            'date' => '2026-03-01',
            'litres' => 200,
        ]);
        $this->mockSheets([$this->ligne('DEMO-007', '01/03/2026', litres: 1000)]); // écart 400%

        Log::spy();

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn ($message, $context) => str_contains($message, "champ 'litres'") && $context['champ'] === 'litres'
        );

        $mesure = RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-03-01')->first();
        $this->assertSame(1000.0, $mesure->litres); // non bloquant : la nouvelle valeur écrase quand même
    }

    public function test_ecart_sous_20_pourcent_nest_pas_signale(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-007']);
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id,
            'date' => '2026-03-01',
            'litres' => 1000,
        ]);
        $this->mockSheets([$this->ligne('DEMO-007', '01/03/2026', litres: 1100)]); // écart 10%

        Log::spy();

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_ancienne_valeur_nulle_ou_zero_nest_jamais_signalee_comme_divergence(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-007']);
        RegistreMesure::factory()->create([
            'camion_id' => $camion->id,
            'date' => '2026-03-01',
            'litres' => null, // premier remplissage : jamais une divergence
            'km' => 0,        // 0 = pourcentage non défini : jamais une divergence
        ]);
        $this->mockSheets([$this->ligne('DEMO-007', '01/03/2026', litres: 1000, km: 9000)]);

        Log::spy();

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_echoue_proprement_si_lordre_des_colonnes_a_change(): void
    {
        Camion::factory()->create(['immatriculation' => 'DEMO-007']);
        // Simule une réorganisation des colonnes de l'onglet DATA : la position attendue de la
        // date (index 1) contient maintenant du texte quelconque. verifierStructure() doit
        // détecter que l'échantillon n'est plus cohérent (<80% de dates valides en position
        // COL_DATE) et arrêter net, plutôt que d'importer des données mal alignées.
        $lignesAlterees = array_map(
            fn ($i) => ['x', 'PAS-UNE-DATE', 'y', "MAT{$i}", 'z', 45, 56.25, 44.1, 100, 10, 100, null, null, 'SITE A'],
            range(1, 5)
        );
        $this->mockSheets($lignesAlterees);

        $this->artisan('sheets:sync-registre')->assertFailed();

        $this->assertSame(0, RegistreMesure::count());
    }

    public function test_echoue_proprement_si_lidentifiant_du_registre_nest_pas_configure(): void
    {
        config(['services.google_sheets.registre_id' => null]);

        // Doit s'arrêter avant toute tentative de connexion à Google.
        Sheets::shouldReceive('spreadsheet')->never();

        $this->artisan('sheets:sync-registre')->assertFailed();
    }

    // % SER/usage et P.E précis — lus dans "Copy of Récap data studio" des
    // fichiers norme interne mensuels individuels, jamais dans l'onglet DATA.

    // Ordre réel des colonnes de "Copy of Récap data studio" (pas d'en-tête exploitable, 2 lignes
    // de titre puis les données) : Usage, Date, Engin, Matricule, Désignation, SER, SER 1,X, Cible,
    // Km, Heures, Litres, P,E(l/h), P,E(l/100), Site, % SER/usage (O, index 14), SER moy, SER x1,X
    // Moy, Cible Moy, Red result, Mat, All PE (U, index 20).
    private function ligneRecap(string $matricule, float $pctSerUsage, float $allPe): array
    {
        return [
            'Transport — catégorie 2', '1/8/2026', 'ENGIN1', $matricule, 'Désignation',
            45, 56.25, 44.1, 5000, 150, 2000, 7.15, 57.96, 'Site A',
            number_format($pctSerUsage * 100, 8, ',', '') . '%', 0, 0, 0, '', "ENGIN1 - {$matricule}", $allPe,
        ];
    }

    // Mock à état partagé : Sheets::spreadsheet($id)->sheet($nom)->get() peut être appelé pour
    // PLUSIEURS (id, nom) différents dans un même test (l'onglet DATA du registre, les onglets
    // index Site A/Site B du même registre, puis "Copy of Récap data studio" de chaque fichier
    // mensuel individuel) — contrairement à mockSheets() ci-dessus qui ne couvre qu'un seul (id,
    // nom). $donnees['test-spreadsheet-id']['DATA'|'Site A'|'Site B'] et $donnees[$fichierId]['Copy of
    // Récap data studio'] sont indexés par (id, nom) exactement comme le mock les distingue.
    private function mockSheetsMulti(array $donnees): void
    {
        $etat = (object) ['id' => null, 'sheet' => null];
        Sheets::shouldReceive('spreadsheet')->andReturnUsing(function ($id) use ($etat) {
            $etat->id = $id;
            return Sheets::getFacadeRoot();
        });
        Sheets::shouldReceive('sheet')->andReturnUsing(function ($sheet) use ($etat) {
            $etat->sheet = $sheet;
            return Sheets::getFacadeRoot();
        });
        Sheets::shouldReceive('get')->andReturnUsing(function () use ($etat, $donnees) {
            return collect($donnees[$etat->id][$etat->sheet] ?? []);
        });
    }

    // Index Site A/Site B : 2 lignes d'en-tête (LIGNE_INDEX_DEBUT=2), puis Mois(fr) | Année | Lien | Statut.
    private function ligneIndex(string $mois, int $annee, string $fichierId, string $statut = 'OK'): array
    {
        return [$mois, (string) $annee, "https://docs.google.com/spreadsheets/d/{$fichierId}/edit", $statut];
    }

    public function test_recap_data_studio_enrichit_le_dernier_mois_avec_pct_ser_usage_et_pe_lh_precis(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-001']);

        $this->mockSheetsMulti([
            'test-spreadsheet-id' => [
                'DATA' => [$this->ligne('DEMO-001', '01/08/2026')],
                'Site A' => [[], [], $this->ligneIndex('août', 2026, 'fichier-aout-2026')],
                'Site B' => [[], []],
            ],
            'fichier-aout-2026' => [
                'Copy of Récap data studio' => [[], [], $this->ligneRecap('DEMO-001', 0.6385760988, 7.146341)],
            ],
        ]);

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        $mesure = RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-08-01')->first();
        $this->assertNotNull($mesure);
        $this->assertEqualsWithDelta(0.6385760988, $mesure->pct_ser_usage, 0.0000001);
        $this->assertEqualsWithDelta(7.146341, $mesure->pe_lh_precis, 0.0000001);
    }

    // mois=1 (par défaut dans ces tests via l'option explicite) : seul le DERNIER mois lié du site
    // doit être lu ; un mois plus ancien du même site ne doit jamais déclencher de lecture de
    // fichier.
    public function test_recap_data_studio_respecte_la_fenetre_de_mois_recents(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-001']);

        $this->mockSheetsMulti([
            'test-spreadsheet-id' => [
                'DATA' => [
                    $this->ligne('DEMO-001', '01/07/2026'),
                    $this->ligne('DEMO-001', '01/08/2026'),
                ],
                'Site A' => [
                    [], [],
                    $this->ligneIndex('juillet', 2026, 'fichier-juillet-2026'),
                    $this->ligneIndex('août', 2026, 'fichier-aout-2026'),
                ],
                'Site B' => [[], []],
            ],
            // Aucune entrée 'fichier-juillet-2026' dans $donnees : si le code tentait quand même de
            // le lire, mockSheetsMulti() renverrait une collection vide (pas une exception) — donc
            // le test vérifie l'ABSENCE d'enrichissement de juillet, pas une erreur provoquée.
            'fichier-aout-2026' => [
                'Copy of Récap data studio' => [[], [], $this->ligneRecap('DEMO-001', 0.5, 8.0)],
            ],
        ]);

        $this->artisan('sheets:sync-registre --mois=1')->assertSuccessful();

        $aout = RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-08-01')->first();
        $juillet = RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-07-01')->first();
        $this->assertEqualsWithDelta(0.5, $aout->pct_ser_usage, 0.0000001);
        $this->assertNull($juillet->pct_ser_usage); // hors fenêtre --mois=1, jamais enrichi
    }

    // Un matricule de "Copy of Récap data studio" hors du périmètre des camions suivis (comme pour
    // l'onglet DATA principal) est ignoré silencieusement, jamais une erreur.
    public function test_recap_data_studio_ignore_un_matricule_inconnu(): void
    {
        Camion::factory()->create(['immatriculation' => 'DEMO-001']);

        $this->mockSheetsMulti([
            'test-spreadsheet-id' => [
                'DATA' => [$this->ligne('DEMO-001', '01/08/2026')],
                'Site A' => [[], [], $this->ligneIndex('août', 2026, 'fichier-aout-2026')],
                'Site B' => [[], []],
            ],
            'fichier-aout-2026' => [
                'Copy of Récap data studio' => [[], [], $this->ligneRecap('AUTRE-MATRICULE-999', 0.5, 8.0)],
            ],
        ]);

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        $this->assertSame(0, RegistreMesure::whereNotNull('pct_ser_usage')->count());
    }

    // Un camion connu mais SANS ligne registre_mesures pour ce mois précis (ex: la synchronisation
    // DATA principale n'a rien pour ce couple camion+mois ce mois-ci) ne doit JAMAIS créer de ligne
    // partielle — seulement enrichir une ligne déjà existante (update(), jamais updateOrCreate()).
    public function test_recap_data_studio_ne_cree_jamais_de_ligne_orpheline(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-001']);

        $this->mockSheetsMulti([
            'test-spreadsheet-id' => [
                // Une ligne DATA pour juillet seulement : aucune registre_mesures n'existe pour août,
                // le mois que le fichier norme interne mensuel ci-dessous tente d'enrichir.
                'DATA' => [$this->ligne('DEMO-001', '01/07/2026')],
                'Site A' => [[], [], $this->ligneIndex('août', 2026, 'fichier-aout-2026')],
                'Site B' => [[], []],
            ],
            'fichier-aout-2026' => [
                'Copy of Récap data studio' => [[], [], $this->ligneRecap('DEMO-001', 0.5, 8.0)],
            ],
        ]);

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        $this->assertSame(1, RegistreMesure::count()); // seule la ligne de juillet créée par DATA
        $this->assertNull(RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-07-01')->value('pct_ser_usage'));
    }

    // Un fichier norme interne mensuel individuel inaccessible (supprimé, droits révoqués...) est journalisé
    // et sauté — la synchronisation continue sur les autres fichiers/sites, jamais un échec net.
    public function test_recap_data_studio_continue_si_un_fichier_est_inaccessible(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-001']);

        $etat = (object) ['id' => null, 'sheet' => null];
        Sheets::shouldReceive('spreadsheet')->andReturnUsing(function ($id) use ($etat) {
            $etat->id = $id;
            return Sheets::getFacadeRoot();
        });
        Sheets::shouldReceive('sheet')->andReturnUsing(function ($sheet) use ($etat) {
            $etat->sheet = $sheet;
            return Sheets::getFacadeRoot();
        });
        Sheets::shouldReceive('get')->andReturnUsing(function () use ($etat, $camion) {
            if ($etat->id === 'test-spreadsheet-id' && $etat->sheet === 'DATA') {
                return collect([$this->ligne('DEMO-001', '01/08/2026')]);
            }
            if ($etat->id === 'test-spreadsheet-id' && $etat->sheet === 'Site A') {
                return collect([[], [], $this->ligneIndex('août', 2026, 'fichier-casse')]);
            }
            if ($etat->id === 'test-spreadsheet-id' && $etat->sheet === 'Site B') {
                return collect([[], []]);
            }
            if ($etat->id === 'fichier-casse') {
                throw new \Exception('Fichier introuvable (supprimé ou droits révoqués)');
            }
            return collect([]);
        });

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        $mesure = RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-08-01')->first();
        $this->assertNotNull($mesure); // la synchronisation DATA principale n'a pas été affectée
        $this->assertNull($mesure->pct_ser_usage); // jamais enrichi, sans faire échouer la commande
    }

    // Une ligne d'index dont le Statut n'est pas "OK" (mois pas encore lié/validé) est ignorée,
    // même si Mois/Année/Lien sont renseignés.
    public function test_recap_data_studio_ignore_une_ligne_index_sans_statut_ok(): void
    {
        $camion = Camion::factory()->create(['immatriculation' => 'DEMO-001']);

        $this->mockSheetsMulti([
            'test-spreadsheet-id' => [
                'DATA' => [$this->ligne('DEMO-001', '01/08/2026')],
                'Site A' => [[], [], $this->ligneIndex('août', 2026, 'fichier-aout-2026', statut: '')],
                'Site B' => [[], []],
            ],
            'fichier-aout-2026' => [
                'Copy of Récap data studio' => [[], [], $this->ligneRecap('DEMO-001', 0.5, 8.0)],
            ],
        ]);

        $this->artisan('sheets:sync-registre')->assertSuccessful();

        $mesure = RegistreMesure::where('camion_id', $camion->id)->where('date', '2026-08-01')->first();
        $this->assertNull($mesure->pct_ser_usage);
    }
}
