<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '+244 923 '.$this->faker->numerify('### ###'),
            'nif' => $this->faker->numerify('#########'),
            'role' => User::ROLE_CLIENT,
            'status' => User::STATUS_ACTIVE,
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function owner()
    {
        return $this->state(['role' => User::ROLE_OWNER, 'status' => User::STATUS_ACTIVE]);
    }

    public function agent()
    {
        return $this->state(['role' => User::ROLE_AGENT, 'status' => User::STATUS_ACTIVE]);
    }

    public function admin()
    {
        return $this->state(['role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE]);
    }

    public function pending()
    {
        return $this->state(['status' => User::STATUS_PENDING]);
    }
}
