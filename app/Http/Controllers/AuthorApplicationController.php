<?php

namespace App\Http\Controllers;

use App\Models\AuthorApplication;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AuthorApplicationController extends Controller
{
    /**
     * Submit an author application
     */
    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user is already an author or higher
        if ($user->canCreateNovels()) {
            return response()->json([
                'message' => 'You already have author privileges',
                'current_role' => $user->role
            ], 400);
        }

        // Check if user already has a pending application
        if ($user->hasPendingAuthorApplication()) {
            return response()->json([
                'message' => 'You already have a pending author application',
                'application' => $user->authorApplication
            ], 409);
        }

        // Check if user has a rejected application (they can reapply)
        $existingApplication = $user->authorApplication;
        if ($existingApplication && $existingApplication->isRejected()) {
            // Allow reapplication by updating existing record
            $validator = Validator::make($request->all(), AuthorApplication::validationRules());

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $existingApplication->update([
                'pen_name' => $request->pen_name,
                'bio' => $request->bio,
                'writing_experience' => $request->writing_experience,
                'sample_work' => $request->sample_work,
                'portfolio_url' => $request->portfolio_url,
                'status' => AuthorApplication::STATUS_PENDING,
                'admin_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            return response()->json([
                'message' => 'Author application resubmitted successfully',
                'application' => $existingApplication
            ], 200);
        }

        // Validate the application data
        $validator = Validator::make($request->all(), AuthorApplication::validationRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create new application
        $application = AuthorApplication::create([
            'user_id' => $user->id,
            'pen_name' => $request->pen_name,
            'bio' => $request->bio,
            'writing_experience' => $request->writing_experience,
            'sample_work' => $request->sample_work,
            'portfolio_url' => $request->portfolio_url,
        ]);

        return response()->json([
            'message' => 'Author application submitted successfully',
            'application' => $application
        ], 201);
    }

    /**
     * Get user's application status
     */
    public function getStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $user->authorApplication;

        if (!$application) {
            return response()->json([
                'message' => 'No author application found',
                'can_apply' => !$user->canCreateNovels(),
                'current_role' => $user->role
            ]);
        }

        return response()->json([
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'pen_name' => $application->pen_name,
                'bio' => $application->bio,
                'writing_experience' => $application->writing_experience,
                'sample_work' => $application->sample_work,
                'portfolio_url' => $application->portfolio_url,
                'admin_notes' => $application->admin_notes,
                'created_at' => $application->created_at,
                'reviewed_at' => $application->reviewed_at,
                'reviewer' => $application->reviewer ? [
                    'id' => $application->reviewer->id,
                    'name' => $application->reviewer->name
                ] : null
            ]
        ]);
    }

    /**
     * Admin: Get all author applications
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $status = $request->query('status', 'all');
        $query = AuthorApplication::with(['user:id,name,email,created_at', 'reviewer:id,name'])
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $applications = $query->paginate(20);

        return response()->json([
            'applications' => $applications,
            'stats' => [
                'total' => AuthorApplication::count(),
                'pending' => AuthorApplication::where('status', AuthorApplication::STATUS_PENDING)->count(),
                'approved' => AuthorApplication::where('status', AuthorApplication::STATUS_APPROVED)->count(),
                'rejected' => AuthorApplication::where('status', AuthorApplication::STATUS_REJECTED)->count(),
            ]
        ]);
    }

    /**
     * Admin: Get single application details
     */
    public function adminShow(Request $request, AuthorApplication $application): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $application->load(['user', 'reviewer']);

        return response()->json([
            'application' => $application
        ]);
    }

    /**
     * Admin: Approve author application
     */
    public function approve(Request $request, AuthorApplication $application): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        if (!$application->isPending()) {
            return response()->json([
                'message' => 'Application has already been reviewed',
                'current_status' => $application->status
            ], 400);
        }

        $request->validate([
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        // Update application status
        $application->update([
            'status' => AuthorApplication::STATUS_APPROVED,
            'admin_notes' => $request->admin_notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Promote user to author
        $application->user->update([
            'role' => User::ROLE_AUTHOR
        ]);

        // Send approval notification
        Notification::createAuthorStatusNotification(
            $application->user->id,
            'approved',
            '🎉 Congratulations! Your author application has been approved. You now have author privileges and can start publishing novels!'
        );

        return response()->json([
            'message' => 'Author application approved successfully',
            'application' => $application->load(['user', 'reviewer'])
        ]);
    }

    /**
     * Admin: Reject author application
     */
    public function reject(Request $request, AuthorApplication $application): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        if (!$application->isPending()) {
            return response()->json([
                'message' => 'Application has already been reviewed',
                'current_status' => $application->status
            ], 400);
        }

        $request->validate([
            'admin_notes' => 'required|string|min:10|max:1000'
        ]);

        // Update application status
        $application->update([
            'status' => AuthorApplication::STATUS_REJECTED,
            'admin_notes' => $request->admin_notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Send rejection notification
        $rejectionMessage = 'Your author application has been reviewed and unfortunately was not approved at this time.';
        if ($request->admin_notes) {
            $rejectionMessage .= ' Admin feedback: ' . $request->admin_notes;
        }
        $rejectionMessage .= ' You may reapply in the future after addressing the feedback.';

        Notification::createAuthorStatusNotification(
            $application->user->id,
            'rejected',
            $rejectionMessage
        );

        return response()->json([
            'message' => 'Author application rejected',
            'application' => $application->load(['user', 'reviewer'])
        ]);
    }

    /**
     * Admin: Update application notes
     */
    public function updateNotes(Request $request, AuthorApplication $application): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Admin access required'], 403);
        }

        $request->validate([
            'admin_notes' => 'required|string|max:1000'
        ]);

        $application->update([
            'admin_notes' => $request->admin_notes
        ]);

        return response()->json([
            'message' => 'Application notes updated',
            'application' => $application
        ]);
    }
}
