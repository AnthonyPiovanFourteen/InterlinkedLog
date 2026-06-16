<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\Contract;
use App\Domain\Entities\Quotation;
use App\Domain\Entities\TrackingEvent;
use App\Domain\Repositories\ContractRepository;
use App\Domain\Repositories\QuotationRepository;
use App\Domain\Repositories\TrackingEventRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ContractController extends Controller
{
    public function __construct(
        private ContractRepository $contractRepository,
        private QuotationRepository $quotationRepository,
        private TrackingEventRepository $trackingRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $filters = [];
        if ($request->has('status')) $filters['status'] = $request->input('status');
        $contracts = $this->contractRepository->findByCompany($companyId, $filters);

        $data = array_map(fn(Contract $c) => [
            'id' => $c->id, 'nf_number' => $c->nfNumber,
            'carrier_name' => $c->carrierName,
            'final_value' => $c->finalValue, 'status' => $c->status,
            'document_number' => $c->documentNumber,
            'cte_number' => $c->cteNumber,
            'origin_city' => $c->originCity, 'destination_city' => $c->destinationCity,
            'deadline' => $c->deadline, 'created_at' => $c->createdAt,
        ], $contracts);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quotation_id' => 'required|string',
            'carrier_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $companyId = $request->attributes->get('company_id');
        $quotation = $this->quotationRepository->findById($request->input('quotation_id'));

        if (!$quotation || $quotation->companyId !== $companyId) {
            return response()->json(['message' => 'Cotação não encontrada'], 404);
        }

        if ($quotation->status !== Quotation::STATUS_VALID) {
            return response()->json(['message' => 'Cotação não está mais válida'], 422);
        }

        $selectedResult = collect($quotation->results)->first(
            fn($r) => $r['carrier_id'] === $request->input('carrier_id')
        );

        if (!$selectedResult) {
            return response()->json(['message' => 'Transportadora não encontrada nos resultados'], 422);
        }

        $contract = Contract::fromQuotation(
            id: uuid_create(),
            documentNumber: 'CT-e ' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
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
            id: uuid_create(),
            contractId: $contract->id,
            title: 'Coleta Agendada',
            date: now()->format('Y-m-d'),
            time: now()->format('H:i'),
            observation: 'Contratação confirmada',
        );
        $this->trackingRepository->save($event);

        return response()->json([
            'data' => [
                'id' => $contract->id,
                'nf_number' => $contract->nfNumber,
                'carrier_name' => $contract->carrierName,
                'final_value' => $contract->finalValue,
                'status' => $contract->status,
                'document_number' => $contract->documentNumber,
            ],
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $contract = $this->contractRepository->findById($id);

        if (!$contract || $contract->companyId !== $companyId) {
            return response()->json(['message' => 'Contratação não encontrada'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $contract->id, 'nf_number' => $contract->nfNumber,
                'carrier_name' => $contract->carrierName,
                'origin_city' => $contract->originCity,
                'destination_city' => $contract->destinationCity,
                'destination_state' => $contract->destinationState,
                'freight_value' => $contract->freightValue,
                'fees' => $contract->fees,
                'final_value' => $contract->finalValue,
                'deadline' => $contract->deadline,
                'status' => $contract->status,
                'document_number' => $contract->documentNumber,
                'cte_number' => $contract->cteNumber,
                'created_at' => $contract->createdAt,
            ],
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $contract = $this->contractRepository->findById($id);

        if (!$contract || $contract->companyId !== $companyId) {
            return response()->json(['message' => 'Contratação não encontrada'], 404);
        }

        if ($contract->status === Contract::STATUS_CANCELLED) {
            return response()->json(['message' => 'Contratação já cancelada'], 422);
        }

        if ($contract->status === Contract::STATUS_DELIVERED) {
            return response()->json(['message' => 'Não é possível cancelar uma entrega já realizada'], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updated = new Contract(
            id: $contract->id, companyId: $contract->companyId,
            quotationId: $contract->quotationId, nfNumber: $contract->nfNumber,
            carrierId: $contract->carrierId, carrierName: $contract->carrierName,
            originCity: $contract->originCity, destinationCity: $contract->destinationCity,
            destinationState: $contract->destinationState,
            freightValue: $contract->freightValue, fees: $contract->fees,
            finalValue: $contract->finalValue, deadline: $contract->deadline,
            status: Contract::STATUS_CANCELLED,
            documentNumber: $contract->documentNumber,
            cteNumber: $contract->cteNumber,
            cancelledAt: now()->toIso8601String(),
            cancelReason: $request->input('reason'),
            createdAt: $contract->createdAt, updatedAt: now()->toIso8601String(),
        );

        $this->contractRepository->save($updated);

        return response()->json(['message' => 'Contratação cancelada']);
    }

    public function updateCte(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $contract = $this->contractRepository->findById($id);

        if (!$contract || $contract->companyId !== $companyId) {
            return response()->json(['message' => 'Contratação não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'cte_number' => 'required|string|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updated = new Contract(
            id: $contract->id, companyId: $contract->companyId,
            quotationId: $contract->quotationId, nfNumber: $contract->nfNumber,
            carrierId: $contract->carrierId, carrierName: $contract->carrierName,
            originCity: $contract->originCity, destinationCity: $contract->destinationCity,
            destinationState: $contract->destinationState,
            freightValue: $contract->freightValue, fees: $contract->fees,
            finalValue: $contract->finalValue, deadline: $contract->deadline,
            status: $contract->status,
            documentNumber: $contract->documentNumber,
            cteNumber: $request->input('cte_number'),
            cancelledAt: $contract->cancelledAt,
            cancelReason: $contract->cancelReason,
            createdAt: $contract->createdAt, updatedAt: now()->toIso8601String(),
        );

        $this->contractRepository->save($updated);

        return response()->json([
            'data' => [
                'id' => $updated->id,
                'cte_number' => $updated->cteNumber,
            ],
        ]);
    }

    public function pdf(Request $request, string $id)
    {
        $companyId = $request->attributes->get('company_id');
        $contract = $this->contractRepository->findById($id);

        if (!$contract || $contract->companyId !== $companyId) {
            return response()->json(['message' => 'Contratação não encontrada'], 404);
        }

        $quotation = $this->quotationRepository->findById($contract->quotationId);

        $data = [
            'contract' => $contract,
            'quotation' => $quotation,
            'cte_number' => $contract->cteNumber ?? 'Aguardando Transportadora',
        ];

        $pdf = Pdf::loadView('pdf.solicitacao-coleta', $data);

        return $pdf->download("solicitacao-coleta-{$contract->documentNumber}.pdf");
    }
}
