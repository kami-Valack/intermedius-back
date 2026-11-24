<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    public function definition()
    {
        $province = 'Luanda';
        $municipality = 'Talatona';
        $neighborhood = 'Benfica';
        $address = $this->faker->unique()->streetAddress();

        return [
            'user_id' => User::factory()->owner(),
            'title' => 'Apartamento T2 '.$this->faker->numerify('###'),
            'category' => 'rent',
            'type' => 'Apartamento',
            'price' => 250000,
            'province' => $province,
            'municipality' => $municipality,
            'neighborhood' => $neighborhood,
            'bedrooms' => 2,
            'baths' => 1,
            'area' => 90,
            'private_address' => $address,
            'private_lat' => '-8.8390000',
            'private_lng' => '13.2894000',
            'visual_pin_x' => 55,
            'visual_pin_y' => 45,
            'description' => 'Apartamento com estacionamento.',
            'amenities' => ['estacionamento'],
            'status' => Property::STATUS_ACTIVE,
            'address_fingerprint' => Normalizer::fingerprint($province, $municipality, $neighborhood, $address),
        ];
    }

    public function pending()
    {
        return $this->state(['status' => Property::STATUS_PENDING]);
    }
}
