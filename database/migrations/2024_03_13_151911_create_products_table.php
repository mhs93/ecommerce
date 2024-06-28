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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->nullable()->index();
            $table->string('serial')->nullable();
            $table->string('name')->nullable()->index();
            $table->longText("main_image")->nullable();
            $table->longText("sub_image")->nullable();
            $table->double("quantity")->default(0);
            $table->double("price")->default(0);
            $table->double("old_price")->default(0)->nullable();
            $table->string("category")->nullable()->index();
            $table->string("sub_category")->nullable()->index();
            $table->string("has_group_variation")->default('false');
            $table->longText("group")->nullable();
            $table->longText("variation")->nullable();
            $table->double("total_stock")->default(0);
            $table->longText("seo")->nullable();
            $table->longText("tag")->nullable();
            $table->longText("description")->nullable();
            $table->longText("links")->nullable();
            $table->double("rating")->default(0);
            $table->string("inserted_by")->nullable();
            $table->string("status")->default('inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
