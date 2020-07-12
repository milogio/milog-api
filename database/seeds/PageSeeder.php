<?php

use App\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    private const PAGES = [
        'Home',
        'About',
        'Sign Up',
        'Features',
        'Pricing',
        'Contact',
        'Blog',
        'Privacy Policy',
        'Terms & Conditions',
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach(self::PAGES as $page) {
            \factory(Page::class)->create([
                'name' => $page,
                'published_at' => \date('Y-m-d H:i:s', \strtotime('yesterday')),
            ]);
        }
    }
}
