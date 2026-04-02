<?php

use App\Models\FypProject;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniKL MIIT | FYP Dashboard</title>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="bg-gray-100 antialiased">

<div class="min-h-screen">
    <nav class="bg-white border-b border-gray-200 p-4 mb-6 shadow-sm">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-2">
                <div class="bg-indigo-600 p-1.5 rounded-lg text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <span class="font-bold text-xl tracking-tight !text-black">UniKL <span class="text-indigo-600">MIIT</span></span>
            </div>
            <div class="text-sm font-medium text-gray-500">
                FYP Tracking System
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        

        <div class="p-6 bg-white dark:bg-gray-900 rounded-lg shadow">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold !text-black dark:text-white">FYP Dashboard: <?php echo e($phase); ?></h2>

                <select wire:model.live="semester" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 !text-black">
                    <option value="OCTOBER 2025">October 2025 (Previous)</option>
                    <option value="MARCH 2026">March 2026 (Current)</option>
                </select>
            </div>

            <div class="flex flex-col md:flex-row justify-between gap-6 mb-6">
                <div class="flex flex-1 items-center gap-4" x-data="{ hasText: false }">
                    <div class="relative w-full md:w-2/3">
                        <input wire:model.live="search"
                               type="text"
                               @input="hasText = $el.value.length > 0"
                               placeholder="Search name, title, or supervisor..."
                               class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 w-full text-sm !text-black pr-10">

                        <button x-show="hasText"
                                x-transition
                                @click="hasText = false; $wire.set('search', '')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors"
                                type="button">
                            ✕
                        </button>
                    </div>

                    <select wire:model.live="platform" class="rounded-md border-gray-300 shadow-sm !text-black text-sm min-w-[140px]">
                        <option value="">All Platforms</option>
                        <option value="Web App">Web App</option>
                        <option value="Mobile App">Mobile App</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <input type="file" wire:model="csvFile"
                           class="text-xs text-gray-500 file:mr-4 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <button wire:click="importCsv"
                            class="bg-indigo-600 text-white px-3 py-1 rounded-md text-xs font-bold hover:bg-indigo-700 transition">
                        Import CSV
                    </button>
                </div>
            </div>

            <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-lg mb-6 max-w-md">
                <button wire:click="$set('phase', 'FYP 1')"
                        class="flex-1 py-2 px-4 rounded-md text-sm font-medium <?php echo e($phase === 'FYP 1' ? 'bg-white shadow text-indigo-600' : 'text-gray-500'); ?>">
                    FYP 1
                </button>
                <button wire:click="$set('phase', 'FYP 2')"
                        class="flex-1 py-2 px-4 rounded-md text-sm font-medium <?php echo e($phase === 'FYP 2' ? 'bg-white shadow text-indigo-600' : 'text-gray-500'); ?>">
                    FYP 2
                </button>
            </div>

            <div class="overflow-x-auto border rounded-lg border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">No.</th>
                        <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Student Name</th>
                        <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Project Title</th>
                        <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Supervisor</th>
                        <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Platform</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4 text-sm !text-black"><?php echo e($index + 1); ?></td>
                            <td class="px-6 py-4 text-sm font-medium !text-black"><?php echo e($project->student_name); ?></td>
                            <td class="px-6 py-4 text-sm !text-black"><?php echo e($project->title); ?></td>
                            <td class="px-6 py-4 text-sm !text-black"><?php echo e($project->supervisor_name); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->application_type === 'Web App'): ?>
                                    <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-bold">Web App</span>
                                <?php elseif($project->application_type === 'Mobile App'): ?>
                                    <span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs font-bold">Mobile App</span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-bold"><?php echo e($project->application_type); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center !text-black">
                                No students found for <strong><?php echo e($phase); ?></strong> in <strong><?php echo e($semester); ?></strong>.
                            </td>
                        </tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

</body>
</html><?php /**PATH C:\Users\amir umar danial\Herd\fyp-dashboard\fyp-clean\resources\views\livewire/fyp-dashboard.blade.php ENDPATH**/ ?>