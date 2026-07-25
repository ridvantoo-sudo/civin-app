<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            SettingSeeder::class,
            LevelSystemSeeder::class,
            ChallengeSystemSeeder::class,
            MarketplaceSeeder::class,
            GamePlatformSeeder::class,
            LuckyWheelSeeder::class,
            LuckyDiceSeeder::class,
            SlotMachineSeeder::class,
            LuckyNumberSeeder::class,
            RedEnvelopeSeeder::class,
            TreasureBoxSeeder::class,
            QuizSeeder::class,
            RpsSeeder::class,
            LudoSeeder::class,
            AdminRoleSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
