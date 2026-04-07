<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', [
        'title' => config('app.name', 'Crucible') . ' - ' . ($pageTitle ?? 'Repository Manager'),
        'viteEntries' => ['resources/css/app.css', 'resources/js/app.js'],
    ])
</head>
<body>

<div class="min-vh-100 d-flex flex-column bg-body">
    <nav class="navbar navbar-expand-lg bg-body border-bottom sticky-top" style="z-index:1025;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
                <i class="fas fa-fire-flame-curved"></i>
                {{ config('app.name', 'Crucible') }}
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain"
                    aria-controls="navbarMain" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                           href="{{ route('dashboard') }}">
                            <i class="fas fa-house me-1"></i>{{ __('Dashboard') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('organizations.*') ? 'active' : '' }}"
                           href="{{ route('organizations.index') }}">
                            <i class="fas fa-building me-1"></i>{{ __('Organizations') }}
                        </a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-2">
                    @auth
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2"
                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle"></i>
                                <span>{{ Auth::user()->name }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" style="min-width:12rem;">
                                <li><span class="dropdown-header">{{ __('Manage Account') }}</span></li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('profile.show') }}">
                                        <i class="fas fa-id-card me-2 text-body-secondary"></i>{{ __('Profile') }}
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('ssh-keys.index') }}">
                                        <i class="fas fa-key me-2 text-body-secondary"></i>{{ __('SSH Keys') }}
                                    </a>
                                </li>
                                @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                    <li>
                                        <a class="dropdown-item" href="{{ route('api-tokens.index') }}">
                                            <i class="fas fa-code me-2 text-body-secondary"></i>{{ __('API Tokens') }}
                                        </a>
                                    </li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="fas fa-sign-out-alt me-2"></i>{{ __('Sign Out') }}
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('login') }}">{{ __('Sign In') }}</a>
                        @if (Route::has('register'))
                            <a class="btn btn-sm btn-primary" href="{{ route('register') }}">{{ __('Register') }}</a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    @if (session('error'))
        <div class="container-fluid mt-3">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-circle-exclamation me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="container-fluid mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-circle-check me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    @endif

    @isset($header)
        <div class="bg-body border-bottom py-3 mb-4">
            <div class="container-fluid">{{ $header }}</div>
        </div>
    @endisset

    <main class="flex-grow-1 py-4">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    @include('layouts.partials.footer')
</div>

@stack('modals')
@livewireScripts
@stack('scripts')
</body>
</html>
