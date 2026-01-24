<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\ApproveRejectLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LeaveRequestController extends Controller
{
    /**
     * @OA\Post(
     *     path="/leave-requests",
     *     summary="Submit a new leave request",
     *     description="Create a new leave request. The request will be automatically routed for approval based on user role (General -> HR, HR -> Admin, Admin -> Auto-approved)",
     *     tags={"Leave Requests"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_type","start_date","reason"},
     *             @OA\Property(property="leave_type", type="string", enum={"full_day", "half_day", "multi_day"}, example="full_day", description="Type of leave request"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-01-20", description="Start date of leave (must be today or future)"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2026-01-22", description="End date (required for multi_day, optional for others)"),
     *             @OA\Property(property="half_day_period", type="string", enum={"first_half", "second_half"}, example="first_half", description="Required for half_day leave type"),
     *             @OA\Property(property="reason", type="string", example="Family emergency", minLength=10, maxLength=1000, description="Reason for leave request")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave request submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave request submitted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="leave_type", type="string", example="full_day"),
     *                 @OA\Property(property="start_date", type="string", example="2026-01-20"),
     *                 @OA\Property(property="end_date", type="string", example="2026-01-20"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="days_count", type="number", example=1.0),
     *                 @OA\Property(property="reason", type="string", example="Family emergency")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function store(StoreLeaveRequestRequest $request)
    {
        $user = auth()->user();
        
        $data = $request->validated();
        
        // Set end_date same as start_date for full_day and half_day if not provided
        if (!isset($data['end_date']) || empty($data['end_date'])) {
            $data['end_date'] = $data['start_date'];
        }
        
        // Calculate days count
        $leaveRequest = new LeaveRequest($data);
        $daysCount = $leaveRequest->calculateDays();
        
        // Prepare leave request data
        $leaveData = [
            'user_id' => $user->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'half_day_period' => $data['half_day_period'] ?? null,
            'reason' => $data['reason'],
            'days_count' => $daysCount,
            'status' => 'pending',
        ];
        
        // Auto-approve for admin users
        if ($user->isAdmin()) {
            $leaveData['status'] = 'approved';
            $leaveData['approved_by'] = $user->id;
            $leaveData['approved_at'] = now();
        }
        
        $leaveRequest = LeaveRequest::create($leaveData);
        $leaveRequest->load('user', 'approver');
        
        $message = $user->isAdmin() 
            ? 'Leave request auto-approved' 
            : 'Leave request submitted successfully';
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $leaveRequest
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/leave-requests",
     *     summary="Get leave requests",
     *     description="Retrieve leave requests. General users see only their own requests. HR and Admin can see all requests. Supports filtering and pagination.",
     *     tags={"Leave Requests"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "approved", "rejected"})
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID (HR/Admin only)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave requests retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="data", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="leave_type", type="string", example="full_day"),
     *                         @OA\Property(property="start_date", type="string", example="2026-01-20"),
     *                         @OA\Property(property="end_date", type="string", example="2026-01-20"),
     *                         @OA\Property(property="status", type="string", example="pending"),
     *                         @OA\Property(property="days_count", type="number", example=1.0),
     *                         @OA\Property(property="user", type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe"),
     *                             @OA\Property(property="email", type="string", example="john@example.com")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=50)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = LeaveRequest::with('user', 'approver');
        
        // General users can only see their own leave requests
        if ($user->isGeneral()) {
            $query->where('user_id', $user->id);
        }
        
        // HR and Admin can see all, but can filter by user_id if provided
        if (($user->isHR() || $user->isAdmin()) && $request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Order by created_at descending
        $query->orderBy('created_at', 'desc');
        
        $perPage = $request->input('per_page', 15);
        $leaveRequests = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $leaveRequests
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/leave-requests/{id}",
     *     summary="Get a specific leave request",
     *     description="Retrieve details of a specific leave request. General users can only view their own requests.",
     *     tags={"Leave Requests"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Leave request ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="leave_type", type="string", example="full_day"),
     *                 @OA\Property(property="start_date", type="string", example="2026-01-20"),
     *                 @OA\Property(property="end_date", type="string", example="2026-01-20"),
     *                 @OA\Property(property="status", type="string", example="approved"),
     *                 @OA\Property(property="days_count", type="number", example=1.0),
     *                 @OA\Property(property="reason", type="string", example="Family emergency"),
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="approver", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to view this leave request"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave request not found"
     *     )
     * )
     */
    public function show($id)
    {
        $user = auth()->user();
        $leaveRequest = LeaveRequest::with('user', 'approver')->find($id);
        
        if (!$leaveRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found'
            ], 404);
        }
        
        // General users can only view their own requests
        if ($user->isGeneral() && $leaveRequest->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this leave request'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $leaveRequest
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/leave-requests/{id}/approve",
     *     summary="Approve a leave request",
     *     description="Approve a pending leave request. HR can approve General user requests. Admin can approve HR requests. Admins cannot approve General user requests directly.",
     *     tags={"Leave Requests"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Leave request ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave request approved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Leave already processed or invalid approval chain"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Insufficient permissions"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave request not found"
     *     )
     * )
     */
    public function approve($id, ApproveRejectLeaveRequest $request)
    {
        $user = auth()->user();
        $leaveRequest = LeaveRequest::with('user')->find($id);
        
        if (!$leaveRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found'
            ], 404);
        }
        
        // Check if leave is already processed
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Leave request has already been ' . $leaveRequest->status
            ], 400);
        }
        
        // Validate approval hierarchy
        $canApprove = $this->canApprove($user, $leaveRequest->user);
        
        if (!$canApprove) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to approve this leave request. ' . 
                           $this->getApprovalMessage($user, $leaveRequest->user)
            ], 403);
        }
        
        // Approve the leave request
        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        
        $leaveRequest->load('user', 'approver');
        
        return response()->json([
            'success' => true,
            'message' => 'Leave request approved successfully',
            'data' => $leaveRequest
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/leave-requests/{id}/reject",
     *     summary="Reject a leave request",
     *     description="Reject a pending leave request with a reason. Same approval hierarchy applies as approval.",
     *     tags={"Leave Requests"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Leave request ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rejection_reason"},
     *             @OA\Property(property="rejection_reason", type="string", example="Insufficient staffing during requested period", minLength=10, maxLength=500)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave request rejected"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Leave already processed"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Leave request not found"
     *     )
     * )
     */
    public function reject($id, ApproveRejectLeaveRequest $request)
    {
        $user = auth()->user();
        $leaveRequest = LeaveRequest::with('user')->find($id);
        
        if (!$leaveRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found'
            ], 404);
        }
        
        // Check if leave is already processed
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Leave request has already been ' . $leaveRequest->status
            ], 400);
        }
        
        // Validate approval hierarchy
        $canApprove = $this->canApprove($user, $leaveRequest->user);
        
        if (!$canApprove) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to reject this leave request. ' . 
                           $this->getApprovalMessage($user, $leaveRequest->user)
            ], 403);
        }
        
        // Reject the leave request
        $leaveRequest->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'rejection_reason' => $request->rejection_reason,
            'approved_at' => now(),
        ]);
        
        $leaveRequest->load('user', 'approver');
        
        return response()->json([
            'success' => true,
            'message' => 'Leave request rejected',
            'data' => $leaveRequest
        ], 200);
    }

    /**
     * Check if the approver can approve the leave request based on role hierarchy
     * 
     * Business Rules:
     * - HR can approve General user requests
     * - Admin can approve HR requests
     * - Admin users get auto-approved (no approval needed)
     * - General users cannot approve anything
     */
    private function canApprove(User $approver, User $requester): bool
    {
        // General users cannot approve any requests
        if ($approver->isGeneral()) {
            return false;
        }
        
        // HR can only approve General user requests
        if ($approver->isHR()) {
            return $requester->isGeneral();
        }
        
        // Admin can approve HR requests
        if ($approver->isAdmin()) {
            return $requester->isHR();
        }
        
        return false;
    }

    /**
     * Get approval hierarchy message
     */
    private function getApprovalMessage(User $approver, User $requester): string
    {
        if ($requester->isGeneral()) {
            return 'General user leave requests must be approved by HR.';
        }
        
        if ($requester->isHR()) {
            return 'HR leave requests must be approved by Admin.';
        }
        
        return 'Invalid approval request.';
    }
}
