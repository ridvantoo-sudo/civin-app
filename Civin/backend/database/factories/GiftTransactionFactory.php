<?php

namespace Database\Factories;

use App\Features\Gifts\Models\Gift;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GiftTransaction>
 */
class GiftTransactionFactory extends Factory
{
    protected $model = GiftTransaction::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $gift = Gift::factory()->create();

        return [
            'sender_id' => User::factory(),
            'receiver_id' => User::factory(),
            'room_id' => LiveRoom::factory(),
            'gift_id' => $gift->id,
            'quantity' => $quantity,
            'coins' => $gift->coin_price * $quantity,
            'metadata' => null,
            'created_at' => now(),
        ];
    }
}
