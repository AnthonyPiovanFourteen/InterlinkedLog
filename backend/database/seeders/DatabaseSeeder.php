<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Models\Carrier;
use App\Models\FreightTable;
use App\Models\FreightTableRoute;
use App\Models\FreightTableWeightRange;
use App\Models\FreightTableFee;
use App\Models\Quotation;
use App\Models\QuotationResult;
use App\Models\Contract;
use App\Models\TrackingEvent;
use App\Models\SystemLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Company
        $company = Company::create([
            'id' => Str::orderedUuid()->toString(),
            'name' => 'Empresa Modelo Ltda',
            'cnpj' => '00.000.000/0001-00',
            'type' => 'Enterprise',
        ]);

        // 2. Users (Admin + 2 Usuário)
        $admin = User::create([
            'id' => Str::orderedUuid()->toString(),
            'company_id' => $company->id,
            'name' => 'Admin Master',
            'email' => 'admin@interlinked.io',
            'password' => bcrypt('admin123'),
            'role' => 'Admin',
            'status' => 'Ativo',
        ]);

        $users = [['Marina Costa', 'marina@interlinked.io'], ['Rafael Souza', 'rafael@interlinked.io']];
        $userIds = [$admin->id];
        foreach ($users as [$name, $email]) {
            $u = User::create([
                'id' => Str::orderedUuid()->toString(),
                'company_id' => $company->id,
                'name' => $name,
                'email' => $email,
                'password' => bcrypt('admin123'),
                'role' => 'Usuário',
                'status' => 'Ativo',
            ]);
            $userIds[] = $u->id;
        }

        $carrierData = [
            ['Braspress', '11.222.333/0001-44', 'São Paulo', 'SP'],
            ['Jamef', '22.333.444/0001-55', 'Belo Horizonte', 'MG'],
            ['TNT Mercúrio', '33.444.555/0001-66', 'São Paulo', 'SP'],
            ['Rodonaves', '44.555.666/0001-77', 'Ribeirão Preto', 'SP'],
            ['Patrus', '55.666.777/0001-88', 'São Paulo', 'SP'],
            ['Solistica', '66.777.888/0001-99', 'São Paulo', 'SP'],
            ['JSL', '77.888.999/0001-00', 'São Paulo', 'SP'],
            ['Total Express', '88.999.000/0001-11', 'São Paulo', 'SP'],
        ];

        $carrierIds = [];
        foreach ($carrierData as [$name, $cnpj, $city, $uf]) {
            $carrier = Carrier::create([
                'id' => Str::orderedUuid()->toString(),
                'company_id' => $company->id,
                'name' => $name,
                'cnpj' => $cnpj,
                'origin_city' => $city,
                'origin_uf' => $uf,
                'contact_name' => 'Contato ' . $name,
                'contact_phone' => '(11) 3000-0000',
                'status' => 'Ativo',
            ]);
            $carrierIds[] = $carrier->id;

            // Simple freight table for each carrier
            $ft = FreightTable::create([
                'id' => Str::orderedUuid()->toString(),
                'carrier_id' => $carrier->id,
                'name' => 'Tabela Geral ' . $name,
                'valid_from' => '2026-01-01',
                'valid_until' => '2026-12-31',
                'status' => 'Ativa',
            ]);

            // Fees
            $fees = [
                ['ad_valorem', 0.30, true],
                ['gris', 18.90, false],
                ['despacho', 25.00, false],
                ['pedagio', 5.00, true],
                ['frete_minimo', 50.00, false],
                ['cubagem', 300, false],
                ['tde', 40.00, false],
            ];
            foreach ($fees as [$type, $value, $pct]) {
                FreightTableFee::create([
                    'id' => Str::orderedUuid()->toString(),
                    'freight_table_id' => $ft->id,
                    'fee_type' => $type,
                    'value' => $value,
                    'is_percentage' => $pct,
                ]);
            }

            // A few routes
            $routes_data = [
                ['São Paulo', 'SP', 'Londrina', 'PR'],
                ['São Paulo', 'SP', 'Curitiba', 'PR'],
                ['São Paulo', 'SP', 'Rio de Janeiro', 'RJ'],
                ['São Paulo', 'SP', 'Campinas', 'SP'],
            ];
            foreach ($routes_data as $rdata) {
                $route = FreightTableRoute::create([
                    'id' => Str::orderedUuid()->toString(),
                    'freight_table_id' => $ft->id,
                    'origin_city' => $rdata[0], 'origin_uf' => $rdata[1],
                    'destination_city' => $rdata[2], 'destination_uf' => $rdata[3],
                ]);

                // Weight ranges
                $wrs = [[0,30,85.50,2], [31,100,142.00,3], [101,300,285.00,4]];
                foreach ($wrs as $wr) {
                    FreightTableWeightRange::create([
                        'id' => Str::orderedUuid()->toString(),
                        'freight_table_route_id' => $route->id,
                        'min_weight' => $wr[0], 'max_weight' => $wr[1],
                        'freight_value' => $wr[2], 'deadline_days' => $wr[3],
                    ]);
                }
            }
        }

        // 5. Sample Quotations with results
        $qdata = [
            ['000001', '17500000', '86020000', 'Marília', 'Londrina', 'PR', 45, 10, 0.15, 5000],
            ['000002', '17500000', '01310100', 'Marília', 'São Paulo', 'SP', 120, 5, 0.40, 12500],
            ['000003', '17500000', '80010000', 'Marília', 'Curitiba', 'PR', 80, 15, 0.27, 8900],
        ];

        foreach ($qdata as [$nf, $ocep, $dcep, $ocity, $dcity, $ds, $w, $b, $v, $cv]) {
            $q = Quotation::create([
                'id' => Str::orderedUuid()->toString(),
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'nf_number' => $nf,
                'sender_cnpj' => '12.345.678/0001-99',
                'receiver_cnpj' => '98.765.432/0001-88',
                'origin_cep' => $ocep, 'destination_cep' => $dcep,
                'origin_city' => $ocity, 'destination_city' => $dcity,
                'destination_state' => $ds,
                'weight' => $w, 'boxes' => $b, 'volume' => $v,
                'cargo_value' => $cv,
                'status' => $nf === '000001' ? 'CONTRATADA' : 'VALIDA',
                'valid_until' => now()->addDays(7),
            ]);

            // Results for each carrier
            foreach ($carrierIds as $ci => $cid) {
                $fv = round(80 + ($ci * 15) + ($w * 0.3), 2);
                QuotationResult::create([
                    'id' => Str::orderedUuid()->toString(),
                    'quotation_id' => $q->id,
                    'carrier_id' => $cid,
                    'carrier_name' => $carrierData[$ci][0],
                    'freight_value' => $fv,
                    'fees' => round($fv * 0.2, 2),
                    'final_value' => round($fv * 1.2, 2),
                    'deadline' => 2 + ($ci % 3),
                    'fees_breakdown' => [],
                ]);
            }
        }

        // 6. Contract for quotation 000001 with first carrier
        $q1 = Quotation::where('nf_number', '000001')->first();
        $bestResult = QuotationResult::where('quotation_id', $q1->id)->orderBy('final_value')->first();
        $contract = Contract::create([
            'id' => Str::orderedUuid()->toString(),
            'company_id' => $company->id,
            'quotation_id' => $q1->id,
            'carrier_id' => $bestResult->carrier_id,
            'carrier_name' => $bestResult->carrier_name,
            'nf_number' => $q1->nf_number,
            'origin_city' => $q1->origin_city,
            'destination_city' => $q1->destination_city,
            'destination_state' => $q1->destination_state,
            'freight_value' => $bestResult->freight_value,
            'fees' => $bestResult->fees,
            'final_value' => $bestResult->final_value,
            'deadline' => $bestResult->deadline,
            'status' => 'Em Trânsito',
            'document_number' => 'OC-' . date('Ymd') . '-0001',
            'cte_number' => null,
        ]);

        // Tracking events for the contract
        $events = [
            ['Coleta Agendada', now()->subDays(3)->format('Y-m-d'), '08:00', 'Contratação confirmada'],
            ['Coletado', now()->subDays(2)->format('Y-m-d'), '10:30', 'Motorista João, placa ABC-1234'],
            ['Em Rota', now()->subDays(1)->format('Y-m-d'), '14:00', 'Saiu de Marília'],
        ];
        foreach ($events as [$title, $date, $time, $obs]) {
            TrackingEvent::create([
                'id' => Str::orderedUuid()->toString(),
                'contract_id' => $contract->id,
                'title' => $title, 'date' => $date, 'time' => $time,
                'observation' => $obs,
            ]);
        }

        // 7. System Logs (10 entries)
        $log_entries = [
            ['INFO', 'login', 'Usuário admin@interlinked.io realizou login'],
            ['INFO', 'criacao', 'Cotação #001 criada para NF 000.000.001'],
            ['INFO', 'contratacao', 'Contratação realizada com Braspress'],
            ['WARNING', 'login', 'Tentativa de login com senha incorreta'],
            ['INFO', 'cadastro', 'Transportadora cadastrada'],
            ['INFO', 'importacao', 'Tabela de frete importada'],
            ['INFO', 'criacao', 'Novo usuário cadastrado'],
            ['WARNING', 'expiracao', 'Cotação expirada sem contratação'],
            ['ERROR', 'erro', 'Falha ao processar cotação - CEP inválido'],
            ['INFO', 'relatorio', 'Relatório de economia gerado'],
        ];
        foreach ($log_entries as $i => [$level, $event, $message]) {
            SystemLog::create([
                'id' => Str::orderedUuid()->toString(),
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'user_name' => 'Admin Master',
                'level' => $level,
                'event' => $event,
                'message' => $message,
                'created_at' => now()->subDays(rand(0, 5))->setTime(rand(8, 20), rand(0, 59)),
            ]);
        }
    }
}
