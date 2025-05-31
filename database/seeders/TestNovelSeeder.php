<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestNovelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test novel
        $novel = \App\Models\Novel::create([
            'title' => 'The Legend of the Dragon King',
            'author' => 'Tang Jia San Shao',
            'description' => 'A young boy must overcome incredible challenges to become the legendary Dragon King.',
            'status' => 'ongoing',
            'total_chapters' => 100,
            'views' => 50000,
            'rating' => 4.8,
            'cover_image' => 'cover.jpg',
            'is_featured' => true,
        ]);

        // Create some test chapters
        for ($i = 1; $i <= 5; $i++) {
            \App\Models\Chapter::create([
                'novel_id' => $novel->id,
                'title' => "Chapter {$i}: The Beginning of Adventure",
                'content' => "This is the content of chapter {$i}. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.\n\nSed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.",
                'chapter_number' => $i,
                'word_count' => 250,
                'is_free' => true,
                'published_at' => now(),
            ]);
        }

        echo "Test novel '{$novel->title}' created with slug: {$novel->slug}\n";
        echo "5 chapters created (1-5)\n";
    }
}
