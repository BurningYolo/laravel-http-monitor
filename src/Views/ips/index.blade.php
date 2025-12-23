@extends('http-monitor::layout')

@section('title', 'Tracked IPs')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Tracked IPs</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-3">
            <div class="col-auto">
                <select name="has_geo" class="form-select" onchange="this.form.submit()">
                    <option value="">All IPs</option>
                    <option value="1" {{ request('has_geo') === '1' ? 'selected' : '' }}>With Geo Data</option>
                    <option value="0" {{ request('has_geo') === '0' ? 'selected' : '' }}>Without Geo Data</option>
                </select>
            </div>
            @if(request('has_geo'))
                <div class="col-auto">
                    <a href="{{ route('http-monitor.ips.index') }}" class="btn btn-secondary">Clear</a>
                </div>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Location</th>
                        <th>ISP</th>
                        <th>Requests</th>
                        <th>Last Seen</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ips as $ip)
                    <tr>
                        <td>{{ $ip->ip_address }}</td>
                        <td>
                            @if($ip->hasGeoData())
                                <span class="badge bg-light text-dark">
                                    🌍 {{ implode(', ', array_filter([$ip->city, $ip->region_name, $ip->country_name])) }}
                                </span>
                            @else
                                <span class="text-muted fst-italic">No geo data available</span>
                            @endif
                        </td>
                        <td>{{ $ip->isp ?? 'N/A' }}</td>
                        <td><span class="badge bg-info">{{ $ip->request_count }}</span></td>
                        <td><small>{{ $ip->last_seen_at?->diffForHumans() ?? 'N/A' }}</small></td>
                        <td>
                            <a href="{{ route('http-monitor.ips.show', $ip->id) }}" class="btn btn-sm btn-primary">View</a>
                            <form method="POST" action="{{ route('http-monitor.ips.destroy', $ip->id) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this IP?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">No IPs found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $ips->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection