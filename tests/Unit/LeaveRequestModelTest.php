<?php

namespace Tests\Unit;

use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test leave request belongs to user
     */
    public function test_leave_request_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $leaveRequest = LeaveRequest::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $leaveRequest->user);
        $this->assertEquals($user->id, $leaveRequest->user->id);
    }

    /**
     * Test leave request belongs to approver
     */
    public function test_leave_request_belongs_to_approver(): void
    {
        $approver = User::factory()->create(['role' => 'hr']);
        $leaveRequest = LeaveRequest::factory()->create([
            'approved_by' => $approver->id,
            'status' => 'approved'
        ]);

        $this->assertInstanceOf(User::class, $leaveRequest->approver);
        $this->assertEquals($approver->id, $leaveRequest->approver->id);
    }

    /**
     * Test isPending method
     */
    public function test_is_pending_method(): void
    {
        $pendingLeave = LeaveRequest::factory()->create(['status' => 'pending']);
        $approvedLeave = LeaveRequest::factory()->create(['status' => 'approved']);

        $this->assertTrue($pendingLeave->isPending());
        $this->assertFalse($approvedLeave->isPending());
    }

    /**
     * Test isApproved method
     */
    public function test_is_approved_method(): void
    {
        $approvedLeave = LeaveRequest::factory()->create(['status' => 'approved']);
        $pendingLeave = LeaveRequest::factory()->create(['status' => 'pending']);

        $this->assertTrue($approvedLeave->isApproved());
        $this->assertFalse($pendingLeave->isApproved());
    }

    /**
     * Test isRejected method
     */
    public function test_is_rejected_method(): void
    {
        $rejectedLeave = LeaveRequest::factory()->create(['status' => 'rejected']);
        $approvedLeave = LeaveRequest::factory()->create(['status' => 'approved']);

        $this->assertTrue($rejectedLeave->isRejected());
        $this->assertFalse($approvedLeave->isRejected());
    }

    /**
     * Test calculateDays for half day leave
     */
    public function test_calculate_days_for_half_day_leave(): void
    {
        $leaveRequest = LeaveRequest::factory()->make([
            'leave_type' => 'half_day',
            'start_date' => '2026-01-20',
            'end_date' => '2026-01-20',
        ]);

        $this->assertEquals(0.5, $leaveRequest->calculateDays());
    }

    /**
     * Test calculateDays for full day leave
     */
    public function test_calculate_days_for_full_day_leave(): void
    {
        $leaveRequest = LeaveRequest::factory()->make([
            'leave_type' => 'full_day',
            'start_date' => '2026-01-20',
            'end_date' => '2026-01-20',
        ]);

        $this->assertEquals(1.0, $leaveRequest->calculateDays());
    }

    /**
     * Test calculateDays for multi day leave
     */
    public function test_calculate_days_for_multi_day_leave(): void
    {
        $leaveRequest = LeaveRequest::factory()->make([
            'leave_type' => 'multi_day',
            'start_date' => '2026-01-20',
            'end_date' => '2026-01-24',
        ]);

        $this->assertEquals(5.0, $leaveRequest->calculateDays());
    }

    /**
     * Test calculateDays for single day multi_day leave
     */
    public function test_calculate_days_for_single_day_multi_day_leave(): void
    {
        $leaveRequest = LeaveRequest::factory()->make([
            'leave_type' => 'multi_day',
            'start_date' => '2026-01-20',
            'end_date' => '2026-01-20',
        ]);

        $this->assertEquals(1.0, $leaveRequest->calculateDays());
    }

    /**
     * Test status scope
     */
    public function test_status_scope(): void
    {
        LeaveRequest::factory()->count(3)->create(['status' => 'pending']);
        LeaveRequest::factory()->count(2)->create(['status' => 'approved']);
        LeaveRequest::factory()->count(1)->create(['status' => 'rejected']);

        $pendingLeaves = LeaveRequest::status('pending')->get();
        $approvedLeaves = LeaveRequest::status('approved')->get();
        $rejectedLeaves = LeaveRequest::status('rejected')->get();

        $this->assertCount(3, $pendingLeaves);
        $this->assertCount(2, $approvedLeaves);
        $this->assertCount(1, $rejectedLeaves);
    }

    /**
     * Test date casting
     */
    public function test_date_casting(): void
    {
        $leaveRequest = LeaveRequest::factory()->create([
            'start_date' => '2026-01-20',
            'end_date' => '2026-01-22',
        ]);

        $this->assertInstanceOf(Carbon::class, $leaveRequest->start_date);
        $this->assertInstanceOf(Carbon::class, $leaveRequest->end_date);
    }

    /**
     * Test days_count is cast to decimal
     */
    public function test_days_count_casting(): void
    {
        $leaveRequest = LeaveRequest::factory()->create([
            'days_count' => 2.5,
        ]);

        // MySQL returns as string, cast it
        $this->assertEquals(2.5, (float) $leaveRequest->days_count);
    }
}
