<?php

use App\Models\Logbook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use function Livewire\Volt\{computed, layout, state};

layout('layouts.app');

state([
    'title' => '',
    'content' => '',
    'date' => '',
    'editingLogbookId' => null,
]);

$entries = computed(function () {
    return Logbook::query()
        ->where('user_id', Auth::id())
        ->orderByDesc('date')
        ->orderByDesc('id')
        ->get();
});

$resetForm = function (): void {
    $this->title = '';
    $this->content = '';
    $this->date = '';
    $this->editingLogbookId = null;
};

$saveEntry = function (): void {
    $validated = Validator::make(
        [
            'title' => $this->title,
            'content' => $this->content,
            'date' => $this->date,
        ],
        [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'date' => ['required', 'date'],
        ],
        [
            'title.required' => 'Please provide a title.',
            'content.required' => 'Please provide the logbook content.',
            'date.required' => 'Please choose the entry date.',
        ],
    )->validate();

    if ($this->editingLogbookId) {
        $entry = Logbook::query()
            ->where('user_id', Auth::id())
            ->findOrFail($this->editingLogbookId);

        $entry->update($validated);

        session()->flash('message', 'Logbook entry updated successfully.');
    } else {
        Logbook::query()->create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        session()->flash('message', 'Logbook entry created successfully.');
    }

    $this->resetForm();
};

$editEntry = function (int $logbookId): void {
    $entry = Logbook::query()
        ->where('user_id', Auth::id())
        ->findOrFail($logbookId);

    $this->editingLogbookId = $entry->id;
    $this->title = $entry->title;
    $this->content = $entry->content;
    $this->date = $entry->date->format('Y-m-d');
};

$deleteEntry = function (int $logbookId): void {
    Logbook::query()
        ->where('user_id', Auth::id())
        ->findOrFail($logbookId)
        ->delete();

    if ($this->editingLogbookId === $logbookId) {
        $this->resetForm();
    }

    session()->flash('message', 'Logbook entry deleted successfully.');
};

$cancelEditing = function (): void {
    $this->resetForm();
};

?>

<section class="mx-auto w-full max-w-5xl space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-semibold text-zinc-900">My Logbook</h1>
            <p class="mt-1 text-sm text-zinc-500">Track your progress with dated weekly entries.</p>
        </div>

        <flux:button variant="ghost" icon="arrow-left" :href="route('dashboard')" wire:navigate>
            Back to Dashboard
        </flux:button>
    </div>

    @if (session()->has('message'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('message') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
        <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="mb-5">
                <h2 class="text-xl font-semibold text-zinc-900">
                    {{ $editingLogbookId ? 'Edit Entry' : 'New Entry' }}
                </h2>
                <p class="mt-1 text-sm text-zinc-500">Only your own entries are visible here.</p>
            </div>

            <form wire:submit="saveEntry" class="space-y-5">
                <flux:input
                    wire:model="title"
                    :label="__('Title')"
                    type="text"
                    required
                    placeholder="Weekly progress update"
                />

                <flux:input
                    wire:model="date"
                    :label="__('Date')"
                    type="date"
                    required
                />

                <div class="space-y-2">
                    <label for="content" class="block text-sm font-medium text-zinc-700">Content</label>
                    <textarea
                        id="content"
                        wire:model="content"
                        rows="8"
                        required
                        class="w-full rounded-2xl border border-zinc-200 px-4 py-3 text-sm text-zinc-700 shadow-xs focus:border-zinc-400 focus:outline-none"
                        placeholder="Summarize what you completed, blockers, and next steps."
                    ></textarea>
                    @error('content')
                        <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3">
                    <flux:button variant="primary" type="submit">
                        {{ $editingLogbookId ? 'Update Entry' : 'Save Entry' }}
                    </flux:button>

                    @if ($editingLogbookId)
                        <flux:button variant="ghost" type="button" wire:click="cancelEditing">
                            Cancel
                        </flux:button>
                    @endif
                </div>
            </form>
        </div>

        <div class="rounded-3xl border border-zinc-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-zinc-900">Your Entries</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ $this->entries->count() }} entry{{ $this->entries->count() === 1 ? '' : 'ies' }}</p>
                </div>
            </div>

            <div class="space-y-4">
                @forelse ($this->entries as $entry)
                    <article class="rounded-2xl border border-zinc-200 bg-zinc-50 px-5 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900">{{ $entry->title }}</h3>
                                <p class="text-sm font-medium text-zinc-500">{{ $entry->date->format('d M Y') }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:button variant="ghost" size="sm" wire:click="editEntry({{ $entry->id }})">
                                    Edit
                                </flux:button>
                                <flux:button variant="danger" size="sm" wire:click="deleteEntry({{ $entry->id }})">
                                    Delete
                                </flux:button>
                            </div>
                        </div>

                        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-zinc-700">{{ $entry->content }}</p>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center">
                        <h3 class="text-base font-semibold text-zinc-900">No logbook entries yet</h3>
                        <p class="mt-2 text-sm text-zinc-500">Create your first entry from the form on the left.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>
