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
        Schema::create('sites', function (Blueprint $table) {
            $table->increments('id');
            $table->string('site_id')->unique();

            $table->boolean('is_published')->default(1)->nullable();
            $table->string('title')->nullable();
            $table->string('title_draft')->nullable();
            $table->boolean('is_hosted')->default(0)->nullable();
            $table->text('ban_reason')->nullable();
            $table->boolean('is_pinned')->default(0)->nullable();
            $table->boolean('is_registry_reported')->default(0)->nullable();
            $table->bigInteger('requests_counter')->default(0)->nullable();
            $table->datetime('published_at')->nullable();
            $table->boolean('is_shell')->default('0');
            $table->string('domain')->nullable();
            $table->text('encrypted_seed')->nullable();

            $table->datetime('last_self_hosting_trackers_announced_at')->nullable();
            $table->boolean('visible_in_ui')->default(0)->nullable();
            $table->datetime('last_self_hosting_published_at')->nullable();
            $table->date('last_request_at')->nullable();
            $table->text('description')->nullable();
            $table->text('description_draft')->nullable();
            $table->string('developed_site_dir')->nullable();
            $table->text('site_config_json_draft')->nullable();
            $table->integer('site_definition_retrieval_count')->default(0)->nullable();
            $table->text('hosting_state_reason')->nullable();
            $table->boolean('is_to_be_hosted')->default(1);
            $table->bigInteger('visitor_records_total_size')->nullable();
            $table->bigInteger('visitor_resources_total_size')->nullable();
            $table->datetime('last_maintenance_at')->nullable();
            $table->string('site_db_password')->nullable();
            $table->string('site_db_encrypted_password')->nullable();
            $table->datetime('site_definitions_replication_end_ts')->nullable();
            $table->datetime('site_peers_replication_end_ts')->nullable();
            $table->datetime('visitor_records_replication_end_ts')->nullable();
            $table->datetime('visitor_resources_replication_end_ts')->nullable();
            $table->boolean('db_created')->nullable();
            $table->integer('db_schema_version')->nullable();
            $table->bigInteger('visitor_records_count')->nullable();
            $table->bigInteger('visitor_resources_count')->nullable();
            $table->datetime('trackers_site_peers_cached_at')->nullable();
            $table->integer('trackers_site_peers_count')->default(0);
            $table->timestamps();
            
            $table->index('site_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};
