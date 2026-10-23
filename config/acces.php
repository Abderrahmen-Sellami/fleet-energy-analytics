<?php

// Seul point d'usage restant : la migration de données
// `2026_09_26_140100_amorcer_admin_depuis_acces_partage`, qui amorce le tout premier compte Admin
// à partir de ces valeurs pour ne pas casser l'accès existant.
return [
    'identifiant' => env('ACCES_IDENTIFIANT'),
    'mot_de_passe' => env('ACCES_MOT_DE_PASSE'),
];
