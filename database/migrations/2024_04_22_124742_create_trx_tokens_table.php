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
        Schema::create('trx_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('trx_id')->nullable()->index();
            $table->string('serial')->nullable();
            $table->longText('client_details')->nullable();
            $table->longText('buyable_products')->nullable();
            $table->longText('stockout_products')->nullable();
            $table->double('grand_total')->default(0);
            $table->string('status')->default('waiting for payment');
            $table->longText('trx_history')->nullable();
            $table->string('time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_tokens');
    }
};
