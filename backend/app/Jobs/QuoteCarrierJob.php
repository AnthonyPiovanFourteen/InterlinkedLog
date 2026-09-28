<?php

namespace App\Jobs;

use App\Domain\Entities\QuotationGatewayAttempt;
use App\Domain\Exceptions\CarrierGatewayException;
use App\Domain\Repositories\CarrierCredentialRepository;
use App\Domain\Repositories\CarrierRepository;
use App\Domain\Repositories\QuotationGatewayAttemptRepository;
use App\Domain\Repositories\QuotationRepository;
use App\Domain\Services\CarrierGateway;
use App\Domain\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Cota UMA transportadora, fora da request. Um job por gateway: assim uma
 * transportadora lenta ou fora do ar não atrasa nem derruba as demais, e o
 * resultado aparece na tela assim que chega.
 *
 * O companyId viaja no payload porque o worker não tem requisição HTTP — sem
 * ele o escopo de tenant ficaria inerte e o job veria dados de todos.
 */
class QuoteCarrierJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public string $companyId,
        public string $quotationId,
        public string $gatewayName,
    ) {}

    public function handle(
        TenantContext $tenant,
        QuotationRepository $quotations,
        CarrierRepository $carriers,
        CarrierCredentialRepository $credentials,
        QuotationGatewayAttemptRepository $attempts,
    ): void {
        $tenant->runAs($this->companyId, function () use ($quotations, $carriers, $credentials, $attempts) {
            $quotation = $quotations->findById($this->quotationId);

            if (! $quotation) {
                return;
            }

            $gateway = $this->resolveGateway();

            if (! $gateway) {
                $this->record($attempts, QuotationGatewayAttempt::ERROR, 'Gateway não encontrado');

                return;
            }

            $carrier = null;
            $credential = null;

            foreach ($credentials->activeForCompany($this->companyId) as $carrierId => $candidate) {
                if ($candidate->gateway !== $this->gatewayName) {
                    continue;
                }
                $found = $carriers->findById($carrierId);
                if ($found && $gateway->supports($found)) {
                    $carrier = $found;
                    $credential = $candidate;
                    break;
                }
            }

            if (! $carrier || ! $credential) {
                $this->record($attempts, QuotationGatewayAttempt::ERROR, 'Credencial ativa não encontrada');

                return;
            }

            try {
                $quote = $gateway->quote($quotation, $carrier, $credential);
            } catch (CarrierGatewayException $e) {
                // Falha da transportadora ou de configuração: mensagem chega ao
                // usuário, as outras transportadoras seguem.
                $this->record($attempts, QuotationGatewayAttempt::UNAVAILABLE, $e->getMessage());

                return;
            } catch (Throwable $e) {
                // Inesperado: é nosso. Registra como erro para investigarmos.
                $this->record($attempts, QuotationGatewayAttempt::ERROR, $e->getMessage());

                throw $e;
            }

            if ($quote === null) {
                $this->record($attempts, QuotationGatewayAttempt::NOT_SERVED, null);

                return;
            }

            // O CarrierQuote não carrega o nome do gateway; quem sabe é o job.
            $quotations->appendResult($this->quotationId, $quote->toResult() + [
                'gateway' => $this->gatewayName,
            ]);
            $this->record($attempts, QuotationGatewayAttempt::QUOTED, null);
        });
    }

    /** Falha definitiva depois das tentativas: o usuário precisa saber. */
    public function failed(?Throwable $e): void
    {
        app(TenantContext::class)->runAs($this->companyId, function () use ($e) {
            app(QuotationGatewayAttemptRepository::class)->save(new QuotationGatewayAttempt(
                id: null,
                quotationId: $this->quotationId,
                gateway: $this->gatewayName,
                status: QuotationGatewayAttempt::ERROR,
                message: $e?->getMessage(),
            ));
        });
    }

    private function resolveGateway(): ?CarrierGateway
    {
        foreach (app()->tagged('carrier.gateways') as $gateway) {
            if ($gateway->name() === $this->gatewayName) {
                return $gateway;
            }
        }

        return null;
    }

    private function record(QuotationGatewayAttemptRepository $attempts, string $status, ?string $message): void
    {
        $attempts->save(new QuotationGatewayAttempt(
            id: null,
            quotationId: $this->quotationId,
            gateway: $this->gatewayName,
            status: $status,
            message: $message,
        ));
    }
}
