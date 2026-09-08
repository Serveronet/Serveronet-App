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
        Schema::create('cached_resources', function (Blueprint $table) {
            $table->increments('id');
            $table->string('sha256')->unique();
            $table->string('file_name')->nullable();
            $table->date('last_request_at')->nullable();
            $table->bigInteger('requests_counter')->default(0)->nullable();
            $table->string('ipfs_hash')->nullable();
            $table->integer('file_size')->default(0);
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
