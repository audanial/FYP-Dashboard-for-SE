<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <script>
        window.Flux.applyAppearance('light');
    </script>
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
@auth
    @php($dashboardRoute = route(auth()->user()->dashboardRouteName()))
    @php($userRole = auth()->user()->role)

    <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white/95 backdrop-blur lg:w-72">
        <flux:sidebar.header class="border-b border-zinc-200 px-4 py-4">
            <x-app-logo :sidebar="true" href="{{ $dashboardRoute }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav class="px-3 py-4">
            <flux:sidebar.group :heading="__('Navigation')" class="grid gap-1 text-zinc-500">
                <flux:sidebar.item
                    icon="layout-grid"
                    :href="$dashboardRoute"
                    :current="request()->routeIs('dashboard', 'admin.dashboard', 'supervisor.dashboard')"
                    wire:navigate
                >
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                @if ($userRole === 'coordinator')
                    <flux:sidebar.item
                        icon="users"
                        :href="route('admin.users')"
                        :current="request()->routeIs('admin.users')"
                        wire:navigate
                    >
                        {{ __('Manage Users') }}
                    </flux:sidebar.item>
                @elseif ($userRole === 'supervisor')
                    <flux:sidebar.item
                        icon="user-group"
                        :href="route('supervisor.students')"
                        :current="request()->routeIs('supervisor.students')"
                        wire:navigate
                    >
                        {{ __('My Students') }}
                    </flux:sidebar.item>
                @else
                    <flux:sidebar.item
                        icon="book-open-text"
                        :href="route('student.logbook')"
                        :current="request()->routeIs('student.logbook')"
                        wire:navigate
                    >
                        {{ __('My Logbook') }}
                    </flux:sidebar.item>
                @endif
            </flux:sidebar.group>
        </flux:sidebar.nav>
    </flux:sidebar>

    <flux:header class="border-b border-zinc-200 bg-white/95 backdrop-blur lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <x-desktop-user-menu />
    </flux:header>
@endauth

{{ $slot }}

@fluxScripts
</body>
</html>
