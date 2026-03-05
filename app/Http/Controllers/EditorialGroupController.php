<?php

namespace App\Http\Controllers;

use App\Models\EditorialGroup;
use App\Models\EditorialGroupMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EditorialGroupController extends Controller
{
    /**
     * List all editorial groups with their members.
     */
    public function index(): JsonResponse
    {
        $groups = EditorialGroup::with(['members.user:id,name,username,email,role,avatar'])
            ->orderBy('name')
            ->get()
            ->map(fn ($group) => $this->formatGroup($group));

        return response()->json([
            'message' => 'Editorial groups',
            'groups'  => $groups,
        ]);
    }

    /**
     * Create a new editorial group.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:editorial_groups,name',
            'tag'         => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $group = EditorialGroup::create([
            'name'        => $request->name,
            'tag'         => $request->tag,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'Editorial group created successfully',
            'group'   => $this->formatGroup($group->load('members.user')),
        ], 201);
    }

    /**
     * Show a single editorial group with members.
     */
    public function show(EditorialGroup $editorial_group): JsonResponse
    {
        $editorial_group->load('members.user:id,name,username,email,role,avatar');

        return response()->json([
            'message' => 'Editorial group details',
            'group'   => $this->formatGroup($editorial_group),
        ]);
    }

    /**
     * Update an editorial group.
     */
    public function update(Request $request, EditorialGroup $editorial_group): JsonResponse
    {
        $request->validate([
            'name'        => 'sometimes|required|string|max:255|unique:editorial_groups,name,' . $editorial_group->id,
            'tag'         => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data = $request->only(['name', 'tag', 'description', 'is_active']);

        // Regenerate slug if name changes
        if (isset($data['name']) && $data['name'] !== $editorial_group->name) {
            $data['slug'] = $editorial_group->generateSlug($data['name']);
        }

        $editorial_group->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json([
            'message' => 'Editorial group updated successfully',
            'group'   => $this->formatGroup($editorial_group->fresh()->load('members.user')),
        ]);
    }

    /**
     * Delete an editorial group (cascades to memberships).
     */
    public function destroy(EditorialGroup $editorial_group): JsonResponse
    {
        $name = $editorial_group->name;
        $editorial_group->delete();

        return response()->json([
            'message' => "Editorial group '{$name}' deleted successfully",
        ]);
    }

    /**
     * Add a user to an editorial group as editor or author.
     *
     * Editor: single username  → { "username": "x", "role": "editor" }
     * Author: bulk usernames   → { "usernames": ["a","b"], "role": "author" }
     */
    public function addMember(Request $request, EditorialGroup $editorial_group): JsonResponse
    {
        $request->validate([
            'role' => 'required|string|in:editor,author',
        ]);

        if ($request->role === 'editor') {
            return $this->addEditor($request, $editorial_group);
        }

        return $this->addAuthors($request, $editorial_group);
    }

    /**
     * Add a single editor to a group.
     */
    private function addEditor(Request $request, EditorialGroup $editorial_group): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|exists:users,username',
        ], [
            'username.exists' => 'No user found with username ":input".',
        ]);

        $user = User::where('username', $request->username)->firstOrFail();

        // Check if user already belongs to a group
        $existingMembership = EditorialGroupMember::where('user_id', $user->id)->first();
        if ($existingMembership) {
            return response()->json([
                'message' => 'This user already belongs to a group: ' . $existingMembership->group->name,
            ], 409);
        }

        // Ensure the group doesn't already have an editor
        if ($editorial_group->hasEditor()) {
            return response()->json([
                'message' => 'This group already has an editor. Remove the current editor first.',
            ], 409);
        }

        DB::transaction(function () use ($editorial_group, $user) {
            EditorialGroupMember::create([
                'editorial_group_id' => $editorial_group->id,
                'user_id'            => $user->id,
                'role'               => 'editor',
            ]);

            $user->update(['role' => User::ROLE_EDITOR]);
        });

        return response()->json([
            'message' => "User '{$user->name}' added to group '{$editorial_group->name}' as editor",
            'group'   => $this->formatGroup($editorial_group->fresh()->load('members.user')),
        ], 201);
    }

    /**
     * Add one or more authors to a group (bulk).
     */
    private function addAuthors(Request $request, EditorialGroup $editorial_group): JsonResponse
    {
        $request->validate([
            'usernames'   => 'required|array|min:1',
            'usernames.*' => 'required|string|distinct',
        ]);

        $usernames = $request->usernames;

        // Resolve all users and validate they exist
        $users = User::whereIn('username', $usernames)->get()->keyBy('username');

        $notFound = array_diff($usernames, $users->keys()->toArray());
        if (!empty($notFound)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => [
                    'usernames' => array_map(
                        fn ($u) => "No user found with username \"{$u}\".",
                        array_values($notFound)
                    ),
                ],
            ], 422);
        }

        // Check if any user already belongs to a group
        $existingMemberships = EditorialGroupMember::whereIn('user_id', $users->pluck('id'))
            ->with('group:id,name')
            ->get()
            ->keyBy('user_id');

        if ($existingMemberships->isNotEmpty()) {
            $conflicts = $existingMemberships->map(function ($membership) use ($users) {
                $user = $users->firstWhere('id', $membership->user_id);
                return "User '{$user->username}' already belongs to group: {$membership->group->name}";
            })->values()->toArray();

            return response()->json([
                'message'   => 'Some users already belong to a group.',
                'conflicts' => $conflicts,
            ], 409);
        }

        // All clear — add everyone in a transaction
        DB::transaction(function () use ($editorial_group, $users) {
            foreach ($users as $user) {
                EditorialGroupMember::create([
                    'editorial_group_id' => $editorial_group->id,
                    'user_id'            => $user->id,
                    'role'               => 'author',
                ]);

                $user->update(['role' => User::ROLE_AUTHOR]);
            }
        });

        $count = count($usernames);
        $names = $users->pluck('name')->join(', ');

        return response()->json([
            'message' => "{$count} author(s) added to group '{$editorial_group->name}': {$names}",
            'group'   => $this->formatGroup($editorial_group->fresh()->load('members.user')),
        ], 201);
    }

    /**
     * Remove a user from an editorial group.
     */
    public function removeMember(EditorialGroup $editorial_group, string $username): JsonResponse
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $membership = EditorialGroupMember::where('editorial_group_id', $editorial_group->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return response()->json([
                'message' => 'User is not a member of this group',
            ], 404);
        }

        DB::transaction(function () use ($membership, $user) {
            $membership->delete();

            // Reset user role to regular user
            $user->update(['role' => User::ROLE_USER]);
        });

        return response()->json([
            'message' => "User '{$user->name}' removed from group '{$editorial_group->name}'",
            'group'   => $this->formatGroup($editorial_group->fresh()->load('members.user')),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Format a group for JSON response.
     */
    private function formatGroup(EditorialGroup $group): array
    {
        $editor  = null;
        $authors = [];

        foreach ($group->members as $member) {
            $userData = [
                'id'     => $member->user->id,
                'name'   => $member->user->name,
                'username' => $member->user->username,
                'avatar' => $member->user->avatar,
                'role'   => $member->role,
                'joined_at' => $member->created_at->toISOString(),
            ];

            if ($member->role === 'editor') {
                $editor = $userData;
            } else {
                $authors[] = $userData;
            }
        }

        return [
            'id'          => $group->id,
            'name'        => $group->name,
            'slug'        => $group->slug,
            'tag'         => $group->tag,
            'description' => $group->description,
            'is_active'   => $group->is_active,
            'editor'      => $editor,
            'authors'     => $authors,
            'member_count'=> count($authors) + ($editor ? 1 : 0),
            'created_at'  => $group->created_at,
            'updated_at'  => $group->updated_at,
        ];
    }
}
