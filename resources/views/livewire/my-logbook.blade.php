<?php

use App\Models\Logbook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use function Livewire\Volt\{computed, layout, state};

layout('layouts.app');

state([
    'title'             => '',
    'content'           => '',
    'date'              => '',
    'editingLogbookId'  => null,
    'showDeleteModal'   => false,
    'pendingDeleteId'   => null,
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

    $this->showDeleteModal = false;
    $this->pendingDeleteId = null;

    session()->flash('message', 'Logbook entry deleted successfully.');
};

$cancelEditing = function (): void {
    $this->resetForm();
};

$openDeleteModal = function (int $logbookId): void {
    Logbook::query()->where('user_id', Auth::id())->findOrFail($logbookId);
    $this->pendingDeleteId  = $logbookId;
    $this->showDeleteModal  = true;
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
        <div x-data="{ show: true }"
             x-show="show"
             x-init="setTimeout(() => { show = false }, 4000)"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
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
                    <flux:button variant="primary" type="submit"
                                 wire:loading.attr="disabled"
                                 wire:target="saveEntry"
                                 wire:loading.class="opacity-50 cursor-not-allowed">
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
                    <p class="mt-1 text-sm text-zinc-500">{{ $this->entries->count() }} entr{{ $this->entries->count() === 1 ? 'y' : 'ies' }}</p>
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
                                <flux:button variant="danger" size="sm" wire:click="openDeleteModal({{ $entry->id }})">
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

    {{-- Delete confirmation modal --}}
    <div x-show="$wire.showDeleteModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         style="display: none;">
        <div x-show="$wire.showDeleteModal"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             @click.stop
             class="relative w-full max-w-md rounded-2xl bg-white shadow-xl">

            <div class="px-6 py-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 rounded-full bg-rose-100 p-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-rose-600">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Delete this entry?</h2>
                        <p class="mt-1 text-sm text-zinc-500">This logbook entry will be permanently deleted and cannot be recovered.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-100 px-6 py-4">
                <button @click="$wire.set('showDeleteModal', false)"
                        type="button"
                        class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 transition-colors hover:bg-zinc-50">
                    Cancel
                </button>
                <button wire:click="deleteEntry({{ (int) $pendingDeleteId }})"
                        wire:loading.attr="disabled"
                        wire:target="deleteEntry"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                        type="button"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-rose-700">
                    Delete entry
                </button>
            </div>

        </div>
    </div>

</section>
