<?php

namespace Burningyolo\LaravelHttpMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $tracked_ip_id
 * @property string $method
 * @property string $url
 * @property string $host
 * @property string $full_url
 * @property string $path
 * @property string|null $query_string
 * @property array|null $headers
 * @property mixed|null $request_body
 * @property int|null $status_code
 * @property array|null $response_headers
 * @property mixed|null $response_body
 * @property float $duration_ms
 * @property int|null $user_id
 * @property string|null $user_type
 * @property string|null $triggered_by
 * @property bool $successful
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TrackedIp|null $trackedIp
 *
 * @method static \Illuminate\Database\Eloquent\Builder|OutboundRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OutboundRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OutboundRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder|OutboundRequest create(array $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder|OutboundRequest firstOrCreate(array $attributes = [], array $values = [])
 * @method static \Illuminate\Database\Eloquent\Builder|OutboundRequest find(int $id)
 * @method static \Illuminate\Database\Eloquent\Builder|OutboundRequest findOrFail(int $id)
 * @method static \Illuminate\Database\Eloquent\Builder|OutboundRequest where(string $column, mixed $operator = null, mixed $value = null)
 */
class OutboundRequest extends Model
{
    protected $fillable = [
        'tracked_ip_id',
        'method',
        'url',
        'host',
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
        'triggered_by',
        'successful',
        'error_message',
    ];

    protected $casts = [
        'headers' => 'array',
        'response_headers' => 'array',
        'duration_ms' => 'float',
        'status_code' => 'integer',
        'user_id' => 'integer',
        'successful' => 'boolean',
    ];

    public function trackedIp(): BelongsTo
    {
        return $this->belongsTo(TrackedIp::class);
    }
}
