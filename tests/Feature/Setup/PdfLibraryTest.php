<?php

namespace Tests\Feature\Setup;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * T214: proves dompdf renders a real PDF in this environment (no GD needed —
 * default pdf_backend is dompdf's own CPDF renderer, confirmed in the guide).
 */
class PdfLibraryTest extends TestCase
{
    public function test_dompdf_renders_html_to_a_real_pdf(): void
    {
        $output = Pdf::loadHTML('<h1>Coolaroo</h1><p>Test receipt line.</p>')->output();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function test_the_default_paper_size_is_a4(): void
    {
        $this->assertSame('a4', config('dompdf.options.default_paper_size'));
    }
}
