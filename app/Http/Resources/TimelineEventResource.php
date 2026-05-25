<?php

namespace App\Http\Resources;

use App\Services\MiLog\TimelineEventFormatter;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineEventResource extends JsonResource
{
    /**
     * Disable the default outer resource wrapper.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'action' => $this->action,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'log_level' => $this->log_level,
            'metadata' => $this->metadata ?? [],
            'occurred_at' => optional($this->occurred_at)->toISOString(),
            'created_at' => optional($this->created_at)->toISOString(),
            'message' => app(TimelineEventFormatter::class)->format($this->resource),
        ];
    }
}
