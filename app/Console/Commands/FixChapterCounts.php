<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Novel;

class FixChapterCounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'novels:fix-chapter-counts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix total_chapters count for all novels by recalculating from actual chapters';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing chapter counts for all novels...');

        $novels = Novel::all();
        $fixed = 0;

        foreach ($novels as $novel) {
            $actualCount = $novel->chapters()->count();
            $currentCount = $novel->total_chapters ?? 0;

            if ($actualCount !== $currentCount) {
                $novel->total_chapters = $actualCount;
                $novel->save();
                $fixed++;

                $this->line("Novel '{$novel->title}': {$currentCount} → {$actualCount} chapters");
            }
        }

        if ($fixed > 0) {
            $this->info("Fixed {$fixed} novels with incorrect chapter counts.");
        } else {
            $this->info("All novels have correct chapter counts.");
        }

        return 0;
    }
}
