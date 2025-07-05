<?php

namespace App\Http\Controllers\API;

use App\Events\NewMessageSent;
use App\Helper\Helper;
use App\Http\Controllers\Controller;
use App\Models\BlockUser;
use App\Models\MessageReact;
use App\Models\User;
use App\Traits\apiresponse;
use App\Traits\bloackeduser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Namu\WireChat\Enums\ConversationType;
use Namu\WireChat\Events\MessageCreated;
use Namu\WireChat\Events\NotifyParticipant;
use Namu\WireChat\Models\Conversation;

class ChatController extends Controller
{
    use apiresponse;
    use bloackeduser;

    public function getConversations(Request $request)
    {
        $user = auth()->user();

        $conversations = $user->conversations()
            ->when($request->filled('type'), function ($query) use ($request, $user) {
                switch ($request->type) {
                    case 'private':
                        $query->where('type', 'private');
                        break;

                    case 'group':
                        $query->where('type', 'group');
                        break;

                    case 'my_own_group':
                        $query->where('type', 'group')
                            ->whereHas('participants', function ($q) use ($user) {
                                $q->where('participantable_id', $user->id)
                                    ->where('participantable_type', get_class($user))
                                    ->where('role', 'owner');
                            });
                        break;
                }
            })
            ->with([
                'participants' => function ($query) {
                    $query->where('participantable_id', '!=', auth()->id())
                        ->select('participantable_type', 'participantable_id', 'conversation_id')
                        ->with('participantable:id,name,avatar');
                },
                'group.cover',
                'lastMessage'
            ])
            ->select('wire_conversations.id', 'wire_conversations.type')
            ->orderByDesc(\DB::raw('(SELECT created_at FROM wire_messages WHERE wire_messages.conversation_id = wire_conversations.id ORDER BY created_at DESC LIMIT 1)'))
            ->get();



        // Transform the conversations for frontend consumption
        $conversations->transform(function ($conversation) use ($request) {
            $authParticipant = $conversation->authParticipant ?? auth()->user();
            $isReadByAuth = $conversation->readBy($authParticipant)
                || $conversation->id == $request->input('selectedConversationId');

            $conversation->readable = $isReadByAuth;
            // dd($conversation->type);

            // Conditionally remove irrelevant data
            if ($conversation->type === ConversationType::GROUP) {
                unset($conversation->participants); // Hide participants for group
            } elseif ($conversation->type === ConversationType::PRIVATE) {
                unset($conversation->group); // Hide group info for private
            }

            unset($conversation->authParticipant, $conversation->pivot);

            return $conversation;
        });
        // dd($conversations->last());

        return $this->success([
            'conversations' => $conversations,
        ], "Conversations fetched successfully", 200);
    }

    public function sendMessage(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'to_user_id' => 'required|exists:users,id',
            'message' => 'required_without:file|string',
            'file' => 'required_without:message|file|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422); // Use 422 for validation errors
        }

        DB::beginTransaction();
        try {
            // Blocked User Check // User blocked check
            if ($this->checkUserBlocked($request->to_user_id)) {
                return $this->error([], "This user is blocked.", 403);
            } elseif ($this->checkBlockedMe($request->to_user_id)) {
                return $this->error([], "This user has blocked you.", 403);
            }

            $auth = auth()->user();
            $recipient = User::where('id', $request->to_user_id)
                ->where('id', '!=', $auth->id) // Prevent sending messages to self
                ->where('status', 'active') // Ensure user is active
                ->first();
            if (!$recipient) {
                return $this->error([], 'Recipient not found', 404);
            }
            $sendMessage = $request->message;
            if ($request->hasFile('file') && $request->file('file')->isValid() && $request->message == null) {
                $rand = Str::random(6);
                $sendMessage = Helper::uploadImage($request->file('file'), 'message', "User-" . $auth->username . "-" . $rand . "-" . time());
            }
            // Use the sendMessageTo method from the Chatable trait
            $message = $auth->sendMessageTo($recipient, $sendMessage);

            // Broadcast events after successful message creation
            broadcast(new NewMessageSent($message))->toOthers();
            broadcast(new MessageCreated($message));
            // broadcast(new NotifyParticipant($message->conversation->participant($recipient), $message));
            DB::commit();

            return $this->success(['message' => $message], "Message sent successfully", 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error([], $e->getMessage(), 500);
        }
    }

    public function linkConversation($encryptedId)
    {
        $conversationId = Crypt::decryptString($encryptedId);
        $data = $this->getConversation($conversationId);

        return $this->success($data, 'Data Fetch Successfully', 200);
    }

