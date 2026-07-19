<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Church;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RelationTesterSeeder extends Seeder
{
    protected $userSystem;
    protected $userAuthor;
    protected $userTeacher;
    protected $userMember; // aluno de classroom, participante de evento, membro de igreja, comentarista de post, adiciona/remove reação de post/media
    protected $church;
    
    public function run(): void
    {
        $this->userSystem = User::where('email', env('APP_USER_SYSTEM_EMAIL'))->first();
        $this->userAuthor = User::updateOrCreate(
            [
                'email' => 'author'.env('APP_MAIL_DOMAIN'),
            ],
            [
                'first_name' => 'Author',
                'last_name' => 'Nossa Casa',
                'password' => bcrypt(env('APP_USER_SYSTEM_PASSWORD')),
            ]
        );
        $this->userTeacher = User::updateOrCreate(
            [
                'email' => 'teacher'.env('APP_MAIL_DOMAIN'),
            ],
            [
                'first_name' => 'Teacher',
                'last_name' => 'Nossa Casa',
                'password' => bcrypt(env('APP_USER_SYSTEM_PASSWORD')),
            ]
        );
        $this->userMember = User::updateOrCreate(
            [
                'email' => 'member'.env('APP_MAIL_DOMAIN'),
            ],
            [
                'first_name' => 'Member',
                'last_name' => 'Nossa Casa',
                'password' => bcrypt(env('APP_USER_SYSTEM_PASSWORD')),
            ]
        );

        

        $this->userAuthor->assignRole(UserRole::MEDIA->value);
        $this->userTeacher->assignRole(UserRole::LEADER->value);

        $this->church = $this->userSystem->church;

        $this->postByChurch();
        $this->postByUser();
        $this->addComments();
        $this->addReactions();
        $this->createClassroomByChurch();
        $this->createEventByChurch();
    }

    private function postByChurch()
    {
        $post = $this->church->posts()->create([
            'title' => Str::limit(fake()->sentence(6, true), 255),
            'slug' => Str::limit(Str::slug(fake()->sentence(6, true)), 255),
            'content' => fake()->paragraph(),
            'published_at' => now(),
            'author_id' => $this->userAuthor->id, // deve ser opcional, se não foi informado, o post usará o author_id do usuário logado
        ]);

        $post->categories()->attach($this->church->categories()->where('type', 'post')->inRandomOrder()->first());
    }

    private function postByUser()
    { 
        $post = $this->userSystem->posts()->create([
            'title' => Str::limit(fake()->sentence(6, true), 255),
            'slug' => Str::limit(Str::slug(fake()->sentence(6, true)), 255),
            'content' => fake()->paragraph(),
            'published_at' => now(),
            'church_id' => $this->church->id, // deve ser opcional, se não foi informado, o post usará o church_id do usuário autor
        ]);

        $post->categories()->attach($this->church->categories()->where('type', 'event')->inRandomOrder()->first());
    }

    private function addComments()
    {
        $post = $this->church->posts()->inRandomOrder()->first();
        $comment =$post->comments()->create([
            'content' => fake()->paragraph(),
            'user_id' => $this->userMember->id,
        ]);
        
        $this->userTeacher->comments()->create([
            'content' => fake()->paragraph(),
            'commentable_type' => get_class($comment),
            'commentable_id' => $comment->id,
        ]); // Response to comment
    }

    private function addReactions()
    {
        $post = $this->church->posts()->inRandomOrder()->first();
        $post->reactions()->create([
            'type' => 'emoji',
            'content' => '👍​',
            'user_id' => $this->userMember->id,
        ]);

        $comment = $post->comments()->inRandomOrder()->first();
        $comment?->reactions()->create([
            'type' => 'emoji',
            'content' => '❤️',
            'user_id' => $this->userTeacher->id,
        ]);
    }

    private function createClassroomByChurch()
    {
        $classroomObreiro = $this->church->classrooms()->updateOrCreate(
        [
            'name' => 'Obreiros - Teste',
        ],
        [            
            'description' => fake()->paragraph(),
            'min_age' => 18,
        ]);
        $classroomKids = $this->church->classrooms()->updateOrCreate(
        [
            'name' => 'Crianças - Teste',
        ],
        [            
            'description' => fake()->paragraph(),
            'min_age' => 0,
            'max_age' => 12,
        ]);

        $classroomObreiro->categories()->attach($this->church->categories()->where('type', 'classroom')->inRandomOrder()->first());
        $classroomKids->categories()->attach($this->church->categories()->where('name', 'Children Ministry')->first());

        $classroomObreiro->assignTeacher($this->userTeacher);
        $classroomObreiro->members()->syncWithoutDetaching($this->userMember);
        $classroomKids->assignTeacher($this->userTeacher);
    }

    private function createEventByChurch()
    {
        $event = $this->church->events()->updateOrCreate(
        [
            'title' => 'Evento de Teste',
        ],
        [
            'slug' => Str::slug('Evento de Teste'),
            'description' => fake()->paragraph(),
            'tags' => ['teste', 'evento'],
            'start_time' => now(),
            'end_time' => now()->addDays(1),
            'author_id' => $this->userAuthor->id,
        ]);

        $event->categories()->syncWithoutDetaching($this->church->categories()->where('type', 'event')->inRandomOrder()->first());
        $event->users()->syncWithoutDetaching($this->userMember);
        $event->users()->syncWithoutDetaching($this->userTeacher);
        $event->confirmations()->updateOrCreate(
            ['user_id' => $this->userMember->id],
            ['check_in_at' => now()]
        );

        $event->userConfirm($this->userTeacher);
        $event->userCheckIn($this->userTeacher);
        $event->userCheckOut($this->userTeacher);
    }
}
