<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inbound_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_ip_id')->nullable()->constrained('tracked_ips')->nullOnDelete();

            // Request details
            $table->string('method', 10);
            $table->text('url');
            $table->text('full_url');
            $table->string('path');
            $table->text('query_string')->nullable();

            // Headers and body
            $table->json('headers')->nullable();
            $table->longText('request_body')->nullable();

            // Response details
            $table->integer('status_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();

            // Timing
            $table->unsignedBigInteger('duration_ms')->nullable(); // in milliseconds

            // User context
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->string('session_id')->nullable();

            // Additional metadata
            $table->string('user_agent')->nullable();
            $table->string('referer')->nullable();
            $table->string('route_name')->nullable();
            $table->string('controller_action')->nullable();

            $table->timestamps();

            $table->index('tracked_ip_id');
            $table->index('method');
            $table->index('status_code');
            $table->index('user_id');
            $table->index('created_at');
            $table->index(['method', 'path']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('inbound_requests');
    }
};
