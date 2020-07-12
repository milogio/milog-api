<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Page;
use Faker\Generator as Faker;

$factory->define(Page::class, function (Faker $faker) {
    $words = $faker->words(2);
    return [
        'name' => \ucwords(\implode(' ', $words)),
        'published_at' => \date('Y-m-d H:m:s', \strtotime('-1 week')),
    ];
});
