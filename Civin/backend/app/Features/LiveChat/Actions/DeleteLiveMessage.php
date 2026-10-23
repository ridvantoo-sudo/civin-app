<?php

namespace App\Features\LiveChat\Actions;

use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveChat\Services\LiveChatService;
use App\Features\Users\Models\User;

final readonly class DeleteLiveMessage
{
    public function __construct(private LiveChatService $chat) {}

    public function execute(LiveMessage $message, User $actor): LiveMessage
    {
        return $this->chat->delete($message, $actor);
    }
}
