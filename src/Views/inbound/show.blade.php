@extends('http-monitor::layout')

@section('title', 'Inbound Request Details')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Inbound Request Details</h5>
        <a href="{{ route('http-monitor.inbound.index') }}" class="btn btn-secondary">← Back</a>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tbody>
                <tr>
                    <th style="width: 200px;">Method</th>
                    <td><span class="badge bg-info">{{ $request->method }}</span></td>
                </tr>
                <tr>
                    <th>URL</th>
                    <td class="text-break">{{ $request->full_url }}</td>
                </tr>
                <tr>
                    <th>Status Code</th>
                    <td>
                        <span class="badge bg-{{ $request->status_code < 400 ? 'success' : 'danger' }}">
                            {{ $request->status_code }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Duration</th>
                    <td>{{ number_format($request->duration_ms, 2) }}ms</td>
                </tr>
                <tr>
                    <th>IP Address</th>
                    <td>
                        @if($request->trackedIp)
                            <a href="{{ route('http-monitor.ips.show', $request->trackedIp->id) }}">{{ $request->trackedIp->ip_address }}</a>
                            @if($request->trackedIp->hasGeoData())
                                <span class="badge bg-light text-dark ms-2">
                                    🌍 {{ $request->trackedIp->city ?? $request->trackedIp->country_name ?? 'Unknown' }}
                                </span>
                            @else
                                <span class="text-muted fst-italic ms-2">(No geo data)</span>
                            @endif
                        @else
                            <span class="text-muted fst-italic">N/A</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>User Agent</th>
                    <td class="text-break">{{ $request->user_agent ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Route</th>
                    <td>{{ $request->route_name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Timestamp</th>
                    <td>{{ $request->created_at->format('Y-m-d H:i:s') }} <small class="text-muted">({{ $request->created_at->diffForHumans() }})</small></td>
                </tr>
            </tbody>
        </table>

        @if($request->headers)
        <h6 class="mt-4">Request Headers</h6>
        <pre class="bg-light p-3 rounded"><code>{{ json_encode($request->headers, JSON_PRETTY_PRINT) }}</code></pre>
        @endif

        @if($request->request_body)
        <h6 class="mt-4">Request Body</h6>
        <pre class="bg-light p-3 rounded"><code>{{ is_string($request->request_body) ? $request->request_body : json_encode($request->request_body, JSON_PRETTY_PRINT) }}</code></pre>
        @endif

        @if($request->response_body)
        <h6 class="mt-4">Response Body</h6>
        <pre class="bg-light p-3 rounded"><code>{{ is_string($request->response_body) ? $request->response_body : json_encode($request->response_body, JSON_PRETTY_PRINT) }}</code></pre>
        @endif
    </div>
</div>
@endsection