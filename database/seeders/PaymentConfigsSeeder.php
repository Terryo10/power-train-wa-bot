<?php

namespace Database\Seeders;

use App\Models\EcocashConfig;
use App\Models\PaypalConfig;
use App\Models\BankTransferDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PaymentConfigsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Disable foreign key checks
        Schema::disableForeignKeyConstraints();
        
        // Clear existing configs
        EcocashConfig::query()->delete();
        PaypalConfig::query()->delete();
        BankTransferDetail::query()->delete();
        
        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();
        
        // Create default EcoCash configuration
        EcocashConfig::create([
            'integration_id' => 'YOUR_ECOCASH_INTEGRATION_ID', // Replace with actual ID
            'integration_key' => 'YOUR_ECOCASH_INTEGRATION_KEY', // Replace with actual key
            'return_url' => url('/payments/ecocash/return'),
            'result_url' => url('/payments/ecocash/callback'),
            'phone_prefix' => '263', // Zimbabwe country code
            'is_active' => true,
        ]);
        
        // Create default PayPal configuration
        PaypalConfig::create([
            'client_id' => 'YOUR_PAYPAL_CLIENT_ID', // Replace with actual ID
            'client_secret' => 'YOUR_PAYPAL_CLIENT_SECRET', // Replace with actual secret
            'sandbox_mode' => true, // Set to false for production
            'return_url' => url('/payments/paypal/return'),
            'cancel_url' => url('/payments/paypal/cancel'),
            'is_active' => true,
        ]);
        
        // Create default Bank Transfer details
        BankTransferDetail::create([
            'bank_name' => 'Example Bank',
            'account_name' => 'Your Company Name',
            'account_number' => '1234567890',
            'branch_code' => '12345',
            'swift_code' => 'EXAMPLE123',
            'instructions' => 'Please use your Order Number as the payment reference. Your order will be processed once payment is confirmed.',
            'is_active' => true,
        ]);
    }
}