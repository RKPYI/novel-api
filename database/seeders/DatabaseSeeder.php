<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed all data in proper order
        $this->call([
            UserSeeder::class,        // Create users first (including admin)
            GenreSeeder::class,       // Create genres
            TestNovelSeeder::class,   // Create novels
            CommentRatingSeeder::class, // Create comments and ratings
        ]);
    }
}
