@extends('http-monitor::layout')

@section('title', 'Outbound Requests')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Outbound Requests</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-3">
            <div class="col-auto">
                <select name="method" class="form-select" onchange="this.form.submit()">
                    <option value="">All Methods</option>
                    <option value="GET" {{ request('method') === 'GET' ? 'selected' : '' }}>GET</option>
                    <option value="POST" {{ request('method') === 'POST' ? 'selected' : '' }}>POST</option>
                </select>
            </div>
            <div class="col-auto">
                <select name="successful" class="form-select" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="1" {{ request('successful') === '1' ? 'selected' : '' }}>Successful</option>
                    <option value="0" {{ request('successful') === '0' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            @if(request()->hasAny(['method', 'successful']))
                <div class="col-auto">
                    <a href="{{ route('http-monitor.outbound.index') }}" class="btn btn-secondary">Clear</a>
                </div>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Host</th>
                        <th>Status</th>
                        <th>Duration</th>
                        <th>Success</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                    <tr>
                        <td><span class="badge bg-info">{{ $req->method }}</span></td>
                        <td class="text-truncate" style="max-width: 250px;" title="{{ $req->host }}">{{ $req->host }}</td>
                        <td>
                            @if($req->status_code)
                                <span class="badge bg-{{ $req->status_code < 400 ? 'success' : 'danger' }}">
                                    {{ $req->status_code }}
                                </span>
                            @else
                                <span class="text-muted fst-italic">N/A</span>
                            @endif
                        </td>
                        <td>{{ number_format($req->duration_ms, 2) }}ms</td>
                        <td>
                            <span class="badge bg-{{ $req->successful ? 'success' : 'danger' }}">
                                {{ $req->successful ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td><small>{{ $req->created_at->diffForHumans() }}</small></td>
                        <td>
                            <a href="{{ route('http-monitor.outbound.show', $req->id) }}" class="btn btn-sm btn-primary">View</a>
                            <form method="POST" action="{{ route('http-monitor.outbound.destroy', $req->id) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button>
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