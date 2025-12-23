@extends('http-monitor::layout')

@section('title', 'Inbound Requests')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Inbound Requests</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-3">
            <div class="col-auto">
                <select name="method" class="form-select" onchange="this.form.submit()">
                    <option value="">All Methods</option>
                    <option value="GET" {{ request('method') === 'GET' ? 'selected' : '' }}>GET</option>
                    <option value="POST" {{ request('method') === 'POST' ? 'selected' : '' }}>POST</option>
                    <option value="PUT" {{ request('method') === 'PUT' ? 'selected' : '' }}>PUT</option>
                    <option value="DELETE" {{ request('method') === 'DELETE' ? 'selected' : '' }}>DELETE</option>
                </select>
            </div>
            <div class="col-auto">
                <input type="number" name="status" class="form-control" placeholder="Status Code" value="{{ request('status') }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
            @if(request()->hasAny(['method', 'status']))
                <div class="col-auto">
                    <a href="{{ route('http-monitor.inbound.index') }}" class="btn btn-secondary">Clear</a>
                </div>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Path</th>
                        <th>Status</th>
                        <th>IP</th>
                        <th>Duration</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr>
                        <td><span class="badge bg-info">{{ $req->method }}</span></td>
                        <td class="text-truncate" style="max-width: 300px;" title="{{ $req->path }}">{{ $req->path }}</td>
                        <td>
                            <span class="badge bg-{{ $req->status_code < 400 ? 'success' : 'danger' }}">
                                {{ $req->status_code }}
                            </span>
                        </td>
                        <td>
                            @if($req->trackedIp)
                                <a href="{{ route('http-monitor.ips.show', $req->trackedIp->id) }}">{{ $req->trackedIp->ip_address }}</a>
                            @else
                                <span class="text-muted fst-italic">N/A</span>
                            @endif
                        </td>
                        <td>{{ number_format($req->duration_ms, 2) }}ms</td>
                        <td><small>{{ $req->created_at->diffForHumans() }}</small></td>
                        <td>
                            <a href="{{ route('http-monitor.inbound.show', $req->id) }}" class="btn btn-sm btn-primary">View</a>
                            <form method="POST" action="{{ route('http-monitor.inbound.destroy', $req->id) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this request?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">No requests found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $requests->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection