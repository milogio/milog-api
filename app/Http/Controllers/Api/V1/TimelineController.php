<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimelineIndexRequest;
use App\Http\Resources\TimelineEventResource;
use App\TimelineEvent;

class TimelineController extends Controller
{
    /**
     * Display the timeline for the current tenant.
     *
     * @param  \App\Http\Requests\TimelineIndexRequest  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(TimelineIndexRequest $request)
    {
        $tenant = $request->attributes->get('milogTenant');
        $filters = $request->validated();

        $events = TimelineEvent::query()
            ->where('tenant_id', $tenant->id)
            ->when(isset($filters['target_id']), function ($query) use ($filters) {
                $query->where('target_id', $filters['target_id']);
            })
            ->when(isset($filters['actor_id']), function ($query) use ($filters) {
                $query->where('actor_id', $filters['actor_id']);
            })
            ->when(isset($filters['type']), function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('actor_type', $filters['type'])
                        ->orWhere('target_type', $filters['type']);
                });
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->paginate((int) config('milog.timeline.per_page', 50))
            ->appends($request->query());

        return TimelineEventResource::collection($events);
    }
}
