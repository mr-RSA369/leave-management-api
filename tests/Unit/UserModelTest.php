<?php

namespace Tests\Unit;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test isAdmin method returns true for admin role
     */
    public function test_is_admin_returns_true_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue($admin->isAdmin());
    }

    /**
     * Test isAdmin method returns false for non-admin roles
     */
    public function test_is_admin_returns_false_for_non_admin(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $general = User::factory()->create(['role' => 'general']);

        $this->assertFalse($hr->isAdmin());
        $this->assertFalse($general->isAdmin());
    }

    /**
     * Test isHR method returns true for hr role
     */
    public function test_is_hr_returns_true_for_hr(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $this->assertTrue($hr->isHR());
    }

    /**
     * Test isHR method returns false for non-hr roles
     */
    public function test_is_hr_returns_false_for_non_hr(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $general = User::factory()->create(['role' => 'general']);

        $this->assertFalse($admin->isHR());
        $this->assertFalse($general->isHR());
    }

    /**
     * Test isGeneral method returns true for general role
     */
    public function test_is_general_returns_true_for_general(): void
    {
        $general = User::factory()->create(['role' => 'general']);
        $this->assertTrue($general->isGeneral());
    }

    /**
     * Test isGeneral method returns false for non-general roles
     */
    public function test_is_general_returns_false_for_non_general(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hr = User::factory()->create(['role' => 'hr']);

        $this->assertFalse($admin->isGeneral());
        $this->assertFalse($hr->isGeneral());
    }

    /**
     * Test user has many leave requests relationship
     */
    public function test_user_has_many_leave_requests(): void
    {
        $user = User::factory()->create();
        LeaveRequest::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->leaveRequests);
        $this->assertInstanceOf(LeaveRequest::class, $user->leaveRequests->first());
    }

    /**
     * Test user has many approved leave requests relationship
     */
    public function test_user_has_many_approved_leave_requests(): void
    {
        $approver = User::factory()->create(['role' => 'hr']);
        LeaveRequest::factory()->count(2)->create([
            'approved_by' => $approver->id,
            'status' => 'approved'
        ]);

        $this->assertCount(2, $approver->approvedLeaveRequests);
    }

    /**
     * Test password is hidden in array conversion
     */
    public function test_password_is_hidden(): void
    {
        $user = User::factory()->create(['password' => 'secret']);
        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
    }

    /**
     * Test remember_token is hidden in array conversion
     */
    public function test_remember_token_is_hidden(): void
    {
        $user = User::factory()->create();
        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    /**
     * Test default annual leave entitlement
     */
    public function test_default_annual_leave_entitlement(): void
    {
        $user = User::factory()->create();
        $this->assertEquals(30, $user->annual_leave_entitlement);
    }

    /**
     * Test default role is general
     */
    public function test_default_role_is_general(): void
    {
        $user = User::factory()->create();
        // Factory defaults to general
        $this->assertEquals('general', $user->role);
    }

    /**
     * Test user email is unique
     */
    public function test_user_email_is_unique(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        User::factory()->create(['email' => 'test@example.com']);
    }

    /**
     * Test password is hashed
     */
    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create(['password' => 'plain-password']);
        
        $this->assertNotEquals('plain-password', $user->password);
        $this->assertTrue(\Hash::check('plain-password', $user->password));
    }
}
