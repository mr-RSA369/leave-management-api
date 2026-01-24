<?php

namespace Database\Seeders;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class LeaveRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get users
        $generalUser1 = User::where('email', 'john@example.com')->first();
        $generalUser2 = User::where('email', 'jane@example.com')->first();
        $hrUser = User::where('email', 'hr@example.com')->first();
        $adminUser = User::where('email', 'admin@example.com')->first();

        if (!$generalUser1 || !$generalUser2 || !$hrUser || !$adminUser) {
            $this->command->error('Users not found. Please run UserSeeder first.');
            return;
        }

        // Create some approved leaves for general user 1
        LeaveRequest::create([
            'user_id' => $generalUser1->id,
            'leave_type' => 'full_day',
            'start_date' => Carbon::now()->subDays(30),
            'end_date' => Carbon::now()->subDays(30),
            'reason' => 'Medical appointment for annual checkup',
            'status' => 'approved',
            'approved_by' => $hrUser->id,
            'approved_at' => Carbon::now()->subDays(29),
            'days_count' => 1.0,
        ]);

        LeaveRequest::create([
            'user_id' => $generalUser1->id,
            'leave_type' => 'multi_day',
            'start_date' => Carbon::now()->subDays(20),
            'end_date' => Carbon::now()->subDays(18),
            'reason' => 'Family vacation to the beach',
            'status' => 'approved',
            'approved_by' => $hrUser->id,
            'approved_at' => Carbon::now()->subDays(19),
            'days_count' => 3.0,
        ]);

        LeaveRequest::create([
            'user_id' => $generalUser1->id,
            'leave_type' => 'half_day',
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->subDays(10),
            'half_day_period' => 'first_half',
            'reason' => 'Personal errands in the morning',
            'status' => 'approved',
            'approved_by' => $hrUser->id,
            'approved_at' => Carbon::now()->subDays(9),
            'days_count' => 0.5,
        ]);

        // Create a pending leave for general user 1
        LeaveRequest::create([
            'user_id' => $generalUser1->id,
            'leave_type' => 'full_day',
            'start_date' => Carbon::now()->addDays(10),
            'end_date' => Carbon::now()->addDays(10),
            'reason' => 'Attending a wedding ceremony',
            'status' => 'pending',
            'days_count' => 1.0,
        ]);

        // Create a rejected leave for general user 1
        LeaveRequest::create([
            'user_id' => $generalUser1->id,
            'leave_type' => 'multi_day',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->subDays(3),
            'reason' => 'Extended weekend trip',
            'status' => 'rejected',
            'approved_by' => $hrUser->id,
            'approved_at' => Carbon::now()->subDays(6),
            'rejection_reason' => 'Insufficient staffing during the requested period',
            'days_count' => 3.0,
        ]);

        // Create leaves for general user 2
        LeaveRequest::create([
            'user_id' => $generalUser2->id,
            'leave_type' => 'multi_day',
            'start_date' => Carbon::now()->subDays(15),
            'end_date' => Carbon::now()->subDays(11),
            'reason' => 'Visiting family overseas',
            'status' => 'approved',
            'approved_by' => $hrUser->id,
            'approved_at' => Carbon::now()->subDays(14),
            'days_count' => 5.0,
        ]);

        LeaveRequest::create([
            'user_id' => $generalUser2->id,
            'leave_type' => 'half_day',
            'start_date' => Carbon::now()->addDays(5),
            'end_date' => Carbon::now()->addDays(5),
            'half_day_period' => 'second_half',
            'reason' => 'Doctor appointment in the afternoon',
            'status' => 'pending',
            'days_count' => 0.5,
        ]);

        // Create a pending leave for HR user
        LeaveRequest::create([
            'user_id' => $hrUser->id,
            'leave_type' => 'multi_day',
            'start_date' => Carbon::now()->addDays(15),
            'end_date' => Carbon::now()->addDays(19),
            'reason' => 'Annual vacation with family',
            'status' => 'pending',
            'days_count' => 5.0,
        ]);

        // Create an approved leave for HR user
        LeaveRequest::create([
            'user_id' => $hrUser->id,
            'leave_type' => 'full_day',
            'start_date' => Carbon::now()->subDays(7),
            'end_date' => Carbon::now()->subDays(7),
            'reason' => 'Personal day off',
            'status' => 'approved',
            'approved_by' => $adminUser->id,
            'approved_at' => Carbon::now()->subDays(6),
            'days_count' => 1.0,
        ]);

        // Create an auto-approved leave for admin user
        LeaveRequest::create([
            'user_id' => $adminUser->id,
            'leave_type' => 'multi_day',
            'start_date' => Carbon::now()->addDays(20),
            'end_date' => Carbon::now()->addDays(22),
            'reason' => 'Conference attendance',
            'status' => 'approved',
            'approved_by' => $adminUser->id,
            'approved_at' => Carbon::now(),
            'days_count' => 3.0,
        ]);

        $this->command->info('Leave requests seeded successfully!');
    }
}
