<?php

namespace App\Infrastructure\Services;

use App\Domain\Exceptions\CepLookupUnavailableException;
use App\Domain\Services\CepLookupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ViaCepLookupService implements CepLookupService
{
    private array $cepMap = [
        '01000' => ['São Paulo', 'SP'], '02000' => ['São Paulo', 'SP'],
        '20000' => ['Rio de Janeiro', 'RJ'], '21000' => ['Rio de Janeiro', 'RJ'],
        '30000' => ['Belo Horizonte', 'MG'], '31000' => ['Belo Horizonte', 'MG'],
        '40000' => ['Salvador', 'BA'], '41000' => ['Salvador', 'BA'],
        '50000' => ['Recife', 'PE'], '51000' => ['Recife', 'PE'],
        '60000' => ['Fortaleza', 'CE'], '61000' => ['Fortaleza', 'CE'],
        '70000' => ['Brasília', 'DF'], '71000' => ['Brasília', 'DF'],
        '80000' => ['Curitiba', 'PR'], '81000' => ['Curitiba', 'PR'],
        '90000' => ['Porto Alegre', 'RS'], '91000' => ['Porto Alegre', 'RS'],
        '74000' => ['Goiânia', 'GO'], '69000' => ['Manaus', 'AM'],
        '17500' => ['Marília', 'SP'],
        '86020' => ['Londrina', 'PR'],
    ];

    public function lookup(string $cep): ?array
    {
        $cep = preg_replace('/\D/', '', $cep);
        $prefix = substr($cep, 0, 5);

        $mapped = $this->cepMap[$prefix] ?? null;
        if ($mapped) {
            return $mapped;
        }

        if (strlen($cep) !== 8) {
            return null;
        }

        return Cache::remember("cep_lookup:{$cep}", now()->addDays(30), function () use ($cep) {
            $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/");

            if ($response->failed()) {
                throw new CepLookupUnavailableException();
            }

            $data = $response->json();
            if (!is_array($data) || !empty($data['erro'])) {
                return null;
            }

            return [$data['localidade'], $data['uf']];
        });
    }
}