<?php

namespace App\Services;

use App\Models\RestaurantTable;
use App\Models\Staff;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * FR14, FR15. Regenerates qr_token and renders it as a signed-URL QR code
 * (PNG or, embedded in a printable page, PDF) — nothing here is stored.
 */
class TableQrService
{
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public function regenerate(RestaurantTable $table, Staff $actor): void
    {
        $table->update(['qr_token' => Str::random(64)]);

        $this->auditLogger->log($actor, 'table_qr_regenerate', $table);
    }

    public function signedUrl(RestaurantTable $table): string
    {
        return URL::signedRoute('table.scan', [
            'table' => $table->table_id,
            'token' => $table->qr_token,
        ]);
    }

    public function png(RestaurantTable $table): string
    {
        return (new Builder(writer: new PngWriter()))
            ->build(data: $this->signedUrl($table), size: 500, margin: 10)
            ->getString();
    }

    public function pdf(RestaurantTable $table): string
    {
        $qrSvg = (new Builder(writer: new SvgWriter()))
            ->build(data: $this->signedUrl($table), size: 400, margin: 10)
            ->getString();

        return Pdf::loadView('pdf.table-qr', [
            'table' => $table,
            'qrSvg' => $qrSvg,
        ])->output();
    }
}
