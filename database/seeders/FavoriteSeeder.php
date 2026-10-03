<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $bookIds = Book::pluck('id');

        foreach ($users as $user) {
            $randomCount = rand(3, 5);
            $randomBookIds = $bookIds->random(min($randomCount, $bookIds->count()));
            $user->favoriteBooks()->syncWithoutDetaching($randomBookIds);
        }
    }
}
