<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique(); // Our unique reference
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD');
            $table->string('status'); // pending, processing, completed, failed, refunded
            $table->string('gateway_reference')->nullable(); // Reference from payment gateway
            $table->string('poll_url')->nullable(); // For EcoCash status checking
            $table->string('customer_phone')->nullable(); // For EcoCash
            $table->string('customer_email')->nullable(); // For PayPal
            $table->json('gateway_response')->nullable(); // Full response from gateway
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
