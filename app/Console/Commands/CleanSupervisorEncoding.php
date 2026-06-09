<?php

namespace App\Console\Commands;

use App\Models\FypProject;
use App\Support\Encoding;
use Illuminate\Console\Command;

class CleanSupervisorEncoding extends Command
{
    protected $signature = 'fyp:clean-supervisor-encoding';

    protected $description = 'Repair supervisor_name values already stored with non-UTF-8 (Windows-1252) bytes.';

    public function handle(): int
    {
        $repaired = 0;

        FypProject::query()
            ->whereNotNull('supervisor_name')
            ->chunkById(200, function ($projects) use (&$repaired) {
                foreach ($projects as $project) {
                    $clean = Encoding::toUtf8($project->supervisor_name);

                    if ($clean !== $project->supervisor_name) {
                        $project->supervisor_name = $clean;
                        $project->save();
                        $repaired++;
                    }
                }
            });

        $this->info("Repaired {$repaired} supervisor name(s).");

        return self::SUCCESS;
    }
}
