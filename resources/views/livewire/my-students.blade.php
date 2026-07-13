<?php

use App\Models\FypProject;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{computed, layout, state, updated, usesPagination};

layout('layouts.app');

usesPagination(theme: 'tailwind');

state([
    'search' => '',
]);

updated([
    'search' => fn () => $this->resetPage(),
]);

$projects = computed(function () {
    return FypProject::query()
        ->forSupervisor(Auth::id(), Auth::user()->name)
        ->where(function ($query) {
            $query
                ->whereLike('student_name', '%'.$this->search.'%')
                ->orWhereLike('student_id', '%'.$this->search.'%');
        })
        ->orderBy('student_name')
        ->paginate(10);
});

?>

<section class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-semibold text-zinc-900">My Students</h1>
            <p class="mt-1 text-sm text-zinc-500">Students assigned to you as supervisor.</p>
        </div>

        <flux:button variant="ghost" icon="arrow-left" :href="route('dashboard')" wire:navigate>
            Back to Dashboard
        </flux:button>
    </div>

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-zinc-900">Assigned Projects</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $this->projects->total() }} project{{ $this->projects->total() === 1 ? '' : 's' }}</p>
            </div>

            <div class="w-full lg:max-w-md">
                <label for="student-search" class="mb-2 block text-sm font-medium text-zinc-700">Search Students</label>
                <div class="relative">
                    <input
                        id="student-search"
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Search by student name or ID"
                        class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 pr-11 text-sm text-zinc-700 shadow-xs transition focus:border-zinc-400 focus:outline-none"
                    >

                    @if ($search !== '')
                        <button
                            type="button"
                            wire:click="$set('search', '')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-medium text-zinc-400 transition hover:text-zinc-600"
                        >
                            Clear
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-zinc-200">
            <table class="min-w-full divide-y divide-zinc-200">
                <thead class="bg-zinc-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">Student Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">Student ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">Domain</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">App Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">FYP Phase</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white">
                    @forelse ($this->projects as $project)
                        <tr class="transition hover:bg-zinc-50">
                            <td class="px-6 py-5 text-sm font-medium text-zinc-800">{{ $project->student_name }}</td>
                            <td class="px-6 py-5 text-sm font-semibold text-zinc-600">{{ $project->student_id }}</td>
                            <td class="px-6 py-5 text-sm text-zinc-700">{{ $project->title }}</td>
                            <td class="px-6 py-5 text-sm text-zinc-700">
                                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">
                                    {{ $project->domain }}
                                </span>
                            </td>
                            <td class="px-6 py-5 text-sm text-zinc-700">{{ $project->app_type ?: $project->application_type }}</td>
                            <td class="px-6 py-5 text-sm text-zinc-700">{{ $project->fyp_phase }}</td>
                            <td class="px-6 py-5 text-sm">
                                <a
                                    href="{{ route('supervisor.students.logbook', ['studentId' => $project->student_id]) }}"
                                    class="text-sm font-medium text-blue-600 hover:underline"
                                >
                                    View Logbook
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-zinc-500">
                                {{ $search !== '' ? 'No students match your search.' : 'No students are currently assigned to you.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->projects->hasPages())
            <div class="mt-5">
                {{ $this->projects->links() }}
            </div>
        @endif
    </div>
</section>
