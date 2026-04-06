@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="UniKL FYP" {{ $attributes }}>
        <x-slot name="logo">
            <div class="text-xl font-black text-indigo-600 tracking-tighter">
                UniKL
            </div>
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="UniKL FYP" {{ $attributes }}>
        <x-slot name="logo">
            <div class="text-xl font-black text-indigo-600 tracking-tighter">
                UniKL
            </div>
        </x-slot>
    </flux:brand>
@endif
