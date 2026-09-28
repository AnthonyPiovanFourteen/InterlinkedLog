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
use Illuminate\Support\Str;

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
                'events' => array_map(fn (TrackingEvent $e) => [
                    'id' => $e->id, 'title' => $e->title,
                    'date' => $e->date, 'time' => $e->time,
                    'observation' => $e->observation,
                ], $events),
            ];
        }, $contracts);

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, string $contractId): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $contract = $this->contractRepository->findById($contractId);

        if (! $contract || $contract->companyId !== $companyId) {
            return response()->json(['message' => 'Contratação não encontrada'], 404);
        }

        $events = $this->trackingRepository->findByContract($contractId);

        $data = array_map(fn (TrackingEvent $e) => [
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

        if (! $contract || $contract->companyId !== $companyId) {
            return response()->json(['message' => 'Contratação não encontrada'], 404);
        }

        // Automático e manual não se misturam: em contrato rastreado pela API,
        // o humano só pode acrescentar observação, nunca criar evento. A regra
        // vale no backend, não só na interface.
        if ($contract->isAutoTracked()) {
            return response()->json([
                'message' => 'Rastreio automático: use observações em vez de criar eventos',
            ], 422);
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
            id: Str::orderedUuid()->toString(),
            contractId: $contractId,
            title: $request->input('title'),
            date: $request->input('date'),
            time: $request->input('time'),
            observation: $request->input('observation', ''),
            origin: TrackingEvent::ORIGIN_MANUAL,
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

    /**
     * Observação num evento existente. É o único input manual permitido em
     * contrato com rastreio automático.
     */
    public function annotate(Request $request, string $contractId, string $eventId): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $contract = $this->contractRepository->findById($contractId);

        if (! $contract || $contract->companyId !== $companyId) {
            return response()->json(['message' => 'Contratação não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), ['observation' => 'required|string|max:1000']);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $event = collect($this->trackingRepository->findByContract($contractId))
            ->first(fn (TrackingEvent $e) => $e->id === $eventId);

        if (! $event) {
            return response()->json(['message' => 'Evento não encontrado'], 404);
        }

        $this->trackingRepository->save(new TrackingEvent(
            id: $event->id,
            contractId: $event->contractId,
            title: $event->title,
            date: $event->date,
            time: $event->time,
            observation: $request->input('observation'),
            createdAt: $event->createdAt,
            origin: $event->origin,
            externalId: $event->externalId,
        ));

        return response()->json(['message' => 'Observação registrada']);
    }
}
