<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use function Livewire\Volt\layout;

layout('layouts.app');

new #[Title('My Profile')] class extends Component {
    use PasswordValidationRules;
    use ProfileValidationRules;
    use WithFileUploads;

    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public $profile_photo = null;

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->username = $user->displayUsername();
        $this->email = $user->email;
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'username' => [
                'required',
                'string',
                'min:3',
                'max:255',
                Rule::unique(User::class, 'username')->ignore($user->id),
            ],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($this->profile_photo) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $user->profile_photo_path = $this->profile_photo->store('profile-photos', 'public');
        }

        $user->fill([
            'name' => $validated['name'],
            'username' => Str::lower($validated['username']),
        ]);

        $user->save();

        $this->username = $user->username;
        $this->reset('profile_photo');

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $exception) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $exception;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section class="mx-auto w-full max-w-4xl space-y-4">
    <div class="flex justify-start">
        <flux:button
            variant="ghost"
            icon="arrow-left"
            :href="route(Auth::user()->dashboardRouteName())"
            wire:navigate
        >
            {{ __('Back to Dashboard') }}
        </flux:button>
    </div>

    <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
        <div class="border-b border-zinc-200 bg-linear-to-r from-zinc-50 to-white px-8 py-10">
            <div class="flex flex-col gap-6 md:flex-row md:items-center">
                @if ($this->profile_photo)
                    <img src="{{ $this->profile_photo->temporaryUrl() }}" alt="{{ $this->name }}" class="h-24 w-24 rounded-full object-cover ring-4 ring-white shadow-sm">
                @elseif (Auth::user()->profilePhotoUrl())
                    <img src="{{ Auth::user()->profilePhotoUrl() }}" alt="{{ $this->name }}" class="h-24 w-24 rounded-full object-cover ring-4 ring-white shadow-sm">
                @else
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-indigo-600 text-3xl font-semibold text-white ring-4 ring-white shadow-sm">
                        {{ Auth::user()->initials() }}
                    </div>
                @endif

                <div class="space-y-1">
                    <div class="flex flex-wrap items-center">
                        <h1 class="text-3xl font-semibold text-zinc-900">{{ $this->name }}</h1>
                        @switch(auth()->user()->role)
                            @case('coordinator')
                                <span class="ml-2 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">Program Coordinator</span>
                                @break
                            @case('supervisor')
                                <span class="ml-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">Supervisor</span>
                                @break
                            @default
                                <span class="ml-2 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">Student</span>
                        @endswitch
                    </div>
                    <p class="text-sm font-medium text-zinc-500">{{ '@'.$this->username }}</p>
                    <p class="text-sm text-zinc-400">{{ $this->email }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-0 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="border-b border-zinc-200 p-8 lg:border-e lg:border-b-0">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-zinc-900">{{ __('Profile Details') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Update your photo, full name, and username.') }}</p>
                </div>

                <form wire:submit="saveProfile" class="space-y-6">
                    <div class="space-y-2">
                        <label for="profile_photo" class="block text-sm font-medium text-zinc-700">{{ __('Profile Photo') }}</label>
                        <input
                            id="profile_photo"
                            wire:model="profile_photo"
                            type="file"
                            accept="image/*"
                            class="block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600 file:me-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                        >
                        @error('profile_photo')
                            <p class="text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-zinc-500">{{ __('Upload a JPG or PNG image up to 2MB.') }}</p>
                    </div>

                    <flux:input wire:model="name" :label="__('Full Name')" type="text" required autocomplete="name" />

                    <flux:input
                        wire:model="username"
                        :label="__('Username')"
                        type="text"
                        required
                        autocomplete="username"
                        placeholder="your_username"
                    />

                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit" data-test="update-profile-button">
                            {{ __('Save Profile') }}
                        </flux:button>

                        <x-action-message on="profile-updated">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>
                </form>
            </div>

            <div class="p-8">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-zinc-900">{{ __('Change Password') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500">{{ __('Use your current password before setting a new one.') }}</p>
                </div>

                <form wire:submit="updatePassword" class="space-y-6">
                    <flux:input
                        wire:model="current_password"
                        :label="__('Current Password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        viewable
                    />

                    <flux:input
                        wire:model="password"
                        :label="__('New Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        viewable
                    />

                    <flux:input
                        wire:model="password_confirmation"
                        :label="__('Confirm New Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        viewable
                    />

                    <div class="flex items-center gap-4">
                        <flux:button variant="primary" type="submit" data-test="update-password-button">
                            {{ __('Update Password') }}
                        </flux:button>

                        <x-action-message on="password-updated">
                            {{ __('Saved.') }}
                        </x-action-message>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
