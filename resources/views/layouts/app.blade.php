<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PFE Admin - @yield('title', 'Tableau de bord')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/ensah-logo.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="{{ asset('css/app-theme.css') }}" rel="stylesheet">
    @stack('styles')
</head>

<body>
    @php
        $academicStart = now()->month >= 9 ? now()->year : now()->year - 1;
        $academicYear = $academicStart . '/' . ($academicStart + 1);
    @endphp

    <div class="app-shell">
        <aside class="sidebar">
            @php
                $siteName = \App\Models\Configuration::get('site_name', 'PFE.Admin');
                $siteSubtitle = \App\Models\Configuration::get('site_subtitle', 'ENSAH');
                $siteLogo = \App\Models\Configuration::logoUrl() ?: asset('images/ensah-logo.png');
            @endphp
            <div class="sidebar-header">
                <div class="brand-logo">
                    <img src="{{ $siteLogo }}" alt="Logo">
                </div>
                <div>
                    <div class="brand-title">{{ $siteName }}</div>
                    <span class="brand-subtitle">{{ $siteSubtitle }}</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-label">ACCUEIL</div>
                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-clipboard-data nav-icon"></i>
                    <span>Tableau de bord</span>
                </a>

                <div class="nav-label">SOUTENANCE</div>
                <a href="{{ route('import.form') }}" class="nav-item {{ request()->routeIs('import.*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-excel nav-icon"></i>
                    <span>Importation Excel</span>
                </a>
                <a href="{{ route('affectation.index') }}" class="nav-item {{ request()->routeIs('affectation.*') ? 'active' : '' }}">
                    <i class="bi bi-people nav-icon"></i>
                    <span>Affectation Encadrants</span>
                </a>
                <a href="{{ route('planning.results') }}" class="nav-item {{ request()->routeIs('planning.*') ? 'active' : '' }}">
                    <i class="bi bi-calendar-week nav-icon"></i>
                    <span>Planning Soutenances</span>
                </a>
                <a href="{{ route('conformite.index') }}" class="nav-item {{ request()->routeIs('conformite.*') ? 'active' : '' }}">
                    <i class="bi bi-shield-check nav-icon"></i>
                    <span>Contrôle de Conformité</span>
                </a>
                <a href="{{ route('verification.index') }}" class="nav-item {{ request()->routeIs('verification.*') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2 nav-icon"></i>
                    <span>Audit des Contraintes</span>
                </a>

                <div class="nav-label">DOCUMENTS</div>
                <a href="{{ route('pv.index') }}" class="nav-item {{ request()->routeIs('pv.*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-word nav-icon"></i>
                    <span>Génération des PVs</span>
                </a>

                <div class="nav-label">CONFIGURATION</div>
                <a href="{{ route('settings.index') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear nav-icon"></i>
                    <span>Paramètres</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                Année Universitaire {{ $academicYear }}
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <h1 class="topbar-title">@yield('page-title', $__env->yieldContent('title', 'Tableau de bord'))</h1>
            </header>

            <section class="content">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if (session('info'))
                    <div class="alert alert-info">{{ session('info') }}</div>
                @endif

                @yield('content')
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.openPlanningModal = function () {
            const modal = document.getElementById('planningModal');
            if (modal) modal.classList.add('is-open');
        };

        window.closePlanningModal = function () {
            const modal = document.getElementById('planningModal');
            if (modal) modal.classList.remove('is-open');
        };

        document.addEventListener('click', function (event) {
            if (event.target?.classList?.contains('app-modal-overlay')) {
                event.target.classList.remove('is-open');
            }
        });
    </script>
    @stack('scripts')
</body>

</html>
