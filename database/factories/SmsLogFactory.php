<?php

namespace Database\Factories;

use App\Models\SmsLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsLog>
 */
class SmsLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => $this->faker->phoneNumber(),
            'message' => $this->faker->sentence(),
            'direction' => $this->faker->randomElement(['sent', 'reply']),
            'status' => $this->faker->randomElement(['pending', 'delivered', 'failed']),
        ];
    }
}
