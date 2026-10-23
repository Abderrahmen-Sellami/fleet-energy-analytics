<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

public function boot(): void
{
    // Longueur d'index par défaut sous utf8mb4 (4 octets/caractère) : sans ce réglage, une colonne
    // string() migrée sans longueur explicite (VARCHAR(255), donc 1020 octets d'index) dépasse la
    // limite MyISAM (1000 octets) et bloque dès la toute première migration sur une base fraîche.
    Schema::defaultStringLength(191);

    if (str_contains(request()->header('host'), 'ngrok-free.dev')) {
        URL::forceScheme('https');
    }
}
}