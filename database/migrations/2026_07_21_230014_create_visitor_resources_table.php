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
        Schema::create('visitor_resources', function (Blueprint $table) {
            $table->id();
            $table->string('sha256')->nullable();
            $table->string('site_id');
            $table->text('visitor_id')->nullable();
            $table->text('visitor_verification_key_base64')->nullable(); 
            $table->string('signer')->nullable();
            $table->text('signer_verification_key_base64')->nullable();
            $table->string('original_file_name')->nullable();
            $table->integer('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->boolean('is_site_admin_locked')->nullable();
            $table->string('entity_created');
            $table->string('entity_updated');
            $table->string('entity_deleted')->nullable();
            $table->datetime('deleted_at')->nullable();
            $table->string('entity_id')->unique();
            $table->text('signature')->nullable();
            $table->datetime('validated_at')->nullable();
            $table->json('record_json')->nullable();
            $table->json('chunks_json')->nullable();
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
