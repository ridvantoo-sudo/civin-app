<?php

namespace Database\Seeders;

use App\Features\Challenges\Models\Challenge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChallengeSystemSeeder extends Seeder
{
    public function run(): void
    {
        $challenges = [
            [
                'code' => 'daily_login',
                'title' => 'Daily Check-in',
                'description' => 'Log in once today.',
                'period' => Challenge::PERIOD_DAILY,
                'task_type' => Challenge::TASK_LOGIN,
                'target' => 1,
                'reward_xp' => 30,
                'reward_coins' => 10,
                'reward_diamonds' => 0,
                'sort_order' => 1,
            ],
            [
                'code' => 'daily_watch',
                'title' => 'Watch Live',
                'description' => 'Watch live streams for 10 minutes.',
                'period' => Challenge::PERIOD_DAILY,
                'task_type' => Challenge::TASK_WATCH,
                'target' => 10,
                'reward_xp' => 40,
                'reward_coins' => 15,
                'reward_diamonds' => 0,
                'sort_order' => 2,
            ],
            [
                'code' => 'daily_gift',
                'title' => 'Gift Task',
                'description' => 'Send gifts worth 100 coins.',
                'period' => Challenge::PERIOD_DAILY,
                'task_type' => Challenge::TASK_GIFT_SEND,
                'target' => 100,
                'reward_xp' => 60,
                'reward_coins' => 20,
                'reward_diamonds' => 2,
                'sort_order' => 3,
            ],
            [
                'code' => 'daily_pk',
                'title' => 'PK Task',
                'description' => 'Finish 1 PK battle.',
                'period' => Challenge::PERIOD_DAILY,
                'task_type' => Challenge::TASK_PK,
                'target' => 1,
                'reward_xp' => 70,
                'reward_coins' => 0,
                'reward_diamonds' => 5,
                'sort_order' => 4,
            ],
            [
                'code' => 'daily_live',
                'title' => 'Go Live',
                'description' => 'Host a live stream for 15 minutes.',
                'period' => Challenge::PERIOD_DAILY,
                'task_type' => Challenge::TASK_LIVE,
                'target' => 15,
                'reward_xp' => 80,
                'reward_coins' => 25,
                'reward_diamonds' => 0,
                'sort_order' => 5,
            ],
            [
                'code' => 'weekly_watch',
                'title' => 'Weekly Watcher',
                'description' => 'Watch live streams for 60 minutes this week.',
                'period' => Challenge::PERIOD_WEEKLY,
                'task_type' => Challenge::TASK_WATCH,
                'target' => 60,
                'reward_xp' => 200,
                'reward_coins' => 80,
                'reward_diamonds' => 5,
                'sort_order' => 1,
            ],
            [
                'code' => 'weekly_gift',
                'title' => 'Weekly Gift Master',
                'description' => 'Send gifts worth 500 coins this week.',
                'period' => Challenge::PERIOD_WEEKLY,
                'task_type' => Challenge::TASK_GIFT_SEND,
                'target' => 500,
                'reward_xp' => 250,
                'reward_coins' => 100,
                'reward_diamonds' => 15,
                'sort_order' => 2,
            ],
            [
                'code' => 'weekly_pk',
                'title' => 'Weekly PK Warrior',
                'description' => 'Finish 5 PK battles this week.',
                'period' => Challenge::PERIOD_WEEKLY,
                'task_type' => Challenge::TASK_PK,
                'target' => 5,
                'reward_xp' => 300,
                'reward_coins' => 0,
                'reward_diamonds' => 25,
                'sort_order' => 3,
            ],
            [
                'code' => 'weekly_live',
                'title' => 'Weekly Broadcaster',
                'description' => 'Host live streams for 60 minutes this week.',
                'period' => Challenge::PERIOD_WEEKLY,
                'task_type' => Challenge::TASK_LIVE,
                'target' => 60,
                'reward_xp' => 220,
                'reward_coins' => 90,
                'reward_diamonds' => 10,
                'sort_order' => 4,
            ],
            [
                'code' => 'weekly_receive_gifts',
                'title' => 'Weekly Popular Host',
                'description' => 'Receive gifts worth 300 coins this week.',
                'period' => Challenge::PERIOD_WEEKLY,
                'task_type' => Challenge::TASK_GIFT_RECEIVE,
                'target' => 300,
                'reward_xp' => 180,
                'reward_coins' => 50,
                'reward_diamonds' => 12,
                'sort_order' => 5,
            ],
        ];

        foreach ($challenges as $challenge) {
            $model = Challenge::query()->firstOrNew(['code' => $challenge['code']]);
            if (! $model->exists) {
                $model->id = (string) Str::uuid();
            }
            $model->fill([
                'title' => $challenge['title'],
                'description' => $challenge['description'],
                'period' => $challenge['period'],
                'task_type' => $challenge['task_type'],
                'target' => $challenge['target'],
                'reward_xp' => $challenge['reward_xp'],
                'reward_coins' => $challenge['reward_coins'],
                'reward_diamonds' => $challenge['reward_diamonds'],
                'status' => Challenge::STATUS_ACTIVE,
                'sort_order' => $challenge['sort_order'],
            ])->save();
        }
    }
}
