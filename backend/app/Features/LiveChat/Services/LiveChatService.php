<?php

namespace App\Features\LiveChat\Services;

use App\Features\Blocking\Repositories\Contracts\BlockRepository;
use App\Features\Followers\Repositories\Contracts\FollowRepository;
use App\Features\LiveChat\DTOs\SendLiveMessageData;
use App\Features\LiveChat\Events\MessageDeleted;
use App\Features\LiveChat\Events\MessageSent;
use App\Features\LiveChat\Events\ViewerJoined;
use App\Features\LiveChat\Events\ViewerLeft;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveChat\Repositories\Contracts\LiveMessageRepository;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final readonly class LiveChatService
{
    private const SEND_RATE_KEY = 'live-chat-send:%s:%s';

    private const SEND_RATE_MAX = 10;

    private const SEND_RATE_DECAY_SECONDS = 60;

    private const DUPLICATE_WINDOW_SECONDS = 10;

    public function __construct(
        private LiveMessageRepository $messages,
        private BlockRepository $blocks,
        private FollowRepository $follows,
    ) {}

    public function initializeRoom(LiveRoom $room): void
    {
        $this->messages->initializeRoom($room);
    }

    public function send(LiveRoom $room, User $user, SendLiveMessageData $data): LiveMessage
    {
        $this->ensureCanAccess($room, $user);
        $this->ensureNotBlockedWithHost($room, $user);
        $this->enforceSpamProtection($room, $user, $data->message);

        $message = $this->messages->send($room, $user, new SendLiveMessageData(
            $data->message,
            LiveMessage::TYPE_TEXT,
            $data->metadata,
        ));

        MessageSent::dispatch($message);

        return $message;
    }

    public function list(LiveRoom $room, User $user, int $perPage): LengthAwarePaginator
    {
        $this->ensureCanAccess($room, $user);

        return $this->messages->forRoom($room, $perPage);
    }

    public function delete(LiveMessage $message, User $actor): LiveMessage
    {
        $room = $message->room()->firstOrFail();
        $this->ensureCanAccess($room, $actor);

        if (! $this->canModerate($room, $actor)) {
            throw new AuthorizationException('Only the host or a moderator can delete chat messages.');
        }

        $deleted = $this->messages->delete($message);
        MessageDeleted::dispatch($room->getKey(), $message->getKey());

        return $deleted;
    }

    public function recordViewerJoined(LiveRoom $room, User $viewer, int $viewerCount): void
    {
        ViewerJoined::dispatch($room->getKey(), $viewer->getKey(), $viewerCount);

        $message = $this->messages->send($room, $viewer, new SendLiveMessageData(
            sprintf('%s joined', $viewer->username ?? 'Viewer'),
            LiveMessage::TYPE_JOIN,
            ['viewer_id' => $viewer->getKey(), 'viewer_count' => $viewerCount],
        ));

        MessageSent::dispatch($message);
    }

    public function recordViewerLeft(LiveRoom $room, User $viewer, int $viewerCount): void
    {
        ViewerLeft::dispatch($room->getKey(), $viewer->getKey(), $viewerCount);

        $message = $this->messages->send($room, $viewer, new SendLiveMessageData(
            sprintf('%s left', $viewer->username ?? 'Viewer'),
            LiveMessage::TYPE_LEAVE,
            ['viewer_id' => $viewer->getKey(), 'viewer_count' => $viewerCount],
        ));

        MessageSent::dispatch($message);
    }

    public function canModerate(LiveRoom $room, User $user): bool
    {
        return $room->host_id === $user->getKey() || $this->messages->isModerator($room, $user);
    }

    private function ensureCanAccess(LiveRoom $room, User $user): void
    {
        if (! $this->messages->isActiveParticipant($room, $user) && ! $this->messages->isModerator($room, $user)) {
            throw new AuthorizationException('Only the host, moderators, or active viewers can access live chat.');
        }
    }

    private function ensureNotBlockedWithHost(LiveRoom $room, User $user): void
    {
        if ($room->host_id === $user->getKey()) {
            return;
        }

        if ($this->blocks->existsBetween($user, $room->host_id)) {
            throw ValidationException::withMessages(['room' => 'You cannot chat in this live room.']);
        }
    }

    private function enforceSpamProtection(LiveRoom $room, User $user, string $message): void
    {
        if ($room->host_id === $user->getKey() || $this->messages->isModerator($room, $user)) {
            return;
        }

        $settings = $this->messages->settingsFor($room);

        if ($settings->followers_only && ! $this->follows->isFollowing($user, $room->host()->firstOrFail())) {
            throw ValidationException::withMessages(['message' => 'Only followers can chat in this live room.']);
        }

        if (! $settings->allow_links && $this->containsLink($message)) {
            throw ValidationException::withMessages(['message' => 'Links are not allowed in this live room.']);
        }

        if ($this->messages->recentDuplicateExists($room, $user, $message, self::DUPLICATE_WINDOW_SECONDS)) {
            throw ValidationException::withMessages(['message' => 'Duplicate messages are not allowed.']);
        }

        if ($settings->slow_mode_seconds > 0) {
            $latest = $this->messages->latestUserTextAt($room, $user);
            if ($latest !== null && $latest->diffInSeconds(now()) < $settings->slow_mode_seconds) {
                throw ValidationException::withMessages([
                    'message' => sprintf('Slow mode is active. Wait %d seconds between messages.', $settings->slow_mode_seconds),
                ]);
            }
        }

        $rateKey = sprintf(self::SEND_RATE_KEY, $room->getKey(), $user->getKey());
        if (RateLimiter::tooManyAttempts($rateKey, self::SEND_RATE_MAX)) {
            throw ValidationException::withMessages(['message' => 'You are sending messages too quickly.']);
        }

        RateLimiter::hit($rateKey, self::SEND_RATE_DECAY_SECONDS);
    }

    private function containsLink(string $message): bool
    {
        return (bool) preg_match('/https?:\/\/|www\.|[a-z0-9-]+\.(com|net|org|io|co|me|tv|gg|app)\b/i', $message);
    }
}
