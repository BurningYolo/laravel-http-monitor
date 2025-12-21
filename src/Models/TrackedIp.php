<?php

namespace Burningyolo\LaravelHttpMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
        return !is_null($this->country_code) || 
               !is_null($this->city) || 
               !is_null($this->latitude);
    }


}