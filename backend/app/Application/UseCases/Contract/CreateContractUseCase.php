<?php

namespace App\Application\UseCases\Contract;

use App\Domain\Entities\Contract;
use App\Domain\Entities\Quotation;
use App\Domain\Entities\TrackingEvent;
use App\Domain\Exceptions\CarrierNotInResultsException;
use App\Domain\Exceptions\QuotationNotFoundException;
use App\Domain\Exceptions\QuotationNotValidException;
use App\Domain\Repositories\ContractRepository;
use App\Domain\Repositories\QuotationRepository;
use App\Domain\Repositories\TrackingEventRepository;
use App\Domain\Services\TransactionManager;
use Illuminate\Support\Str;

class CreateContractUseCase
{
    public function __construct(
        private QuotationRepository $quotationRepository,
        private ContractRepository $contractRepository,
        private TrackingEventRepository $trackingRepository,
        private TransactionManager $transactionManager,
    ) {}

    public function execute(string $quotationId, string $carrierId, string $companyId): Contract
    {
        return $this->transactionManager->run(function () use ($quotationId, $carrierId, $companyId) {
            $quotation = $this->quotationRepository->findByIdForUpdate($quotationId);

            if (! $quotation || $quotation->companyId !== $companyId) {
                throw new QuotationNotFoundException;
            }

            if ($quotation->status !== Quotation::STATUS_VALID) {
                throw new QuotationNotValidException;
            }

            $selectedResult = null;
            foreach ($quotation->results as $result) {
                if ($result['carrier_id'] === $carrierId) {
                    $selectedResult = $result;
                    break;
                }
            }

            if (! $selectedResult) {
                throw new CarrierNotInResultsException;
            }

            $contract = Contract::fromQuotation(
                id: Str::orderedUuid()->toString(),
                documentNumber: 'CT-e '.date('Ymd').str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
                companyId: $companyId,
                quotationId: $quotation->id,
                nfNumber: $quotation->nfNumber,
                carrierId: $selectedResult['carrier_id'],
                carrierName: $selectedResult['carrier_name'],
                originCity: $quotation->originCity,
                destinationCity: $quotation->destinationCity,
                destinationState: $quotation->destinationState,
                freightValue: $selectedResult['freight_value'],
                fees: $selectedResult['fees'],
                finalValue: $selectedResult['final_value'],
                deadline: $selectedResult['deadline'],
            );

            $this->contractRepository->save($contract);

            $quotation = new Quotation(
                id: $quotation->id, companyId: $quotation->companyId,
                userId: $quotation->userId,
                nfNumber: $quotation->nfNumber, senderCnpj: $quotation->senderCnpj,
                receiverCnpj: $quotation->receiverCnpj,
                originCep: $quotation->originCep, destinationCep: $quotation->destinationCep,
                originCity: $quotation->originCity, destinationCity: $quotation->destinationCity,
                destinationState: $quotation->destinationState,
                weight: $quotation->weight, boxes: $quotation->boxes,
                volume: $quotation->volume, cargoValue: $quotation->cargoValue,
                status: Quotation::STATUS_CONTRACTED, results: $quotation->results,
                validUntil: $quotation->validUntil,
                createdAt: $quotation->createdAt, updatedAt: now()->toIso8601String(),
            );
            $this->quotationRepository->save($quotation);

            $event = TrackingEvent::create(
                id: Str::orderedUuid()->toString(),
                contractId: $contract->id,
                title: 'Coleta Agendada',
                date: now()->format('Y-m-d'),
                time: now()->format('H:i'),
                observation: 'Contratação confirmada',
            );
            $this->trackingRepository->save($event);

            return $contract;
        });
    }
}
