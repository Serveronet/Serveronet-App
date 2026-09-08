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
        Schema::create('local_tracker_info_hash_peers', function (Blueprint $table) {
            $table->id();
            $table->string('info_hash');
            $table->string('ip');
            $table->string('port');
            $table->string('peer_url')->nullable();
            $table->string('peer_id');
            $table->timestamp('last_announce')->nullable();
            $table->string('uploaded')->nullable();
            $table->string('downloaded')->nullable();
            $table->string('left')->nullable();
            $table->string('last_event')->nullable();
            $table->timestamp('archived_at')->nullable();
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
