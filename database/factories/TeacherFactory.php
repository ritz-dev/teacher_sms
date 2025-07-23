<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->uuid,
            'personal_slug' => $this->faker->uuid,
            'teacher_name' => $this->faker->name,
            'teacher_code' => 'TCH' . $this->faker->unique()->numberBetween(100, 999),
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'qualification' => $this->faker->randomElement(['BSc', 'MSc', 'PhD']),
            'subject' => $this->faker->randomElement(['Mathematics', 'Science', 'English']),
            'experience_years' => $this->faker->numberBetween(1, 40),
            'salary' => $this->faker->numberBetween(30000, 120000),
            'hire_date' => $this->faker->date(),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'employment_type' => $this->faker->randomElement(['Full-time', 'Part-time']),
        ];
    }
}
