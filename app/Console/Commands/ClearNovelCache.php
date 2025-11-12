<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearNovelCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-novels {--tag=* : Specific cache tags to clear}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear novel-related caches';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tags = $this->option('tag');

        if (empty($tags)) {
            // Clear all novel-related caches
            $this->info('Clearing all novel caches...');

            Cache::tags([
                'novels',
                'novels-index',
                'novels-search',
                'novels-latest',
                'novels-updated',
                'novels-popular',
                'novels-recommendations'
            ])->flush();

            $this->info('✓ All novel caches cleared successfully!');
        } else {
            // Clear specific tags
            $this->info('Clearing cache tags: ' . implode(', ', $tags));
            Cache::tags($tags)->flush();
            $this->info('✓ Cache cleared for specified tags!');
        }

        return 0;
    }
}
