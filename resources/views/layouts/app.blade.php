<x-layouts::app.sidebar :title="$title ?? null">
    <header class="w-full flex justify-end items-center px-6 py-4 bg-white border-b border-zinc-200 dark:bg-zinc-900 dark:border-zinc-700">
        <flux:spacer />
        <x-desktop-user-menu />
    </header>

    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
