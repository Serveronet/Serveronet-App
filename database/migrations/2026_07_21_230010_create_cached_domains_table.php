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
        Schema::create('cached_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('site_id');
            $table->datetime('valid_until')->nullable();
            $table->text('signature')->nullable();
            $table->boolean('is_persistent')->default(0)->nullable();
            $table->text('record_json')->nullable();
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
