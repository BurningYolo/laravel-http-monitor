@extends('http-monitor::layout')

@section('title', 'HTTP Monitor Dashboard')

@section('content')
<div class="mb-4">
    <h1 class="mb-1">Dashboard</h1>
    <p class="text-muted">Overview of HTTP activity</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-bg-primary">
            <div class="card-body">
                <h6 class="card-title">Total Requests</h6>
                <h3>{{ $totalRequests }}</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-success">
            <div class="card-body">
                <h6 class="card-title">Inbound Requests</h6>
                <h3>{{ $totalInbound }}</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-warning">
            <div class="card-body">
                <h6 class="card-title">Outbound Requests</h6>
                <h3>{{ $totalOutbound }}</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-bg-dark">
            <div class="card-body">
                <h6 class="card-title">Unique IPs</h6>
                <h3>{{ $uniqueIps }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Outbound Status</div>
            <div class="card-body">
                <p class="mb-1">Successful: <strong>{{ $successfulOutbound }}</strong></p>
                <p class="mb-0">Failed: <strong>{{ $failedOutbound }}</strong></p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-5">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Recent Inbound Requests</div>
            <ul class="list-group list-group-flush">
                @forelse($recentInbound as $req)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $req->method }} {{ $req->path }}</span>
                        <span class="badge bg-secondary">{{ $req->status_code }}</span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No inbound requests</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Recent Outbound Requests</div>
            <ul class="list-group list-group-flush">
                @forelse($recentOutbound as $req)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $req->method }} {{ $req->url }}</span>
                        <span class="badge {{ $req->successful ? 'bg-success' : 'bg-danger' }}">
                            {{ $req->successful ? 'OK' : 'Fail' }}
                        </span>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No outbound requests</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>


@endsection
