<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $leaveType = fake()->randomElement(['full_day', 'half_day', 'multi_day']);
        $startDate = \Carbon\Carbon::now()->addDays(fake()->numberBetween(1, 30));

        // Calculate end date and days count based on leave type
        if ($leaveType === 'half_day') {
            $endDate = $startDate->copy();
            $daysCount = 0.5;
            $halfDayPeriod = fake()->randomElement(['first_half', 'second_half']);
        } elseif ($leaveType === 'full_day') {
            $endDate = $startDate->copy();
            $daysCount = 1.0;
            $halfDayPeriod = null;
        } else { // multi_day
            $endDate = $startDate->copy()->addDays(fake()->numberBetween(1, 7));
            $daysCount = $startDate->diffInDays($endDate) + 1;
            $halfDayPeriod = null;
        }

        return [
            'user_id' => User::factory(),
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'half_day_period' => $halfDayPeriod,
            'reason' => fake()->sentence(15),
            'status' => 'pending',
            'approved_by' => null,
            'rejection_reason' => null,
            'approved_at' => null,
            'days_count' => $daysCount,
        ];
    }

    /**
     * Indicate that the leave request is approved.
     */
    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            $approver = User::factory()->create(['role' => 'hr']);
            return [
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ];
        });
    }

    /**
     * Indicate that the leave request is rejected.
     */
    public function rejected(): static
    {
        return $this->state(function (array $attributes) {
            $approver = User::factory()->create(['role' => 'hr']);
            return [
                'status' => 'rejected',
                'approved_by' => $approver->id,
                'rejection_reason' => fake()->sentence(10),
                'approved_at' => now(),
            ];
        });
    }

    /**
     * Indicate that the leave request is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_by' => null,
            'rejection_reason' => null,
            'approved_at' => null,
        ]);
    }

    /**
     * Set leave type to full_day.
     */
    public function fullDay(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = $attributes['start_date'];
            return [
                'leave_type' => 'full_day',
                'end_date' => $startDate,
                'half_day_period' => null,
                'days_count' => 1.0,
            ];
        });
    }

    /**
     * Set leave type to half_day.
     */
    public function halfDay(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = $attributes['start_date'];
            return [
                'leave_type' => 'half_day',
                'end_date' => $startDate,
                'half_day_period' => fake()->randomElement(['first_half', 'second_half']),
                'days_count' => 0.5,
            ];
        });
    }

    /**
     * Set leave type to multi_day.
     */
    public function multiDay(): static
    {
        return $this->state(function (array $attributes) {
            $startDate = \Carbon\Carbon::parse($attributes['start_date']);
            $endDate = $startDate->copy()->addDays(fake()->numberBetween(2, 7));
            $daysCount = $startDate->diffInDays($endDate) + 1;

            return [
                'leave_type' => 'multi_day',
                'end_date' => $endDate,
                'half_day_period' => null,
                'days_count' => $daysCount,
            ];
        });
    }
}
