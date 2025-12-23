<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HTTP Monitor')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <span class="navbar-brand">HTTP Monitor</span>
            <div class="navbar-nav">
                <a class="nav-link {{ request()->routeIs('http-monitor.inbound.*') ? 'active' : '' }}" href="{{ route('http-monitor.inbound.index') }}">Inbound</a>
                <a class="nav-link {{ request()->routeIs('http-monitor.outbound.*') ? 'active' : '' }}" href="{{ route('http-monitor.outbound.index') }}">Outbound</a>
                <a class="nav-link {{ request()->routeIs('http-monitor.ips.*') ? 'active' : '' }}" href="{{ route('http-monitor.ips.index') }}">IPs</a>
            </div>
        </div>
    </nav>

    <div class="container">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        
        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>