<?php

namespace App\Events;

use App\Models\PositionHistorique;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// Prototype temps réel, scopé à la position GPS uniquement; ce POC reste volontairement minimal :
// un canal public, pas d'authentification de canal, un seul event)
class PositionMiseAJour implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $camionId;
    public float $latitude;
    public float $longitude;
    public ?float $vitesse;
    public string $horodatage;

    public function __construct(PositionHistorique $position)
    {
        $this->camionId = $position->camion_id;
        $this->latitude = $position->latitude;
        $this->longitude = $position->longitude;
        $this->vitesse = $position->vitesse;
        $this->horodatage = $position->horodatage_position->toIso8601String();
    }

    public function broadcastOn(): Channel
    {
        return new Channel('positions-flotte');
    }

    public function broadcastAs(): string
    {
        return 'position.maj';
    }

    public function broadcastWith(): array
    {
        return [
            'camion_id' => $this->camionId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'vitesse' => $this->vitesse,
            'horodatage' => $this->horodatage,
        ];
    }
}
