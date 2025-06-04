<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('12345678'),
            'is_admin' => true,
            'is_varified' => true,
            'email_verified_at' => now()
        ]);

        User::create([
            'name' => 'user',
            'username' => 'user',
            'email' => 'user@user.com',
            'password' => Hash::make('12345678'),
            'is_varified' => true,
            'email_verified_at' => now()
        ]);

        User::create([
            'name' => 'test',
            'username' => 'test',
            'email' => 'test@test.com',
            'password' => Hash::make('12345678'),
            'is_varified' => true,
            'email_verified_at' => now()
        ]);
    }
}
