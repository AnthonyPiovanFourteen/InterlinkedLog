<?php

namespace App\Infrastructure\Gateways;

use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\Contract;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Domain\Services\TrackingGateway;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Jadlog — POST /embarcador/api/tracking/consultar.
 *
 * Consulta pela nota fiscal do contrato. A resposta traz
 * consulta[].tracking.eventos[] com data, status e unidade; os eventos não têm
 * identificador próprio, então a chave de deduplicação é derivada de
 * data + status, que é o que os distingue na origem.
 */
class JadlogTrackingGateway implements TrackingGateway
{
    private const URL = 'https://prd-traffic.jadlogtech.com.br/embarcador/api/tracking/consultar';

    private const TIMEOUT_SECONDS = 8;

    /** 1 = Nota Fiscal, conforme a tabela de tpDocumento da Jadlog. */
    private const TIPO_DOCUMENTO_NF = 1;

    public function name(): string
    {
        return 'jadlog';
    }

    public function events(Contract $contract, CarrierCredential $credential): array
    {
        $token = $credential->secret('token');

        if (! $token) {
            throw new CarrierGatewayException('Credencial Jadlog sem token');
        }

        try {
            $response = Http::asJson()
                ->withHeaders(['Authorization' => $token])
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(self::URL, ['consulta' => [[
                    'df' => [
                        'nf' => $contract->nfNumber,
                        'tpDocumento' => self::TIPO_DOCUMENTO_NF,
                    ],
                ]]]);
        } catch (Throwable $e) {
            throw new CarrierGatewayException('Jadlog indisponível (rastreio): '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new CarrierGatewayException('Jadlog (rastreio) respondeu '.$response->status());
        }

        $consulta = $response->json('consulta');

        if (! is_array($consulta) || $consulta === []) {
            return [];
        }

        $eventos = $consulta[0]['tracking']['eventos'] ?? [];
        $events = [];

        foreach (is_array($eventos) ? $eventos : [] as $evento) {
            $raw = (string) ($evento['data'] ?? '');
            $status = trim((string) ($evento['status'] ?? ''));

            if ($raw === '' || $status === '') {
                continue;
            }

            // "2023-03-22 15:12:06"
            [$date, $time] = array_pad(explode(' ', $raw, 2), 2, '00:00:00');

            $events[] = [
                'external_id' => substr(sha1($raw.'|'.$status), 0, 40),
                'title' => $status,
                'date' => $date,
                'time' => substr($time, 0, 5),
            ];
        }

        usort($events, fn ($a, $b) => ($a['date'].$a['time']) <=> ($b['date'].$b['time']));

        return $events;
    }
}
