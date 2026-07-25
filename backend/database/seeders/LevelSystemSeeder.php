<?php

namespace Database\Seeders;

use App\Features\Levels\Models\Badge;
use App\Features\Levels\Models\DailyTask;
use App\Features\Levels\Models\XpTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LevelSystemSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            [
                'code' => 'daily_login',
                'title' => 'Daily Login',
                'description' => 'Log in once today.',
                'source' => XpTransaction::SOURCE_LOGIN,
                'target' => 1,
                'xp_reward' => 30,
                'sort_order' => 1,
            ],
            [
                'code' => 'watch_live',
                'title' => 'Watch Live',
                'description' => 'Watch live streams for 10 minutes.',
                'source' => XpTransaction::SOURCE_WATCHING,
                'target' => 10,
                'xp_reward' => 40,
                'sort_order' => 2,
            ],
            [
                'code' => 'go_live',
                'title' => 'Go Live',
                'description' => 'Host a live stream for 15 minutes.',
                'source' => XpTransaction::SOURCE_LIVE,
                'target' => 15,
                'xp_reward' => 80,
                'sort_order' => 3,
            ],
            [
                'code' => 'send_gifts',
                'title' => 'Send Gifts',
                'description' => 'Send gifts worth 100 coins.',
                'source' => XpTransaction::SOURCE_GIFT_SEND,
                'target' => 100,
                'xp_reward' => 60,
                'sort_order' => 4,
            ],
            [
                'code' => 'receive_gifts',
                'title' => 'Receive Gifts',
                'description' => 'Receive gifts worth 100 coins.',
                'source' => XpTransaction::SOURCE_GIFT_RECEIVE,
                'target' => 100,
                'xp_reward' => 60,
                'sort_order' => 5,
            ],
        ];

        foreach ($tasks as $task) {
            $model = DailyTask::query()->firstOrNew(['code' => $task['code']]);
            if (! $model->exists) {
                $model->id = (string) Str::uuid();
            }
            $model->fill([
                'title' => $task['title'],
                'description' => $task['description'],
                'source' => $task['source'],
                'target' => $task['target'],
                'xp_reward' => $task['xp_reward'],
                'status' => DailyTask::STATUS_ACTIVE,
                'sort_order' => $task['sort_order'],
            ])->save();
        }

        $badges = [
            [
                'code' => 'newcomer',
                'name' => 'Newcomer',
                'description' => 'Reach level 5.',
                'unlock_type' => Badge::UNLOCK_LEVEL,
                'unlock_value' => 5,
                'xp_reward' => 50,
                'sort_order' => 1,
            ],
            [
                'code' => 'rising_star',
                'name' => 'Rising Star',
                'description' => 'Reach level 25.',
                'unlock_type' => Badge::UNLOCK_LEVEL,
                'unlock_value' => 25,
                'xp_reward' => 150,
                'sort_order' => 2,
            ],
            [
                'code' => 'legend',
                'name' => 'Legend',
                'description' => 'Reach level 50.',
                'unlock_type' => Badge::UNLOCK_LEVEL,
                'unlock_value' => 50,
                'xp_reward' => 300,
                'sort_order' => 3,
            ],
            [
                'code' => 'max_level',
                'name' => 'Max Level',
                'description' => 'Reach level 100.',
                'unlock_type' => Badge::UNLOCK_LEVEL,
                'unlock_value' => 100,
                'xp_reward' => 1000,
                'sort_order' => 4,
            ],
            [
                'code' => 'generous',
                'name' => 'Generous',
                'description' => 'Send gifts 10 times.',
                'unlock_type' => Badge::UNLOCK_GIFTS_SENT,
                'unlock_value' => 10,
                'xp_reward' => 100,
                'sort_order' => 5,
            ],
            [
                'code' => 'beloved',
                'name' => 'Beloved',
                'description' => 'Receive gifts 10 times.',
                'unlock_type' => Badge::UNLOCK_GIFTS_RECEIVED,
                'unlock_value' => 10,
                'xp_reward' => 100,
                'sort_order' => 6,
            ],
            [
                'code' => 'broadcaster',
                'name' => 'Broadcaster',
                'description' => 'Earn 60 minutes of live XP.',
                'unlock_type' => Badge::UNLOCK_LIVE_MINUTES,
                'unlock_value' => 60,
                'xp_reward' => 120,
                'sort_order' => 7,
            ],
            [
                'code' => 'loyal',
                'name' => 'Loyal',
                'description' => 'Claim daily login XP 7 times.',
                'unlock_type' => Badge::UNLOCK_LOGIN_STREAK,
                'unlock_value' => 7,
                'xp_reward' => 80,
                'sort_order' => 8,
            ],
        ];

        foreach ($badges as $badge) {
            $model = Badge::query()->firstOrNew(['code' => $badge['code']]);
            if (! $model->exists) {
                $model->id = (string) Str::uuid();
            }
            $model->fill([
                'name' => $badge['name'],
                'description' => $badge['description'],
                'unlock_type' => $badge['unlock_type'],
                'unlock_value' => $badge['unlock_value'],
                'xp_reward' => $badge['xp_reward'],
                'status' => Badge::STATUS_ACTIVE,
                'sort_order' => $badge['sort_order'],
            ])->save();
        }
    }
}
