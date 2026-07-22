<?php

namespace App\Features\LiveChat\Actions;

use App\Features\LiveChat\DTOs\SendLiveMessageData;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveChat\Services\LiveChatService;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;

final readonly class SendLiveMessage
{
    public function __construct(private LiveChatService $chat) {}

    public function execute(LiveRoom $room, User $user, SendLiveMessageData $data): LiveMessage
    {
        return $this->chat->send($room, $user, $data);
    }
}
