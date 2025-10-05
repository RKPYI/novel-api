<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Get user's notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $type = $request->query('type', 'all');
        $read = $request->query('read', 'all');

        $query = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($type !== 'all') {
            $query->ofType($type);
        }

        if ($read === 'unread') {
            $query->unread();
        } elseif ($read === 'read') {
            $query->read();
        }

        $notifications = $query->paginate(20);

        return response()->json([
            'notifications' => $notifications,
            'stats' => [
                'total' => Notification::where('user_id', $user->id)->count(),
                'unread' => Notification::where('user_id', $user->id)->unread()->count(),
                'read' => Notification::where('user_id', $user->id)->read()->count(),
            ]
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        // Check if user owns this notification
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read',
            'notification' => $notification->fresh()
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread(Request $request, Notification $notification): JsonResponse
    {
        // Check if user owns this notification
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $notification->markAsUnread();

        return response()->json([
            'message' => 'Notification marked as unread',
            'notification' => $notification->fresh()
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $updatedCount = Notification::where('user_id', $user->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'message' => 'All notifications marked as read',
            'updated_count' => $updatedCount
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        // Check if user owns this notification
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = Notification::where('user_id', $user->id)
            ->unread()
            ->count();

        return response()->json([
            'unread_count' => $count
        ]);
    }

    /**
     * Delete all read notifications
     */
    public function clearRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $deletedCount = Notification::where('user_id', $user->id)
            ->read()
            ->delete();

        return response()->json([
            'message' => 'Read notifications cleared',
            'deleted_count' => $deletedCount
        ]);
    }
}
