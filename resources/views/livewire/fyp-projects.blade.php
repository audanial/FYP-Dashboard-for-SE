<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12">
        <?php
        use App\Models\FypProject;
        use App\Services\CsvHeaderResolver;
        use Illuminate\Support\Facades\Auth;
        use Illuminate\Support\Facades\DB;
        use function Livewire\Volt\{state, computed, usesFileUploads};

        usesFileUploads();

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
                        ->orWhere('domain', 'like', '%' . $this->search . '%')      // Search Domain
                        ->orWhere('application_type', 'like', '%' . $this->search . '%'); // Search Platform

                    // Logic to search "Industrial" or "Regular" keywords
                    if (stripos('Industrial', $this->search) !== false) {
                        $query->orWhere('is_ifyp', true);
                    }
                    if (stripos('Regular', $this->search) !== false) {
                        $query->orWhere('is_ifyp', false);
                    }
                })
                ->get();
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
            $data = array_map('str_getcsv', file($path));

            if (empty($data)) {
                $this->reset('csvFile');
                return;
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
                    foreach ($data as $index => $row) {
                        if ($index === 0) continue;

                        $studentId      = trim($row[$map['student_id']] ?? '');
                        $studentName    = trim($row[$map['student_name']] ?? '');
                        $title          = trim($row[$map['title']] ?? '');
                        $supervisorName = trim($row[$map['supervisor_name']] ?? '');
                        $assessorName   = ($map['assessor_name'] !== null && isset($row[$map['assessor_name']]) && trim($row[$map['assessor_name']]) !== '')
                            ? trim($row[$map['assessor_name']])
                            : null;

                        $rawDomain = ($map['domain'] !== null && isset($row[$map['domain']]) && trim($row[$map['domain']]) !== '')
                            ? trim($row[$map['domain']])
                            : '';

                        $rawApplicationType = ($map['application_type'] !== null && isset($row[$map['application_type']]) && trim($row[$map['application_type']]) !== '')
                            ? trim($row[$map['application_type']])
                            : '';

                        $fields = [
                            'student_name'     => $studentName,
                            'student_id'       => $studentId,
                            'title'            => $title,
                            'supervisor_name'  => $supervisorName,
                            'assessor_name'    => $assessorName,
                            'domain'           => $this->normalizeDomain($rawDomain),
                            'application_type' => $this->normalizeApplicationType($rawApplicationType),
                            'fyp_phase'        => $this->phase,
                            'semester'         => $this->semester,
                        ];

                        if ($duplicateMode === 'update') {
                            FypProject::updateOrCreate(['student_id' => $studentId], $fields);
                            $this->importedCount++;
                            continue;
                        }

                        if (FypProject::where('student_id', $studentId)->exists()) {
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
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-black! dark:text-white">FYP Dashboard: {{ $phase }}</h2>

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

                @if (Auth::user()?->role === 'coordinator')
                    <div class="flex flex-col items-end gap-1">
                        <div class="flex items-center gap-2">
                            <input type="file" wire:model="csvFile"
                                   class="text-xs text-gray-500 file:mr-4 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <button wire:click="importCsv"
                                    class="bg-indigo-600 text-white px-3 py-1 rounded-md text-xs font-bold hover:bg-indigo-700 transition">
                                Import CSV
                            </button>
                        </div>
                        @if ($importError)
                            <p class="text-xs text-red-600 font-medium">{{ $importError }}</p>
                        @endif
                        @if ($importReport)
                            <div class="mt-1 text-right text-xs space-y-0.5">
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

            <div class="overflow-x-auto border rounded-lg border-gray-200 dark:border-gray-700">
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
            </div>
        </div>
    </main>
