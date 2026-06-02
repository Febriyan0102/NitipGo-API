<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
        //admin
        User::updateOrCreate(
            ['email' => 'admin1@gmail.com'],
            [
                'name' => 'Admin',
                'phone' => '081465765778',
                'password' => Hash::make('123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );
        User::updateOrCreate(
            ['email' => 'reman@gmail.com'],
            [
                'name' => 'Reman Fauzi Faturrahman',
                'phone' => '081392176321',
                'password' => Hash::make('123'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        //customer
        User::updateOrCreate(
            ['email' => 'customer1@gmail.com'],
            [
                'name' => 'Customer',
                'phone' => '0836757646568',
                'password' => Hash::make('123'),
                'role' => 'customer',
                'status' => 'active',
            ]
        );
    }
}
