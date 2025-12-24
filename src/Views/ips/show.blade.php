@extends('http-monitor::layout')

@section('title', 'IP Details')
@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">IP Address: {{ $ip->ip_address }}</h5>
        <a href="{{ route('http-monitor.ips.index') }}" class="btn btn-secondary">← Back</a>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tbody>
                <tr>
                    <th style="width: 200px;">Location</th>
                    <td>
                        @if($ip->hasGeoData())
                            <span class="badge bg-dark text-dark">
                                🌍 {{ implode(', ', array_filter([$ip->city, $ip->region_name, $ip->country_name])) }}
                            </span>
                            @if($ip->latitude && $ip->longitude)
                                <br><small class="text-muted">Coordinates: {{ $ip->latitude }}, {{ $ip->longitude }}</small>
                            @endif
                        @else
                            <span class="text-muted fst-italic">No geographic data available</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>ISP</th>
                    <td>{{ $ip->isp ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Organization</th>
                    <td>{{ $ip->organization ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Timezone</th>
                    <td>{{ $ip->timezone ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Total Requests</th>
                    <td><span class="badge bg-info">{{ $ip->request_count }}</span></td>
                </tr>
                <tr>
                    <th>First Seen</th>
                    <td>{{ $ip->first_seen_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Last Seen</th>
                    <td>
                        {{ $ip->last_seen_at?->format('Y-m-d H:i:s') ?? 'N/A' }} 
                        <small class="text-muted">({{ $ip->last_seen_at?->diffForHumans() ?? 'N/A' }})</small>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@if($ip->inboundRequests->isNotEmpty())
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Recent Inbound Requests (Last 10)</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Path</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ip->inboundRequests as $req)
                    <tr>
                        <td><span class="badge bg-info">{{ $req->method }}</span></td>
                        <td class="text-truncate" style="max-width: 300px;">{{ $req->path }}</td>
                        <td><span class="badge bg-{{ $req->status_code < 400 ? 'success' : 'danger' }}">{{ $req->status_code }}</span></td>
                        <td><small>{{ $req->created_at->diffForHumans() }}</small></td>
                        <td><a href="{{ route('http-monitor.inbound.show', $req->id) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if($ip->outboundRequests->isNotEmpty())
<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Recent Outbound Requests (Last 10)</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Host</th>
                        <th>Status</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ip->outboundRequests as $req)
                    <tr>
                        <td><span class="badge bg-info">{{ $req->method }}</span></td>
                        <td class="text-truncate" style="max-width: 250px;">{{ $req->host }}</td>
                        <td>
                            @if($req->status_code)
                                <span class="badge bg-{{ $req->status_code < 400 ? 'success' : 'danger' }}">{{ $req->status_code }}</span>
                            @else
                                <span class="text-muted fst-italic">N/A</span>
                            @endif
                        </td>
                        <td><small>{{ $req->created_at->diffForHumans() }}</small></td>
                        <td><a href="{{ route('http-monitor.outbound.show', $req->id) }}" class="btn btn-sm btn-primary">View</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection