<?php

namespace Database\Factories;

use App\Features\Kyc\Models\KycSubmission;
use App\Features\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KycSubmission> */
class KycSubmissionFactory extends Factory
{
    protected $model = KycSubmission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'document_type' => KycSubmission::DOCUMENT_PASSPORT,
            'document_number' => strtoupper(fake()->bothify('??######')),
            'full_legal_name' => fake()->name(),
            'nationality' => fake()->countryCode(),
            'date_of_birth' => fake()->date(),
            'document_front_path' => 'kyc/front.jpg',
            'document_back_path' => null,
            'selfie_path' => 'kyc/selfie.jpg',
            'face_match_score' => 72.5,
            'face_match_status' => KycSubmission::FACE_MATCH_REQUIRES_MANUAL,
            'status' => KycSubmission::STATUS_PENDING,
            'withdrawal_verified' => false,
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => KycSubmission::STATUS_APPROVED,
            'face_match_status' => KycSubmission::FACE_MATCH_MATCHED,
            'face_match_score' => 92.0,
            'withdrawal_verified' => true,
            'reviewed_at' => now(),
        ]);
    }

    public function nationalId(): static
    {
        return $this->state(fn (): array => [
            'document_type' => KycSubmission::DOCUMENT_NATIONAL_ID,
            'document_back_path' => 'kyc/back.jpg',
        ]);
    }
}
