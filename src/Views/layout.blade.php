<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HTTP Monitor')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-primary: #0d1117;
            --bg-secondary: #161b22;
            --bg-tertiary: #1c2128;
            --border-color: #30363d;
            --text-primary: #e6edf3;
            --text-secondary: #7d8590;
            --accent-blue: #2f81f7;
            --accent-green: #3fb950;
            --accent-orange: #d29922;
            --accent-red: #f85149;
            --accent-purple: #a371f7;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
        }

        /* Navbar */
        .navbar {
            background-color: var(--bg-secondary) !important;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.25rem;
            color: var(--text-primary) !important;
        }

        .nav-link {
            color: var(--text-secondary) !important;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: var(--text-primary) !important;
        }

        .nav-link.active {
            color: var(--accent-blue) !important;
        }

        /* Cards */
        .card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            background-color: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }

        .card-body {
            color: var(--text-primary);
        }

        /* Stat Cards */
        .stat-card {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-tertiary) 100%);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card-title {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-card-change {
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Table */
        .table {
            color: var(--text-primary);
            border-color: var(--border-color);
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-primary);
        }

        .table>:not(caption)>*>* {
            padding: .5rem .5rem;
            background-color: transparent;
            color: var(--text-primary);
            border-bottom-width: var(--bs-border-width);
            box-shadow: none;
        }

        .table thead th {
            background-color: var(--bg-tertiary) !important;
            border-color: var(--border-color);
            color: var(--text-secondary) !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            border-color: var(--border-color);
            transition: background-color 0.2s;
        }

        .table tbody td {
            background-color: transparent !important;
            color: var(--text-primary) !important;
        }

        .table-hover tbody tr:hover {
            background-color: var(--bg-tertiary) !important;
        }

        .table-hover tbody tr:hover td {
            background-color: var(--bg-tertiary) !important;
        }

        .table-bordered td, .table-bordered th {
            background-color: transparent !important;
        }

        /* Badges */
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 500;
            border-radius: 4px;
        }

        .badge.bg-info {
            background-color: var(--accent-blue) !important;
        }

        .badge.bg-success {
            background-color: var(--accent-green) !important;
        }

        .badge.bg-warning {
            background-color: var(--accent-orange) !important;
            color: #000 !important;
        }

        .badge.bg-danger {
            background-color: var(--accent-red) !important;
        }

        .badge.bg-secondary {
            background-color: #6e7681 !important;
        }

        /* Buttons */
        .btn-primary {
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
        }

        .btn-primary:hover {
            background-color: #1f6feb;
            border-color: #1f6feb;
        }

        .btn-secondary {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background-color: #2c3138;
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .btn-danger {
            background-color: var(--accent-red);
            border-color: var(--accent-red);
        }

        /* Forms */
        .form-select, .form-control {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .form-select:focus, .form-control:focus {
            background-color: var(--bg-tertiary);
            border-color: var(--accent-blue);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.25rem rgba(47, 129, 247, 0.25);
        }

        .form-select option {
            background-color: var(--bg-secondary);
        }

        /* List Groups */
        .list-group-item {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .list-group-item:hover {
            background-color: var(--bg-tertiary);
        }

        /* Alerts */
        .alert-success {
            background-color: rgba(63, 185, 80, 0.1);
            border-color: var(--accent-green);
            color: var(--accent-green);
        }

        /* Footer */
        footer {
            background-color: var(--bg-secondary) !important;
            border-top: 1px solid var(--border-color);
        }

        footer a {
            color: var(--text-secondary) !important;
            transition: color 0.2s;
        }

        footer a:hover {
            color: var(--accent-blue) !important;
        }

        /* Text colors */
        .text-muted {
            color: var(--text-secondary) !important;
        }

        /* Pre/Code blocks */
        pre {
            background-color: var(--bg-tertiary) !important;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        /* Pagination */
        .pagination .page-link {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .pagination .page-link:hover {
            background-color: var(--bg-tertiary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route('http-monitor.index') }}">
                🔍 HTTP Monitor
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
                 <a class="nav-link {{ request()->routeIs('http-monitor.commands.*') ? 'active' : '' }}"
                    href="{{ route('http-monitor.commands.index') }}">
                    Commands
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
<footer class="mt-5">
    <div class="container py-4">
        <div class="row align-items-center">

            <!-- Left -->
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                    <span style="font-size: 1.5rem; margin-right: 0.5rem;">🔍</span>
                    <div>
                        <strong style="font-size: 1.1rem;">HTTP Monitor</strong><br>
                        <span class="small text-secondary">
                            © {{ now()->year }} Created by Hishu
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right -->
            <div class="col-md-6 text-center text-md-end">
                <a href="https://github.com/BurningYolo"
                   target="_blank"
                   class="text-decoration-none me-3 d-inline-flex align-items-center">
                    <svg width="20" height="20" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                        <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 16 8c0-4.42-3.58-8-8-8z"/>
                    </svg>
                    GitHub Profile
                </a>

                <a href="https://github.com/BurningYolo/laravel-http-monitor"
                   target="_blank"
                   class="text-decoration-none d-inline-flex align-items-center">
                    <svg width="18" height="18" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                        <path d="M2 2.5A2.5 2.5 0 0 1 4.5 0h8.75a.75.75 0 0 1 .75.75v12.5a.75.75 0 0 1-.75.75h-2.5a.75.75 0 0 1 0-1.5h1.75v-2h-8a1 1 0 0 0-.714 1.7.75.75 0 1 1-1.072 1.05A2.495 2.495 0 0 1 2 11.5v-9zm10.5-1V9h-8c-.356 0-.694.074-1 .208V2.5a1 1 0 0 1 1-1h8zM5 12.25v3.25a.25.25 0 0 0 .4.2l1.45-1.087a.25.25 0 0 1 .3 0L8.6 15.7a.25.25 0 0 0 .4-.2v-3.25a.25.25 0 0 0-.25-.25h-3.5a.25.25 0 0 0-.25.25z"/>
                    </svg>
                    Project Repo
                </a>
            </div>

        </div>
    </div>
</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>