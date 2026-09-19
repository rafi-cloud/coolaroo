<?php

namespace Tests\Feature\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Staff;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_a_customer_action_sets_customer_id_not_staff_id(): void
    {
        $customer = Customer::factory()->create();

        $log = app(AuditLogger::class)->log($customer, 'login', $customer);

        $this->assertSame($customer->customer_id, $log->customer_id);
        $this->assertNull($log->staff_id);
    }

    public function test_a_customer_login_writes_an_audit_log_entry(): void
    {
        $customer = Customer::factory()->create(['password_hash' => 'password123']);

        $this->post('/login', ['email' => $customer->email, 'password' => 'password123']);

        $this->assertNotNull(AuditLog::where('action_type', 'login')->where('customer_id', $customer->customer_id)->first());
    }

    public function test_a_staff_login_writes_an_audit_log_entry(): void
    {
        $staff = Staff::factory()->create(['password_hash' => 'password123']);

        $this->post('/staff/login', ['email' => $staff->email, 'password' => 'password123']);

        $this->assertNotNull(AuditLog::where('action_type', 'login')->where('staff_id', $staff->staff_id)->first());
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $log = app(AuditLogger::class)->log(null, 'system_event', Staff::factory()->create());

        $this->expectException(RuntimeException::class);

        $log->update(['action_type' => 'changed']);
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $log = app(AuditLogger::class)->log(null, 'system_event', Staff::factory()->create());

        $this->expectException(RuntimeException::class);

        $log->delete();
    }
}
