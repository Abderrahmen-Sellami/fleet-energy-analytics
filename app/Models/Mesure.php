<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mesure extends Model
{
    use HasFactory;


    // Borne haute de plausibilité pour consommation_l100.
    public const CONSO_L100_MAX_PLAUSIBLE = 100.0;

    protected $appends = ['conso_exploitable'];

    protected $fillable = [
        'camion_id', 'date', 'consommation_l100',
        'km_parcourus', 'taux_ralenti', 'ecoscore', 'heures_moteur',
        'anticipation', 'utilisation_abusive', 'utilisation_optimale',
        'arret', 'vitesse_moyenne', 'nb_arrets', 'conso_horaire',
        'usage_mode_puissance', 'engine_overrev', 'est_partielle',
    ];

    protected $casts = [
        'date' => 'date',
        'consommation_l100' => 'float',
        'km_parcourus' => 'float',
        'heures_moteur' => 'float',
        'ecoscore' => 'float',
        'anticipation' => 'float',
        'utilisation_abusive' => 'float',
        'utilisation_optimale' => 'float',
        'arret' => 'float',
        'taux_ralenti' => 'float',
        'vitesse_moyenne' => 'float',
        'nb_arrets' => 'float',
        'conso_horaire' => 'float',
        'usage_mode_puissance' => 'float',
        'engine_overrev' => 'float',
        'est_partielle' => 'boolean',
    ];

    public function camion()
    {
        return $this->belongsTo(Camion::class);
    }

    // Même filtre partout : whereNotNull + > 0 (0 = pas de donnée Télématique A ce mois-ci.
    public function scopeExploitable($query)
    {
        return $query->whereNotNull('consommation_l100')
            ->where('consommation_l100', '>', 0)
            ->where('consommation_l100', '<=', self::CONSO_L100_MAX_PLAUSIBLE)
            ->where('est_partielle', false);
    }

    public static function estValeurConsoExploitable($valeur): bool
    {
        return $valeur !== null && $valeur > 0 && $valeur <= self::CONSO_L100_MAX_PLAUSIBLE;
    }

    // Appended en JSON pour que le frontend (dashboard.blade.php, direction.blade.php) applique
    // le même critère sans dupliquer le seuil numérique côté JS.
    public function getConsoExploitableAttribute(): bool
    {
        return self::estValeurConsoExploitable($this->consommation_l100);
    }
}