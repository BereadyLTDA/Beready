<?php

use App\Services\LembreteService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('beready:lembretes', function (LembreteService $service) {
    $this->info($service->enviar().' lembrete(s) enviado(s)');
})->purpose('Envia o lembrete de estudo para quem tem cards vencidos e parou de estudar');

// Em produção, um cron precisa chamar "php artisan schedule:run" a cada minuto
Schedule::command('beready:lembretes')->dailyAt('18:00')->withoutOverlapping();
