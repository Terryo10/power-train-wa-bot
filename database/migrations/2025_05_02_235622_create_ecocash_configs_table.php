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
        Schema::create('ecocash_configs', function (Blueprint $table) {
            $table->id();
            $table->string('integration_id');
            $table->string('integration_key');
            $table->string('return_url');
            $table->string('result_url');
            $table->string('phone_prefix')->default('263'); // Zimbabwe country code
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecocash_configs');
    }
};
