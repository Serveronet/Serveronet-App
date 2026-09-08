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
        Schema::create('content_retrievals', function (Blueprint $table) {
            $table->increments('id');
            $table->string('retrieval_id');
            $table->string('fulfiller_id')->nullable();
            $table->string('action_type');
            $table->text('site_id')->nullable();
            $table->string('entity_id')->nullable();
            $table->string('state')->default(RecordStates::retrieval_pending)->nullable();
            $table->string('sha256')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('retries_count')->default(0);
            $table->integer('file_size')->nullable();
            $table->text('ipfs_hash')->nullable();
            $table->text('payload')->nullable();
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
