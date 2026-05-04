<?php
use App\Models\FypProject;
use function Livewire\Volt\{state, computed};

state(['semester' => 'MARCH 2026', 'phase' => 'all']);

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

$updatedSemester = function () {
    $this->dispatch('charts-updated',
        domainLabels:   $this->domainStats->keys()->toArray(),
        domainValues:   $this->domainStats->values()->toArray(),
        platformLabels: $this->platformStats->keys()->toArray(),
        platformValues: $this->platformStats->values()->toArray(),
        ifypLabels:     $this->ifypStats->keys()->toArray(),
        ifypValues:     $this->ifypStats->values()->toArray(),
    );
};

$updatedPhase = function () {
    $this->dispatch('charts-updated',
        domainLabels:   $this->domainStats->keys()->toArray(),
        domainValues:   $this->domainStats->values()->toArray(),
        platformLabels: $this->platformStats->keys()->toArray(),
        platformValues: $this->platformStats->values()->toArray(),
        ifypLabels:     $this->ifypStats->keys()->toArray(),
        ifypValues:     $this->ifypStats->values()->toArray(),
    );
};
?>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endassets

<div class="flex flex-col gap-4">

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

</div>

@script
<script>
    console.log('analytics script loaded');

    let chartData = @js([
        'domainLabels'   => $this->domainStats->keys()->toArray(),
        'domainValues'   => $this->domainStats->values()->toArray(),
        'platformLabels' => $this->platformStats->keys()->toArray(),
        'platformValues' => $this->platformStats->values()->toArray(),
        'ifypLabels'     => $this->ifypStats->keys()->toArray(),
        'ifypValues'     => $this->ifypStats->values()->toArray(),
    ]);

    let domainChart = null, platformChart = null, ifypChart = null;

    function getChartData() {
        return chartData;
    }

    function buildCharts() {
        const domainEl   = document.getElementById('domainChart');
        const platformEl = document.getElementById('platformChart');
        const ifypEl     = document.getElementById('ifypChart');

        if (!domainEl || !platformEl || !ifypEl) return;

        if (domainChart)   { domainChart.destroy();   domainChart   = null; }
        if (platformChart) { platformChart.destroy(); platformChart = null; }
        if (ifypChart)     { ifypChart.destroy();     ifypChart     = null; }

        const d = getChartData();

        console.log('creating domain chart');
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

        console.log('creating platform chart');
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

        console.log('creating ifyp chart');
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

    }

    function tryBuildCharts(attempt = 1) {
        try {
            console.log('charts init attempt', attempt);
            console.log('Chart available:', typeof Chart);

            const domainEl   = document.getElementById('domainChart');
            const platformEl = document.getElementById('platformChart');
            const ifypEl     = document.getElementById('ifypChart');

            console.log('domainChart canvas:', domainEl);
            console.log('platformChart canvas:', platformEl);
            console.log('ifypChart canvas:', ifypEl);

            if (!domainEl || !platformEl || !ifypEl) {
                console.log('canvas missing — retry in 50ms');
                if (attempt < 3) setTimeout(() => tryBuildCharts(attempt + 1), 50);
                return;
            }

            console.log('canvas found');
            buildCharts();
            console.log('charts rendered successfully');
        } catch (e) {
            console.error('tryBuildCharts error (attempt ' + attempt + '):', e);
        }
    }

    tryBuildCharts();

    $wire.$on('charts-updated', (fresh) => {
        chartData = fresh;
        tryBuildCharts();
    });
</script>
@endscript
