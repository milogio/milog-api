<?php

use App\Component;
use Illuminate\Database\Seeder;

class ComponentSeeder extends Seeder
{
    private const COMPONENTS = [
        'Rich Text',
        'Media',
    ];
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach(self::COMPONENTS as $component) {
            \factory(Component::class)->create([
                'name' => $component,
            ]);
        }
    }
}
