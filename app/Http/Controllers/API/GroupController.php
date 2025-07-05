<?php

namespace App\Http\Controllers\API;

use App\Events\NewMessageSent;
use App\Http\Controllers\Controller;
use App\Models\GroupRequest;
use App\Models\User;
use App\Traits\apiresponse;
use Aws\Endpoint\Partition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Namu\WireChat\Enums\ConversationType;
use Namu\WireChat\Enums\GroupType;
use Namu\WireChat\Enums\ParticipantRole;
use Namu\WireChat\Models\Group;
use Illuminate\Support\Facades\Validator;
use Namu\WireChat\Models\Conversation;
use Namu\WireChat\Models\Participant;
use PhpParser\Node\Expr\FuncCall;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Namu\WireChat\Facades\WireChat;

class GroupController extends Controller
{
    use apiresponse;

    public function search(Request $request)
    {
        $search = trim($request->input('search'));
        $joinedOnly = filter_var($request->input('joined_only'), FILTER_VALIDATE_BOOLEAN);
        $userId = auth()->id();

        $groupsQuery = Group::with([
            'cover',
            'conversation.participants' => function ($q) use ($userId) {
                $q->where('participantable_id', $userId);
            }
        ])->select('id', 'conversation_id', 'name', 'avatar_url', 'description', 'type', 'created_at');

        // 1. If searching by keyword
        if (!empty($search)) {
            $groupsQuery->where('name', 'like', '%' . $search . '%')
                ->orderBy('created_at', 'desc');
        }

        // 2. If requesting only joined groups (and not searching)
        elseif ($joinedOnly) {
            $groupsQuery->whereHas('conversation.participants', function ($q) use ($userId) {
                $q->where('participantable_id', $userId);
            });
        }

        // 3. Default: latest 5 public groups
        else {
            $groupsQuery->where('type', 'public')->latest()->limit(5);
        }

        $groups = $groupsQuery->get();

        // Pre-fetch GroupRequests in bulk to reduce queries
        $groupRequests = GroupRequest::where('user_id', $userId)
            ->whereIn('conversation_id', $groups->pluck('conversation_id')->filter()->unique())
            ->pluck('conversation_id')
            ->toArray();

        $results = $groups->map(function ($group) use ($userId, $groupRequests) {
            $isParticipant = $group->conversation && $group->conversation->participants->isNotEmpty();
            $hasRequested = in_array($group->conversation_id, $groupRequests);

            $status = $isParticipant ? 'joined' : ($hasRequested ? 'requested' : 'join');
            // dd($group);
            return [
                'conversation_id' => $group->conversation_id,
                'description' => $group->description,
                'name' => $group->name,
                'avatar_url' => $group->avatar_url ?? null,
                'participants_count' => $group->conversation?->participants->count() ?? 0,
                'status' => $status,
                'type' => $group->type,
                'created_at' => optional($group->created_at)->format('Y-m-d'),
            ];
        });

        return $this->success($results, 'Data fetched successfully!', 200);
    }

