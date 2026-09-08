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
        Schema::create('replication_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->string('data_type');
            $table->string('site_id');
            $table->datetime('from');
            $table->datetime('to');
            $table->datetime('archived_at')->nullable();
            $table->string('state')->nullable();
            $table->string('detailed_state')->nullable();
            $table->integer('retries')->default(0);
            $table->datetime('last_heartbeat')->nullable();
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
