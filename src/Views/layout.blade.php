<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HTTP Monitor')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="d-flex flex-column min-vh-100">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route('http-monitor.index') }}">
                HTTP Monitor
            </a>

            <div class="navbar-nav">
                <a class="nav-link {{ request()->routeIs('http-monitor.inbound.*') ? 'active' : '' }}"
                   href="{{ route('http-monitor.inbound.index') }}">
                    Inbound
                </a>

                <a class="nav-link {{ request()->routeIs('http-monitor.outbound.*') ? 'active' : '' }}"
                   href="{{ route('http-monitor.outbound.index') }}">
                    Outbound
                </a>

                <a class="nav-link {{ request()->routeIs('http-monitor.ips.*') ? 'active' : '' }}"
                   href="{{ route('http-monitor.ips.index') }}">
                    IPs
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-fill">
        <div class="container">

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5">
        <div class="container py-3">
            <div class="row align-items-center">

                <!-- Left -->
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <strong>HTTP Monitor</strong><br>
                    <span class="small text-secondary">
                        © {{ now()->year }} Created by Hishu
                    </span>
                </div>

                <!-- Right -->
                <div class="col-md-6 text-center text-md-end">
                    <a href="https://github.com/BurningYolo"
                       target="_blank"
                       class="text-white text-decoration-none me-3">
                        GitHub Profile
                    </a>

                    <a href="https://github.com/BurningYolo/laravel-http-monitor"
                       target="_blank"
                       class="text-white text-decoration-none">
                        Project Repo
                    </a>
                </div>

            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
