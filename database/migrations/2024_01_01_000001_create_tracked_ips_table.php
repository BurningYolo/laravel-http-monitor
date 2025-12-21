<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tracked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            
            // Geo data columns
            $table->string('country_code', 2)->nullable();
            $table->string('country_name')->nullable();
            $table->string('region_code', 10)->nullable();
            $table->string('region_name')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->nullable();
            $table->string('isp')->nullable();
            $table->string('organization')->nullable();
            
            // Metadata
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('request_count')->default(0);
            
            $table->timestamps();
            
            $table->index('ip_address');
            $table->index('country_code');
            $table->index('last_seen_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tracked_ips');
    }
};