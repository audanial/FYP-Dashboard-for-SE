@auth
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" square class="group">
            <flux:avatar :name="auth()->user()->name" size="sm" class="bg-indigo-600 text-white" />
        </flux:button>

        <flux:menu>
            <div class="px-3 py-2">
                <div class="flex flex-wrap items-center">
                    <span class="text-sm font-semibold text-zinc-900">{{ auth()->user()->name }}</span>
                    @switch(auth()->user()->role)
                        @case('coordinator')
                            <span class="ml-2 rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">Program Coordinator</span>
                            @break
                        @case('supervisor')
                            <span class="ml-2 rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">Supervisor</span>
                            @break
                        @default
                            <span class="ml-2 rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Student</span>
                    @endswitch
                </div>
            </div>

            <flux:menu.separator />

            <flux:menu.group heading="Account">
                <flux:menu.item icon="user" :href="route('profile.edit')" wire:navigate>
                    {{ __('My Profile') }}
                </flux:menu.item>
            </flux:menu.group>

            <flux:menu.separator />

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:menu.item icon="arrow-right-start-on-rectangle" type="submit" variant="danger">
                    {{ __('Log Out') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
@endauth
