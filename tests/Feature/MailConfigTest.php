<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * NFR15, 07.2: from-address matches the venue, not the generic Laravel default.
 */
class MailConfigTest extends TestCase
{
    public function test_default_from_address_and_name_match_the_venue(): void
    {
        $this->assertSame('bookings@coolaroo.com.au', config('mail.from.address'));
        $this->assertSame('Coolaroo Restaurant & Bistro', config('mail.from.name'));
    }
}
