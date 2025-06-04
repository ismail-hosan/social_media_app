<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GroupRequest;
use App\Models\User;
use App\Traits\apiresponse;
use Illuminate\Http\Request;
use Namu\WireChat\Enums\ConversationType;
use Namu\WireChat\Enums\ParticipantRole;
use Namu\WireChat\Models\Group;
use Illuminate\Support\Facades\Validator;
use Namu\WireChat\Models\Conversation;
use Namu\WireChat\Models\Participant;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    use apiresponse;
    public function search(Request $request)
    {
        $search = trim($request->input('search'));

        $groups = Group::when($search, function ($query, $search) {
            $query->where('name', 'like', '%' . $search . '%');
        })
            ->with(['cover', 'conversation.participants']) // eager load conversation & participants
            ->select('id', 'conversation_id', 'name')
            ->get()
            ->map(function ($group) {
                return [
                    'conversation_id' => $group->conversation_id,
                    'name' => $group->name,
                    'cover_url' => $group->cover ? $group->cover->url : null,
                    // count participants of the conversation, or 0 if no conversation
                    'participants_count' => $group->conversation ? $group->conversation->participants->count() : 0,
                ];
            });

        return $this->success($groups, 'Data fetched successfully!', 200);
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


        $existingRequest = GroupRequest::where('user_id', $userId)
            ->where('conversation_id', $conversationId)
            ->first();

        if ($existingRequest) {
            $existingRequest->delete();
            return $this->error([], 'Remove conversations.', 409);
        }

        $newRequest = GroupRequest::create([
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'reaction' => $request->react,
        ]);

        return $this->success($newRequest, 'Request successfully sent.', 200);
    }

    public function get()
    {
        $authuser = auth()->user()->id;

        $conversation = Participant::where('participantable_id', $authuser)
            ->whereIn('role', ['owner', 'admin'])
            ->whereHas('conversation', function ($query) {
                $query->where('type', 'group');
            })
            ->with([
                'conversation' => function ($query) {
                    $query->where('type', 'group');
                }
            ])
            ->pluck('conversation_id');

        $participant = GroupRequest::whereIn('conversation_id', $conversation)->with('conversation.group')->get();
        dd($participant);
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
                photo: $request->photo,
            );
            DB::commit();
            return $this->success($conversation, 'Group created successfully!', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->error([], $th->getMessage(), 500);
        }
    }



}
