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
        Schema::create('site_peers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('site_id');
            $table->string('client_address');
            $table->datetime('last_verification_at')->nullable();
            $table->boolean('is_passive_client')->nullable();
            $table->string('passive_token')->nullable();
            $table->datetime('archived_at')->nullable();
            $table->timestamp('last_successfully_verified_at', 6)->nullable();
            $table->datetime('last_heartbeat_at')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
