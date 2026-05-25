<?php

namespace Tests\Unit;

use App\Services\MiLog\Formatters\GenericTimelineEventFormatter;
use App\TimelineEvent;
use Tests\TestCase;

class TimelineEventFormatterTest extends TestCase
{
    /**
     * Test the generic formatter formats an event.
     *
     * @return void
     */
    public function testGenericFormatterFormatsEvents()
    {
        $event = new TimelineEvent([
            'actor_type' => 'user',
            'actor_id' => '42',
            'action' => 'created',
            'target_type' => 'invoice',
            'target_id' => 'inv_1',
        ]);

        $formatter = new GenericTimelineEventFormatter();

        $this->assertSame('user 42 created invoice inv_1', $formatter->format($event));
        $this->assertTrue($formatter->supports($event));
    }
}
