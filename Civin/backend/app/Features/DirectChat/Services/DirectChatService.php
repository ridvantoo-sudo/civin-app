<?php

namespace App\Features\DirectChat\Services;

use App\Features\Blocking\Models\Block;
use App\Features\DirectChat\Events\DirectMessageSent;
use App\Features\DirectChat\Models\Conversation;
use App\Features\DirectChat\Models\DirectMessage;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DirectChatService
{
    public function list(User $user, int $perPage): LengthAwarePaginator
    {
        return Conversation::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_low_id', $user->getKey())
                    ->orWhere('user_high_id', $user->getKey());
            })
            ->with(['lowUser.profile', 'highUser.profile'])
            ->latest('last_message_at')
            ->paginate($perPage);
    }

    public function open(User $user, User $other): Conversation
    {
        $this->assertCanMessage($user, $other);

        [$low, $high] = $this->pair($user->getKey(), $other->getKey());

        return Conversation::query()->firstOrCreate(
            ['user_low_id' => $low, 'user_high_id' => $high],
        )->load(['lowUser.profile', 'highUser.profile']);
    }

    public function messages(User $user, Conversation $conversation, int $perPage): LengthAwarePaginator
    {
        $this->assertParticipant($user, $conversation);

        return DirectMessage::query()
            ->where('conversation_id', $conversation->getKey())
            ->with('sender.profile')
            ->latest('created_at')
            ->paginate($perPage);
    }

    public function send(User $user, Conversation $conversation, string $body): DirectMessage
    {
        $this->assertParticipant($user, $conversation);
        $other = User::query()->findOrFail($conversation->otherUserId($user->getKey()));
        $this->assertCanMessage($user, $other);

        $message = DB::transaction(function () use ($user, $conversation, $body): DirectMessage {
            $message = DirectMessage::query()->create([
                'conversation_id' => $conversation->getKey(),
                'sender_id' => $user->getKey(),
                'body' => $body,
                'created_at' => now(),
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message->load('sender.profile');
        });

        DirectMessageSent::dispatch($message);

        return $message;
    }

    private function assertParticipant(User $user, Conversation $conversation): void
    {
        if (! $conversation->involves($user->getKey())) {
            throw ValidationException::withMessages([
                'conversation' => 'You are not part of this conversation.',
            ]);
        }
    }

    private function assertCanMessage(User $user, User $other): void
    {
        if ($user->getKey() === $other->getKey()) {
            throw ValidationException::withMessages([
                'user_id' => 'You cannot message yourself.',
            ]);
        }

        $blocked = Block::query()
            ->where(function ($query) use ($user, $other): void {
                $query->where('blocker_id', $user->getKey())->where('blocked_id', $other->getKey());
            })
            ->orWhere(function ($query) use ($user, $other): void {
                $query->where('blocker_id', $other->getKey())->where('blocked_id', $user->getKey());
            })
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages([
                'user_id' => 'Messaging is not available between these accounts.',
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function pair(string $left, string $right): array
    {
        return strcmp($left, $right) < 0 ? [$left, $right] : [$right, $left];
    }
}
