<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::firstOrCreate(
            ['role_name' => 'admin'],
            ['display_name' => 'Administrator', 'landing_screen' => '/admin', 'is_active' => true]
        );

        return Staff::factory()->create([
            'role_id' => $role->role_id,
            'is_active' => true,
        ]);
    }

    private function waitstaff(): Staff
    {
        $role = Role::firstOrCreate(
            ['role_name' => 'waitstaff'],
            ['display_name' => 'Waitstaff', 'landing_screen' => '/staff/floor', 'is_active' => true]
        );

        return Staff::factory()->create([
            'role_id' => $role->role_id,
            'is_active' => true,
        ]);
    }

    public function test_guest_is_redirected_to_staff_login(): void
    {
        $this->get(route('admin.reports.export', ['type' => 'sales']))
            ->assertRedirect(route('staff.login'));
    }

    public function test_waitstaff_cannot_export_reports(): void
    {
        $waitstaff = $this->waitstaff();

        $this->actingAs($waitstaff, 'staff')
            ->get(route('admin.reports.export', ['type' => 'sales']))
            ->assertForbidden();
    }

    public function test_unknown_report_type_returns_404(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.export', ['type' => 'unknown_metrics']))
            ->assertNotFound();
    }

    public function test_admin_can_export_sales_report_as_csv_fr88(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.export', [
                'type' => 'sales',
                'format' => 'csv',
                'from' => '2026-09-01',
                'to' => '2026-09-20',
            ]))
            ->assertOk();

        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="report-sales-2026-09-01-to-2026-09-20.csv"', $response->headers->get('Content-Disposition') ?? '');

        $csv = $response->getContent();
        $this->assertStringContainsString('"Report Type","Sales Report"', $csv);
        $this->assertStringContainsString('"Gross Sales"', $csv);
        $this->assertStringContainsString('"Net Takings"', $csv);
        $this->assertStringContainsString('"GST Liability (1/11th)"', $csv);
        $this->assertStringContainsString('"Daily Sales Breakdown"', $csv);
    }

    public function test_admin_can_export_csv_for_all_report_types_fr88(): void
    {
        $admin = $this->admin();
        $types = ['items', 'operations', 'reservations', 'feedback', 'staff'];

        foreach ($types as $type) {
            $response = $this->actingAs($admin, 'staff')
                ->get(route('admin.reports.export', [
                    'type' => $type,
                    'format' => 'csv',
                    'from' => '2026-09-01',
                    'to' => '2026-09-20',
                ]))
                ->assertOk();

            $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
            $this->assertStringContainsString("report-{$type}-2026-09-01-to-2026-09-20.csv", $response->headers->get('Content-Disposition') ?? '');
            $this->assertNotEmpty($response->getContent());
        }
    }

    public function test_admin_can_export_sales_report_as_pdf_fr88(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.export', [
                'type' => 'sales',
                'format' => 'pdf',
                'from' => '2026-09-01',
                'to' => '2026-09-20',
            ]))
            ->assertOk();

        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="report-sales-2026-09-01-to-2026-09-20.pdf"', $response->headers->get('Content-Disposition') ?? '');

        $pdfBytes = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
    }

    public function test_admin_can_export_pdf_for_all_report_types_fr88(): void
    {
        $admin = $this->admin();
        $types = ['items', 'operations', 'reservations', 'feedback', 'staff'];

        foreach ($types as $type) {
            $response = $this->actingAs($admin, 'staff')
                ->get(route('admin.reports.export', [
                    'type' => $type,
                    'format' => 'pdf',
                    'from' => '2026-09-01',
                    'to' => '2026-09-20',
                ]))
                ->assertOk();

            $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString("report-{$type}-2026-09-01-to-2026-09-20.pdf", $response->headers->get('Content-Disposition') ?? '');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }
    }

    public function test_report_page_displays_export_links(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'staff')
            ->get(route('admin.reports.show', ['type' => 'sales']))
            ->assertOk();

        $response->assertSee('data-testid="admin-report-export-csv"', false)
            ->assertSee('data-testid="admin-report-export-pdf"', false);
    }
}
