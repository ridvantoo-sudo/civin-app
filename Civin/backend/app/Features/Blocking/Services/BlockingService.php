<?php

namespace App\Features\Blocking\Services;

use App\Features\Blocking\Models\Block;
use App\Features\Blocking\Repositories\Contracts\BlockRepository;
use App\Features\Followers\Repositories\Contracts\FollowRepository;
use App\Features\Users\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class BlockingService
{
    public function __construct(
        private BlockRepository $blocks,
        private FollowRepository $follows,
    ) {}

    public function block(User $actor, User $target): Block
    {
        $this->ensureDifferentUsers($actor, $target);

        return DB::transaction(function () use ($actor, $target): Block {
            $block = $this->blocks->block($actor, $target);
            $this->follows->removeBetween($actor, $target);

            return $block;
        });
    }

    public function unblock(User $actor, User $target): void
    {
        $this->ensureDifferentUsers($actor, $target);
        $this->blocks->unblock($actor, $target);
    }

    public function index(User $actor, int $perPage): LengthAwarePaginator
    {
        return $this->blocks->blockedBy($actor, $perPage);
    }

    private function ensureDifferentUsers(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages(['user_id' => 'You cannot block yourself.']);
        }
    }
}
