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
        Schema::create('crowd_query_results', function (Blueprint $table) {
            $table->increments('id');
            $table->string('query_id');
            $table->string('fulfiller_id')->nullable();
            $table->text('payload')->nullable();
            $table->string('remote_peer_id')->nullable();
            $table->string('trusted_site_peer_token')->nullable();
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
