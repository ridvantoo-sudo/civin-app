<?php

namespace App\Features\LiveChat\Repositories\Contracts;

use App\Features\LiveChat\DTOs\SendLiveMessageData;
use App\Features\LiveChat\Models\LiveChatSetting;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

interface LiveMessageRepository
{
    public function initializeRoom(LiveRoom $room): LiveChatSetting;

    public function settingsFor(LiveRoom $room): LiveChatSetting;

    public function send(LiveRoom $room, ?User $user, SendLiveMessageData $data): LiveMessage;

    public function forRoom(LiveRoom $room, int $perPage): LengthAwarePaginator;

    public function delete(LiveMessage $message): LiveMessage;

    public function isActiveParticipant(LiveRoom $room, User $user): bool;

    public function isModerator(LiveRoom $room, User $user): bool;

    public function latestUserTextAt(LiveRoom $room, User $user): ?Carbon;

    public function recentDuplicateExists(LiveRoom $room, User $user, string $message, int $withinSeconds = 10): bool;
}
