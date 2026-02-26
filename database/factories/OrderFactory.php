<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'order_number' => 'ORD-' . strtoupper($this->faker->unique()->bothify('####-####')),
            'type' => 'merchandise',
            'status' => 'pending',
            'printful_order_id' => null,
            'printful_status' => null,
            'shipping_name' => $this->faker->name,
            'shipping_address_1' => $this->faker->streetAddress,
            'shipping_address_2' => null,
            'shipping_city' => $this->faker->city,
            'shipping_state' => $this->faker->stateAbbr,
            'shipping_zip' => $this->faker->postcode,
            'shipping_country' => 'US',
            'shipping_phone' => $this->faker->phoneNumber,
            'subtotal' => $this->faker->randomFloat(2, 10, 100),
            'shipping_cost' => $this->faker->randomFloat(2, 5, 20),
            'tax' => $this->faker->randomFloat(2, 1, 10),
            'total' => function (array $attributes) {
                return $attributes['subtotal'] + $attributes['shipping_cost'] + $attributes['tax'];
            },
            'stripe_payment_intent_id' => 'pi_test_' . $this->faker->uuid,
            'payment_status' => 'paid',
            'paid_at' => now(),
            'tracking_number' => null,
            'tracking_url' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'refunded_at' => null,
        ];
    }
}

