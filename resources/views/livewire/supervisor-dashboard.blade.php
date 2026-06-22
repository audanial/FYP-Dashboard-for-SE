<?php

use function Livewire\Volt\layout;

layout('layouts.app');

?>

<section class="mx-auto w-full max-w-7xl space-y-6">
    <div>
        <h1 class="text-3xl font-semibold text-zinc-900">My Dashboard</h1>
        <p class="mt-1 text-sm text-zinc-500">Your supervision overview.</p>
    </div>

    <livewire:supervisor-overview />
</section>
