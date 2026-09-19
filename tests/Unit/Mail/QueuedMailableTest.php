<?php

namespace Tests\Unit\Mail;

use App\Mail\QueuedMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\TestCase;

class QueuedMailableTest extends TestCase
{
    public function test_it_queues_on_the_mail_queue_with_three_tries_and_a_backoff(): void
    {
        $mailable = new class extends QueuedMailable {};

        $this->assertInstanceOf(ShouldQueue::class, $mailable);
        $this->assertSame('mail', $mailable->queue);
        $this->assertSame(3, $mailable->tries);
        $this->assertSame([60, 300, 900], $mailable->backoff);
    }
}
