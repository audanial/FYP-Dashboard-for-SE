@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Final Year Project" {{ $attributes }}>
        <x-slot name="logo">
            <img src="{{ asset ('images/unikl-logo.jpg') }}" alt="UniKL Logo" class="h-9 w-auto">   
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Final Year Project" {{ $attributes }}>
        <x-slot name="logo">
            <img src="{{ asset ('images/unikl-logo.jpg') }}" alt="UniKL Logo" class="h-9 w-auto">   
            </div>
        </x-slot>
    </flux:brand>
@endif
