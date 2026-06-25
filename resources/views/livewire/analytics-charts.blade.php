<?php
use App\Models\FypProject;
use App\Models\Supervisor;
use function Livewire\Volt\{state, computed};

state(['semester' => 'MARCH 2026', 'phase' => 'all']);

/*
 * Effective supervisor key used for all supervisor-based grouping and counting
 * during the supervisor_id transition:
 *   A) supervisor_id set and the relation resolves -> linked user's name
 *      (canonical; collapses string duplicates like "Noor Widasuria" x2)
 *   B) else supervisor_name present                -> supervisor_name
 *   C) else                                        -> "Unlinked"
 * The relation is eager-loaded (with('supervisor')) wherever this is used to
 * avoid N+1 queries.
 */
$effectiveSupervisorKey = function (FypProject $project): string {
    if ($project->supervisor_id && $project->supervisor) {
        return $project->supervisor->name;
    }

    return $project->supervisor_name ?: 'Unlinked';
};

$domainStats = computed(function () {
    return FypProject::where('semester', $this->semester)
        ->get()
        ->filter(fn($p) => $this->phase === 'all' || $p->fyp_phase === $this->phase)
        ->groupBy(fn($p) => $p->domain ?: 'Others')
        ->map(fn($group) => $group->count())
        ->sortDesc()
        ->whenEmpty(fn() => collect([]));
});

$platformStats = computed(function () {
    return FypProject::where('semester', $this->semester)
        ->get()
        ->filter(fn($p) => $this->phase === 'all' || $p->fyp_phase === $this->phase)
        ->groupBy(fn($p) => $p->application_type ?: 'Unknown')
        ->map(fn($group) => $group->count())
        ->sortDesc()
        ->whenEmpty(fn() => collect([]));
});

$ifypStats = computed(function () {
    $all = FypProject::where('semester', $this->semester)
        ->get()
        ->filter(fn($p) => $this->phase === 'all' || $p->fyp_phase === $this->phase);
    return collect([
        'Industrial (IFYP)' => $all->filter(fn($p) => (bool) $p->is_ifyp)->count(),
        'Regular'           => $all->filter(fn($p) => ! (bool) $p->is_ifyp)->count(),
    ]);
});

$summaryStats = computed(function () use ($effectiveSupervisorKey) {
    $projects = FypProject::with('supervisor')
        ->where('semester', $this->semester)
        ->get()
        ->filter(fn($p) => $this->phase === 'all' || $p->fyp_phase === $this->phase);

    $students        = $projects->count();
    $pairs           = $projects->pluck('pair_number')->filter()->unique()->count();
    $industrial      = $projects->filter(fn($p) => (bool) $p->is_ifyp)->count();
    $supervisorCount = $projects->map($effectiveSupervisorKey)->unique()->count();

    return [
        'students'           => $students, // denominator for industrial_pct; not rendered directly
        'pairs'              => $pairs,
        'industrial'         => $industrial,
        'industrial_pct'     => $students > 0 ? round($industrial / $students * 100) : 0,
        'domains_covered'    => $projects->pluck('domain')->filter()->unique()->count(),
        'avg_per_supervisor' => $supervisorCount > 0 ? round($pairs / $supervisorCount, 1) : 0,
    ];
});

$supervisorWorkload = computed(function () use ($effectiveSupervisorKey) {
    $workload = FypProject::with('supervisor')
        ->where('semester', $this->semester)
        ->get()
        ->filter(fn($p) => $this->phase === 'all' || $p->fyp_phase === $this->phase)
        ->groupBy($effectiveSupervisorKey)
        ->map(fn($group) => $group->pluck('pair_number')->filter()->unique()->count());

    // Ensure every roster lecturer appears, even with no pairs this term, so the
    // chart shows the full department instead of only those currently supervising.
    // The roster's canonical name matches the linked user's name (set at seed),
    // so a supervising lecturer is not double-counted.
    foreach (Supervisor::orderBy('name')->pluck('name') as $rosterName) {
        if (! $workload->has($rosterName)) {
            $workload[$rosterName] = 0;
        }
    }

    return $workload->sortDesc();
});

