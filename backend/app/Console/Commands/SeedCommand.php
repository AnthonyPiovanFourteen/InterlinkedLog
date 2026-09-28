<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class SeedCommand extends Command
{
    protected $signature = 'app:seed';

    protected $description = 'Popular dados iniciais do sistema';

    public function handle(): void
    {
        // Dados de demonstração: só rodam quando o modo demo está ligado.
        if (! config('app.demo_mode')) {
            $this->warn('Modo demo desligado (DEMO_MODE). Nenhum dado de demonstração foi criado.');
            $this->line('Para o primeiro acesso, use: php artisan app:create-admin');

            return;
        }

        $this->call('db:seed', ['--class' => DatabaseSeeder::class]);
        $this->info('Dados de DEMONSTRAÇÃO populados.');
        $this->info('Email: admin@interlinked.io');
        $this->info('Senha: admin123');
    }
}
