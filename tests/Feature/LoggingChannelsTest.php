<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoggingChannelsTest extends TestCase
{
    public function test_integrations_channel_is_configured(): void
    {
        $channel = config('logging.channels.integrations');

        $this->assertNotNull($channel);
        $this->assertSame('daily', $channel['driver']);
    }
}
