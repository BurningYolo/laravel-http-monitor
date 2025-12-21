<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('outbound_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_ip_id')->nullable()->constrained('tracked_ips')->nullOnDelete();
            
            // Request details
            $table->string('method', 10);
            $table->text('url');
            $table->string('host');
            $table->text('full_url');
            $table->string('path')->nullable();
            $table->text('query_string')->nullable();
            
            // Headers and body
            $table->json('headers')->nullable();
            $table->longText('request_body')->nullable();
            
            // Response details
            $table->integer('status_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            
            // Timing
            $table->unsignedBigInteger('duration_ms')->nullable();
            
            // Context (which part of your app made this request)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->string('triggered_by')->nullable(); // controller, job, command, etc.
            
            // Error tracking
            $table->boolean('successful')->default(true);
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            $table->index('tracked_ip_id');
            $table->index('method');
            $table->index('host');
            $table->index('status_code');
            $table->index('successful');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('outbound_requests');
    }
};