<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\Contract;
use App\Domain\Entities\TrackingEvent;
use App\Domain\Repositories\ContractRepository;
use App\Domain\Repositories\TrackingEventRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class TrackingController extends Controller
{
    public function __construct(
        private TrackingEventRepository $trackingRepository,
        private ContractRepository $contractRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $contracts = $this->contractRepository->findByCompany($companyId, [
            'status' => $request->input('status'),
        ]);

        $data = array_map(function (Contract $c) {
            $events = $this->trackingRepository->findByContract($c->id);
            return [
                'contract_id' => $c->id,
                'nf_number' => $c->nfNumber,
                'carrier_name' => $c->carrierName,
                'origin_city' => $c->originCity,
                'destination_city' => $c->destinationCity,
                'status' => $c->status,
                'deadline' => $c->deadline,
                'events' => array_map(fn(TrackingEvent $e) => [
                    'id' => $e->id, 'title' => $e->title,
                    'date' => $e->date, 'time' => $e->time,
                    'observation' => $e->observation,
                ], $events),
            ];
        }, $contracts);

        return response()->json(['data' => $data]);
    }

    public function show(string $contractId): JsonResponse
    {
        $events = $this->trackingRepository->findByContract($contractId);

        $data = array_map(fn(TrackingEvent $e) => [
            'id' => $e->id, 'title' => $e->title,
            'date' => $e->date, 'time' => $e->time,
            'observation' => $e->observation,
            'created_at' => $e->createdAt,
        ], $events);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request, string $contractId): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $contract = $this->contractRepository->findById($contractId);

        if (!$contract || $contract->companyId !== $companyId) {
            return response()->json(['message' => 'Contratação não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required|string',
            'observation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $event = TrackingEvent::create(
            id: uuid_create(),
            contractId: $contractId,
            title: $request->input('title'),
            date: $request->input('date'),
            time: $request->input('time'),
            observation: $request->input('observation', ''),
        );

        $this->trackingRepository->save($event);

        $validStatuses = [
            'Coleta Agendada' => Contract::STATUS_SCHEDULED,
            'Coletado' => Contract::STATUS_COLLECTED,
            'Em Trânsito' => Contract::STATUS_IN_TRANSIT,
            'Unidade de Distribuição' => Contract::STATUS_DISTRIBUTION,
            'Saiu para Entrega' => Contract::STATUS_OUT_FOR_DELIVERY,
            'Entregue' => Contract::STATUS_DELIVERED,
        ];

        if (isset($validStatuses[$request->input('title')])) {
            $updated = new Contract(
                id: $contract->id, companyId: $contract->companyId,
                quotationId: $contract->quotationId, nfNumber: $contract->nfNumber,
                carrierId: $contract->carrierId, carrierName: $contract->carrierName,
                originCity: $contract->originCity, destinationCity: $contract->destinationCity,
                destinationState: $contract->destinationState,
                freightValue: $contract->freightValue, fees: $contract->fees,
                finalValue: $contract->finalValue, deadline: $contract->deadline,
                status: $validStatuses[$request->input('title')],
                documentNumber: $contract->documentNumber,
                cancelledAt: $contract->cancelledAt, cancelReason: $contract->cancelReason,
                createdAt: $contract->createdAt, updatedAt: now()->toIso8601String(),
            );
            $this->contractRepository->save($updated);
        }

        return response()->json([
            'data' => ['id' => $event->id, 'title' => $event->title],
        ], 201);
    }
}
