<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_ADMIN,
            'provider' => 'email',
            'bio' => 'System Administrator',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Create sample regular users
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_USER,
            'provider' => 'email',
            'bio' => 'Novel enthusiast and avid reader',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_USER,
            'provider' => 'email',
            'bio' => 'Love fantasy and sci-fi novels',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Mike Wilson',
            'email' => 'mike@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_USER,
            'provider' => 'email',
            'bio' => 'Action and adventure lover',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Create a Google OAuth user example
        User::create([
            'name' => 'Sarah Google',
            'email' => 'sarah.google@gmail.com',
            'password' => Hash::make(Str::random(32)), // Random password for OAuth users
            'provider' => 'google',
            'provider_id' => '123456789',
            'avatar' => 'https://lh3.googleusercontent.com/a/default-user=s96-c',
            'bio' => 'Signed up with Google',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }
}
