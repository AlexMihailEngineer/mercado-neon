<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        // Valid pairs for the FAN Courier / Sameday sandboxes
        $locations = [
            ['county' => 'Bucuresti', 'city' => 'Bucuresti'],
            ['county' => 'Cluj', 'city' => 'Cluj-Napoca'],
            ['county' => 'Timis', 'city' => 'Timisoara'],
            ['county' => 'Iasi', 'city' => 'Iasi'],
            ['county' => 'Brasov', 'city' => 'Brasov'],
        ];

        $loc = $this->faker->randomElement($locations);

        return [
            'invoice_number' => 'MN-2026-'.$this->faker->unique()->numberBetween(100, 999),
            'total_amount_ron' => $this->faker->randomFloat(2, 100, 1000),
            'status' => 'pending',
            'payment_status' => 'pending',
            'logistics_status' => null,
            'awb_number' => null,
            'customer_name' => $this->faker->name(),
            'customer_phone' => '07'.$this->faker->randomNumber(8, true),
            'customer_email' => $this->faker->safeEmail(),
            'shipping_county' => $loc['county'],
            'shipping_city' => $loc['city'],
            'shipping_address' => $this->faker->streetAddress(),
        ];
    }
}
