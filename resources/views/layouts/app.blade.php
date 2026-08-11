<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="themeManager()"
    x-init="init()"
    :class="isDark ? 'dark' : ''"
>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Eva Agent Desktop') }}</title>
    <link rel="icon" href="{{ asset('icon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- D3.js + Chart.js -->
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    {{-- Prevent flash of wrong theme before Alpine boots --}}
    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (!stored && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/css/dashboard.css', 'resources/js/app.js', 'resources/js/dashboard.js'])
    @livewireStyles
</head>
<body class="antialiased bg-gray-950 dark:bg-gray-950 text-gray-100">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <x-sidebar />

        <!-- Main Content -->
        {{-- chat needs full height with no scroll/padding; other pages keep p-6 --}}
        <main @class(['flex-1 overflow-hidden' => request()->routeIs('chat'), 'flex-1 overflow-y-auto p-6' => !request()->routeIs('chat')])>
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
    <script>
        // Use 127.0.0.1 to avoid localhost vs 127.0.0.1 CORS mismatch in dev
        window.KERNEL_API_BASE = window.KERNEL_API_BASE || 'http://127.0.0.1:8779';

        // Belt-and-suspenders: also trigger init on Livewire's navigate event
        document.addEventListener('livewire:navigated', function() {
            if (typeof window.initCurrentPage === 'function') window.initCurrentPage();
        });

        function themeManager() {
            return {
                isDark: false,
                init() {
                    const stored = localStorage.getItem('theme');
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    this.isDark = stored === 'dark' || (!stored && prefersDark);
                    // Follow system when no override is set
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                        if (!localStorage.getItem('theme')) this.isDark = e.matches;
                    });
                    // Listen for sidebar theme buttons
                    window.addEventListener('theme:set', e => {
                        localStorage.setItem('theme', e.detail);
                        this.isDark = e.detail === 'dark';
                    });
                    window.addEventListener('theme:reset', () => {
                        localStorage.removeItem('theme');
                        this.isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    });
                },
                toggle() {
                    this.isDark = !this.isDark;
                    localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
                },
            };
        }
    </script>
</body>
</html>
