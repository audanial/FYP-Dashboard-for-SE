<?php

use App\Models\FypProject;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\computed;

$enrichedProjects = computed(function () {
    $projects = FypProject::query()
        ->forSupervisor(Auth::id(), Auth::user()->name)
        ->orderBy('student_name')
        ->get();

    $studentIds = $projects->pluck('student_id')->all();

    $students = User::query()
        ->whereIn('username', $studentIds)
        ->where('role', 'student')
        ->get()
        ->keyBy('username');

    $userIds = $students->pluck('id')->all();

    $stats = collect();

    if (! empty($userIds)) {
        $stats = Logbook::query()
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, MAX(date) as last_date, COUNT(*) as entry_count')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');
    }

    $threshold = now()->startOfDay()->subDays(14);

    return $projects->map(function ($project) use ($students, $stats, $threshold) {
        $student = $students->get($project->student_id);
        $stat    = $student ? $stats->get($student->id) : null;

        $lastDate = $stat ? Carbon::parse($stat->last_date) : null;

        $needsAttention = ! $student
            || $stat === null
            || $lastDate->lt($threshold);

        return [
            'project'        => $project,
            'lastDate'       => $lastDate,
            'needsAttention' => $needsAttention,
        ];
    });
});

?>

<div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-semibold text-zinc-900">My Assigned Students</h2>
            <p class="mt-0.5 text-sm text-zinc-500">
                {{ $this->enrichedProjects->count() }} Assigned
                · {{ $this->enrichedProjects->where('needsAttention', true)->count() }} needing attention
            </p>
        </div>
        <flux:button variant="ghost" icon="users" :href="route('supervisor.students')" wire:navigate>
            View All
        </flux:button>
    </div>

    <div class="space-y-3">
        @forelse ($this->enrichedProjects as $row)
            <div class="flex items-start justify-between rounded-2xl border border-zinc-200 bg-zinc-50 px-5 py-4">
                <div>
                    <p class="text-sm font-semibold text-zinc-900">{{ $row['project']->student_name }}</p>
                    <p class="mt-0.5 text-sm text-zinc-500">{{ $row['project']->title }}</p>
                    <p class="mt-1 text-xs text-zinc-400">
                        Last entry:
                        {{ $row['lastDate'] ? $row['lastDate']->format('d M Y') : 'No entries' }}
                    </p>
                </div>

                @if ($row['needsAttention'])
                    <span class="ml-4 shrink-0 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                        Needs Attention
                    </span>
                @endif
            </div>
        @empty
            <p class="text-sm text-zinc-500">No students are currently assigned to you.</p>
        @endforelse
    </div>
</div>
