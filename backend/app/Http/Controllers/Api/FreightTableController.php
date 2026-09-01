<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\FreightTable;
use App\Domain\Repositories\FreightTableRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FreightTableController extends Controller
{
    public function __construct(private FreightTableRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $filters = [];
        if ($request->has('carrier_id')) {
            $filters['carrier_id'] = $request->input('carrier_id');
        }
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        $tables = $this->repository->findAll($companyId, $filters);

        $data = array_map(fn (FreightTable $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'carrier_id' => $t->carrierId,
            'origin_city' => $t->originCity,
            'validity_start' => $t->validityStart,
            'validity_end' => $t->validityEnd,
            'status' => $t->status,
            'routes_count' => count($t->routes),
            'weight_ranges_count' => count($t->routes[0]['weightRanges'] ?? []),
            'fees_count' => count($t->fees),
        ], $tables);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'carrier_id' => 'required|string',
            'origin_city' => 'required|string|max:255',
            'validity_start' => 'required|date',
            'validity_end' => 'required|date|after:validity_start',
            'routes' => 'nullable|array',
            'routes.*.city' => 'required|string',
            'routes.*.state' => 'required|string|size:2',
            'routes.*.deadline' => 'required|integer|min:1',
            'weight_ranges' => 'nullable|array',
            'weight_ranges.*.start' => 'required|numeric|min:0',
            'weight_ranges.*.end' => 'required|numeric|gt:weight_ranges.*.start',
            'weight_ranges.*.value' => 'required|numeric|min:0',
            'fees' => 'nullable|array',
            'fees.*.type' => 'required|string',
            'fees.*.value' => 'nullable|numeric|min:0',
            'fees.*.percentage' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $companyId = $request->attributes->get('company_id');

        $table = FreightTable::create(
            id: Str::orderedUuid()->toString(),
            companyId: $companyId,
            name: $request->input('name'),
            carrierId: $request->input('carrier_id'),
            originCity: $request->input('origin_city'),
            validityStart: $request->input('validity_start'),
            validityEnd: $request->input('validity_end'),
        );

        $table = new FreightTable(
            id: $table->id, companyId: $table->companyId, name: $table->name, carrierId: $table->carrierId,
            originCity: $table->originCity,
            validityStart: $table->validityStart, validityEnd: $table->validityEnd,
            status: $table->status,
            routes: array_map(
                fn ($r) => array_merge($r, ['weightRanges' => $request->input('weight_ranges', [])]),
                $request->input('routes', []),
            ),
            fees: $request->input('fees', []),
        );

        $this->repository->save($table);

        return response()->json(['data' => ['id' => $table->id, 'name' => $table->name]], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $table = $this->repository->findById($companyId, $id);
        if (! $table) {
            return response()->json(['message' => 'Tabela não encontrada'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $table->id, 'name' => $table->name,
                'carrier_id' => $table->carrierId, 'origin_city' => $table->originCity,
                'validity_start' => $table->validityStart, 'validity_end' => $table->validityEnd,
                'status' => $table->status,
                'routes' => $table->routes,
                'weight_ranges' => $table->routes[0]['weightRanges'] ?? [],
                'fees' => $table->fees,
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $table = $this->repository->findById($companyId, $id);
        if (! $table) {
            return response()->json(['message' => 'Tabela não encontrada'], 404);
        }

        $weightRanges = $request->input('weight_ranges');
        $routes = array_map(function ($r) use ($weightRanges, $table) {
            if ($weightRanges !== null) {
                return array_merge($r, ['weightRanges' => $weightRanges]);
            }
            if (isset($r['weightRanges'])) {
                return $r;
            }

            return array_merge($r, ['weightRanges' => $table->routes[0]['weightRanges'] ?? []]);
        }, $request->input('routes', $table->routes));

        $updated = new FreightTable(
            id: $table->id,
            companyId: $table->companyId,
            name: $request->input('name', $table->name),
            carrierId: $table->carrierId,
            originCity: $request->input('origin_city', $table->originCity),
            validityStart: $request->input('validity_start', $table->validityStart),
            validityEnd: $request->input('validity_end', $table->validityEnd),
            status: $request->input('status', $table->status),
            routes: $routes,
            fees: $request->input('fees', $table->fees),
            createdAt: $table->createdAt,
            updatedAt: now()->toIso8601String(),
        );

        $this->repository->save($updated);

        return response()->json(['data' => ['id' => $updated->id, 'name' => $updated->name, 'status' => $updated->status]]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        if (! $this->repository->findById($companyId, $id)) {
            return response()->json(['message' => 'Tabela não encontrada'], 404);
        }
        $this->repository->delete($companyId, $id);

        return response()->json(['message' => 'Tabela removida']);
    }
}
