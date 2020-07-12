<?php

use App\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    private const SITE = 'squamishspace.ca';
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \factory(Site::class)->create([
            'name' => self::SITE,
        ]);
    }
}
