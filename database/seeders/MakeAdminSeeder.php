<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class MakeAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@example.com')->first();
        if ($user) {
            $user->assignRole('Super Admin');
        }
    }
}
