<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <?php
        use App\Models\FypProject;
        use App\Services\CsvHeaderResolver;
        use App\Support\Encoding;
        use Illuminate\Support\Facades\Auth;
        use Illuminate\Support\Facades\DB;
        use function Livewire\Volt\{state, computed, usesFileUploads, usesPagination, updated};

        usesFileUploads();
        usesPagination(theme: 'tailwind');

        state([
            'phase' => 'FYP 1',
            'semester' => 'MARCH 2026',
            'search' => '',
            'platform' => '',
            'domain' => '',
            'is_ifyp' => '',
            'csvFile' => null,
            'importError' => null,
            'importReport' => null,
            'importedCount' => 0,
            'skippedCount' => 0,
            'failedCount' => 0,
            'skippedDuplicates' => [],
            'viewMode' => 'pair',
        ]);

        updated([
            'search'    => fn() => $this->resetPage(),
            'phase'     => fn() => $this->resetPage(),
            'semester'  => fn() => $this->resetPage(),
            'platform'  => fn() => $this->resetPage(),
            'domain'    => fn() => $this->resetPage(),
            'is_ifyp'   => fn() => $this->resetPage(),
        ]);

        $platforms = computed(function () {
            return FypProject::query()
                ->whereNotNull('application_type')
                ->where('application_type', '!=', '')
                ->distinct()
                ->orderBy('application_type')
                ->pluck('application_type');
        });

        $projects = computed(function () {
            return FypProject::where('fyp_phase', $this->phase)
                ->where('semester', $this->semester)
                ->when($this->platform, fn($q) => $q->where('application_type', $this->platform))
                ->when($this->domain, fn($q) => $q->where('domain', $this->domain))
                ->when($this->is_ifyp !== '', fn($q) => $q->where('is_ifyp', $this->is_ifyp))
                ->where(function($query) {
                    $query->where('student_name', 'like', '%' . $this->search . '%')
                        ->orWhere('student_id', 'like', '%' . $this->search . '%')
                        ->orWhere('title', 'like', '%' . $this->search . '%')
                        ->orWhere('supervisor_name', 'like', '%' . $this->search . '%')
                        ->orWhere('assessor_name', 'like', '%' . $this->search . '%')
                        ->orWhere('domain', 'like', '%' . $this->search . '%')
                        ->orWhere('application_type', 'like', '%' . $this->search . '%');

                    if (stripos('Industrial', $this->search) !== false) {
                        $query->orWhere('is_ifyp', true);
                    }
                    if (stripos('Regular', $this->search) !== false) {
                        $query->orWhere('is_ifyp', false);
                    }
                })
                ->paginate(15);
        });

        $groupedProjects = computed(function () {
            $withPair    = $this->projects->whereNotNull('pair_number')->sortBy('pair_number');
            $withoutPair = $this->projects->whereNull('pair_number');
            return [
                'paired'   => $withPair->groupBy('pair_number'),
                'unpaired' => $withoutPair,
            ];
        });

        $normalizeApplicationType = function (string $raw): string {
            $v = strtolower(trim($raw));
            return match (true) {
                in_array($v, ['web', 'web app', 'webapp', 'website'])                      => 'Web App',
                in_array($v, ['mobile', 'mobile app', 'android', 'ios'])                   => 'Mobile App',
                $v === 'pwa'                                                                => 'PWA',
                in_array($v, ['cross', 'cross platform', 'cross-platform'])                => 'Cross Platform',
                in_array($v, ['desktop', 'desktop app'])                                   => 'Desktop App',
                in_array($v, ['iot', 'hardware', 'iot/hardware', 'arduino', 'raspberry'])  => 'IoT/Hardware',
                default                                                                     => 'Web App',
            };
        };

        $normalizeDomain = function (string $raw): string {
            $v = strtolower(trim($raw));
            return match (true) {
                in_array($v, ['ai', 'artificial intelligence', 'machine learning'])        => 'AI',
                in_array($v, ['medical', 'health', 'healthcare', 'hospital'])              => 'Medical',
                in_array($v, ['iot', 'internet of things'])                                => 'IoT',
                in_array($v, ['education', 'e-learning', 'learning'])                     => 'Education',
                in_array($v, ['business', 'finance', 'marketing'])                        => 'Business',
                default                                                                     => 'Others',
            };
        };

        $normalizeIfyp = function (string $raw): bool {
            $v = strtolower(trim($raw));
            return in_array($v, ['industrial', 'ifyp'], true);
        };

        $importCsv = function () {
            abort_unless(Auth::user()?->role === 'coordinator', 403);

            $this->importError = null;
            $this->importReport = null;
            $this->importedCount = 0;
            $this->skippedCount = 0;
            $this->failedCount = 0;
            $this->skippedDuplicates = [];

            // Change to 'strict' to reject any duplicate, 'update' to upsert.
            $duplicateMode = 'skip'; // 'strict' | 'skip' | 'update'

            $this->validate(['csvFile' => 'required|mimes:csv,txt|max:1024']);
            $path = $this->csvFile->getRealPath();

            // Parse with fgetcsv so quoted fields that span multiple lines stay
            // within a single record, and row order is preserved exactly.
            $data = [];
            if (($handle = fopen($path, 'r')) !== false) {
                while (($row = fgetcsv($handle, escape: '')) !== false) {
                    $data[] = $row;
                }
                fclose($handle);
            }

            if (empty($data)) {
                $this->reset('csvFile');
                return;
            }

            // Normalise encoding before anything reads the cells: strip a leading
            // UTF-8 BOM (which would otherwise break the first header), then coerce
            // any non-UTF-8 bytes (the real CSV is Windows-1252) to clean UTF-8 so
            // supervisor names store and display without mojibake.
            $data[0][0] = Encoding::stripBom((string) ($data[0][0] ?? ''));
            foreach ($data as $r => $row) {
                foreach ($row as $c => $value) {
                    if (is_string($value)) {
                        $data[$r][$c] = Encoding::toUtf8($value);
                    }
                }
            }

            $resolver = app(CsvHeaderResolver::class);
            $map = $resolver->resolve($data[0]);

            $requiredFields = ['student_name', 'student_id', 'title', 'supervisor_name'];
            $missing = array_filter($requiredFields, fn($f) => $map[$f] === null);
            if (!empty($missing)) {
                $labels = array_map(fn($f) => str_replace('_', ' ', $f), $missing);
                $this->importError = 'Missing required CSV columns: ' . implode(', ', $labels) . '. Please check your CSV headers.';
                $this->reset('csvFile');
                return;
            }

            $totalRows = count($data) - 1;

            try {
                DB::transaction(function () use ($data, $map, $duplicateMode) {
                    // Read a mapped cell as a trimmed string ('' when unmapped/missing).
                    $cell = fn (array $row, ?int $i): string => ($i !== null && isset($row[$i])) ? trim($row[$i]) : '';

                    // Build once per import: supervisor name → user id (role=supervisor only).
                    // Used below to set supervisor_id on exact match. Queried here so the
                    // lookup is a single query rather than one per row.
                    $supervisorIdByName = \App\Models\User::where('role', 'supervisor')
                        ->pluck('id', 'name');

                    // Carried project metadata. A partner/continuation row whose
                    // Group, title, supervisor, etc. are blank inherits the last
                    // seen value from the pair's lead row (forward-fill).
                    $carryPair = null;
                    $carryTitle = '';
                    $carrySupervisor = '';
                    $carryAssessor = '';
                    $carryDomain = '';
                    $carryApplicationType = '';
                    $carryType = '';

                    foreach ($data as $index => $row) {
                        if ($index === 0) continue;

                        // Student identity is always per-row, never filled down.
                        $studentId   = $cell($row, $map['student_id']);
                        $studentName = $cell($row, $map['student_name']);

                        // Group → pair_number: inherit the previous row's group when blank.
                        $rawPair = $cell($row, $map['pair_number']);
                        $pairNumber = $rawPair !== '' ? (int) $rawPair : $carryPair;
                        $carryPair = $pairNumber;

                        // Project metadata: use the row's value when present,
                        // otherwise inherit the carried (lead-row) value.
                        $rawTitle = $cell($row, $map['title']);
                        $title = $rawTitle !== '' ? $rawTitle : $carryTitle;
                        $carryTitle = $title;

                        $rawSupervisor = $cell($row, $map['supervisor_name']);
                        $supervisorName = $rawSupervisor !== '' ? $rawSupervisor : $carrySupervisor;
                        $carrySupervisor = $supervisorName;

                        $rawAssessor = $cell($row, $map['assessor_name']);
                        $assessor = $rawAssessor !== '' ? $rawAssessor : $carryAssessor;
                        $carryAssessor = $assessor;
                        $assessorName = $assessor !== '' ? $assessor : null;

                        $rawDomain = $cell($row, $map['domain']);
                        $domain = $rawDomain !== '' ? $rawDomain : $carryDomain;
                        $carryDomain = $domain;

                        $rawApplicationType = $cell($row, $map['application_type']);
                        $applicationType = $rawApplicationType !== '' ? $rawApplicationType : $carryApplicationType;
                        $carryApplicationType = $applicationType;

                        $rawType = $cell($row, $map['is_ifyp']);
                        $type = $rawType !== '' ? $rawType : $carryType;
                        $carryType = $type;

                        // A studentless "lead" row carries a project's metadata for the
                        // following partner row but must not create a record of its own.
                        if ($studentId === '') {
                            continue;
                        }

                        $fields = [
                            'student_name'     => $studentName,
                            'student_id'       => $studentId,
                            'title'            => $title,
                            'supervisor_name'  => $supervisorName,
                            'assessor_name'    => $assessorName,
                            'domain'           => $this->normalizeDomain($domain),
                            'application_type' => $this->normalizeApplicationType($applicationType),
                            'is_ifyp'          => $this->normalizeIfyp($type),
                            'fyp_phase'        => $this->phase,
                            'semester'         => $this->semester,
                            'pair_number'      => $pairNumber,
                        ];

                        // Only set supervisor_id when the CSV name exactly matches a
                        // supervisor account. Intentionally omitted (not set to null) on
                        // no-match so that updateOrCreate never overwrites a hand-curated link.
                        if (isset($supervisorIdByName[$supervisorName])) {
                            $fields['supervisor_id'] = $supervisorIdByName[$supervisorName];
                        }

                        if ($duplicateMode === 'update') {
                            FypProject::updateOrCreate(
                                ['student_id' => $studentId, 'semester' => $this->semester, 'fyp_phase' => $this->phase],
                                $fields,
                            );
                            $this->importedCount++;
                            continue;
                        }

                        if (FypProject::where('student_id', $studentId)
                                ->where('semester', $this->semester)
                                ->where('fyp_phase', $this->phase)
                                ->exists()) {
                            if ($duplicateMode === 'strict') {
                                throw_if(true, \RuntimeException::class, "Duplicate student ID found: {$studentId}. Import cancelled.");
                            }
                            $this->skippedDuplicates[] = $studentId;
                            $this->skippedCount++;
                            continue;
                        }

                        FypProject::create($fields);
                        $this->importedCount++;
                    }
                });
            } catch (\Throwable $e) {
                $this->importReport = null;
                $this->importError = ($e instanceof \RuntimeException)
                    ? $e->getMessage()
                    : 'Import failed. No data was saved. Please check your CSV format.';
                $this->reset('csvFile');
                return;
            }

            $this->importReport = [
                'total'      => $totalRows,
                'imported'   => $this->importedCount,
                'skipped'    => $this->skippedCount,
                'failed'     => $this->failedCount,
                'duplicates' => $this->skippedDuplicates,
            ];

            $this->reset('csvFile');
        };
        ?>

        <div class="p-6 bg-white dark:bg-gray-900 rounded-lg shadow">

            {{-- Page header --}}
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-black! dark:text-white">FYP Projects: {{ $phase }}</h2>

                <select wire:model.live="semester" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 !text-black">
                    <option value="OCTOBER 2025">October 2025 (Previous)</option>
                    <option value="MARCH 2026">March 2026 (Current)</option>
                </select>
            </div>

            {{-- Filters + controls row --}}
            <div class="flex flex-col md:flex-row justify-between gap-6 mb-6">

                {{-- Left: search + platform --}}
                <div class="flex flex-1 items-center gap-4" x-data="{ hasText: false }">
                    <div class="relative w-full md:w-2/3">
                        <input wire:model.live="search"
                               type="text"
                               @input="hasText = $el.value.length > 0"
                               placeholder="Search name, ID, title, domain, or type..."
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
                        @foreach ($this->platforms as $platformOption)
                            <option value="{{ $platformOption }}">{{ $platformOption }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Right: view toggle + CSV import --}}
                <div class="flex flex-col items-end gap-2">

                    {{-- View toggle --}}
                    <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-lg">
                        <button wire:click="$set('viewMode', 'pair')"
                                type="button"
                                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors {{ $viewMode === 'pair' ? 'bg-white shadow text-indigo-600' : 'text-gray-500 hover:text-gray-700' }}">
                            Pair View
                        </button>
                        <button wire:click="$set('viewMode', 'flat')"
                                type="button"
                                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors {{ $viewMode === 'flat' ? 'bg-white shadow text-indigo-600' : 'text-gray-500 hover:text-gray-700' }}">
                            Flat View
                        </button>
                    </div>

                    @if (Auth::user()?->role === 'coordinator')
                        <div class="flex flex-col items-end gap-1">
                            <div class="flex items-center gap-2">
                                <input type="file" wire:model="csvFile"
                                       class="text-xs text-gray-500 file:mr-4 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                <button wire:click="importCsv"
                                        wire:loading.attr="disabled"
                                        wire:target="importCsv"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        class="bg-indigo-600 text-white px-3 py-1 rounded-md text-xs font-bold hover:bg-indigo-700 transition">
                                    <span wire:loading.remove wire:target="importCsv">Import CSV</span>
                                    <span wire:loading wire:target="importCsv">Importing…</span>
                                </button>
                            </div>
                            @if ($importError)
                                <div x-data="{ show: true }"
                                     x-show="show"
                                     x-init="setTimeout(() => { show = false }, 4000)"
                                     x-transition:leave="transition ease-in duration-300"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0">
                                    <p class="text-xs text-red-600 font-medium">{{ $importError }}</p>
                                </div>
                            @endif
                            @if ($importReport)
                                <div x-data="{ show: true }"
                                     x-show="show"
                                     x-init="setTimeout(() => { show = false }, 4000)"
                                     x-transition:leave="transition ease-in duration-300"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0"
                                     class="mt-1 text-right text-xs space-y-0.5">
                                    <p class="font-semibold text-green-600">Import completed successfully</p>
                                    <p class="text-gray-500 dark:text-gray-400">Total rows: {{ $importReport['total'] }}</p>
                                    <p class="text-green-600">Imported: {{ $importReport['imported'] }}</p>
                                    @if ($importReport['skipped'] > 0)
                                        <p class="text-yellow-600">Skipped: {{ $importReport['skipped'] }}</p>
                                    @endif
                                    @if ($importReport['failed'] > 0)
                                        <p class="text-red-600">Failed: {{ $importReport['failed'] }}</p>
                                    @endif
                                    @if (!empty($importReport['duplicates']))
                                        <div x-data="{ open: false }">
                                            <button @click="open = !open"
                                                    class="text-yellow-600 underline hover:text-yellow-800 transition-colors">
                                                View skipped IDs ({{ count($importReport['duplicates']) }})
                                            </button>
                                            <p x-show="open" x-transition class="text-yellow-700 break-words max-w-xs">
                                                {{ implode(', ', $importReport['duplicates']) }}
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                </div>
            </div>

            {{-- Phase tabs --}}
            <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-lg mb-6 max-w-md">
                <button wire:click="$set('phase', 'FYP 1')"
                        class="flex-1 py-2 px-4 rounded-md text-sm font-medium {{ $phase === 'FYP 1' ? 'bg-white shadow text-indigo-600' : 'text-gray-500' }}">
                    FYP 1
                </button>
                <button wire:click="$set('phase', 'FYP 2')"
                        class="flex-1 py-2 px-4 rounded-md text-sm font-medium {{ $phase === 'FYP 2' ? 'bg-white shadow text-indigo-600' : 'text-gray-500' }}">
                    FYP 2
                </button>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto border rounded-lg border-gray-200 dark:border-gray-700">

                @if ($viewMode === 'flat')
                    {{-- ── FLAT VIEW ─────────────────────────────────────────────── --}}
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">No.</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Student Name</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Student ID</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Project Title</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Supervisor</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Assessor</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Domain</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Platform</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                            @forelse($this->projects as $index => $project)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 text-sm !text-black">{{ $index + 1 }}</td>
                                    <td class="px-6 py-4 text-sm font-medium !text-black">{{ $project->student_name }}</td>
                                    <td class="px-6 py-4 text-sm font-semibold !text-black">{{ $project->student_id }}</td>
                                    <td class="px-6 py-4 text-sm !text-black">{{ $project->title }}</td>
                                    <td class="px-6 py-4 text-sm !text-black">{{ $project->supervisor_name }}</td>
                                    <td class="px-6 py-4 text-sm !text-black">{{ $project->assessor_name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm !text-black">
                                        <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">
                                            {{ $project->domain }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm !text-black">{{ $project->application_type }}</td>
                                    <td class="px-6 py-4 text-sm !text-black">
                                        @if($project->is_ifyp)
                                            <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded text-xs font-bold">Industrial (IFYP)</span>
                                        @else
                                            <span class="text-gray-400 text-xs italic">Regular</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-10 text-center !text-black">
                                        No students found for <strong>{{ $phase }}</strong> in <strong>{{ $semester }}</strong>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                @else
                    {{-- ── PAIR VIEW ─────────────────────────────────────────────── --}}
                    <table class="min-w-full bg-white dark:bg-gray-900">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-center text-xs font-bold !text-black uppercase w-16">Pair</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Student Name</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Student ID</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Project Title</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Supervisor</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Assessor</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Domain</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Platform</th>
                                <th class="px-6 py-3 text-left text-xs font-bold !text-black uppercase">Type</th>
                            </tr>
                        </thead>
                        <tbody>

                            @if ($this->projects->isEmpty())
                                <tr>
                                    <td colspan="9" class="px-6 py-10 text-center !text-black">
                                        No students found for <strong>{{ $phase }}</strong> in <strong>{{ $semester }}</strong>.
                                    </td>
                                </tr>
                            @else

                                {{-- Paired groups --}}
                                @php
                                    $firstPairKey = $this->groupedProjects['paired']->keys()->first();
                                    $pairOffset = $firstPairKey
                                        ? \App\Models\FypProject::where('fyp_phase', $phase)
                                              ->where('semester', $semester)
                                              ->whereNotNull('pair_number')
                                              ->where('pair_number', '<', $firstPairKey)
                                              ->distinct('pair_number')
                                              ->count()
                                        : 0;
                                @endphp
                                @foreach ($this->groupedProjects['paired'] as $pairNum => $pairStudents)
                                    @foreach ($pairStudents as $student)
                                        @php
                                            $isFirstInPair  = $loop->first;
                                            $isFirstPair    = $loop->parent->first;
                                            $rowBorder = match (true) {
                                                $isFirstInPair && $isFirstPair  => '',
                                                $isFirstInPair && !$isFirstPair => 'border-t-2 border-gray-400',
                                                default                          => 'border-t border-gray-100',
                                            };
                                        @endphp
                                        <tr class="{{ $rowBorder }} hover:bg-gray-50 dark:hover:bg-gray-800">

                                            {{-- Pair number cell — rowspan spans both students in this pair --}}
                                            @if ($isFirstInPair)
                                                <td rowspan="{{ $pairStudents->count() }}"
                                                    class="px-4 py-4 text-sm font-bold text-center text-indigo-600 align-middle border-r border-gray-100 dark:border-gray-700 w-16">
                                                    {{ $pairOffset + $loop->parent->index + 1 }}
                                                </td>
                                            @endif

                                            {{-- Student name + ID always shown --}}
                                            <td class="px-6 py-4 text-sm font-medium !text-black">{{ $student->student_name }}</td>
                                            <td class="px-6 py-4 text-sm font-semibold !text-black">{{ $student->student_id }}</td>

                                            {{-- Shared columns: only first student in pair shows values --}}
                                            @if ($isFirstInPair)
                                                <td class="px-6 py-4 text-sm !text-black">{{ $student->title }}</td>
                                                <td class="px-6 py-4 text-sm !text-black">{{ $student->supervisor_name }}</td>
                                                <td class="px-6 py-4 text-sm !text-black">{{ $student->assessor_name ?? '-' }}</td>
                                                <td class="px-6 py-4 text-sm !text-black">
                                                    <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">
                                                        {{ $student->domain }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-sm !text-black">{{ $student->application_type }}</td>
                                                <td class="px-6 py-4 text-sm !text-black">
                                                    @if($student->is_ifyp)
                                                        <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded text-xs font-bold">Industrial (IFYP)</span>
                                                    @else
                                                        <span class="text-gray-400 text-xs italic">Regular</span>
                                                    @endif
                                                </td>
                                            @else
                                                <td class="px-6 py-4"></td>
                                                <td class="px-6 py-4"></td>
                                                <td class="px-6 py-4"></td>
                                                <td class="px-6 py-4"></td>
                                                <td class="px-6 py-4"></td>
                                                <td class="px-6 py-4"></td>
                                                <td class="px-6 py-4"></td>
                                            @endif
                                        </tr>
                                    @endforeach
                                @endforeach

                                {{-- Unpaired students — shown at the bottom --}}
                                @foreach ($this->groupedProjects['unpaired'] as $student)
                                    @php
                                        $unpairBorder = match (true) {
                                            $loop->first && $this->groupedProjects['paired']->isNotEmpty() => 'border-t-2 border-gray-400',
                                            $loop->first                                                   => '',
                                            default                                                        => 'border-t border-gray-100',
                                        };
                                    @endphp
                                    <tr class="{{ $unpairBorder }} hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="px-4 py-4 text-sm text-center text-gray-300 border-r border-gray-100 dark:border-gray-700 w-16">—</td>
                                        <td class="px-6 py-4 text-sm font-medium !text-black">{{ $student->student_name }}</td>
                                        <td class="px-6 py-4 text-sm font-semibold !text-black">{{ $student->student_id }}</td>
                                        <td class="px-6 py-4 text-sm !text-black">{{ $student->title }}</td>
                                        <td class="px-6 py-4 text-sm !text-black">{{ $student->supervisor_name }}</td>
                                        <td class="px-6 py-4 text-sm !text-black">{{ $student->assessor_name ?? '-' }}</td>
                                        <td class="px-6 py-4 text-sm !text-black">
                                            <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">
                                                {{ $student->domain }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm !text-black">{{ $student->application_type }}</td>
                                        <td class="px-6 py-4 text-sm !text-black">
                                            @if($student->is_ifyp)
                                                <span class="bg-amber-100 text-amber-700 px-2 py-1 rounded text-xs font-bold">Industrial (IFYP)</span>
                                            @else
                                                <span class="text-gray-400 text-xs italic">Regular</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                            @endif
                        </tbody>
                    </table>
                @endif

            </div>

            @if ($this->projects->hasPages())
                <div class="mt-4 px-2">
                    {{ $this->projects->links() }}
                </div>
            @endif
        </div>
    </main>
