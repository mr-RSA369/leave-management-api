<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test general user can view own leave balance
     */
    public function test_general_user_can_view_own_leave_balance(): void
    {
        $user = User::factory()->create([
            'role' => 'general',
            'annual_leave_entitlement' => 30
        ]);

        // Create some approved leaves
        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'days_count' => 5.0
        ]);
        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'days_count' => 2.5
        ]);

        // Create a pending leave
        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'days_count' => 1.0
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/leave-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'annual_leave_entitlement' => 30.0,
                    'used_days' => 7.5,
                    'remaining_days' => 22.5,
                    'pending_requests_days' => 1.0,
                    'breakdown' => [
                        'approved_leaves' => 2,
                        'pending_leaves' => 1,
                    ]
                ]
            ]);
    }

    /**
     * Test hr can view other user leave balance
     */
    public function test_hr_can_view_other_user_leave_balance(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $generalUser = User::factory()->create([
            'role' => 'general',
            'annual_leave_entitlement' => 30
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $generalUser->id,
            'status' => 'approved',
            'days_count' => 10.0
        ]);

        $response = $this->actingAs($hr)
            ->getJson("/api/leave-balance?user_id={$generalUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $generalUser->id,
                    'used_days' => 10.0,
                    'remaining_days' => 20.0,
                ]
            ]);
    }

    /**
     * Test admin can view other user leave balance
     */
    public function test_admin_can_view_other_user_leave_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $generalUser = User::factory()->create([
            'role' => 'general',
            'annual_leave_entitlement' => 30
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/api/leave-balance?user_id={$generalUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test general user cannot view other user leave balance
     */
    public function test_general_user_cannot_view_other_user_leave_balance(): void
    {
        $user = User::factory()->create(['role' => 'general']);
        $otherUser = User::factory()->create(['role' => 'general']);

        $response = $this->actingAs($user)
            ->getJson("/api/leave-balance?user_id={$otherUser->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized. You can only view your own leave balance.'
            ]);
    }

    /**
     * Test leave balance calculation with only pending leaves
     */
    public function test_leave_balance_with_only_pending_leaves(): void
    {
        $user = User::factory()->create([
            'role' => 'general',
            'annual_leave_entitlement' => 30
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'days_count' => 5.0
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/leave-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'used_days' => 0.0,
                    'remaining_days' => 30.0,
                    'pending_requests_days' => 5.0,
                ]
            ]);
    }

    /**
     * Test leave balance calculation with rejected leaves
     */
    public function test_leave_balance_excludes_rejected_leaves(): void
    {
        $user = User::factory()->create([
            'role' => 'general',
            'annual_leave_entitlement' => 30
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'rejected',
            'days_count' => 5.0
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'days_count' => 3.0
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/leave-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'used_days' => 3.0,
                    'remaining_days' => 27.0,
                    'breakdown' => [
                        'rejected_leaves' => 1,
                    ]
                ]
            ]);
    }

    /**
     * Test hr can view all users leave balance
     */
    public function test_hr_can_view_all_users_leave_balance(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        User::factory()->count(5)->create(['role' => 'general']);

        $response = $this->actingAs($hr)
            ->getJson('/api/leave-balance/all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'user_id',
                        'user_name',
                        'user_email',
                        'user_role',
                        'annual_leave_entitlement',
                        'used_days',
                        'remaining_days',
                    ]
                ]
            ]);
    }

    /**
     * Test admin can view all users leave balance
     */
    public function test_admin_can_view_all_users_leave_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'general']);

        $response = $this->actingAs($admin)
            ->getJson('/api/leave-balance/all');

        $response->assertStatus(200);
    }

    /**
     * Test general user cannot view all users leave balance
     */
    public function test_general_user_cannot_view_all_users_leave_balance(): void
    {
        $user = User::factory()->create(['role' => 'general']);

        $response = $this->actingAs($user)
            ->getJson('/api/leave-balance/all');

        $response->assertStatus(403);
    }

    /**
     * Test leave balance with half day leaves
     */
    public function test_leave_balance_with_half_day_leaves(): void
    {
        $user = User::factory()->create([
            'role' => 'general',
            'annual_leave_entitlement' => 30
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'leave_type' => 'half_day',
            'days_count' => 0.5
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'leave_type' => 'half_day',
            'days_count' => 0.5
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/leave-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'used_days' => 1.0,
                    'remaining_days' => 29.0,
                ]
            ]);
    }

    /**
     * Test leave balance returns 404 for non-existent user
     */
    public function test_leave_balance_returns_404_for_non_existent_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->getJson('/api/leave-balance?user_id=99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User not found'
            ]);
    }

    /**
     * Test unauthenticated user cannot access leave balance
     */
    public function test_unauthenticated_user_cannot_access_leave_balance(): void
    {
        $response = $this->getJson('/api/leave-balance');
        $response->assertStatus(401);

        $response = $this->getJson('/api/leave-balance/all');
        $response->assertStatus(401);
    }

    /**
     * Test leave balance with mixed leave types
     */
    public function test_leave_balance_with_mixed_leave_types(): void
    {
        $user = User::factory()->create([
            'role' => 'general',
            'annual_leave_entitlement' => 30
        ]);

        // Full day
        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'leave_type' => 'full_day',
            'days_count' => 1.0
        ]);

        // Half day
        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'leave_type' => 'half_day',
            'days_count' => 0.5
        ]);

        // Multi day
        LeaveRequest::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'leave_type' => 'multi_day',
            'days_count' => 3.0
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/leave-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'used_days' => 4.5,
                    'remaining_days' => 25.5,
                    'breakdown' => [
                        'approved_leaves' => 3,
                    ]
                ]
            ]);
    }
}
