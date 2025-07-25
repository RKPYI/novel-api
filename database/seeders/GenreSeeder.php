<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Genre;
use Illuminate\Support\Str;

class GenreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $genres = [
            [
                'name' => 'Fantasy',
                'description' => 'Stories featuring magical or supernatural elements in a fictional universe.'
            ],
            [
                'name' => 'Romance',
                'description' => 'Stories focused on relationships and love between characters.'
            ],
            [
                'name' => 'Mystery',
                'description' => 'Stories involving puzzles, crimes, or unexplained events to be solved.'
            ],
            [
                'name' => 'Sci-Fi',
                'description' => 'Science fiction stories featuring futuristic concepts and advanced technology.'
            ],
            [
                'name' => 'Adventure',
                'description' => 'Stories featuring exciting journeys and dangerous quests.'
            ],
            [
                'name' => 'Horror',
                'description' => 'Stories intended to frighten, unsettle, or create suspense.'
            ],
            [
                'name' => 'Drama',
                'description' => 'Stories focused on realistic characters and emotional themes.'
            ],
            [
                'name' => 'Action',
                'description' => 'Fast-paced stories featuring combat, chases, and physical feats.'
            ],
            [
                'name' => 'Thriller',
                'description' => 'Suspenseful stories designed to keep readers on edge.'
            ],
            [
                'name' => 'Historical Fiction',
                'description' => 'Stories set in the past, depicting historical periods and events.'
            ],
            [
                'name' => 'Urban Fantasy',
                'description' => 'Fantasy stories set in modern, urban environments.'
            ],
            [
                'name' => 'Paranormal',
                'description' => 'Stories involving supernatural phenomena and beings.'
            ],
            [
                'name' => 'Young Adult',
                'description' => 'Stories targeted at teenage and young adult readers.'
            ],
            [
                'name' => 'Contemporary',
                'description' => 'Stories set in the present day with realistic themes.'
            ],
            [
                'name' => 'Slice of Life',
                'description' => 'Stories depicting mundane experiences in daily life.'
            ],
            [
                'name' => 'Xianxia',
                'description' => 'Chinese fantasy stories featuring cultivation, immortals, and mystical martial arts.'
            ],
            [
                'name' => 'Wuxia',
                'description' => 'Chinese martial arts adventures set in ancient times, focusing on chivalry and heroism.'
            ],
            [
                'name' => 'Xuanhuan',
                'description' => 'Fantasy stories blending Eastern and Western elements, often with cultivation and magic.'
            ],
            [
                'name' => 'RPG',
                'description' => 'Stories set in game-like worlds with RPG mechanics, stats, and leveling up.'
            ],
            [
                'name' => 'Virtual Reality',
                'description' => 'Stories centered around virtual reality games and immersive digital worlds.'
            ],
            [
                'name' => 'Game System',
                'description' => 'Stories where characters gain powers or progress through a system similar to video games.'
            ],
            [
                'name' => 'Martial Arts',
                'description' => 'Stories focused on martial arts, training, and combat, often with tournaments and sects.'
            ]
        ];

        foreach ($genres as $genreData) {
            Genre::firstOrCreate(
                ['name' => $genreData['name']],
                [
                    'slug' => Str::slug($genreData['name']),
                    'description' => $genreData['description'],
                ]
            );
        }
    }
}