    public function getUserConversation($id)
    {
        $data = $this->getConversation($id);

        return $this->success($data, 'Data Fetch Successfully', 200);
    }

    private function getConversation($id)
    {
        $user = auth()->user();

        // Get the conversation and eager load relationships
        $conversation = Conversation::with([
            'messages.sendable',
            'messages.react',
            'group.cover'
        ])
            ->select('wire_conversations.id', 'wire_conversations.type')
            ->findOrFail($id);

        // Mark the conversation as read
        $conversation->markAsRead();

        // Map messages with sender and reaction details
        $messages = $conversation->messages->map(function ($message) use ($user) {
            $reactions = $message->react->groupBy('react')->map(function ($group) {
                return $group->count();
            });

            return [
                'id' => $message->id,
                'body' => $message->body,
                'type' => $message->type->value ?? 'text',
                'created_at' => $message->created_at->toDateTimeString(),
                'is_me' => $message->sendable_id == $user->id && $message->sendable_type == $user->getMorphClass(),
                'sender' => [
                    'id' => $message->sendable->id ?? null,
                    'name' => $message->sendable->name ?? null,
                    'avatar' => $message->sendable->avatar_url ?? $message->sendable->avatar ?? null,
                ],
                'reactions' => $reactions,
            ];
        });

        // Determine top info (either group or receiver)
        $topInfo = null;

        if ($conversation->type === ConversationType::GROUP) {
            $group = $conversation->group;
            $topInfo = [
                'type' => 'group',
                'id' => $group->id ?? null,
                'name' => $group->name ?? null,
                'avatar' => $group->avatar_url ?? null,
                'cover_image' => $group->cover->url ?? null,
            ];
        } else {
            // Call the getReceiver() method manually
            $receiver = $conversation->getReceiver();
            $topInfo = [
                'type' => 'private',
                'id' => $receiver->id ?? null,
                'name' => $receiver->name ?? null,
                'avatar' => $receiver->avatar_url ?? $receiver->avatar ?? null,
            ];
        }

        return [
            'my_id' => $user->id,
            'conversation_id' => $conversation->id,
            'top_info' => $topInfo,
            'messages' => $messages,
        ];
    }

    public function createCovesation(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        $otherUser = User::find($request->user_id);
        if (!$otherUser) {
            return $this->error([], 'User Not found!', 422);
        }

        $auth = auth()->user();
        $conversation = $auth->createConversationWith($otherUser); // Pass the model, not ID

        return $this->success($conversation, 'Conversation Created Successfully', 200);
    }

    public function covesationBlock(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'blocked_user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        $authUser = auth()->user();

        if ($authUser->id == $request->blocked_user_id) {
            return $this->error([], 'You cannot block yourself.', 422);
        }

        $alreadyBlocked = BlockUser::where('user_id', $authUser->id)
            ->where('blocked_user_id', $request->blocked_user_id)
            ->first();

        if ($alreadyBlocked) {
            $alreadyBlocked->delete();
            return $this->success([], 'User unblocked', 200);
        }

        $block = BlockUser::create([
            'user_id' => $authUser->id,
            'blocked_user_id' => $request->blocked_user_id,
            'created_at' => now()
        ]);

        return $this->success($block, 'User blocked successfully.', 200);
    }

    public function messageReact(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'message_id' => 'required|exists:wire_messages,id',
            'react' => 'required|string', // You can add in:love,like,haha etc. for stricter validation
        ]);
        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        $userId = auth()->id();

        // Update if exists, else create new
        $reaction = MessageReact::updateOrCreate(
            [
                'user_id' => $userId,
                'message_id' => $request->message_id,
            ],
            [
                'react' => $request->react,
            ]
        );

        return $this->success($reaction, 'Reaction recorded.', 200);
    }

    public function searchUsers(Request $request)
    {
        // Validate the request to ensure 'query' exists
        $validation = Validator::make($request->all(), [
            'query' => 'required|string',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422); // Validation error
        }

        $searchQuery = $request->input('query');

        $user = new User();

        $users = $user->searchChatables($searchQuery);

        return $this->success([
            'users' => $users->select('id', 'name', 'avatar'),
        ], "Users fetched successfully", 200);
    }

    public function removeCovesation(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:wire_conversations,id',
        ]);

        if ($validation->fails()) {
            return $this->error([], $validation->errors(), 422);
        }

        $auth = auth()->user();
        $conversation = $auth->conversations()->find($request->conversation_id);
        $conversation->deleteFor($auth);

        return $this->success([], 'Delete Conversation successfully');
    }
}
