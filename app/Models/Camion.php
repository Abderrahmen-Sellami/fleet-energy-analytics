<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Camion extends Model
{
    use HasFactory;

    protected $fillable = [
        'vpi', 'vin', 'immatriculation', 'marque', 'groupe',
        'modele', 'euro_class', 'ser_reference', 'actif',
        'ser_norme_ref', 'ser_norme_ref_incertitude', 'ser_norme_ref_cible', 'usage_norme_ref',
        'conducteur_source_b', 'source_principale',
    ];

    protected $casts = [
        'ser_reference' => 'float',
        'objectif_conso' => 'float',
        'conso_initiale_ser' => 'float',
        'ser_norme_ref' => 'float',
        'ser_norme_ref_incertitude' => 'float',
        'ser_norme_ref_cible' => 'float',
    ];

    public function mesures()
    {
        return $this->hasMany(Mesure::class);
    }

    public function registreMesures()
    {
        return $this->hasMany(RegistreMesure::class);
    }

    public function alertes()
    {
        return $this->hasMany(Alerte::class);
    }

    public function suggestions()
    {
        return $this->hasMany(Suggestion::class);
    }

    public function derniereMesure()
    {
        return $this->hasOne(Mesure::class)->latestOfMany();
    }
}