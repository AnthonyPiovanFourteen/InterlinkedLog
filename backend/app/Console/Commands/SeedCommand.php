<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedCommand extends Command
{
    protected $signature = 'app:seed';

    protected $description = 'Popular dados iniciais do sistema';

    public function handle(): void
    {
        $this->call('db:seed', ['--class' => \Database\Seeders\DatabaseSeeder::class]);
        $this->info('Dados iniciais populados com sucesso!');
        $this->info('Email: admin@interlinked.io');
        $this->info('Senha: admin123');
    }
}
