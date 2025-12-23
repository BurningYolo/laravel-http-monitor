<?php

namespace Burningyolo\LaravelHttpMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ip_address
 * @property string|null $country_code
 * @property string|null $country_name
 * @property string|null $region_code
 * @property string|null $region_name
 * @property string|null $city
 * @property string|null $zip_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $timezone
 * @property string|null $isp
 * @property string|null $organization
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property int $request_count
 * @property-read \Illuminate\Database\Eloquent\Collection|InboundRequest[] $inboundRequests
 * @property-read \Illuminate\Database\Eloquent\Collection|OutboundRequest[] $outboundRequests
 *
 * @method static \Illuminate\Database\Eloquent\Builder|TrackedIp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TrackedIp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TrackedIp query()
 * @method static \Illuminate\Database\Eloquent\Builder|TrackedIp find(int $attributes)
 * @method static \Illuminate\Database\Eloquent\Builder|TrackedIp findOrFail(int $attributes)
 * @method static \Illuminate\Database\Eloquent\Builder|TrackedIp firstOrCreate(array $attributes = [], array $values = [])
 * @method static \Illuminate\Database\Eloquent\Builder|TrackedIp where(string $column, mixed $operator = null, mixed $value = null)
 */
class TrackedIp extends Model
{
    protected $fillable = [
        'ip_address',
        'country_code',
        'country_name',
        'region_code',
        'region_name',
        'city',
        'zip_code',
        'latitude',
        'longitude',
        'timezone',
        'isp',
        'organization',
        'first_seen_at',
        'last_seen_at',
        'request_count',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'request_count' => 'integer',
    ];

    public function inboundRequests(): HasMany
    {
        return $this->hasMany(InboundRequest::class);
    }

    public function outboundRequests(): HasMany
    {
        return $this->hasMany(OutboundRequest::class);
    }

    public static function getOrCreateFromIp(string $ip): self
    {
        /** @var TrackedIp $trackedIp */
        $trackedIp = static::firstOrCreate(
            ['ip_address' => $ip],
            ['first_seen_at' => Carbon::now()]
        );

        $trackedIp->increment('request_count');
        $trackedIp->update(['last_seen_at' => Carbon::now()]);

        return $trackedIp;
    }

    /**
     * Check if this IP has geo data
     */
    public function hasGeoData(): bool
    {
        return ! is_null($this->country_code) ||
               ! is_null($this->city) ||
               ! is_null($this->latitude);
    }
}
