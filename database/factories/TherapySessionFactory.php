<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\TherapySession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TherapySession>
 */
class TherapySessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'session_date' => fake()->date(),
            'therapy_type' => 'Transakcijska analiza (TA)',
            'transcript' => fake()->paragraph(),
            'status' => 'draft',
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (): array => ['status' => 'processing']);
    }

    public function withAudio(string $path = 'sessions/narek.webm'): static
    {
        return $this->state(fn (): array => [
            'audio_path' => $path,
            'audio_mime' => 'audio/webm',
        ]);
    }
}
