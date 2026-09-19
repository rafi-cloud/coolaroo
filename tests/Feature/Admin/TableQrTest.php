<?php

namespace Tests\Feature\Admin;

use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TableQrTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Staff
    {
        $role = Role::factory()->admin()->create();

        return Staff::factory()->create(['role_id' => $role->role_id]);
    }

    public function test_the_png_download_is_a_real_png(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::factory()->create();

        $response = $this->actingAs($admin, 'staff')->get("/admin/tables/{$table->table_id}/qr.png");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_the_pdf_download_is_a_real_pdf(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::factory()->create();

        $response = $this->actingAs($admin, 'staff')->get("/admin/tables/{$table->table_id}/qr.pdf");

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_regenerating_changes_the_qr_token(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::factory()->create();
        $originalToken = $table->qr_token;

        $this->actingAs($admin, 'staff')->patch("/admin/tables/{$table->table_id}/qr/regenerate");

        $this->assertNotSame($originalToken, $table->fresh()->qr_token);
    }

    public function test_a_non_admin_cannot_download_a_table_qr(): void
    {
        $role = Role::factory()->waitstaff()->create();
        $waiter = Staff::factory()->create(['role_id' => $role->role_id]);
        $table = RestaurantTable::factory()->create();

        $this->actingAs($waiter, 'staff')->get("/admin/tables/{$table->table_id}/qr.png")->assertForbidden();
    }

    public function test_the_signed_url_encoded_in_the_qr_actually_validates(): void
    {
        $table = RestaurantTable::factory()->create();

        $url = URL::signedRoute('table.scan', ['table' => $table->table_id, 'token' => $table->qr_token]);

        $this->get($url)->assertStatus(501);
    }
}
