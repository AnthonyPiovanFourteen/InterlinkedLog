<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\FreightTable as FreightTableEntity;
use App\Domain\Repositories\FreightTableRepository;
use App\Models\Carrier;
use App\Models\FreightTable;
use App\Models\FreightTableFee;
use App\Models\FreightTableRoute;
use App\Models\FreightTableWeightRange;
use Illuminate\Support\Str;

class EloquentFreightTableRepository implements FreightTableRepository
{
    public function findById(string $id): ?FreightTableEntity
    {
        $model = FreightTable::with(['routes.weightRanges', 'fees'])->find($id);
        if (!$model) return null;
        return $this->toEntity($model);
    }

    public function findByCarrier(string $carrierId): array
    {
        return FreightTable::with(['routes.weightRanges', 'fees'])
            ->where('carrier_id', $carrierId)
            ->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function findActiveByCarrierAndRoute(string $carrierId, string $originCity, string $destCity, string $destState): ?FreightTableEntity
    {
        $table = FreightTable::with(['routes.weightRanges', 'fees'])
            ->where('carrier_id', $carrierId)
            ->where('status', 'Ativa')
            ->whereHas('routes', function ($q) use ($destCity, $destState) {
                $q->where('destination_city', 'like', $destCity)
                  ->where('destination_uf', $destState);
            })
            ->first();

        if (!$table) return null;
        return $this->toEntity($table);
    }

    public function findAll(array $filters = []): array
    {
        $query = FreightTable::with(['routes.weightRanges', 'fees']);

        if (!empty($filters['carrier_id'])) {
            $query->where('carrier_id', $filters['carrier_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()
            ->map(fn($m) => $this->toEntity($m))
            ->all();
    }

    public function save(FreightTableEntity $table): void
    {
        $id = $table->id ?? Str::orderedUuid()->toString();
        FreightTable::updateOrCreate(
            ['id' => $id],
            [
                                'id' => $id,
                'carrier_id' => $table->carrierId,
                'name' => $table->name,
                'valid_from' => $table->validityStart,
                'valid_until' => $table->validityEnd,
                'status' => $table->status,
                'created_at' => $table->createdAt ?: now(),
                'updated_at' => $table->updatedAt ?: now(),
            ]
        );

        $carrier = Carrier::find($table->carrierId);
        $originUf = $carrier?->origin_uf ?? 'SP';

        if (!empty($table->routes)) {
            FreightTableRoute::where('freight_table_id', $id)->delete();

            $routeModels = [];
            foreach ($table->routes as $idx => $route) {
                $routeModel = FreightTableRoute::create([
                    'id' => uuid_create(),
                    'freight_table_id' => $id,
                    'origin_city' => $table->originCity,
                    'origin_uf' => $originUf,
                    'destination_city' => $route['city'],
                    'destination_uf' => $route['state'],
                ]);
                $routeModels[] = $routeModel;
            }

            if (!empty($table->weightRanges)) {
                FreightTableWeightRange::whereHas('route', fn($q) => $q->where('freight_table_id', $id))->delete();

                foreach ($routeModels as $idx => $routeModel) {
                    $deadline = $table->routes[$idx]['deadline'] ?? 1;
                    foreach ($table->weightRanges as $wr) {
                        FreightTableWeightRange::create([
                            'id' => uuid_create(),
                            'freight_table_route_id' => $routeModel->id,
                            'min_weight' => $wr['start'],
                            'max_weight' => $wr['end'],
                            'freight_value' => $wr['value'],
                            'deadline_days' => $deadline,
                        ]);
                    }
                }
            }
        }

        if (!empty($table->fees)) {
            FreightTableFee::where('freight_table_id', $id)->delete();

            foreach ($table->fees as $fee) {
                FreightTableFee::create([
                    'id' => uuid_create(),
                    'freight_table_id' => $id,
                    'fee_type' => $fee['type'],
                    'value' => $fee['value'],
                    'is_percentage' => ($fee['percentage'] ?? 0) > 0,
                ]);
            }
        }
    }

    public function delete(string $id): void
    {
        FreightTable::destroy($id);
    }

    private function toEntity(FreightTable $model): FreightTableEntity
    {
        $routes = $model->routes->map(function ($route) {
            $deadline = 1;
            $firstWr = $route->weightRanges->first();
            if ($firstWr) {
                $deadline = $firstWr->deadline_days;
            }
            return [
                'city' => $route->destination_city,
                'state' => $route->destination_uf,
                'deadline' => $deadline,
            ];
        })->toArray();

        $weightRanges = [];
        if ($model->routes->isNotEmpty()) {
            $firstRoute = $model->routes->first();
            $weightRanges = $firstRoute->weightRanges->map(function ($wr) {
                return [
                    'start' => (float) $wr->min_weight,
                    'end' => (float) $wr->max_weight,
                    'value' => (float) $wr->freight_value,
                ];
            })->toArray();
        }

        $fees = $model->fees->map(function ($fee) {
            return [
                'type' => $fee->fee_type,
                'value' => (float) $fee->value,
                'percentage' => $fee->is_percentage ? (float) $fee->value : 0,
            ];
        })->toArray();

        return new FreightTableEntity(
            id: $model->id,
            name: $model->name,
            carrierId: $model->carrier_id,
            originCity: $model->routes->first()?->origin_city ?? '',
            validityStart: $model->valid_from,
            validityEnd: $model->valid_until,
            status: $model->status,
            routes: $routes,
            weightRanges: $weightRanges,
            fees: $fees,
            createdAt: $model->created_at?->toIso8601String() ?? '',
            updatedAt: $model->updated_at?->toIso8601String() ?? '',
        );
    }
}
