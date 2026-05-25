<?php

namespace App\Services\MiLog\Formatters;

use App\Services\MiLog\Contracts\FormatsTimelineEvents;
use App\TimelineEvent;

class GenericTimelineEventFormatter implements FormatsTimelineEvents
{
    /**
     * Determine if this formatter supports the given event.
     *
     * @param  \App\TimelineEvent  $event
     * @return bool
     */
    public function supports(TimelineEvent $event)
    {
        return true;
    }

    /**
     * Format the given event.
     *
     * @param  \App\TimelineEvent  $event
     * @return string
     */
    public function format(TimelineEvent $event)
    {
        return sprintf(
            '%s %s %s %s',
            $event->actor_type,
            $event->actor_id,
            $event->action,
            $event->target_type.' '.$event->target_id
        );
    }
}
