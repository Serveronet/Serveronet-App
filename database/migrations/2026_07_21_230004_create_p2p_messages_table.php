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
        Schema::create('p2p_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('message_id');
            $table->string('type');
            $table->text('json_payload')->nullable();
            $table->datetime('consumed_at')->nullable();
            $table->string('site_id')->nullable();
            $table->datetime('purged_at')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->string('sender_address')->nullable();
            $table->integer('successful_deliveries_count')->default(0);
            $table->boolean('self_origin')->default(false);
            $table->datetime('sending_started_at')->nullable();
            $table->integer('priority')->nullable();
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
