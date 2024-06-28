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
        Schema::create('product_purchase_by_users', function (Blueprint $table) {
            $table->id();
            $table->string('trx_id')->nullbale()->index();
            $table->string('order_id')->nullbale()->index();
            $table->string('master_order_id')->nullbale()->index();
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('client_id')->nullable()->index();
            $table->string('client_name')->nullable()->index();
            $table->longText('shipping_info')->nullable();
            $table->longText('product_primary_details')->nullable();
            $table->longText('product_variations')->nullable();
            $table->double('product_total_price')->default(0);
            $table->double('delivery_charge')->default(0);
            $table->double('grand_price')->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('waiting for payment')->index();
            $table->string('note')->nullable();
            $table->string('cs_response')->nullable();
            $table->longText('cs_note')->nullable();
            $table->string('tracking')->nullable()->index();
            $table->longText('note_by_delivery')->nullable();
            $table->string('order_type')->default('online')->nullable();
            $table->string('time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_purchase_by_users');
    }
};
