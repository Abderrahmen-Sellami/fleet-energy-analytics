<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusion temps réel (WebSocket) d'un changement de statut de la norme interne — même structure
 * que PositionMiseAJour.
 */
class StatutSerMisAJour implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $id;
    public string $immatriculation;
    public ?string $groupe;
    public string $statut;
    public ?string $raisonInsuffisance;
    public ?float $ipe;
    public ?string $unite;
    public ?float $ser;
    public ?float $ser1x;
    public ?float $cible;
    public ?string $derniereDate;
    public ?string $sourceAActifDepuis;

    public function __construct(array $statutSer)
    {
        $this->id = $statutSer['id'];
        $this->immatriculation = $statutSer['immatriculation'];
        $this->groupe = $statutSer['groupe'] ?? null;
        $this->statut = $statutSer['statut'];
        $this->raisonInsuffisance = $statutSer['raison_insuffisance'] ?? null;
        $this->ipe = $statutSer['ipe'] ?? null;
        $this->unite = $statutSer['unite'] ?? null;
        $this->ser = $statutSer['ser'] ?? null;
        $this->ser1x = $statutSer['ser_1x'] ?? null;
        $this->cible = $statutSer['cible'] ?? null;
        $this->derniereDate = $statutSer['derniere_date'] ?? null;
        // Uniquement présent côté branche DONNEES_INSUFFISANTES sans mesure exploitable (cf.
        // SerDynamiqueController::index()) — nécessaire à texteDonneesInsuffisantes() côté frontend
        // pour le cas ABONNEMENT_RECENT, même valeur que ce que /api/ser-dynamique renvoie déjà.
        $this->sourceAActifDepuis = $statutSer['source_a_actif_depuis'] ?? null;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('ser-dynamique-flotte');
    }

    public function broadcastAs(): string
    {
        return 'statut.maj';
    }

    // Même forme que la réponse JSON de SerDynamiqueController::index() (un élément du tableau) —
    // pour que le frontend puisse remplacer directement une entrée de SER_DYN_DATA par ce payload
    // sans transformation, exactement comme il consomme déjà /api/ser-dynamique.
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'immatriculation' => $this->immatriculation,
            'groupe' => $this->groupe,
            'statut' => $this->statut,
            'raison_insuffisance' => $this->raisonInsuffisance,
            'ipe' => $this->ipe,
            'unite' => $this->unite,
            'ser' => $this->ser,
            'ser_1x' => $this->ser1x,
            'cible' => $this->cible,
            'derniere_date' => $this->derniereDate,
            'source_a_actif_depuis' => $this->sourceAActifDepuis,
        ];
    }
}
