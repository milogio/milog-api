<?php

use App\Page;
use App\Site;
use App\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UserSeeder::class)
            ->call(ComponentSeeder::class)
            ->call(SiteSeeder::class)
            ->call(PageSeeder::class);

        // create pivot table entries
        $user = User::find(1);
        $site = Site::find(1);
        $pages = Page::all();

        $user->sites()->attach($site->id);
        $pages->each(function($page) use($site) {
            $site->pages()->attach($page->id);
        });
    }
}
