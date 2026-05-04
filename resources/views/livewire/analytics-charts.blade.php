<?php
use App\Models\FypProject;
use function Livewire\Volt\{computed};

$domainStats = computed(function () {
    return FypProject::where('semester', 'MARCH 2026')
        ->whereNotNull('domain')
        ->where('domain', '!=', '')
        ->get()
        ->groupBy('domain')
        ->map(fn($group) => $group->count())
        ->sortDesc();
});

$platformStats = computed(function () {
    return FypProject::where('semester', 'MARCH 2026')
        ->whereNotNull('application_type')
        ->where('application_type', '!=', '')
        ->get()
        ->groupBy('application_type')
        ->map(fn($group) => $group->count())
        ->sortDesc();
});

$ifypStats = computed(function () {
    $all = FypProject::where('semester', 'MARCH 2026')->get();
    return collect([
        'Industrial (IFYP)' => $all->where('is_ifyp', true)->count(),
        'Regular'           => $all->where('is_ifyp', false)->count(),
    ]);
});
?>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endassets

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">

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

@script
<script>
    function initCharts() {
        ['domainChart', 'platformChart', 'ifypChart'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                const existing = Chart.getChart(el);
                if (existing) existing.destroy();
            }
        });

        new Chart(document.getElementById('domainChart'), {
            type: 'bar',
            data: {
                labels: @js($this->domainStats->keys()),
                datasets: [{
                    label: 'Projects',
                    data: @js($this->domainStats->values()),
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

        new Chart(document.getElementById('platformChart'), {
            type: 'doughnut',
            data: {
                labels: @js($this->platformStats->keys()),
                datasets: [{
                    data: @js($this->platformStats->values()),
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

        new Chart(document.getElementById('ifypChart'), {
            type: 'doughnut',
            data: {
                labels: @js($this->ifypStats->keys()),
                datasets: [{
                    data: @js($this->ifypStats->values()),
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCharts);
    } else {
        initCharts();
    }

    document.addEventListener('livewire:navigated', initCharts);
</script>
@endscript
