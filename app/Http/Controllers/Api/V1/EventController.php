<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimelineEventRequest;
use App\Http\Resources\TimelineEventResource;
use App\Services\MiLog\CreateTimelineEvent;

class EventController extends Controller
{
    /**
     * Store a newly created timeline event.
     *
     * @param  \App\Http\Requests\StoreTimelineEventRequest  $request
     * @param  \App\Services\MiLog\CreateTimelineEvent  $creator
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreTimelineEventRequest $request, CreateTimelineEvent $creator)
    {
        $event = $creator->handle(
            $request->attributes->get('milogTenant'),
            $request->validated()
        );

        $response = (new TimelineEventResource($event))->response();

        if (! $event->wasRecentlyCreated) {
            $response->headers->set('Idempotency-Replayed', 'true');

            return $response->setStatusCode(200);
        }

        return $response->setStatusCode(201);
    }
}
