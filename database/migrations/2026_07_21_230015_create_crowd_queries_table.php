<?php

use App\Dicts\RecordStates;
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
        Schema::create('crowd_queries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('query_id');
            $table->string('action_type');
            $table->text('query_parameters')->nullable();
            $table->string('state')->default(RecordStates::query_pending)->nullable();
            $table->integer('retries_count')->default(0);
            $table->text('site_id')->nullable();
            $table->integer('ttl')->default(3);
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
