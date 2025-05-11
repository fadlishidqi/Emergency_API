<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat user admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('kikipoiu'),
            'role' => 'admin',
        ]);

        // Buat user relawan
        User::create([
            'name' => 'Relawan Example',
            'email' => 'relawan@gmail.com',
            'password' => Hash::make('kikipoiu'),
            'role' => 'relawan',
            'nik' => '1234567890123456',
            'no_telp' => '081234567890',
        ]);

        // Buat user biasa
        User::create([
            'name' => 'User Example',
            'email' => 'user@gmail.com',
            'password' => Hash::make('kikipoiu'),
            'role' => 'user',
        ]);
    }
}