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
        Schema::create('site_definitions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('site_id');
            $table->string('title')->nullable();
            $table->text('site_config_json')->nullable();
            $table->bigInteger('requests_counter')->default(0)->nullable();
            $table->date('last_request_at')->nullable();
            $table->text('description')->nullable();
            $table->text('file_listing_json');
            $table->text('signer_verification_key_base64')->nullable();
            $table->text('signature');
            $table->text('record_json')->nullable();
            $table->integer('files_count');
            $table->bigInteger('files_size');
            $table->string('download_state')->default(RecordStates::download_unknown)->nullable();
            $table->boolean('is_pending_distribution')->nullable()->default(0);
            $table->datetime('download_scheduled_at')->nullable();
            $table->datetime('last_download_check_at')->nullable();
            $table->date('distributed_at')->nullable();
            $table->string('record_state')->default(\App\Dicts\RecordStates::download_unknown)->nullable();
            $table->string('entity_created')->nullable();
            $table->integer('visitor_records_preliminary_total_size')->nullable();
            $table->integer('visitor_resources_preliminary_total_size')->nullable();
            $table->index(['site_id'], 'site_definitions_site_id_index');
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
