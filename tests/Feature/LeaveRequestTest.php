<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test general user can submit full day leave request
     */
    public function test_general_user_can_submit_full_day_leave_request(): void
    {
        $user = User::factory()->create(['role' => 'general']);
        
        $response = $this->actingAs($user)
            ->postJson('/api/leave-requests', [
                'leave_type' => 'full_day',
                'start_date' => '2026-01-20',
                'reason' => 'Personal emergency that requires my immediate attention',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Leave request submitted successfully',
                'data' => [
                    'leave_type' => 'full_day',
                    'status' => 'pending',
                ]
            ]);

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $user->id,
            'leave_type' => 'full_day',
            'status' => 'pending',
        ]);
    }

    /**
     * Test general user can submit half day leave request
     */
    public function test_general_user_can_submit_half_day_leave_request(): void
    {
        $user = User::factory()->create(['role' => 'general']);
        
        $response = $this->actingAs($user)
            ->postJson('/api/leave-requests', [
                'leave_type' => 'half_day',
                'start_date' => '2026-01-20',
                'half_day_period' => 'first_half',
                'reason' => 'Doctor appointment in the morning hours',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'leave_type' => 'half_day',
                    'half_day_period' => 'first_half',
                    'days_count' => 0.5,
                ]
            ]);
    }

    /**
     * Test general user can submit multi day leave request
     */
    public function test_general_user_can_submit_multi_day_leave_request(): void
    {
        $user = User::factory()->create(['role' => 'general']);
        
        $response = $this->actingAs($user)
            ->postJson('/api/leave-requests', [
                'leave_type' => 'multi_day',
                'start_date' => '2026-01-20',
                'end_date' => '2026-01-22',
                'reason' => 'Family vacation planned for three days',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'leave_type' => 'multi_day',
                    'days_count' => 3.0,
                ]
            ]);
    }

    /**
     * Test admin leave request is auto-approved
     */
    public function test_admin_leave_request_is_auto_approved(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)
            ->postJson('/api/leave-requests', [
                'leave_type' => 'full_day',
                'start_date' => '2026-01-20',
                'reason' => 'Administrative tasks requiring full day',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Leave request auto-approved',
                'data' => [
                    'status' => 'approved',
                ]
            ]);
    }

    /**
     * Test leave request fails without reason
     */
    public function test_leave_request_fails_without_reason(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->postJson('/api/leave-requests', [
                'leave_type' => 'full_day',
                'start_date' => '2026-01-20',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    /**
     * Test leave request fails with short reason
     */
    public function test_leave_request_fails_with_short_reason(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->postJson('/api/leave-requests', [
                'leave_type' => 'full_day',
                'start_date' => '2026-01-20',
                'reason' => 'Short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    /**
     * Test leave request fails with past date
     */
    public function test_leave_request_fails_with_past_date(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->postJson('/api/leave-requests', [
                'leave_type' => 'full_day',
                'start_date' => '2025-01-01',
                'reason' => 'This is a past date leave request',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    /**
     * Test half day leave requires half_day_period
     */
    public function test_half_day_leave_requires_half_day_period(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->postJson('/api/leave-requests', [
                'leave_type' => 'half_day',
                'start_date' => '2026-01-20',
                'reason' => 'Need half day for personal work',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['half_day_period']);
    }

    /**
     * Test multi day leave requires end_date
     */
    public function test_multi_day_leave_requires_end_date(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->postJson('/api/leave-requests', [
                'leave_type' => 'multi_day',
                'start_date' => '2026-01-20',
                'reason' => 'Multiple days needed for family event',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    /**
     * Test general user can view own leave requests
     */
    public function test_general_user_can_view_own_leave_requests(): void
    {
        $user = User::factory()->create(['role' => 'general']);
        LeaveRequest::factory()->count(3)->create(['user_id' => $user->id]);
        LeaveRequest::factory()->count(2)->create(); // Other users' requests

        $response = $this->actingAs($user)
            ->getJson('/api/leave-requests');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(3, 'data.data');
    }

    /**
     * Test hr can view all leave requests
     */
    public function test_hr_can_view_all_leave_requests(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        LeaveRequest::factory()->count(5)->create();

        $response = $this->actingAs($hr)
            ->getJson('/api/leave-requests');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data');
    }

    /**
     * Test admin can view all leave requests
     */
    public function test_admin_can_view_all_leave_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        LeaveRequest::factory()->count(5)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/leave-requests');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data');
    }

    /**
     * Test filter leave requests by status
     */
    public function test_can_filter_leave_requests_by_status(): void
    {
        $user = User::factory()->create(['role' => 'hr']);
        LeaveRequest::factory()->count(2)->create(['status' => 'pending']);
        LeaveRequest::factory()->count(3)->create(['status' => 'approved']);

        $response = $this->actingAs($user)
            ->getJson('/api/leave-requests?status=approved');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    }

    /**
     * Test general user can view own specific leave request
     */
    public function test_general_user_can_view_own_specific_leave_request(): void
    {
        $user = User::factory()->create(['role' => 'general']);
        $leaveRequest = LeaveRequest::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $leaveRequest->id,
                ]
            ]);
    }

    /**
     * Test general user cannot view others leave request
     */
    public function test_general_user_cannot_view_others_leave_request(): void
    {
        $user = User::factory()->create(['role' => 'general']);
        $otherUser = User::factory()->create(['role' => 'general']);
        $leaveRequest = LeaveRequest::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/leave-requests/{$leaveRequest->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized to view this leave request'
            ]);
    }

    /**
     * Test hr can approve general user leave request
     */
    public function test_hr_can_approve_general_user_leave_request(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $generalUser = User::factory()->create(['role' => 'general']);
        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $generalUser->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($hr)
            ->postJson("/api/leave-requests/{$leaveRequest->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Leave request approved successfully',
                'data' => [
                    'status' => 'approved',
                ]
            ]);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'approved_by' => $hr->id,
        ]);
    }

    /**
     * Test admin can approve hr leave request
     */
    public function test_admin_can_approve_hr_leave_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hrUser = User::factory()->create(['role' => 'hr']);
        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $hrUser->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/leave-requests/{$leaveRequest->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'approved',
                ]
            ]);
    }

    /**
     * Test hr cannot approve other hr leave request
     */
    public function test_hr_cannot_approve_hr_leave_request(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $otherHr = User::factory()->create(['role' => 'hr']);
        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $otherHr->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($hr)
            ->postJson("/api/leave-requests/{$leaveRequest->id}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test general user cannot approve any leave request
     */
    public function test_general_user_cannot_approve_leave_request(): void
    {
        $generalUser = User::factory()->create(['role' => 'general']);
        $otherUser = User::factory()->create(['role' => 'general']);
        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $otherUser->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($generalUser)
            ->postJson("/api/leave-requests/{$leaveRequest->id}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test hr can reject general user leave request with reason
     */
    public function test_hr_can_reject_general_user_leave_request(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $generalUser = User::factory()->create(['role' => 'general']);
        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $generalUser->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($hr)
            ->postJson("/api/leave-requests/{$leaveRequest->id}/reject", [
                'rejection_reason' => 'Insufficient staffing during the requested period'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Leave request rejected',
                'data' => [
                    'status' => 'rejected',
                ]
            ]);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient staffing during the requested period',
        ]);
    }

    /**
     * Test rejection fails without reason
     */
    public function test_rejection_fails_without_reason(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $generalUser = User::factory()->create(['role' => 'general']);
        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $generalUser->id,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($hr)
            ->postJson("/api/leave-requests/{$leaveRequest->id}/reject");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    /**
     * Test cannot approve already processed leave request
     */
    public function test_cannot_approve_already_processed_leave_request(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        $generalUser = User::factory()->create(['role' => 'general']);
        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $generalUser->id,
            'status' => 'approved'
        ]);

        $response = $this->actingAs($hr)
            ->postJson("/api/leave-requests/{$leaveRequest->id}/approve");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test unauthenticated user cannot access leave requests
     */
    public function test_unauthenticated_user_cannot_access_leave_requests(): void
    {
        $response = $this->getJson('/api/leave-requests');
        $response->assertStatus(401);

        $response = $this->postJson('/api/leave-requests', [
            'leave_type' => 'full_day',
            'start_date' => '2026-01-20',
            'reason' => 'Testing unauthenticated access',
        ]);
        $response->assertStatus(401);
    }
}
