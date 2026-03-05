<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillUsernames extends Command
{
    protected $signature = 'users:backfill-usernames';
    protected $description = 'Generate usernames for existing users that do not have one';

    public function handle(): int
    {
        $users = User::whereNull('username')->get();

        if ($users->isEmpty()) {
            $this->info('All users already have usernames.');
            return 0;
        }

        $this->info("Found {$users->count()} user(s) without a username.");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            $user->username = $this->generateUniqueUsername($user->name);
            $user->save();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done! All usernames have been backfilled.');

        return 0;
    }

    /**
     * Generate a unique username from a display name.
     * e.g. "John Doe" → "johndoe", "johndoe1", "johndoe2", ...
     */
    private function generateUniqueUsername(string $name): string
    {
        // Slugify: lowercase, keep alphanumerics only, collapse spaces/dashes
        $base = Str::slug($name, '');
        // Fallback if name produces empty string (e.g. all special chars)
        if ($base === '') {
            $base = 'user';
        }
        // Truncate to 45 chars to leave room for a numeric suffix
        $base = Str::limit($base, 45, '');

        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }
}
