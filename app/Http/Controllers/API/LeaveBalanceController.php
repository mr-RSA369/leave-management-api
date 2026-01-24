<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/leave-balance",
     *     summary="Get leave balance",
     *     description="Retrieve the leave balance for the authenticated user, including total entitlement, used days, and remaining balance. HR and Admin can check other users' balance by providing user_id parameter.",
     *     tags={"Leave Balance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="User ID to check balance for (HR/Admin only)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave balance retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="user_name", type="string", example="John Doe"),
     *                 @OA\Property(property="annual_leave_entitlement", type="number", example=30),
     *                 @OA\Property(property="used_days", type="number", example=12.5),
     *                 @OA\Property(property="remaining_days", type="number", example=17.5),
     *                 @OA\Property(property="pending_requests_days", type="number", example=2.0, description="Days in pending leave requests"),
     *                 @OA\Property(property="breakdown", type="object",
     *                     @OA\Property(property="approved_leaves", type="integer", example=5, description="Number of approved leave requests"),
     *                     @OA\Property(property="pending_leaves", type="integer", example=1, description="Number of pending leave requests"),
     *                     @OA\Property(property="rejected_leaves", type="integer", example=0, description="Number of rejected leave requests")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - General users can only view their own balance"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $authUser = auth()->user();

        // Determine which user's balance to check
        $userId = $request->input('user_id');

        // General users can only check their own balance
        if ($authUser->isGeneral() && $userId && $userId != $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only view your own leave balance.'
            ], 403);
        }

        // If no user_id provided or general user, use auth user
        if (!$userId || $authUser->isGeneral()) {
            $userId = $authUser->id;
        }

        // Find the user
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Calculate approved leave days
        $approvedDays = LeaveRequest::where('user_id', $userId)
            ->where('status', 'approved')
            ->sum('days_count');

        // Calculate pending leave days
        $pendingDays = LeaveRequest::where('user_id', $userId)
            ->where('status', 'pending')
            ->sum('days_count');

        // Calculate remaining days
        $remainingDays = $user->annual_leave_entitlement - $approvedDays;

        // Get counts for breakdown
        $approvedCount = LeaveRequest::where('user_id', $userId)
            ->where('status', 'approved')
            ->count();

        $pendingCount = LeaveRequest::where('user_id', $userId)
            ->where('status', 'pending')
            ->count();

        $rejectedCount = LeaveRequest::where('user_id', $userId)
            ->where('status', 'rejected')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_role' => $user->role,
                'annual_leave_entitlement' => (float) $user->annual_leave_entitlement,
                'used_days' => (float) $approvedDays,
                'remaining_days' => (float) $remainingDays,
                'pending_requests_days' => (float) $pendingDays,
                'breakdown' => [
                    'approved_leaves' => $approvedCount,
                    'pending_leaves' => $pendingCount,
                    'rejected_leaves' => $rejectedCount,
                ],
            ]
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/leave-balance/all",
     *     summary="Get all users' leave balance summary",
     *     description="Retrieve leave balance summary for all users. Only accessible by HR and Admin roles.",
     *     tags={"Leave Balance"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="Filter by user role",
     *         required=false,
     *         @OA\Schema(type="string", enum={"general", "hr", "admin"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave balance summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="user_name", type="string", example="John Doe"),
     *                     @OA\Property(property="user_role", type="string", example="general"),
     *                     @OA\Property(property="annual_leave_entitlement", type="number", example=30),
     *                     @OA\Property(property="used_days", type="number", example=12.5),
     *                     @OA\Property(property="remaining_days", type="number", example=17.5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Only HR and Admin can access this endpoint"
     *     )
     * )
     */
    public function all(Request $request)
    {
        $authUser = auth()->user();

        // Only HR and Admin can view all users' balance
        if ($authUser->isGeneral()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only HR and Admin can view all users leave balance.'
            ], 403);
        }

        $query = \App\Models\User::query();

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->get();

        $balances = $users->map(function ($user) {
            $approvedDays = LeaveRequest::where('user_id', $user->id)
                ->where('status', 'approved')
                ->sum('days_count');

            $pendingDays = LeaveRequest::where('user_id', $user->id)
                ->where('status', 'pending')
                ->sum('days_count');

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_role' => $user->role,
                'annual_leave_entitlement' => (float) $user->annual_leave_entitlement,
                'used_days' => (float) $approvedDays,
                'remaining_days' => (float) ($user->annual_leave_entitlement - $approvedDays),
                'pending_requests_days' => (float) $pendingDays,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $balances
        ], 200);
    }
}
