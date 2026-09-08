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
        Schema::create('passive_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('passive_token')->nullable();
            $table->string('remote_ip')->nullable();
            $table->string('passive_client_address')->nullable();
            $table->datetime('last_heartbeat_at')->nullable();
            $table->boolean('should_stop')->nullable();
            $table->text('site_ids_json')->nullable();
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