$updatedSemester = function () {
    $this->dispatch('charts-updated',
        domainLabels:     $this->domainStats->keys()->toArray(),
        domainValues:     $this->domainStats->values()->toArray(),
        platformLabels:   $this->platformStats->keys()->toArray(),
        platformValues:   $this->platformStats->values()->toArray(),
        ifypLabels:       $this->ifypStats->keys()->toArray(),
        ifypValues:       $this->ifypStats->values()->toArray(),
        supervisorLabels: $this->supervisorWorkload->keys()->toArray(),
        supervisorValues: $this->supervisorWorkload->values()->toArray(),
    );
};

$updatedPhase = function () {
    $this->dispatch('charts-updated',
        domainLabels:     $this->domainStats->keys()->toArray(),
        domainValues:     $this->domainStats->values()->toArray(),
        platformLabels:   $this->platformStats->keys()->toArray(),
        platformValues:   $this->platformStats->values()->toArray(),
        ifypLabels:       $this->ifypStats->keys()->toArray(),
        ifypValues:       $this->ifypStats->values()->toArray(),
        supervisorLabels: $this->supervisorWorkload->keys()->toArray(),
        supervisorValues: $this->supervisorWorkload->values()->toArray(),
    );
};
?>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endassets

<div class="flex flex-col gap-4">

    {{-- Header row: title + filters --}}
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-bold text-gray-600">Analytics — {{ $semester }}</h3>
        <div class="flex items-center gap-2">
            <select wire:model.live="phase" class="rounded-md border-gray-300 shadow-sm text-sm !text-black focus:border-indigo-500">
                <option value="all">All Phases</option>
                <option value="FYP 1">FYP 1</option>
                <option value="FYP 2">FYP 2</option>
            </select>
            <select wire:model.live="semester" class="rounded-md border-gray-300 shadow-sm text-sm !text-black focus:border-indigo-500">
                <option value="MARCH 2026">March 2026 (Current)</option>
                <option value="OCTOBER 2025">October 2025 (Previous)</option>
            </select>
        </div>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

        <div class="bg-white rounded-xl border border-indigo-100 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Total Pairs</p>
            <p class="mt-2 text-3xl font-bold text-indigo-900">{{ $this->summaryStats['pairs'] }}</p>
            <p class="mt-1 text-xs text-gray-400">{{ $phase === 'all' ? 'all phases' : $phase }}</p>
        </div>

        <div class="bg-white rounded-xl border border-amber-100 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-500">Industrial (IFYP)</p>
            <p class="mt-2 text-3xl font-bold text-amber-900">{{ $this->summaryStats['industrial'] }}</p>
            <p class="mt-1 text-xs text-gray-400">{{ $this->summaryStats['industrial_pct'] }}% of cohort</p>
        </div>

        <div class="bg-white rounded-xl border border-emerald-100 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-500">Domains Covered</p>
            <p class="mt-2 text-3xl font-bold text-emerald-900">{{ $this->summaryStats['domains_covered'] }}</p>
            <p class="mt-1 text-xs text-gray-400">unique domains</p>
        </div>

        <div class="bg-white rounded-xl border border-violet-100 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-500">Avg Pairs / Supervisor</p>
            <p class="mt-2 text-3xl font-bold text-violet-900">{{ $this->summaryStats['avg_per_supervisor'] }}</p>
            <p class="mt-1 text-xs text-gray-400">pairs per supervisor</p>
        </div>

    </div>

    {{-- Existing three charts --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4" wire:key="charts-{{ $semester }}">

        <div class="bg-white rounded-xl border border-neutral-200 p-5 shadow-sm">
            <p class="text-sm font-bold text-gray-600 mb-3">Projects by Domain</p>
            <div class="relative h-64">
                <canvas id="domainChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-neutral-200 p-5 shadow-sm">
            <p class="text-sm font-bold text-gray-600 mb-3">Projects by Platform</p>
            <div class="relative h-64">
                <canvas id="platformChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-neutral-200 p-5 shadow-sm">
            <p class="text-sm font-bold text-gray-600 mb-3">Regular vs Industrial</p>
            <div class="relative h-64">
                <canvas id="ifypChart"></canvas>
            </div>
        </div>

    </div>

    {{-- Supervisor workload chart --}}
    <div class="bg-white rounded-xl border border-neutral-200 p-5 shadow-sm">
        <p class="text-sm font-bold text-gray-600 mb-3">Supervisor Workload Distribution</p>
        <div class="relative h-80">
            <canvas id="supervisorChart"></canvas>
        </div>
    </div>

</div>

@script
<script>
    let chartData = @js([
        'domainLabels'     => $this->domainStats->keys()->toArray(),
        'domainValues'     => $this->domainStats->values()->toArray(),
        'platformLabels'   => $this->platformStats->keys()->toArray(),
        'platformValues'   => $this->platformStats->values()->toArray(),
        'ifypLabels'       => $this->ifypStats->keys()->toArray(),
        'ifypValues'       => $this->ifypStats->values()->toArray(),
        'supervisorLabels' => $this->supervisorWorkload->keys()->toArray(),
        'supervisorValues' => $this->supervisorWorkload->values()->toArray(),
    ]);

    let domainChart = null, platformChart = null, ifypChart = null, supervisorChart = null;

    function getChartData() {
        return chartData;
    }

    function buildCharts() {
        const domainEl     = document.getElementById('domainChart');
        const platformEl   = document.getElementById('platformChart');
        const ifypEl       = document.getElementById('ifypChart');
        const supervisorEl = document.getElementById('supervisorChart');

        if (!domainEl || !platformEl || !ifypEl || !supervisorEl) return;

        if (domainChart)     { domainChart.destroy();     domainChart     = null; }
        if (platformChart)   { platformChart.destroy();   platformChart   = null; }
        if (ifypChart)       { ifypChart.destroy();       ifypChart       = null; }
        if (supervisorChart) { supervisorChart.destroy(); supervisorChart = null; }

        const d = getChartData();

        domainChart = new Chart(domainEl, {
            type: 'bar',
            data: {
                labels: d.domainLabels,
                datasets: [{
                    label: 'Projects',
                    data: d.domainValues,
                    backgroundColor: 'rgba(99, 102, 241, 0.75)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });

        platformChart = new Chart(platformEl, {
            type: 'doughnut',
            data: {
                labels: d.platformLabels,
                datasets: [{
                    data: d.platformValues,
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(107, 114, 128, 0.8)',
                    ],
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });

        ifypChart = new Chart(ifypEl, {
            type: 'doughnut',
            data: {
                labels: d.ifypLabels,
                datasets: [{
                    data: d.ifypValues,
                    backgroundColor: [
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(99, 102, 241, 0.8)',
                    ],
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
                }
            }
        });

        supervisorChart = new Chart(supervisorEl, {
            type: 'bar',
            data: {
                labels: d.supervisorLabels,
                datasets: [{
                    label: 'Pairs',
                    data: d.supervisorValues,
                    backgroundColor: 'rgba(99, 102, 241, 0.75)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    function tryBuildCharts(attempt = 1) {
        try {
            const domainEl     = document.getElementById('domainChart');
            const platformEl   = document.getElementById('platformChart');
            const ifypEl       = document.getElementById('ifypChart');
            const supervisorEl = document.getElementById('supervisorChart');

            if (!domainEl || !platformEl || !ifypEl || !supervisorEl) {
                if (attempt < 3) setTimeout(() => tryBuildCharts(attempt + 1), 50);
                return;
            }

            buildCharts();
        } catch (e) {
            // chart init failed silently
        }
    }

    tryBuildCharts();

    $wire.$on('charts-updated', (fresh) => {
        chartData = fresh;
        tryBuildCharts();
    });
</script>
@endscript
