<?php

namespace Burningyolo\LaravelHttpMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundRequest extends Model
{
    protected $fillable = [
        'tracked_ip_id',
        'method',
        'url',
        'full_url',
        'path',
        'query_string',
        'headers',
        'request_body',
        'status_code',
        'response_headers',
        'response_body',
        'duration_ms',
        'user_id',
        'user_type',
        'session_id',
        'user_agent',
        'referer',
        'route_name',
        'controller_action',
    ];

    protected $casts = [
        'headers' => 'array',
        'response_headers' => 'array',
        'duration_ms' => 'integer',
        'status_code' => 'integer',
        'user_id' => 'integer',
    ];

    public function trackedIp(): BelongsTo
    {
        return $this->belongsTo(TrackedIp::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo($this->user_type ?? 'App\\Models\\User', 'user_id');
    }
}
