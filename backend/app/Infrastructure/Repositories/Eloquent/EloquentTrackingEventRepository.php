<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Domain\Entities\TrackingEvent as TrackingEventEntity;
use App\Domain\Repositories\TrackingEventRepository;
use App\Models\TrackingEvent;
use Illuminate\Support\Str;

class EloquentTrackingEventRepository implements TrackingEventRepository
{
    public function findByContract(string $contractId): array
    {
        return TrackingEvent::where('contract_id', $contractId)
            ->get()
            ->map(fn ($m) => $this->toEntity($m))
            ->all();
    }

    public function save(TrackingEventEntity $event): void
    {
        $id = $event->id ?? Str::orderedUuid()->toString();

        // Evento vindo da API é identificado pela chave da transportadora, não
        // pelo nosso id: assim o sincronismo pode rodar quantas vezes quiser.
        $key = $event->externalId !== null
            ? ['contract_id' => $event->contractId, 'external_id' => $event->externalId]
            : ['id' => $id];

        TrackingEvent::updateOrCreate(
            $key,
            [
                'id' => $id,
                'contract_id' => $event->contractId,
                'title' => $event->title,
                'date' => $event->date,
                'time' => $event->time,
                'observation' => $event->observation,
                'origin' => $event->origin,
                'external_id' => $event->externalId,
                'created_at' => $event->createdAt ?: now(),
            ]
        );
    }

    private function toEntity(TrackingEvent $model): TrackingEventEntity
    {
        return new TrackingEventEntity(
            id: $model->id,
            contractId: $model->contract_id,
            title: $model->title,
            date: $model->date,
            time: $model->time,
            observation: $model->observation ?? '',
            createdAt: $model->created_at?->toIso8601String() ?? '',
            origin: $model->origin ?? TrackingEventEntity::ORIGIN_MANUAL,
            externalId: $model->external_id,
        );
    }
}
