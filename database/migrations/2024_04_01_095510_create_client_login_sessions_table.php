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
        Schema::create('client_login_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->nullable()->index();
            $table->string('key')->nullable()->index();
            $table->string('identifier')->nullable();
            $table->string('access_token')->nullable()->index();
            $table->string('user_agent')->nullable();
            $table->string('time_limit')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_login_sessions');
    }
};
