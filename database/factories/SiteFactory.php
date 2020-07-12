<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Site;
use Faker\Generator as Faker;

$factory->define(Site::class, function (Faker $faker) {
    return [
        'name' => $faker->domainName,
        'published_at' => $faker->date('Y-m-d H:m:s', \strtotime('-1 week')),
    ];
});
