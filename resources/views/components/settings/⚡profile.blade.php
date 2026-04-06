<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    public function mount()
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }
}; ?>

<div class="p-6">
    <flux:heading size="xl" level="1">Profile Settings</flux:heading>
    <flux:subheading>Manage your personal information and account details.</flux:subheading>

    <div class="mt-8 max-w-2xl">
        <flux:card>
            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <flux:avatar :name="$name" size="xl" class="bg-indigo-600 text-white" />
                    <div>
                        <flux:heading>{{ $name }}</flux:heading>
                        <flux:text>{{ $email }}</flux:text>
                    </div>
                </div>

                <flux:separator />

                <div class="grid gap-6">
                    <flux:input label="Full Name" wire:model="name" icon="user" />
                    <flux:input label="Email Address" wire:model="email" icon="envelope" disabled />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">Save Changes</flux:button>
                    </div>
                </div>
            </div>
        </flux:card>
    </div>
</div>
