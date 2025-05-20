<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder {
	/**
	 * Seed the application's database.
	 */
	public function run(): void {
		User::factory(34)->create()->each(function (User $user) {
			$user->profile()->save(
				Profile::factory()->make()
			);

			$addresses = Address::factory(2)->make();
			$user->addresses()->saveMany($addresses);

			$user->posts()->saveMany(
				Post::factory(rand(2, 6))->make()
			)->each(function (Post $post) {
				$post->comments()->saveMany(
					Comment::factory(rand(2, 10))->make()
				);
			});
		});
	}
}
