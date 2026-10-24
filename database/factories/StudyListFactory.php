<?php

namespace Database\Factories;

use App\Models\StudyList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyList>
 */
class StudyListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'description' => null,
            'category' => 'german_uni',
            'visibility' => StudyList::VISIBILITY_PRIVATE,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['visibility' => StudyList::VISIBILITY_PUBLIC]);
    }
}
