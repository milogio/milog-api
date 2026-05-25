<?php

namespace App\Services\MiLog;

use App\TimelineEvent;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

class TimelineEventFormatter
{
    /**
     * The application container.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * Formatter class names.
     *
     * @var array
     */
    protected $formatters;

    /**
     * Create a new formatter registry.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @param  array  $formatters
     * @return void
     */
    public function __construct(Container $container, array $formatters)
    {
        $this->container = $container;
        $this->formatters = $formatters;
    }

    /**
     * Format a timeline event into a display string.
     *
     * @param  \App\TimelineEvent  $event
     * @return string
     */
    public function format(TimelineEvent $event)
    {
        foreach ($this->formatters as $formatterClass) {
            $formatter = $this->container->make($formatterClass);

            if ($formatter->supports($event)) {
                return $formatter->format($event);
            }
        }

        throw new RuntimeException('No timeline event formatter matched the event.');
    }
}
