<x-layouts::app :title="__('Dashboard')">
@php
    $currentSemester  = 'MARCH 2026';
    $previousSemester = 'OCTOBER 2025';

    $currentTotal  = \App\Models\FypProject::where('semester', $currentSemester)->count();
    $previousTotal = \App\Models\FypProject::where('semester', $previousSemester)->count();

    $currentFyp1  = \App\Models\FypProject::where('semester', $currentSemester)->where('fyp_phase', 'FYP 1')->count();
    $previousFyp1 = \App\Models\FypProject::where('semester', $previousSemester)->where('fyp_phase', 'FYP 1')->count();

    $currentFyp2  = \App\Models\FypProject::where('semester', $currentSemester)->where('fyp_phase', 'FYP 2')->count();
    $previousFyp2 = \App\Models\FypProject::where('semester', $previousSemester)->where('fyp_phase', 'FYP 2')->count();

    $user      = \Illuminate\Support\Facades\Auth::user();
    $roleLabel = ucfirst($user->role);
    $roleColor = match ($user->role) {
        'coordinator' => 'bg-violet-50 text-violet-700 ring-violet-200',
        'supervisor'  => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        default       => 'bg-sky-50 text-sky-700 ring-sky-200',
    };
@endphp

    <div class="flex flex-col gap-8">

        {{-- ── Page header ─────────────────────────────────────────────────── --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900">Dashboard</h1>
                <p class="mt-0.5 text-sm text-zinc-500">FYP Tracking — Bachelor of Software Engineering</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-indigo-200">
                    MARCH 2026 · Current
                </span>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $roleColor }}">
                    {{ $roleLabel }}
                </span>
            </div>
        </div>

        {{-- ── Enrollment stat cards ────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

            {{-- Total SE Students --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total SE Students</p>
                <div class="mt-3 flex items-end justify-between">
                    <div>
                        <p class="text-4xl font-bold text-gray-900">{{ $currentTotal }}</p>
                        <p class="mt-1 text-xs font-semibold text-indigo-600">MARCH 2026 · Current</p>
                    </div>
                    <div class="rounded-lg bg-indigo-50 p-2.5 text-indigo-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-2xl font-semibold text-gray-400">{{ $previousTotal }}</p>
                    <p class="text-xs text-gray-400">OCTOBER 2025 · Previous</p>
                </div>
            </div>

            {{-- FYP 1 --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">FYP 1</p>
                <div class="mt-3 flex items-end justify-between">
                    <div>
                        <p class="text-4xl font-bold text-gray-900">{{ $currentFyp1 }}</p>
                        <p class="mt-1 text-xs font-semibold text-blue-600">MARCH 2026 · Current</p>
                    </div>
                    <div class="rounded-lg bg-blue-50 p-2.5 text-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-2xl font-semibold text-gray-400">{{ $previousFyp1 }}</p>
                    <p class="text-xs text-gray-400">OCTOBER 2025 · Previous</p>
                </div>
            </div>

            {{-- FYP 2 --}}
            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">FYP 2</p>
                <div class="mt-3 flex items-end justify-between">
                    <div>
                        <p class="text-4xl font-bold text-gray-900">{{ $currentFyp2 }}</p>
                        <p class="mt-1 text-xs font-semibold text-orange-600">MARCH 2026 · Current</p>
                    </div>
                    <div class="rounded-lg bg-orange-50 p-2.5 text-orange-500">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.627 48.627 0 0 1 12 20.904a48.627 48.627 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.57 50.57 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.902 59.902 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                        </svg>
                    </div>
                </div>
                <div class="mt-4 border-t border-gray-100 pt-3">
                    <p class="text-2xl font-semibold text-gray-400">{{ $previousFyp2 }}</p>
                    <p class="text-xs text-gray-400">OCTOBER 2025 · Previous</p>
                </div>
            </div>

        </div>

        {{-- ── Supervisor panel (visible only to supervisors) ─────────────── --}}
        @if ($user->hasRole('supervisor'))
            <livewire:supervisor-overview />
        @endif

        {{-- ── Analytics section ────────────────────────────────────────────── --}}
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Analytics</h2>
                <div class="flex-1 border-t border-zinc-100"></div>
            </div>

            <livewire:analytics-charts />
        </div>

    </div>
</x-layouts::app>
