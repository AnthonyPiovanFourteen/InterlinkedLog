<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\Carrier;
use App\Domain\Entities\CarrierStatus;
use App\Domain\Entities\Role;
use App\Domain\Repositories\CarrierCredentialRepository;
use App\Domain\Repositories\CarrierRepository;
use App\Domain\Services\CarrierGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CarrierController extends Controller
{
    /** @param  iterable<CarrierGateway>  $gateways */
    public function __construct(
        private CarrierRepository $repository,
        private CarrierCredentialRepository $credentials,
        private iterable $gateways,
    ) {}

    private function isAdmin(Request $request): bool
    {
        return $request->attributes->get('user_role') === Role::ADMIN;
    }

    private function adminDenied(): JsonResponse
    {
        return response()->json(['message' => 'Acesso restrito a administradores'], 403);
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $configured = $this->credentials->activeForCompany($companyId);

        $carriers = $this->repository->findAll();
        $data = array_map(fn (Carrier $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'cnpj' => $c->cnpj,
            'origin_city' => $c->originCity,
            'origin_state' => $c->originState,
            'status' => $c->status,
            'contact_name' => $c->contactName,
            'contact_phone' => $c->contactPhone,
            'contact_email' => $c->contactEmail,
            // Integração é opcional e por transportadora: o tenant pode
            // integrar a Rodonaves e deixar as demais na tabela.
            'gateway' => $this->gatewayFor($c),
            'integrated' => isset($configured[$c->id]),
        ], $carriers);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return $this->adminDenied();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'cnpj' => 'required|string|max:18',
            'origin_city' => 'required|string|max:255',
            'origin_state' => 'required|string|size:2',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $carrier = Carrier::create(
            id: Str::orderedUuid()->toString(),
            name: $request->input('name'),
            cnpj: $request->input('cnpj'),
            originCity: $request->input('origin_city'),
            originState: $request->input('origin_state'),
            contactName: $request->input('contact_name', ''),
            contactPhone: $request->input('contact_phone', ''),
            contactEmail: $request->input('contact_email', ''),
        );

        $this->repository->save($carrier);

        return response()->json(['data' => ['id' => $carrier->id, 'name' => $carrier->name]], 201);
    }

    public function show(string $id): JsonResponse
    {
        $carrier = $this->repository->findById($id);
        if (! $carrier) {
            return response()->json(['message' => 'Transportadora não encontrada'], 404);
        }

        return response()->json(['data' => [
            'id' => $carrier->id, 'name' => $carrier->name, 'cnpj' => $carrier->cnpj,
            'origin_city' => $carrier->originCity, 'origin_state' => $carrier->originState,
            'status' => $carrier->status, 'contact_name' => $carrier->contactName,
            'contact_phone' => $carrier->contactPhone, 'contact_email' => $carrier->contactEmail,
        ]]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return $this->adminDenied();
        }

        $carrier = $this->repository->findById($id);
        if (! $carrier) {
            return response()->json(['message' => 'Transportadora não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'origin_city' => 'sometimes|string|max:255',
            'origin_state' => 'sometimes|string|size:2',
            'status' => 'sometimes|string|in:'.implode(',', CarrierStatus::all()),
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updated = new Carrier(
            id: $carrier->id, name: $request->input('name', $carrier->name),
            cnpj: $carrier->cnpj,
            originCity: $request->input('origin_city', $carrier->originCity),
            originState: $request->input('origin_state', $carrier->originState),
            status: $request->input('status', $carrier->status),
            contactName: $request->input('contact_name', $carrier->contactName),
            contactPhone: $request->input('contact_phone', $carrier->contactPhone),
            contactEmail: $request->input('contact_email', $carrier->contactEmail),
            createdAt: $carrier->createdAt, updatedAt: now()->toIso8601String(),
        );

        $this->repository->save($updated);

        return response()->json(['data' => ['id' => $updated->id, 'name' => $updated->name, 'status' => $updated->status]]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return $this->adminDenied();
        }

        if (! $this->repository->findById($id)) {
            return response()->json(['message' => 'Transportadora não encontrada'], 404);
        }
        $this->repository->delete($id);

        return response()->json(['message' => 'Transportadora removida']);
    }

    /** Gateway que atende esta transportadora, ou null se não há integração. */
    private function gatewayFor(Carrier $carrier): ?string
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($carrier)) {
                return $gateway->name();
            }
        }

        return null;
    }
}
