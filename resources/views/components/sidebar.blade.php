<div
    x-data="{
        collapsed: JSON.parse(localStorage.getItem('sidebar.collapsed') || 'false'),
        mobileOpen: false,
        isMobile() { return window.innerWidth < 768; }
    }"
    x-init="
        $watch('collapsed', v => localStorage.setItem('sidebar.collapsed', v));
        window.addEventListener('resize', () => { if (!isMobile()) mobileOpen = false; });
    "
    @keydown.escape.window="mobileOpen = false"
class="">
    {{-- Mobile hamburger (fixed top-left, hidden on desktop) --}}
    <button
        @click="mobileOpen = true"
        class="md:hidden fixed top-3 left-3 z-40 p-2 rounded-lg bg-gray-900 border border-gray-700 text-gray-400 hover:text-gray-100"
        aria-label="Open menu"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>
        </svg>
    </button>

    {{-- Mobile backdrop --}}
    <div
        x-show="mobileOpen"
        x-transition.opacity
        @click="mobileOpen = false"
        class="md:hidden fixed inset-0 bg-black/50 z-40"
        style="display:none;"
    ></div>

    {{-- Sidebar nav --}}
    <nav
        :class="{
            'translate-x-0': mobileOpen,
            '-translate-x-full': !mobileOpen,
            'w-64': !collapsed,
            'md:w-16': collapsed,
        }"
        class="fixed md:relative inset-y-0 left-0 z-50 md:z-auto
               flex flex-col shrink-0
               bg-gray-900 border-r border-gray-800
               w-64 transition-all duration-200
               -translate-x-full md:translate-x-0"
    >
        {{-- Header: logo + collapse toggle --}}
        <div class="flex items-center justify-between gap-2 p-3 border-b border-gray-800 min-h-[56px]">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0" wire:navigate>
                <img src="{{ asset('icon.png') }}" alt="Logo" class="w-7 h-7 shrink-0">
                <span
                    x-show="!collapsed"
                    x-transition:enter="transition-opacity duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="text-sm font-semibold text-gray-100 truncate"
                >{{ config('app.name', 'EvAgent') }}</span>
            </a>

            {{-- Desktop collapse toggle --}}
            <button
                @click="collapsed = !collapsed"
                class="hidden md:inline-flex shrink-0 p-1.5 rounded-md text-gray-400 hover:text-gray-100 hover:bg-gray-800 transition-colors"
                :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            >
                <svg class="w-5 h-5 transition-transform duration-200" :class="collapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                </svg>
            </button>

            {{-- Mobile close button --}}
            <button
                @click="mobileOpen = false"
                class="md:hidden shrink-0 p-1.5 rounded-md text-gray-400 hover:text-gray-100 hover:bg-gray-800"
                aria-label="Close menu"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Navigation links --}}
        <div class="flex-1 py-2 overflow-y-auto overflow-x-hidden">
            @php
                $tabs = [
                    ['route' => 'chat',         'label' => 'Agent',             'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
                    ['route' => 'dashboard',    'label' => 'Evolution',         'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    ['route' => 'activity',     'label' => 'Activity',          'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
                    ['route' => 'insights',     'label' => 'Insights',          'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z'],
                    ['route' => 'skills',       'label' => 'Skills & Routines', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
                    ['route' => 'replicas',     'label' => 'Replicas',          'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                    ['route' => 'trajectories', 'label' => 'Trajectories',      'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    ['route' => 'memory',       'label' => 'Memory',            'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['route' => 'system',       'label' => 'System',            'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
                    ['route' => 'voice',        'label' => 'Voice',             'icon' => 'M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z'],
                    ['route' => 'settings',     'label' => 'Settings',          'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
                ];
            @endphp

            @foreach ($tabs as $tab)
                <a
                    href="{{ route($tab['route']) }}"
                    @class([
                        'flex items-center gap-3 mx-2 px-2 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        'bg-emerald-600/20 text-emerald-400'                  => request()->routeIs($tab['route']),
                        'text-gray-400 hover:text-gray-200 hover:bg-gray-800' => !request()->routeIs($tab['route']),
                    ])
                    wire:navigate
                    :title="collapsed ? '{{ $tab['label'] }}' : ''"
                >
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/>
                    </svg>
                    <span
                        x-show="!collapsed"
                        x-transition:enter="transition-opacity duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        class="truncate"
                    >{{ $tab['label'] }}</span>
                </a>
            @endforeach
        </div>

        {{-- Footer: theme toggle + GitHub + Setup Wizard --}}
        <div class="p-2 border-t border-gray-800 flex flex-col gap-1">

            {{-- GitHub repo picker prototype --}}
            <div x-data="{
                    open: false,
                    search: '',
                    selected: [],
                    repos: [
                        { id:1, name:'kernel-desktop-v1',      org:'fabiopacificicom', private:true,  lang:'PHP'        },
                        { id:2, name:'ai-providers-for-laravel',org:'fabiopacificicom', private:false, lang:'PHP'        },
                        { id:3, name:'eva-agent-ui',            org:'fabiopacificicom', private:true,  lang:'JavaScript' },
                        { id:4, name:'kernel-central',          org:'fabiopacificicom', private:true,  lang:'PHP'        },
                        { id:5, name:'livewire-components',     org:'fabiopacificicom', private:false, lang:'PHP'        },
                        { id:6, name:'desktop-native-shell',    org:'fabiopacificicom', private:true,  lang:'JavaScript' },
                    ],
                    get filtered() {
                        return this.repos.filter(r =>
                            r.name.toLowerCase().includes(this.search.toLowerCase()) ||
                            r.org.toLowerCase().includes(this.search.toLowerCase())
                        );
                    },
                    toggle(id) {
                        this.selected.includes(id)
                            ? this.selected = this.selected.filter(s => s !== id)
                            : this.selected.push(id);
                    }
                }"
                @keydown.escape.window="open = false"
                class="relative"
            >
                <button
                    @click="open = !open"
                    class="flex items-center gap-3 w-full px-2 py-2.5 rounded-lg text-sm font-medium text-gray-400 hover:text-gray-200 hover:bg-gray-800 transition-colors"
                    :title="collapsed ? 'GitHub Repositories' : ''"
                >
                    {{-- GitHub mark SVG --}}
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.385-1.335-1.755-1.335-1.755-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                    </svg>
                    <span x-show="!collapsed" class="flex-1 text-left truncate">GitHub Repos</span>
                    <span
                        x-show="!collapsed && selected.length > 0"
                        class="text-xs bg-emerald-700/60 text-emerald-300 rounded-full px-1.5 py-0.5 leading-none"
                        x-text="selected.length"
                    ></span>
                </button>

                {{-- Dropdown panel --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click.outside="open = false"
                    class="absolute bottom-full left-0 mb-2 w-72 bg-gray-900 border border-gray-700 rounded-xl shadow-2xl z-50 flex flex-col overflow-hidden"
                    style="display:none;"
                >
                    <div class="flex items-center justify-between gap-2 px-3 py-2.5 border-b border-gray-700">
                        <span class="text-xs font-semibold text-gray-300 uppercase tracking-wide">Link Repositories</span>
                        <button @click="open = false" class="text-gray-500 hover:text-gray-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="px-3 py-2 border-b border-gray-700">
                        <input
                            x-model="search"
                            type="search"
                            placeholder="Search repos…"
                            class="w-full text-xs bg-gray-800 border border-gray-600 rounded-lg px-3 py-1.5 text-gray-200 placeholder-gray-500 outline-none focus:border-indigo-500"
                        >
                    </div>
                    <ul class="overflow-y-auto max-h-56 divide-y divide-gray-800">
                        <template x-for="repo in filtered" :key="repo.id">
                            <li>
                                <button
                                    @click="toggle(repo.id)"
                                    class="flex items-center gap-3 w-full px-3 py-2.5 text-left hover:bg-gray-800 transition-colors"
                                    :class="selected.includes(repo.id) ? 'bg-indigo-900/30' : ''"
                                >
                                    <svg class="w-4 h-4 shrink-0" :class="selected.includes(repo.id) ? 'text-indigo-400' : 'text-gray-500'" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.385-1.335-1.755-1.335-1.755-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                                    </svg>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-medium text-gray-200 truncate" x-text="repo.name"></div>
                                        <div class="text-xs text-gray-500 truncate" x-text="repo.org + ' · ' + repo.lang"></div>
                                    </div>
                                    <span
                                        x-show="repo.private"
                                        class="text-[10px] bg-gray-700 text-gray-400 rounded px-1 py-0.5 leading-none shrink-0"
                                    >private</span>
                                    <svg x-show="selected.includes(repo.id)" class="w-4 h-4 text-indigo-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                </button>
                            </li>
                        </template>
                        <li x-show="filtered.length === 0" class="px-3 py-4 text-xs text-gray-500 text-center">No repositories found</li>
                    </ul>
                    <div x-show="selected.length > 0" class="px-3 py-2 border-t border-gray-700 flex items-center justify-between gap-2">
                        <span class="text-xs text-gray-400" x-text="selected.length + ' repo(s) linked to session'"></span>
                        <button @click="selected = []" class="text-xs text-red-400 hover:text-red-300">Clear</button>
                    </div>
                </div>
            </div>

            {{-- Theme toggle --}}
            <div
                x-data="{
                    get mode() {
                        const s = localStorage.getItem('theme');
                        return s ? s : 'system';
                    }
                }"
                class="flex items-center gap-1 px-2 py-1.5 rounded-lg"
                :title="collapsed ? 'Theme' : ''"
            >
                <template x-if="!collapsed">
                    <span class="text-xs text-gray-500 mr-1 shrink-0">Theme</span>
                </template>

                {{-- System --}}
                <button
                    @click="window.dispatchEvent(new CustomEvent('theme:reset'))"
                    title="Use system theme"
                    class="p-1.5 rounded-md transition-colors hover:bg-gray-700"
                    :class="localStorage.getItem('theme') === null ? 'text-indigo-400 bg-gray-700' : 'text-gray-500'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3"/>
                    </svg>
                </button>

                {{-- Light --}}
                <button
                    @click="window.dispatchEvent(new CustomEvent('theme:set', {detail:'light'}))"
                    title="Light theme"
                    class="p-1.5 rounded-md transition-colors hover:bg-gray-700"
                    :class="localStorage.getItem('theme') === 'light' ? 'text-yellow-400 bg-gray-700' : 'text-gray-500'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                    </svg>
                </button>

                {{-- Dark --}}
                <button
                    @click="window.dispatchEvent(new CustomEvent('theme:set', {detail:'dark'}))"
                    title="Dark theme"
                    class="p-1.5 rounded-md transition-colors hover:bg-gray-700"
                    :class="localStorage.getItem('theme') === 'dark' ? 'text-blue-400 bg-gray-700' : 'text-gray-500'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                    </svg>
                </button>
            </div>

            {{-- Setup Wizard --}}
            <a
                href="{{ route('setup-wizard') }}"
                class="flex items-center gap-3 px-2 py-2.5 rounded-lg text-sm font-medium bg-indigo-600/20 text-indigo-400 hover:bg-indigo-600/30 transition-colors"
                wire:navigate
                :title="collapsed ? 'Setup Wizard' : ''"
            >
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                <span
                    x-show="!collapsed"
                    x-transition:enter="transition-opacity duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="truncate"
                >Setup Wizard</span>
            </a>
        </div>
    </nav>
</div>
