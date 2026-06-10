<?php

use App\Models\FypProject;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use function Livewire\Volt\layout;

layout('layouts.app');

new #[Title('Student Logbook')] class extends Component {
    public FypProject $project;

    public ?User $student = null;

    public function mount(string $studentId): void
    {
        $supervisorId = Auth::id();
        $supervisorName = Auth::user()->name;

        $this->project = FypProject::query()
            ->where('student_id', $studentId)
            ->where(function ($query) use ($supervisorId, $supervisorName) {
                // Dual-read during the supervisor_id transition: prefer the
                // structural FK, falling back to the legacy name match only for
                // rows not yet linked. ID precedence is mandatory — a project
                // owned by another supervisor must never be reachable through a
                // coincidental name match. firstOrFail keeps the gate fail-closed.
                $query
                    ->where('supervisor_id', $supervisorId)
                    ->orWhere(function ($fallback) use ($supervisorName) {
                        $fallback
                            ->whereNull('supervisor_id')
                            ->where('supervisor_name', $supervisorName);
                    });
            })
            ->firstOrFail();

        $this->student = User::query()
            ->where('username', $this->project->student_id)
            ->where('role', 'student')
            ->first();
    }

    public function entries(): Collection
    {
        if (! $this->student) {
            return new Collection();
        }

        return Logbook::query()
            ->where('user_id', $this->student->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }
}; ?>

<section class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-semibold text-zinc-900">Student Logbook</h1>
            <p class="mt-1 text-sm text-zinc-500">Review dated progress entries for {{ $this->project->student_name }}.</p>
        </div>

        <flux:button variant="ghost" icon="arrow-left" :href="route('supervisor.students')" wire:navigate>
            Back to My Students
        </flux:button>
    </div>

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="mb-6 flex flex-col gap-4 border-b border-zinc-200 pb-6 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-zinc-900">{{ $this->project->student_name }}</h2>
                <p class="mt-1 text-sm font-medium text-zinc-500">Student ID: {{ $this->project->student_id }}</p>
            </div>

            <div class="rounded-2xl bg-zinc-50 px-4 py-3 text-sm text-zinc-600">
                <p class="font-medium text-zinc-500">Supervisor View</p>
                <p class="mt-1 text-zinc-900">Only logbook entries for your assigned student are shown here.</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Project Title</p>
                <p class="mt-2 text-sm font-medium leading-6 text-zinc-900">{{ $this->project->title }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Domain</p>
                <p class="mt-2 text-sm font-medium text-zinc-900">{{ $this->project->domain }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">App Type</p>
                <p class="mt-2 text-sm font-medium text-zinc-900">{{ $this->project->app_type ?: $this->project->application_type }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 px-5 py-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">FYP Phase</p>
                <p class="mt-2 text-sm font-medium text-zinc-900">{{ $this->project->fyp_phase }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-zinc-900">Logbook Entries</h2>
                <p class="mt-1 text-sm text-zinc-500">Entries are ordered from newest to oldest.</p>
            </div>
        </div>

        <div class="space-y-4">
            @if (! $this->student)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700">
                    No linked student account was found for this student ID.
                </div>
            @elseif ($this->entries()->isEmpty())
                <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center">
                    <h2 class="text-base font-semibold text-zinc-900">No logbook entries yet</h2>
                    <p class="mt-2 text-sm text-zinc-500">This student has not created any logbook entries.</p>
                </div>
            @else
                @foreach ($this->entries() as $entry)
                    <article class="rounded-2xl border border-zinc-200 bg-zinc-50 px-5 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900">{{ $entry->title }}</h3>
                                <p class="text-sm font-medium text-zinc-500">{{ $entry->date->format('d M Y') }}</p>
                            </div>
                        </div>

                        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-zinc-700">{{ $entry->content }}</p>
                    </article>
                @endforeach
            @endif
        </div>
    </div>
</section>
