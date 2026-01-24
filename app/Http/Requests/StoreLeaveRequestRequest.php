<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\LeaveRequest;
use Carbon\Carbon;

class StoreLeaveRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'leave_type' => 'required|in:full_day,half_day,multi_day',
            'start_date' => 'required|date|after_or_equal:today',
            'reason' => 'required|string|min:10|max:1000',
        ];

        if ($this->leave_type === 'half_day') {
            $rules['half_day_period'] = 'required|in:first_half,second_half';
            $rules['end_date'] = 'sometimes|date|same:start_date';
        } elseif ($this->leave_type === 'multi_day') {
            $rules['end_date'] = 'required|date|after:start_date';
        } elseif ($this->leave_type === 'full_day') {
            $rules['end_date'] = 'sometimes|date|same:start_date';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'leave_type.required' => 'Leave type is required',
            'leave_type.in' => 'Leave type must be one of: full_day, half_day, multi_day',
            'start_date.required' => 'Start date is required',
            'start_date.after_or_equal' => 'Start date cannot be in the past',
            'end_date.required' => 'End date is required for multi-day leave',
            'end_date.after' => 'End date must be after start date',
            'half_day_period.required' => 'Half day period is required for half-day leave',
            'half_day_period.in' => 'Half day period must be either first_half or second_half',
            'reason.required' => 'Reason is required',
            'reason.min' => 'Reason must be at least 10 characters',
            'reason.max' => 'Reason cannot exceed 1000 characters',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check for duplicate pending/approved leave requests
            $this->checkDuplicateLeave($validator);

            // Check for overlapping leave requests
            $this->checkOverlappingLeaves($validator);

            // Check if user has sufficient leave balance
            $this->checkLeaveBalance($validator);
        });
    }

    /**
     * Check for duplicate leave request (same day, any type)
     */
    protected function checkDuplicateLeave($validator)
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = $this->end_date ? Carbon::parse($this->end_date) : $startDate;

        // Check if there's ANY leave request (any type) for the same date
        $existingRequest = LeaveRequest::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($startDate, $endDate) {
                // Check if any existing leave overlaps with requested dates
                $query->where(function ($q) use ($startDate, $endDate) {
                    // Existing leave starts or ends within requested period
                    $q->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      // Or requested period falls within existing leave
                      ->orWhere(function ($subQ) use ($startDate, $endDate) {
                          $subQ->where('start_date', '<=', $startDate)
                               ->where('end_date', '>=', $endDate);
                      });
                });
            })
            ->exists();

        if ($existingRequest) {
            $validator->errors()->add(
                'start_date',
                'A leave request already exists for the selected date(s). Please choose different dates.'
            );
        }
    }

    /**
     * Check for overlapping approved leave requests
     */
    protected function checkOverlappingLeaves($validator)
    {
        $startDate = Carbon::parse($this->start_date);
        $endDate = $this->end_date ? Carbon::parse($this->end_date) : $startDate;

        $overlapping = LeaveRequest::where('user_id', auth()->id())
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                          ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($overlapping) {
            $validator->errors()->add(
                'start_date',
                'You have an approved leave request that overlaps with these dates.'
            );
        }
    }

    /**
     * Check if user has sufficient leave balance
     */
    protected function checkLeaveBalance($validator)
    {
        $user = auth()->user();

        // Calculate days for this request
        $requestedDays = $this->calculateRequestedDays();

        // Calculate used leave days
        $usedDays = LeaveRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->sum('days_count');

        $remainingDays = $user->annual_leave_entitlement - $usedDays;

        if ($requestedDays > $remainingDays) {
            $validator->errors()->add(
                'leave_type',
                "Insufficient leave balance. You have {$remainingDays} days remaining but requested {$requestedDays} days."
            );
        }
    }

    /**
     * Calculate the number of days for this request
     */
    protected function calculateRequestedDays(): float
    {
        if ($this->leave_type === 'half_day') {
            return 0.5;
        }

        if ($this->leave_type === 'full_day') {
            return 1.0;
        }

        // Multi-day
        $start = Carbon::parse($this->start_date);
        $end = Carbon::parse($this->end_date);
        return $start->diffInDays($end) + 1;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422));
    }
}
