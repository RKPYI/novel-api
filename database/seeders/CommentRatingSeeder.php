<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\CommentVote;
use App\Models\Rating;
use App\Models\Novel;
use App\Models\Chapter;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentRatingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', User::ROLE_USER)->get();
        $novels = Novel::all();

        if ($users->isEmpty() || $novels->isEmpty()) {
            $this->command->warn('No users or novels found. Please run UserSeeder and NovelSeeder first.');
            return;
        }

        // Create ratings for novels
        foreach ($novels as $novel) {
            $ratingCount = rand(5, 15);
            for ($i = 0; $i < $ratingCount; $i++) {
                $user = $users->random();

                // Avoid duplicate ratings
                if (!Rating::where('user_id', $user->id)->where('novel_id', $novel->id)->exists()) {
                    $rating = Rating::create([
                        'user_id' => $user->id,
                        'novel_id' => $novel->id,
                        'rating' => rand(3, 5), // Mostly positive ratings
                        'review' => rand(1, 3) === 1 ? $this->getRandomReview() : null, // 33% chance of review
                    ]);
                }
            }

            // Update novel rating
            $novel->updateRating();
        }

        // Create comments for novels
        foreach ($novels->take(3) as $novel) {
            $commentCount = rand(5, 10);
            for ($i = 0; $i < $commentCount; $i++) {
                $user = $users->random();

                $comment = Comment::create([
                    'user_id' => $user->id,
                    'novel_id' => $novel->id,
                    'content' => $this->getRandomComment(),
                    'is_spoiler' => rand(1, 10) === 1, // 10% chance of spoiler
                    'is_approved' => rand(1, 20) !== 1, // 95% approved
                ]);

                // Add some replies
                if (rand(1, 3) === 1) {
                    $replyUser = $users->where('id', '!=', $user->id)->random();
                    Comment::create([
                        'user_id' => $replyUser->id,
                        'novel_id' => $novel->id,
                        'parent_id' => $comment->id,
                        'content' => $this->getRandomReply(),
                        'is_approved' => true,
                    ]);
                }

                // Add some votes to comments
                $voteCount = rand(0, 5);
                for ($j = 0; $j < $voteCount; $j++) {
                    $voteUser = $users->random();
                    if (!CommentVote::where('user_id', $voteUser->id)->where('comment_id', $comment->id)->exists()) {
                        CommentVote::create([
                            'user_id' => $voteUser->id,
                            'comment_id' => $comment->id,
                            'is_upvote' => rand(1, 4) !== 1, // 75% upvotes
                        ]);
                    }
                }

                $comment->updateVoteCounts();
            }
        }

        // Create chapter comments
        $chapters = Chapter::whereIn('novel_id', $novels->pluck('id'))->take(5)->get();
        foreach ($chapters as $chapter) {
            $commentCount = rand(2, 5);
            for ($i = 0; $i < $commentCount; $i++) {
                $user = $users->random();

                Comment::create([
                    'user_id' => $user->id,
                    'novel_id' => $chapter->novel_id,
                    'chapter_id' => $chapter->id,
                    'content' => $this->getRandomChapterComment(),
                    'is_spoiler' => rand(1, 5) === 1, // 20% chance of spoiler for chapters
                    'is_approved' => true,
                ]);
            }
        }
    }

    private function getRandomComment(): string
    {
        $comments = [
            "This novel is absolutely amazing! The character development is incredible.",
            "I love the world-building in this story. So detailed and immersive.",
            "The plot twists keep me on the edge of my seat. Can't wait for the next chapter!",
            "The author has a great writing style. Very engaging and easy to read.",
            "This is one of the best novels I've read in a long time. Highly recommend!",
            "The romance subplot is well done and doesn't feel forced.",
            "Great action sequences and fight scenes. Very well described.",
            "The magic system is unique and well thought out.",
            "I've been following this story since the beginning and it just keeps getting better.",
            "The character interactions feel natural and realistic.",
        ];

        return $comments[array_rand($comments)];
    }

    private function getRandomReply(): string
    {
        $replies = [
            "I completely agree! This author really knows how to write.",
            "Thanks for the recommendation. I'll definitely check it out.",
            "Interesting perspective. I hadn't thought of it that way.",
            "I have to disagree here, but I respect your opinion.",
            "Great point! I noticed that too.",
            "You're absolutely right about that character development.",
            "I'm glad I'm not the only one who thinks this!",
            "That's a good observation. Thanks for sharing!",
        ];

        return $replies[array_rand($replies)];
    }

    private function getRandomChapterComment(): string
    {
        $comments = [
            "This chapter was intense! Didn't see that coming.",
            "Great pacing in this chapter. Perfect balance of action and dialogue.",
            "The cliffhanger at the end has me hooked. When's the next update?",
            "I love how the author is developing this character arc.",
            "This chapter answered some questions but raised even more!",
            "The fight scene in this chapter was epic!",
            "I'm getting emotional reading this. Such good writing.",
            "This chapter ties everything together nicely.",
        ];

        return $comments[array_rand($comments)];
    }

    private function getRandomReview(): string
    {
        $reviews = [
            "An excellent novel with compelling characters and an engaging plot. The author does a fantastic job of world-building and character development. Highly recommended for fans of the genre.",
            "This story has everything - action, romance, mystery, and great character development. The pacing is perfect and the writing quality is top-notch.",
            "I was skeptical at first, but this novel really grew on me. The characters are well-developed and the plot is engaging. Looking forward to more!",
            "A solid read with interesting plot twists and good character interactions. The writing style is engaging and easy to follow.",
            "Great story with a unique magic system and interesting world-building. The characters feel real and their motivations are believable.",
        ];

        return $reviews[array_rand($reviews)];
    }
}
