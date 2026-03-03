<?php

namespace App\Console\Commands;

use App\Models\Chapter;
use Illuminate\Console\Command;

class ReleaseExpiredChapterClaims extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chapters:release-expired-claims';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release chapter claims that have been held for more than 24 hours without review';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expiredCount = Chapter::expiredClaims()->count();

        if ($expiredCount === 0) {
            $this->info('No expired chapter claims found.');
            return self::SUCCESS;
        }

        Chapter::expiredClaims()->update([
            'claimed_by' => null,
            'claimed_at' => null,
        ]);

        $this->info("Released {$expiredCount} expired chapter claim(s).");

        return self::SUCCESS;
    }
}
