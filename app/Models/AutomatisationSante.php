<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomatisationSante extends Model
{
    protected $table = 'automatisations_sante';

    protected $fillable = ['tache', 'derniere_execution_reussie'];

    protected $casts = [
        'derniere_execution_reussie' => 'datetime',
    ];
}
