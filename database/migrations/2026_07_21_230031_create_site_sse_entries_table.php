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
        Schema::create('site_sse_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('event_id');
            $table->text('message');
            $table->string('event', 50);
            $table->string('type', 50);
            $table->enum('delivered', [0, 1])->default(0);
            $table->string('client', 50)->nullable();
            $table->string('site_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_sse_entries');
    }
};
