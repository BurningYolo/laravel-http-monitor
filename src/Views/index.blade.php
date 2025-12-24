@extends('http-monitor::layout')

@section('title', 'HTTP Monitor Dashboard')

@section('content')
<div class="mb-4">
    <h1 class="mb-1">Dashboard</h1>
    <p class="text-muted">Real-time overview of HTTP activity and performance metrics</p>
</div>

<!-- Main Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(47, 129, 247, 0.15);">
                <span style="color: #2f81f7;">📊</span>
            </div>
            <div class="stat-card-title">Total Requests</div>
            <div class="stat-card-value" style="color: #2f81f7;">{{ number_format($totalRequests) }}</div>
            <div class="stat-card-change text-muted">
                All time monitoring
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(63, 185, 80, 0.15);">
                <span style="color: #3fb950;">📥</span>
            </div>
            <div class="stat-card-title">Inbound Requests</div>
            <div class="stat-card-value" style="color: #3fb950;">{{ number_format($totalInbound) }}</div>
            <div class="stat-card-change" style="color: #3fb950;">
                {{ $totalRequests > 0 ? number_format(($totalInbound / $totalRequests) * 100, 1) : 0 }}% of total
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(210, 153, 34, 0.15);">
                <span style="color: #d29922;">📤</span>
            </div>
            <div class="stat-card-title">Outbound Requests</div>
            <div class="stat-card-value" style="color: #d29922;">{{ number_format($totalOutbound) }}</div>
            <div class="stat-card-change" style="color: #d29922;">
                {{ $totalRequests > 0 ? number_format(($totalOutbound / $totalRequests) * 100, 1) : 0 }}% of total
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-card-icon" style="background: rgba(163, 113, 247, 0.15);">
                <span style="color: #a371f7;">🌐</span>
            </div>
            <div class="stat-card-title">Unique IPs</div>
            <div class="stat-card-value" style="color: #a371f7;">{{ number_format($uniqueIps) }}</div>
            <div class="stat-card-change text-muted">
                Tracked sources
            </div>
        </div>
    </div>
</div>

<!-- Performance Metrics -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Outbound Success Rate</div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-muted small mb-1">Success Rate</div>
                        <h2 class="mb-0" style="color: #3fb950;">
                            {{ $totalOutbound > 0 ? number_format(($successfulOutbound / $totalOutbound) * 100, 1) : 0 }}%
                        </h2>
                    </div>
                    <div class="text-end">
                        <div style="font-size: 3rem; opacity: 0.3;">✓</div>
                    </div>
                </div>
                <div class="progress mb-2" style="height: 8px; background-color: #1c2128;">
                    <div class="progress-bar" role="progressbar" 
                         style="width: {{ $totalOutbound > 0 ? ($successfulOutbound / $totalOutbound) * 100 : 0 }}%; background-color: #3fb950;">
                    </div>
                </div>
                <div class="d-flex justify-content-between text-muted small">
                    <span>✓ Successful: {{ number_format($successfulOutbound) }}</span>
                    <span>✗ Failed: {{ number_format($failedOutbound) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Average Response Time</div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-muted small mb-1">Outbound Requests</div>
                        <h2 class="mb-0" style="color: #2f81f7;">
                            {{ $avgResponseTime ? number_format($avgResponseTime, 0) : 0 }}<span class="text-muted small">ms</span>
                        </h2>
                    </div>
                    <div class="text-end">
                        <div style="font-size: 3rem; opacity: 0.3;">⚡</div>
                    </div>
                </div>
                <div class="text-muted small">
                    <div class="mb-1">Fastest: {{ $fastestRequest ? number_format($fastestRequest, 2) : 0 }}ms</div>
                    <div>Slowest: {{ $slowestRequest ? number_format($slowestRequest, 2) : 0 }}ms</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Request Activity</div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="text-muted small mb-1">Last 24 Hours</div>
                        <h2 class="mb-0" style="color: #d29922;">
                            {{ number_format($requestsLast24h) }}
                        </h2>
                    </div>
                    <div class="text-end">
                        <div style="font-size: 3rem; opacity: 0.3;">📈</div>
                    </div>
                </div>
                <div class="text-muted small">
                    <div class="mb-1">Last Hour: {{ number_format($requestsLastHour) }}</div>
                    <div>Avg per hour: {{ $requestsLast24h > 0 ? number_format($requestsLast24h / 24, 1) : 0 }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Method Distribution -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Request Methods (Inbound)</div>
            <div class="card-body">
                @forelse($inboundMethods as $method)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="badge bg-info">{{ $method->method }}</span>
                            <span class="text-muted">{{ number_format($method->count) }} requests</span>
                        </div>
                        <div class="progress" style="height: 6px; background-color: #1c2128;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: {{ ($method->count / $totalInbound) * 100 }}%; background-color: #2f81f7;">
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No data available</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Status Code Distribution</div>
            <div class="card-body">
                @forelse($statusCodes as $status)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="badge bg-{{ $status->status_code < 400 ? 'success' : 'danger' }}">
                                {{ $status->status_code }}
                            </span>
                            <span class="text-muted">{{ number_format($status->count) }} requests</span>
                        </div>
                        <div class="progress" style="height: 6px; background-color: #1c2128;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: {{ ($status->count / $totalInbound) * 100 }}%; background-color: {{ $status->status_code < 400 ? '#3fb950' : '#f85149' }};">
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No data available</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row g-4 mb-5">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Inbound Requests</span>
                <a href="{{ route('http-monitor.inbound.index') }}" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($recentInbound as $req)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="mb-1">
                                    <span class="badge bg-info me-2">{{ $req->method }}</span>
                                    <span class="text-truncate d-inline-block" style="max-width: 250px;">{{ $req->path }}</span>
                                </div>
                                <small class="text-muted">{{ $req->created_at->diffForHumans() }}</small>
                            </div>
                            <span class="badge bg-{{ $req->status_code < 400 ? 'success' : 'danger' }}">
                                {{ $req->status_code }}
                            </span>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No inbound requests</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Outbound Requests</span>
                <a href="{{ route('http-monitor.outbound.index') }}" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($recentOutbound as $req)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="mb-1">
                                    <span class="badge bg-info me-2">{{ $req->method }}</span>
                                    <span class="text-truncate d-inline-block" style="max-width: 200px;">{{ $req->host }}</span>
                                </div>
                                <small class="text-muted">
                                    {{ $req->created_at->diffForHumans() }} • {{ number_format($req->duration_ms, 0) }}ms
                                </small>
                            </div>
                            <span class="badge bg-{{ $req->successful ? 'success' : 'danger' }}">
                                {{ $req->successful ? 'OK' : 'Fail' }}
                            </span>
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No outbound requests</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

@endsection