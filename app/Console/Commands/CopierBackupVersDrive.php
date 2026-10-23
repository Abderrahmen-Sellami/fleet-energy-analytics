<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CopierBackupVersDrive extends Command
{
    protected $signature = 'backup:copier-vers-drive';

    protected $description = "Copie la sauvegarde locale la plus récente (backup:run) vers un dossier Drive dédié "
        . "(scope Drive demandé uniquement par ce client ; config('google.scopes') reste en lecture seule).";

    private const NOM_DOSSIER = 'Fleet Energy Analytics — Sauvegardes';

    public function handle(): int
    {
        if (! config('google.service.enable')) {
            $this->error("Le compte de service Google n'est pas activé (GOOGLE_SERVICE_ENABLED) — impossible de copier vers Drive.");

            return self::FAILURE;
        }

        $fichierCredentials = config('google.service.file');
        if (empty($fichierCredentials) || ! file_exists($fichierCredentials)) {
            $this->error("Fichier de credentials Google introuvable ({$fichierCredentials}).");

            return self::FAILURE;
        }

        $zipLocal = $this->dernierZipLocal();
        if (! $zipLocal) {
            $this->error('Aucune sauvegarde locale trouvée dans storage/app/backups — lance d\'abord backup:run.');

            return self::FAILURE;
        }

        try {
            $drive = $this->creerServiceDrive($fichierCredentials);
            $dossierId = $this->trouverOuCreerDossier($drive);

            $fichierId = $this->televerser($drive, $dossierId, $zipLocal['chemin'], $zipLocal['nom']);

            // Vérifie la taille rapportée par Drive après upload contre la taille locale — seul
            // signal nécessaire (sans retélécharger) qu'aucune troncature/corruption n'a eu lieu
            // pendant le transfert.
            $fichierDrive = $drive->files->get($fichierId, ['fields' => 'id, name, size']);
            $tailleDrive = (int) $fichierDrive->getSize();

            if ($tailleDrive !== $zipLocal['taille']) {
                $this->error("Taille différente après upload : local={$zipLocal['taille']} octets, Drive={$tailleDrive} octets — upload potentiellement corrompu.");
                Log::error('backup:copier-vers-drive : taille incohérente après upload', [
                    'fichier' => $zipLocal['nom'],
                    'taille_locale' => $zipLocal['taille'],
                    'taille_drive' => $tailleDrive,
                ]);

                return self::FAILURE;
            }

            $this->info("Copié vers Drive : {$zipLocal['nom']} ({$tailleDrive} octets) dans le dossier '".self::NOM_DOSSIER."'.");

            $supprimes = $this->purgerAnciens($drive, $dossierId);
            if ($supprimes > 0) {
                $this->info("{$supprimes} ancienne(s) sauvegarde(s) supprimée(s) sur Drive (rétention dépassée).");
            }

            Log::info('backup:copier-vers-drive : succès', [
                'fichier' => $zipLocal['nom'],
                'taille' => $tailleDrive,
                'dossier_id' => $dossierId,
                'supprimes' => $supprimes,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Échec de la copie vers Drive : {$e->getMessage()}");
            Log::error('backup:copier-vers-drive : échec', ['erreur' => $e->getMessage()]);

            return self::FAILURE;
        }
    }

    /** @return array{chemin: string, nom: string, taille: int}|null */
    private function dernierZipLocal(): ?array
    {
        $disque = Storage::disk('backups');
        $sousDossier = config('backup.backup.name', 'laravel-backup');

        $plusRecent = collect($disque->files($sousDossier))
            ->filter(fn (string $f) => str_ends_with($f, '.zip'))
            ->sortByDesc(fn (string $f) => $disque->lastModified($f))
            ->first();

        if (! $plusRecent) {
            return null;
        }

        return [
            'chemin' => $disque->path($plusRecent),
            'nom' => basename($plusRecent),
            'taille' => $disque->size($plusRecent),
        ];
    }

    private function creerServiceDrive(string $fichierCredentials): Drive
    {
        $client = new GoogleClient;
        $client->setApplicationName(config('google.application_name') ?: 'Fleet Energy Analytics');
        $client->setAuthConfig($fichierCredentials);
        $client->setScopes([Drive::DRIVE]);

        return new Drive($client);
    }

    private function trouverOuCreerDossier(Drive $drive): string
    {
        $requete = sprintf(
            "mimeType = 'application/vnd.google-apps.folder' and name = '%s' and trashed = false",
            addslashes(self::NOM_DOSSIER)
        );

        $resultats = $drive->files->listFiles([
            'q' => $requete,
            'fields' => 'files(id, name)',
            'spaces' => 'drive',
        ]);

        $existant = $resultats->getFiles()[0] ?? null;
        if ($existant) {
            return $existant->getId();
        }

        $dossier = new DriveFile([
            'name' => self::NOM_DOSSIER,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        $cree = $drive->files->create($dossier, ['fields' => 'id']);

        $this->info("Dossier Drive '".self::NOM_DOSSIER."' créé (id: {$cree->getId()}).");

        return $cree->getId();
    }

    private function televerser(Drive $drive, string $dossierId, string $chemin, string $nom): string
    {
        $metadonnees = new DriveFile([
            'name' => $nom,
            'parents' => [$dossierId],
        ]);

        $fichier = $drive->files->create($metadonnees, [
            'data' => file_get_contents($chemin),
            'mimeType' => 'application/zip',
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);

        return $fichier->getId();
    }

    /**
     * Même fenêtre de rétention que le nettoyage local (config('backup.cleanup.default_strategy.
     * keep_daily_backups_for_days'), 14 jours) — lue depuis cette config plutôt que redupliquée en
     * dur ici, pour que les deux rétentions ne puissent pas diverger silencieusement si l'une change.
     */
    private function purgerAnciens(Drive $drive, string $dossierId): int
    {
        $conserver = (int) (config('backup.cleanup.default_strategy.keep_daily_backups_for_days') ?? 14);

        $resultats = $drive->files->listFiles([
            'q' => "'{$dossierId}' in parents and trashed = false",
            'fields' => 'files(id, name, createdTime)',
            'orderBy' => 'createdTime desc',
            'pageSize' => 1000,
        ]);

        /** @var Collection $fichiers */
        $fichiers = collect($resultats->getFiles());
        $aSupprimer = $fichiers->slice($conserver);

        $aSupprimer->each(fn ($f) => $drive->files->delete($f->getId()));

        return $aSupprimer->count();
    }
}
