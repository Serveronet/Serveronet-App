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
        Schema::create('peer_replication_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->string('client_address');
            $table->integer('current_page')->nullable();
            $table->integer('per_page')->nullable();
            $table->integer('total_records')->nullable();
            $table->string('state')->nullable();
            $table->string('detailed_state')->nullable();
            $table->integer('retries')->default(0);
            $table->datetime('last_heartbeat')->nullable();
            $table->dateTime('last_completed_date')->nullable();
            $table->text('record_count_map')->nullable();
            $table->datetime('archived_at')->nullable();
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
