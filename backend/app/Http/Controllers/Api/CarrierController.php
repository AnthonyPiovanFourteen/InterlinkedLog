<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\Carrier;
use App\Domain\Repositories\CarrierRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class CarrierController extends Controller
{
    public function __construct(private CarrierRepository $repository) {}

    public function index(): JsonResponse
    {
        $carriers = $this->repository->findAll();
        $data = array_map(fn(Carrier $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'cnpj' => $c->cnpj,
            'origin_city' => $c->originCity,
            'origin_state' => $c->originState,
            'status' => $c->status,
            'contact_name' => $c->contactName,
            'contact_phone' => $c->contactPhone,
            'contact_email' => $c->contactEmail,
        ], $carriers);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
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
            id: uuid_create(),
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
        if (!$carrier) return response()->json(['message' => 'Transportadora não encontrada'], 404);

        return response()->json(['data' => [
            'id' => $carrier->id, 'name' => $carrier->name, 'cnpj' => $carrier->cnpj,
            'origin_city' => $carrier->originCity, 'origin_state' => $carrier->originState,
            'status' => $carrier->status, 'contact_name' => $carrier->contactName,
            'contact_phone' => $carrier->contactPhone, 'contact_email' => $carrier->contactEmail,
        ]]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $carrier = $this->repository->findById($id);
        if (!$carrier) return response()->json(['message' => 'Transportadora não encontrada'], 404);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'origin_city' => 'sometimes|string|max:255',
            'origin_state' => 'sometimes|string|size:2',
            'status' => 'sometimes|string|in:Ativa,Inativa',
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

    public function destroy(string $id): JsonResponse
    {
        if (!$this->repository->findById($id)) {
            return response()->json(['message' => 'Transportadora não encontrada'], 404);
        }
        $this->repository->delete($id);
        return response()->json(['message' => 'Transportadora removida']);
    }
}
