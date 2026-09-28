<?php

namespace Database\Seeders;

use App\Domain\Entities\CarrierStatus;
use App\Models\Carrier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Transportadoras para as quais existe adaptador de API.
 *
 * Roda SEMPRE, independente do modo demo: não são dados fictícios, são o
 * catálogo de quem o sistema sabe consultar. Sem elas cadastradas, o usuário
 * não teria onde configurar a credencial.
 *
 * O nome é o que casa com o gateway (CarrierGateway::supports), então alterá-lo
 * desliga a integração daquela transportadora.
 *
 * O CNPJ fica em branco de propósito: preencher com número não confirmado seria
 * pior que deixar vazio, porque ele sai em documento. O usuário completa no
 * cadastro.
 */
class CarrierCatalogSeeder extends Seeder
{
    /** @var array<int,array{0:string,1:string,2:string}> nome, cidade, UF */
    private array $catalog = [
        ['Braspress', 'São Paulo', 'SP'],
        ['Jadlog', 'São Paulo', 'SP'],
        ['Jamef', 'Belo Horizonte', 'MG'],
        ['Loggi', 'São Paulo', 'SP'],
        ['Rodonaves', 'Ribeirão Preto', 'SP'],
    ];

    public function run(): void
    {
        foreach ($this->catalog as [$name, $city, $uf]) {
            // Idempotente pelo nome: rodar de novo não duplica nem sobrescreve
            // o que o usuário já ajustou (CNPJ, contato).
            Carrier::withoutGlobalScopes()->firstOrCreate(
                ['name' => $name],
                [
                    'id' => Str::orderedUuid()->toString(),
                    'cnpj' => '',
                    'origin_city' => $city,
                    'origin_uf' => $uf,
                    'status' => CarrierStatus::ATIVA,
                ]
            );
        }
    }
}
