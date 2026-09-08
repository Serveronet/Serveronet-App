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
        Schema::create('visitor_records', function (Blueprint $table) {
            $table->id();
            $table->string('site_id');
            $table->string('visitor_id')->nullable();
            $table->text('visitor_verification_key_base64')->nullable();
            $table->string('signer')->nullable();
            $table->text('signer_verification_key_base64')->nullable();
            $table->text('record_json')->nullable();
            $table->datetime('deleted_at')->nullable();
            $table->string('table')->default('');
            $table->boolean('is_grant_record')->nullable();
            $table->string('grantee_visitor_id')->nullable();
            $table->boolean('is_site_admin_locked')->nullable();
            $table->datetime('validated_at')->nullable();
            $table->string('entity_id')->unique();
            $table->text('signature')->nullable();
            $table->string('entity_created');
            $table->string('entity_updated');
            $table->string('entity_deleted')->nullable();
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
