<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\User;
use App\Models\Notification as NotificationModel;
use App\Notifications\NewContactSubmitted;
use App\Notifications\ContactResponseReceived;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    /**
     * Submit a contact form
     */
    public function store(Request $request): JsonResponse
    {
        // Validate the contact form data
        $validator = Validator::make($request->all(), Contact::validationRules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get user data if authenticated (silently)
            $user = $request->user();

            // Create new contact record
            $contactData = [
                'name' => $request->name,
                'email' => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
                'status' => Contact::STATUS_NEW,
            ];

            // If user is authenticated, store their user_id
            if ($user) {
                $contactData['user_id'] = $user->id;
            }

            $contact = Contact::create($contactData);

            // Send notification to all admins
            $this->notifyAdmins($contact);

            // Log the contact submission
            Log::info('Contact form submitted', [
                'contact_id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'subject' => $contact->subject,
                'is_authenticated' => $user !== null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully. We\'ll get back to you soon!'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Contact form submission failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get all contact messages (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status');

        $query = Contact::query()
            ->with(['user', 'responder'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $contacts = $query->paginate($perPage);

        return response()->json($contacts);
    }

    /**
     * Get a specific contact message (admin only)
     */
    public function show(Contact $contact): JsonResponse
    {
        // Load relationships
        $contact->load(['user', 'responder']);

        // Mark as read when admin views it
        $contact->markAsRead();

        return response()->json($contact);
    }

    /**
     * Update contact status (admin only)
     */
    public function updateStatus(Request $request, Contact $contact): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:new,read,replied',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contact->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contact status updated successfully',
            'contact' => $contact
        ]);
    }

    /**
     * Respond to a contact message (admin only)
     */
    public function respond(Request $request, Contact $contact): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'admin_response' => 'required|string|min:10|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = $request->user();

            // Update contact with admin response
            $contact->update([
                'admin_response' => $request->admin_response,
                'responded_by' => $admin->id,
                'responded_at' => now(),
                'status' => Contact::STATUS_REPLIED,
            ]);

            // Notify the user if they are registered
            if ($contact->user) {
                $contact->user->notify(new ContactResponseReceived($contact));
            }

            // If not a registered user, send email notification
            if (!$contact->user) {
                Notification::route('mail', $contact->email)
                    ->notify(new ContactResponseReceived($contact));
            }

            Log::info('Contact response sent', [
                'contact_id' => $contact->id,
                'admin_id' => $admin->id,
                'has_registered_user' => $contact->user !== null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Response sent successfully',
                'contact' => $contact->load(['user', 'responder'])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send contact response', [
                'error' => $e->getMessage(),
                'contact_id' => $contact->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send response. Please try again later.'
            ], 500);
        }
    }

    /**
     * Delete a contact message (admin only)
     */
    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact message deleted successfully'
        ]);
    }

    /**
     * Get user's own contact messages (authenticated users)
     */
    public function myContacts(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);

        $contacts = Contact::where('user_id', $user->id)
            ->with('responder')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json($contacts);
    }

    /**
     * Get a specific contact message (user's own)
     */
    public function showMyContact(Request $request, Contact $contact): JsonResponse
    {
        $user = $request->user();

        // Ensure user can only view their own contacts
        if ($contact->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $contact->load('responder');

        return response()->json($contact);
    }

    /**
     * Send notification to all admins about new contact
     * Excludes the contact submitter if they are also an admin
     */
    private function notifyAdmins(Contact $contact): void
    {
        try {
            // Get all admin users, excluding the contact submitter if they're an admin
            $admins = User::where('role', User::ROLE_ADMIN)
                ->when($contact->user_id, function ($query) use ($contact) {
                    // Exclude the contact submitter from admin notifications
                    $query->where('id', '!=', $contact->user_id);
                })
                ->get();

            // Send notification to each admin
            foreach ($admins as $admin) {
                // Create in-app notification
                NotificationModel::create([
                    'user_id' => $admin->id,
                    'type' => NotificationModel::TYPE_NEW_CONTACT,
                    'title' => 'New Contact Message',
                    'message' => sprintf(
                        'New contact message from %s: %s',
                        $contact->name,
                        $contact->subject
                    ),
                    'data' => [
                        'contact_id' => $contact->id,
                        'name' => $contact->name,
                        'email' => $contact->email,
                        'subject' => $contact->subject,
                        'is_registered_user' => $contact->user_id !== null,
                    ],
                ]);
            }

            Log::info('Admin notifications sent for new contact', [
                'contact_id' => $contact->id,
                'admin_count' => $admins->count(),
                'submitter_excluded' => $contact->user_id !== null,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send admin notifications', [
                'error' => $e->getMessage(),
                'contact_id' => $contact->id,
            ]);
        }
    }
}
