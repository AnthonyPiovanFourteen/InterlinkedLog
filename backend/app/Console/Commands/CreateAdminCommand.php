<?php

namespace App\Console\Commands;

use App\Domain\Entities\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Cria a empresa inicial e o primeiro administrador.
 *
 * Separado do seed de demonstração de propósito: um sistema novo precisa de
 * alguém que consiga entrar, mas não precisa de transportadoras e contratos
 * fictícios.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
        {--company= : Nome da empresa}
        {--name= : Nome do administrador}
        {--email= : E-mail de acesso}
        {--password= : Senha de acesso}';

    protected $description = 'Cria a empresa inicial e o primeiro administrador';

    public function handle(): int
    {
        $defaults = config('app.bootstrap_admin');

        $company = $this->option('company') ?: $defaults['company'];
        $name = $this->option('name') ?: $defaults['name'];
        $email = $this->option('email') ?: $defaults['email'];
        $password = $this->option('password') ?: $defaults['password'];

        if (! $company || ! $email || ! $password) {
            $this->error('Informe empresa, e-mail e senha — por opção ou pelas variáveis ADMIN_COMPANY, ADMIN_EMAIL e ADMIN_PASSWORD.');

            return self::FAILURE;
        }

        if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->warn("Já existe usuário com o e-mail {$email} — nada a fazer.");

            return self::SUCCESS;
        }

        $companyModel = Company::create([
            'id' => Str::orderedUuid()->toString(),
            'name' => $company,
            'cnpj' => (string) ($defaults['company_cnpj'] ?? ''),
            'type' => 'Enterprise',
        ]);

        User::create([
            'id' => Str::orderedUuid()->toString(),
            'company_id' => $companyModel->id,
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => Role::ADMIN,
            'status' => 'Ativo',
        ]);

        $this->info("Empresa '{$company}' e administrador {$email} criados.");

        return self::SUCCESS;
    }
}
