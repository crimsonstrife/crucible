@php
    /** Defaults (override when including) */
    $pageTitle = $title ?? config('app.name', 'Crucible');
    /** @var array<int, string> $viteEntries */
    $viteEntries = $viteEntries ?? ['resources/css/app.css', 'resources/js/app.js'];
@endphp

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $pageTitle }}</title>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

{{-- THEME: initial paint without flash --}}
<script>
    (() => {
        try {
            const ls = localStorage;
            const getPref = () => ls.getItem('theme') || 'auto';
            const prefersDark = () => matchMedia('(prefers-color-scheme: dark)').matches;
            const resolve = (pref = getPref()) => pref === 'auto' ? (prefersDark() ? 'dark' : 'light') : pref;

            const apply = (theme) => {
                const root = document.documentElement;
                root.setAttribute('data-bs-theme', theme);
                root.classList.toggle('dark', theme === 'dark');

                let meta = document.querySelector('meta[name="theme-color"]');
                if (!meta) { meta = document.createElement('meta'); meta.name = 'theme-color'; document.head.appendChild(meta); }
                meta.content = theme === 'dark' ? '#212529' : '#ffffff';

                window.__isDarkTheme = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
                window.dispatchEvent(new CustomEvent('theme:changed', { detail: { theme } }));
            };

            window.__setTheme = (pref) => { ls.setItem('theme', pref); apply(resolve(pref)); };

            apply(resolve());

            matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (getPref() === 'auto') apply(resolve('auto'));
            });
        } catch (e) { /* no-op */ }
    })();
</script>

@vite($viteEntries)
@livewireStyles

@stack('meta')
@stack('styles')
