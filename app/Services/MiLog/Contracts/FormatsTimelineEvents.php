<?php

namespace App\Services\MiLog\Contracts;

use App\TimelineEvent;

interface FormatsTimelineEvents
{
    /**
     * Determine if this formatter supports the given event.
     *
     * @param  \App\TimelineEvent  $event
     * @return bool
     */
    public function supports(TimelineEvent $event);

    /**
     * Format the given event.
     *
     * @param  \App\TimelineEvent  $event
     * @return string
     */
    public function format(TimelineEvent $event);
}
