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
        Schema::create('reporting_peer_edges', function (Blueprint $table) {
            $table->id();
            $table->string('self_client_address')->nullable();
            $table->string('remote_client_address')->nullable();
            $table->integer('reputation')->nullable();
            $table->boolean('is_remote_connectable')->nullable();
            $table->boolean('is_return_self_connectable')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporting_peer_edges');
    }
};