    public function addGroupMember(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'conversation_id' => 'required|integer|exists:wire_conversations,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        try {
            $authUser = auth()->user();
            $conversation = Conversation::find($request->conversation_id);

            // Check if it's a group conversation
            if ($conversation->type !== ConversationType::GROUP) {
                return $this->error([], 'Only group conversations can have members added.', 403);
            }

            // Check if the authenticated user is a participant
            $participant = $conversation->participants()
                ->where('participantable_id', $authUser->id)
                ->where('participantable_type', get_class($authUser)) // if polymorphic
                ->first();

            if (!$participant) {
                return $this->error([], 'User is not a participant of this conversation.', 404);
            }

            // Conditional checks based on group privacy
            if ($conversation->group->type === 'private') {
                if (in_array($participant->role, [ParticipantRole::OWNER, ParticipantRole::ADMIN])) {
                    return $this->error([], 'Only the owner can add members to a private group.', 403);
                }
            }
            // Add the specified user
            $newUser = User::find($request->user_id);
            $data = $conversation->addParticipant($newUser);

            return $this->success($data, 'Member added successfully!', 200);
        } catch (\Throwable $th) {
            return $this->error([], [$th->getMessage()], 401);
        }
    }

    public function groupMessage(Request $request)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'message' => 'required|string|max:1000',
                'conversation_id' => 'required|integer|exists:wire_conversations,id',
            ]);

            $auth = auth()->user();

            // Fetch the conversation using the ID from the request
            $conversation = Conversation::findOrFail($validated['conversation_id']);

            // Send the message
            $message = $auth->sendMessageTo($conversation, $validated['message']);
            broadcast(new NewMessageSent($message))->toOthers();
            return $this->success($message, 'Message sent successfully!', 200);
        } catch (HttpException $e) {
            return $this->error([], [$e->getMessage()], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function sendRequest(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:wire_conversations,id',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        $userId = auth()->id();
        $conversationId = $request->conversation_id;

        $conversation = Conversation::with('group')->find($conversationId);

        if (!$conversation || $conversation->type !== ConversationType::GROUP) {
            return $this->error([], 'Only group conversations are allowed.', 403);
        }
        // Check if already a participant
        $isAlreadyParticipant = $conversation->participants()
            ->where('participantable_id', $userId)
            ->where('participantable_type', get_class(auth()->user()))
            ->exists();

        if ($isAlreadyParticipant) {
            return $this->error([], 'User is already a member of the group.', 409);
        }


        if ($conversation->group->type === GroupType::PRIVATE) {
            // Private: create a group join request
            $existingRequest = GroupRequest::where('user_id', $userId)
                ->where('conversation_id', $conversationId)
                ->first();

            if ($existingRequest) {
                $existingRequest->delete();
                return $this->success([], 'Removed existing request.', 200);
            }

            $newRequest = GroupRequest::create([
                'user_id' => $userId,
                'conversation_id' => $conversationId,
                'reaction' => $request->react,
            ]);

            return $this->success($newRequest, 'Request successfully sent.', 200);
        } else {
            // Public: add user directly to the group
            $user = auth()->user();
            $data = $conversation->addParticipant($user);

            return $this->success($data, 'User added directly to public group.', 200);
        }
    }

    public function removeRequest(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:wire_conversations,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        $authUserId = auth()->user()->id;

        // Check if current user is an admin or owner in the conversation
        $isParticipant = Participant::where('participantable_id', $authUserId)
            ->where('conversation_id', $request->conversation_id)
            ->whereIn('role', ['owner', 'admin'])
            ->exists();
        if (!$isParticipant) {
            return $this->error([], 'You are not authorized to remove requests from this conversation.', 403);
        }

        // Proceed to delete the request
        $deleted = DB::table('group_requests')
            ->where('conversation_id', $request->conversation_id)
            ->where('user_id', $request->user_id)
            ->delete();

        if ($deleted) {
            return $this->success([], 'Request deleted successfully.');
        } else {
            return $this->error([], 'No matching request found.', 404);
        }
    }


    public function get()
    {
        $authUserId = auth()->id();

        // Step 1: Get conversation IDs where the user is owner/admin in a group
        $conversationIds = Participant::where('participantable_id', $authUserId)
            ->whereIn('role', ['owner', 'admin'])
            ->whereHas('conversation', function ($query) {
                $query->where('type', 'group');
            })
            ->pluck('conversation_id');

        // Step 2: Fetch group requests with related data
        $requests = GroupRequest::whereIn('conversation_id', $conversationIds)
            ->with(['user', 'conversation.group', 'conversation.participants'])
            ->get();
        // Step 3: Format the response
        $formattedRequests = $requests->map(function ($request) {
            $group = $request->conversation->group;

            return [
                'user_name' => $request->user->name ?? '',
                'user_avatar' => $request->user->avatar ?? '',
                'group_name' => $group->name ?? '',
                'group_type' => $group->type ?? '',
                'participants_count' => $request->conversation->participants->count(),
            ];
        });

        return $this->success($formattedRequests, 'Data Fetch Successfully', 200);
    }

    public function groupCreate(Request $request)
    {
        // Step 1: Validate the input
        $validation = Validator::make($request->all(), [
            'name' => 'required|string', // Changed 'exists:users,id' to 'string' as 'name' is likely not user ID
            'description' => 'required_without:file|string',
            'photo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:2048', // Made photo optional
            'type' => 'required|in:private,public'
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422); // Validation failed
        }

        // Step 2: Check if a user is authenticated
        $user = auth()->user();
        if (!$user) {
            return $this->error([], 'User not authenticated', 401); // Return error if user is not authenticated
        }

        // Step 3: Handle the photo upload if it exists
        // $photo = null;
        // if ($request->hasFile('photo')) {
        //     $photo = Helper::uploadImage($request->file('file'), 'message');
        // }

        // Step 4: Create the group in a database transaction
        DB::beginTransaction();

        try {
            $conversation = $user->createGroup(
                name: $request->input('name'),
                description: $request->input('description'),
                photo: $request->file('photo'),
                type: $request->type
            );

            DB::commit();

            return $this->success($conversation, 'Group created successfully!', 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return $this->error([], $th->getMessage(), 500);
        }
    }

    public function info($id)
    {
        $conversation = Conversation::with(['group.cover'])->find($id);

        if (!$conversation || !$conversation->group) {
            return $this->error([], 'Group not found', 404);
        }

        $group = $conversation->group;

        $data = [
            'conversation_id' => $conversation->id,
            'description' => $group->description,
            'name' => $group->name,
            'type' => $group->type,
            'cover_url' => $group->cover ? $group->cover->url : null,
        ];

        return $this->success($data, 'Data Fetch Successfully', 200);
    }

    public function member(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'conversation_id' => 'required|integer',
            'type' => 'nullable|in:admin'
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        $conversation = Conversation::with('participants.participantable')->find($request->conversation_id);
        if (!$conversation) {
            return $this->error('Conversation not found', 404);
        }

        $type = $request->input('type');
        $authUserId = auth()->id();

        // Find authenticated user's participant record
        $authParticipant = $conversation->participants->firstWhere('participantable_id', $authUserId);

        // Check if authenticated user has admin or owner role
        $canRemove = false;
        if ($authParticipant && in_array($authParticipant->role ?? '', [ParticipantRole::ADMIN, ParticipantRole::OWNER])) {
            $canRemove = true;
        }

        $members = $conversation->participants->filter(function ($participant) use ($type) {
            if ($type === 'admin') {
                return in_array($participant->role ?? '', [ParticipantRole::ADMIN, ParticipantRole::OWNER]);
            }
            return true;
        })->map(function ($participant) use ($canRemove) {
            $user = $participant->participantable;
            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar ?? null,
                'role' => $user->role ?? null,
                'remove' => $canRemove // This controls the remove button per member
            ];
        })->values();

        return $this->success([
            'members' => $members
        ], 'Members fetched successfully', 200);
    }


    public function leave(Request $request)
    {
        // Step 1: Validate input
        $validation = Validator::make($request->all(), [
            'conversation_id' => 'required|integer|exists:wire_conversations,id',
            'user_id' => 'nullable|integer|exists:users,id', // Optional for admin/owner
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        // Step 2: Retrieve the conversation
        $conversation = Conversation::find($request->conversation_id); // Use correct model
        if (!$conversation) {
            return $this->error([], 'Conversation not found.', 404);
        }

        $currentUser = auth()->user();

        // Step 3: Attempt to remove another user
        if ($request->filled('user_id') && $request->user_id != $currentUser->id) {
            $targetUser = User::find($request->user_id);
            if (!$targetUser) {
                return $this->error([], 'Target user not found.', 404);
            }

            // Only owner or admin can remove another user
            if (!($conversation->isOwner($currentUser) || $conversation->isAdmin($currentUser))) {
                return $this->error([], 'You are not authorized to remove users from this conversation.', 403);
            }

            $result = $targetUser->exitConversation($conversation);

            if (!$result) {
                return $this->error([], 'The user is not a participant in this conversation.', 400);
            }

            return $this->success([], 'User removed from conversation.', 200);
        }

        // Step 4: Self-leave
        $result = $currentUser->exitConversation($conversation);

        if (!$result) {
            return $this->error([], 'You are not a participant in this conversation.', 400);
        }

        return $this->success([], 'Successfully left the conversation.', 200);
    }

    public function updateInfo(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => 'sometimes|nullable|string|max:255',
            'conversation_id' => 'required|integer|exists:wire_conversations,id',
            'type' => 'sometimes|nullable|in:private,public',
            'image' => 'sometimes|nullable|image|max:2048',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        $conversation = Conversation::findOrFail($request->conversation_id);
        $user = auth()->user();

        if (!($conversation->isAdmin($user) || $conversation->isOwner($user))) {
            return $this->error([], 'You are not authorized to change information from this conversation.', 403);
        }

        $group = $conversation->group;
        $data = [];

        if ($request->filled('name')) {
            $data['name'] = $request->name;
        }

        if ($request->filled('type')) {
            $data['type'] = $request->type;
        }

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($group && $group->avatar_url && Storage::disk(WireChat::storageDisk())->exists($group->avatar_url)) {
                Storage::disk(WireChat::storageDisk())->delete($group->avatar_url);
            }

            // Store new image
            $imagePath = $request->file('image')->store(WireChat::storageFolder(), WireChat::storageDisk());
            $data['avatar_url'] = $imagePath;
        }

        // Update group
        $group->update($data);

        return $this->success($conversation->fresh(), 'Conversation updated successfully');
    }

    public function generateLink(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:wire_conversations,id',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }
        // Encrypt the conversation ID
        $encryptedId = Crypt::encryptString($request->conversation_id);

        // Generate full URL for API access
        $link = url('/chat/link/conversation/' . urlencode($encryptedId));

        return $this->success(['link' => $link], 'Link create successfully', 200);
    }
}
