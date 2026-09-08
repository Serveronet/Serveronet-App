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
        Schema::create('background_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('tag');
            $table->boolean('ensure_daily_execution')->default(0);
            $table->string('method_name');
            $table->integer('execution_hour')->nullable();
            $table->integer('execution_minute')->nullable();
            $table->datetime('last_execution_at')->nullable();
            $table->string('cron')->nullable();
            $table->boolean('is_one_time')->nullable()->default(0);
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
