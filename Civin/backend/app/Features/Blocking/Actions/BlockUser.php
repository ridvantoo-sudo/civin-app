<?php

namespace App\Features\Blocking\Actions;

use App\Features\Blocking\DTOs\BlockUserData;
use App\Features\Blocking\Models\Block;
use App\Features\Blocking\Services\BlockingService;
use App\Features\Users\Models\User;

final readonly class BlockUser
{
    public function __construct(private BlockingService $blocking) {}

    public function execute(User $actor, BlockUserData $data): Block
    {
        return $this->blocking->block($actor, User::query()->findOrFail($data->userId));
    }
}
