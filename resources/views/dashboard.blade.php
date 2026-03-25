<x-layouts::app :title="__('FYP Management Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 p-6 bg-white dark:bg-neutral-800 shadow-sm">
                <p class="text-sm font-bold text-gray-600">Total SE Students</p>

                <div class="mt-4">
                    <h3 class="text-2xl font-black !text-black">210</h3>
                    <p class="text-xs text-green-600 font-bold">Active in MAR 2026 (Current)</p>
                </div>

                <div class="mt-4 opacity-75">
                    <h3 class="text-xl font-bold !text-gray-500">200</h3>
                    <p class="text-xs text-gray-500">Active in OCT 2025 (Previous)</p>
                </div>
            </div>

            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 p-6 bg-white dark:bg-neutral-800 shadow-sm">
                <p class="text-sm font-bold text-gray-600">FYP 1 (SRS & STP)</p>

                <div class="mt-4">
                    <h3 class="text-2xl font-black !text-black">95</h3>
                    <p class="text-xs text-blue-600 font-bold">Current Semester (MAR 2026)</p>
                </div>

                <div class="mt-4 opacity-75">
                    <h3 class="text-xl font-bold !text-gray-500">115</h3>
                    <p class="text-xs text-gray-400">Previous Semester (OCT 2025)</p>
                </div>
            </div>

            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 p-6 bg-white dark:bg-neutral-800 shadow-sm">
                <p class="text-sm font-bold text-gray-600">FYP 2 (Final Submission)</p>

                <div class="mt-4">
                    <h3 class="text-2xl font-black !text-black">115</h3>
                    <p class="text-xs text-orange-600 font-bold">Current Semester (MAR 2026)</p>
                </div>

                <div class="mt-4 opacity-75">
                    <h3 class="text-xl font-bold !text-gray-500">85</h3>
                    <p class="text-xs text-gray-400">Previous Semester (OCT 2025)</p>
                </div>
            </div>
        </div>

        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 bg-white dark:bg-neutral-900">
            <livewire:fyp-dashboard />
        </div>

    </div>
</x-layouts::app>
