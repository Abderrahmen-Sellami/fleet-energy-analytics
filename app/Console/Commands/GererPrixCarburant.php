<?php

namespace App\Console\Commands;

use App\Models\PrixCarburantHistorique;
use App\Support\Concerns\ValideDatesCalendaires;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GererPrixCarburant extends Command
{
    use ValideDatesCalendaires;

    protected $signature = 'carburant:prix
        {prix : Nouveau prix HT du gasoil, en TND/L (ex: 2.050)}
        {--date= : Date d\'entrée en vigueur (AAAA-MM-JJ), par défaut aujourd\'hui}';

    protected $description = "Enregistre un changement de prix du gasoil HT dans l'historique (prix_carburant_historique) — le Coût carburant de la Fiche camion utilise ensuite le prix en vigueur à la date de chaque mesure au lieu du prix actuel appliqué rétroactivement";

    public function handle(): int
    {
        $prixBrut = $this->argument('prix');
        if (!is_numeric($prixBrut) || (float) $prixBrut <= 0) {
            $this->error('Le prix doit être un nombre positif (ex: 1.985).');
            return Command::FAILURE;
        }
        $prix = (float) $prixBrut;

        $dateOption = $this->option('date') ?? now()->toDateString();
        // Validation calendaire stricte : la regex seule laissait passer ou, que Carbon décalait
        // silencieusement vers une autre date enregistrée ensuite comme date d'effet du prix —
        // dans la base réelle, sans aucun message.
        if (!$this->estDateCalendaireValide($dateOption)) {
            $this->error('Date invalide, format attendu AAAA-MM-JJ (date du calendrier réel).');
            return Command::FAILURE;
        }
        $dateDebut = Carbon::createFromFormat('!Y-m-d', $dateOption)->toDateString();

        // Ferme la période précédente encore ouverte, si elle précède la nouvelle date d'effet.
        $precedente = PrixCarburantHistorique::whereNull('date_fin')
            ->where('date_debut', '<', $dateDebut)
            ->orderByDesc('date_debut')
            ->first();
        if ($precedente) {
            $finPrecedente = Carbon::parse($dateDebut)->subDay()->toDateString();
            $precedente->update(['date_fin' => $finPrecedente]);
            $this->info("Période précédente ({$precedente->date_debut->toDateString()} → {$finPrecedente}) clôturée.");
        }

        // Si une période démarre après la nouvelle date (saisie rétroactive), la nouvelle période
        // s'arrête juste avant, pour ne jamais avoir deux prix ouverts en même temps.
        $suivante = PrixCarburantHistorique::where('date_debut', '>', $dateDebut)
            ->orderBy('date_debut')
            ->first();
        $dateFin = $suivante ? Carbon::parse($suivante->date_debut)->subDay()->toDateString() : null;

        PrixCarburantHistorique::updateOrCreate(
            ['date_debut' => $dateDebut],
            ['prix_ht' => $prix, 'date_fin' => $dateFin]
        );

        $this->info(
            "Prix gasoil HT = {$prix} TND/L enregistré à partir du {$dateDebut}"
            . ($dateFin ? " jusqu'au {$dateFin}." : " (en vigueur jusqu'à nouvel ordre).")
        );

        return Command::SUCCESS;
    }
}
