<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rastreio automático: a cada 30 minutos, enfileira a sincronização dos
// contratos em andamento. Cada job define o próprio tenant.
Schedule::command('tracking:sync')->everyThirtyMinutes()->withoutOverlapping();
