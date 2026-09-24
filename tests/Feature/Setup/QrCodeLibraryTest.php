<?php

namespace Tests\Feature\Setup;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Tests\TestCase;

class QrCodeLibraryTest extends TestCase
{
    public function test_the_svg_writer_renders_a_scannable_qr_code(): void
    {
        $result = (new Builder(writer: new SvgWriter))
            ->build(data: 'https://coolaroo.test/t/1/example-token');

        $this->assertSame('image/svg+xml', $result->getMimeType());
        $this->assertStringContainsString('<svg', $result->getString());
    }
}
