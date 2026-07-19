<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Church;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StarterKitSeeder extends Seeder
{
    public function run(): void
    {
        $churchs = Church::all();

        // Categoria de Post
        $Postcategories = [
            ['name' => 'Post', 'slug' => Str::slug('Post'), 'type' => 'post'],
            ['name' => 'News', 'slug' => Str::slug('News'), 'type' => 'post'],
            ['name' => 'Anouncements', 'slug' => Str::slug('Anouncements'), 'type' => 'post'],
            ['name' => 'Events', 'slug' => Str::slug('Events'), 'type' => 'post'],
            ['name' => 'Testimonies', 'slug' => Str::slug('Testimonies'), 'type' => 'post'],
            ['name' => 'Devotionals', 'slug' => Str::slug('Devotionals'), 'type' => 'post'],
            ['name'=> 'Anouncements', 'slug'=> Str::slug('Anouncements'), 'type'=> 'post'],
            ['name'=> 'Events', 'slug'=> Str::slug('Events'), 'type'=> 'post'],
            ['name'=> 'Testimonies', 'slug'=> Str::slug('Testimonies'), 'type'=> 'post'],
            ['name'=> 'Devotionals', 'slug'=> Str::slug('Devotionals'), 'type'=> 'post'],
        ];
        // Categoria de Classroom
        $Classroomcategories = [
            ['name' => 'Bible Study', 'slug' => Str::slug('Bible Study'), 'type' => 'classroom'],
            ['name' => 'Prayer Group', 'slug' => Str::slug('Prayer Group'), 'type' => 'classroom'],
            ['name' => 'Youth Ministry', 'slug' => Str::slug('Youth Ministry'), 'type' => 'classroom'],
            ['name' => 'Children Ministry', 'slug' => Str::slug('Children Ministry'), 'type' => 'classroom'], 
        ];
        // Categoria de Event
        $Eventcategories = [
            ['name'=> 'Children Ministry', 'slug'=> Str::slug('Children Ministry'), 'type'=> 'event'],
            ['name'=> 'Youth Ministry', 'slug'=> Str::slug('Youth Ministry'), 'type'=> 'event'],
            ['name'=> 'Adult Ministry', 'slug'=> Str::slug('Adult Ministry'), 'type'=> 'event'],
            ['name'=> 'Community Service', 'slug'=> Str::slug('Community Service'), 'type'=> 'event'],
        ];
        // Categoria de Media
        $Mediacategories = [
            ['name'=> 'Music', 'slug'=> Str::slug('Music'), 'type'=> 'media'],
            ['name'=> 'Video', 'slug'=> Str::slug('Video'), 'type'=> 'media'],
            ['name'=> 'Podcast', 'slug'=> Str::slug('Podcast'), 'type'=> 'media'],
            ['name'=> 'Blog', 'slug'=> Str::slug('Blog'), 'type'=> 'media'],
            ['name'=> 'Worship Service', 'slug'=> Str::slug('Worship Service'), 'type'=> 'media'],
            ['name'=> 'Live Stream', 'slug'=> Str::slug('Live Stream'), 'type'=> 'media'],
        ];

        $churchs->each(function ($church) use ($Postcategories, $Classroomcategories, $Eventcategories, $Mediacategories) {
            foreach ($Postcategories as $category) {
                $church->categories()->updateOrCreate(
                    ['slug' => $category['slug']],
                    ['name' => $category['name'], 'type' => $category['type']]
                );
            }
            foreach ($Classroomcategories as $category) {
                $church->categories()->updateOrCreate(
                    ['slug' => $category['slug']],
                    ['name' => $category['name'], 'type' => $category['type']]
                );
            }
            foreach ($Eventcategories as $category) {
                $church->categories()->updateOrCreate(
                    ['slug' => $category['slug']],
                    ['name' => $category['name'], 'type' => $category['type']]
                );
            }
            foreach ($Mediacategories as $category) {
                $church->categories()->updateOrCreate(
                    
                    ['slug' => $category['slug']],
                    ['name' => $category['name'], 'type' => $category['type']]
                );
            }
        });
    }
}
