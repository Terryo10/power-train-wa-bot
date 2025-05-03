<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Disable foreign key checks to safely delete payment methods
        Schema::disableForeignKeyConstraints();
        
        // Clear existing payment methods
        PaymentMethod::query()->delete();
        
        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
        
        // Define default payment methods
        $paymentMethods = [
            [
                'name' => 'EcoCash',
                'code' => 'ecocash',
                'description' => 'Pay with your EcoCash mobile wallet',
                'instructions' => 'You will receive a prompt on your mobile phone to enter your EcoCash PIN',
                'is_active' => true,
                'display_order' => 1,
            ],
            [
                'name' => 'PayPal',
                'code' => 'paypal',
                'description' => 'Pay with PayPal or credit/debit card',
                'instructions' => 'You will be redirected to PayPal to complete your payment',
                'is_active' => true,
                'display_order' => 2,
            ],
            [
                'name' => 'Bank Transfer',
                'code' => 'bank_transfer',
                'description' => 'Pay by bank transfer to our account',
                'instructions' => 'You will receive bank details to make your transfer',
                'is_active' => true,
                'display_order' => 3,
            ],
            [
                'name' => 'Cash on Delivery',
                'code' => 'cod',
                'description' => 'Pay cash when your order is delivered',
                'instructions' => 'Have the exact amount ready when delivery arrives',
                'is_active' => true,
                'display_order' => 4,
            ],
        ];
        
        // Create payment methods
        foreach ($paymentMethods as $method) {
            PaymentMethod::create($method);
        }
    }
}