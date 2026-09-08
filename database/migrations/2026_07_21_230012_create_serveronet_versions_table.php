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
        Schema::create('serveronet_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version');
            $table->string('file_name');
            $table->text('record_json');
            $table->datetime('applied_at')->nullable();
            $table->string('sha256');
            $table->string('type')->nullable();
            $table->string('channel')->nullable();
            $table->text('signature')->nullable();
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
