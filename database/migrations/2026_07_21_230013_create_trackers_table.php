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
        Schema::create('trackers', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('url')->unique();
            $table->datetime('last_connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('priority')->default(2);
            $table->integer('fails_count')->default(0);
            $table->datetime('archived_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
