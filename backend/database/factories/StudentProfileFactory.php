<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $middleName = fake()->boolean(60) ? fake()->firstName() : null;
        $lastName = fake()->lastName();

        return [
            'user_id' => User::factory()->state([
                'role' => Role::Student,
                'name' => User::composeName($firstName, $middleName, $lastName),
            ]),
            'status' => 'active',
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'phone' => fake()->numerify('+2547########'),
            'national_id' => fake()->numerify('#########'),
            'date_of_birth' => fake()->dateTimeBetween('-30 years', '-16 years')->format('Y-m-d'),
            'county' => fake()->randomElement(['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret']),
            'place_of_residence' => fake()->city(),
            'religion' => fake()->randomElement(['Christianity', 'Islam', 'Hinduism', 'None']),
            'address' => fake()->address(),
            'profile_picture' => null,
            'alternative_phone' => fake()->boolean(40) ? fake()->numerify('+2547########') : null,
            'is_pwd' => fake()->boolean(10),
            'disability_type' => null,
            'disability_description' => null,
        ];
    }
}
