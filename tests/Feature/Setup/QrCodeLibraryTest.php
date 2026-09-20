<?php

namespace Tests\Feature\Setup;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Tests\TestCase;

/**
 * T214: proves endroid/qr-code renders a real QR code in this environment.
 * Only the SVG writer is tested — the PNG writer needs the GD extension,
 * which isn't enabled here (confirmed via `php -m`); see this task's guide
 * and PROGRESS.md follow-up for T051, which needs a real PNG download.
 */
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
