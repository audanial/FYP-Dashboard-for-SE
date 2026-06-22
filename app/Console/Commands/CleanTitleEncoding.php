<?php

namespace App\Console\Commands;

use App\Models\FypProject;
use App\Support\Encoding;
use Illuminate\Console\Command;

class CleanTitleEncoding extends Command
{
    protected $signature = 'fyp:clean-title-encoding';

    protected $description = 'Repair title values already stored with non-UTF-8 (Windows-1252) bytes.';

    public function handle(): int
    {
        $repaired = 0;

        FypProject::query()
            ->whereNotNull('title')
            ->chunkById(200, function ($projects) use (&$repaired) {
                foreach ($projects as $project) {
                    $clean = Encoding::toUtf8($project->title);

                    if ($clean !== $project->title) {
                        $project->title = $clean;
                        $project->save();
                        $repaired++;
                    }
                }
            });

        $this->info("Repaired {$repaired} title(s).");

        return self::SUCCESS;
    }
}
