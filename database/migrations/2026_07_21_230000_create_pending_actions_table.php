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
        Schema::create('pending_actions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('action_type');
            $table->string('sha256')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('site_id')->nullable();
            $table->string('state')->default(RecordStates::action_pending)->nullable();
            $table->date('processing_started_at')->nullable();
            $table->integer('retries_count')->default(0);
            $table->datetime('completed_at')->nullable();
            $table->text('failed_message')->nullable();
            $table->string('client_address')->nullable();
            $table->string('entity_id')->nullable();
            $table->integer('file_size')->nullable();
            $table->text('ipfs_hash')->nullable();
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
