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
        Schema::create('peers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('client_address')->unique();
            $table->string('ip')->nullable();
            $table->boolean('is_tor_address')->default(0);
            $table->bigInteger('reputation')->default(0);
            $table->date('last_connection_check_at')->nullable();
            $table->date('last_connected_at')->nullable();
            $table->integer('response_speed_seconds')->nullable();
            $table->boolean('is_passive_client')->nullable();
            $table->string('active_token')->nullable();
            $table->datetime('last_heartbeat_at')->nullable();
            $table->date('last_inbound_connection_check_at')->nullable();
            $table->date('last_inbound_connected_at')->nullable();
            $table->integer('passive_failures_count')->default(0);
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
