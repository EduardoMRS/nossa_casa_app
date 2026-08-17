<?php

namespace Database\Seeders;

use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Enums\UserRelationships;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\AiQuery;
use App\Models\Calendar;
use App\Models\Category;
use App\Models\Church;
use App\Models\Classroom;
use App\Models\ClassroomPresence;
use App\Models\Community;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Highlight;
use App\Models\Library;
use App\Models\Media;
use App\Models\Network;
use App\Models\Post;
use App\Models\PrayerRequest;
use App\Models\Translation;
use App\Models\User;
use App\Models\UserRelationship;
use App\Models\Vercicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RelationTesterSeeder extends Seeder
{
    private Church $church;

    private Church $campus;

    /** @var array<string, User> */
    private array $users = [];

    public function run(): void
    {
        $this->seedOrganization();
        $this->seedUsers();
        $this->call(StarterKitSeeder::class);
        $this->seedContentAndInteractions();
        $this->seedMinistries();
        $this->seedSupportingModules();
    }

    private function seedOrganization(): void
    {

        $community = Community::query()->updateOrCreate(
            ['slug' => 'comunidade-nossa-casa-teste'],
            ['name' => 'Comunidade Nossa Casa', 'description' => 'Ambiente de demonstração completo.', 'found_date' => '2018-03-10'],
        );

        $this->church = Church::query()->updateOrCreate(
            ['slug' => 'nossa-casa-central'],
            ['name' => 'Nossa Casa Central', 'community_id' => $community->id, 'status' => 'active', 'found_date' => '2018-03-10', 'domain' => domainBase('central')]
        );
        $this->campus = Church::query()->updateOrCreate(
            ['slug' => 'nossa-casa-norte'],
            ['name' => 'Nossa Casa Norte', 'community_id' => $community->id, 'status' => 'active', 'found_date' => '2022-08-20', 'domain' => domainBase('norte')],
        );

        Network::query()->updateOrCreate(
            ['parent_church_id' => $this->church->id, 'child_church_id' => $this->campus->id],
            ['community_id' => $community->id],
        );

        foreach ([$this->church, $this->campus] as $church) {
            Address::query()->updateOrCreate(
                ['addressable_type' => Church::class, 'addressable_id' => $church->id],
                ['country' => 'Brasil', 'state' => 'SP', 'city' => 'São Paulo', 'neighborhood' => 'Centro', 'street' => 'Rua da Comunidade', 'number' => $church->id === $this->church->id ? '100' : '200', 'zipcode' => '01000-000'],
            );
        }
    }

    private function seedUsers(): void
    {
        $roles = [
            'guest' => UserRole::GUEST,
            'system' => UserRole::SYSTEM,
            'superadmin' => UserRole::SUPERADMIN,
            'admin' => UserRole::ADMIN,
            'leader' => UserRole::LEADER,
            'media' => UserRole::MEDIA,
            'member' => UserRole::MEMBER,
            'child' => UserRole::MEMBER,
        ];

        foreach ($roles as $key => $role) {
            $this->users[$key] = User::query()->updateOrCreate(
                ['email' => "{$key}@nossacasa.test"],
                ['first_name' => ucfirst($key), 'last_name' => 'Teste', 'password' => Hash::make('password'), 'birth_date' => $key === 'child' ? now()->subYears(8)->toDateString() : now()->subYears(28)->toDateString(), 'role' => $role->value],
            );
            $this->users[$key]->profile()->updateOrCreate(
                ['user_id' => $this->users[$key]->id],
                ['church_id' => $this->church->id, 'community_id' => $this->church->community_id, 'phone' => '+55 11 99999-'.str_pad((string) array_search($key, array_keys($roles), true), 4, '0', STR_PAD_LEFT), 'location_lang' => 'pt-BR', 'gender' => in_array($key, ['admin', 'child'], true) ? 'female' : 'male'],
            );
        }

        foreach ([['parent', 'child', UserRelationships::PARENT], ['child', 'parent', UserRelationships::CHILD], ['member', 'leader', UserRelationships::FRIEND]] as [$user, $relatedUser, $type]) {
            $source = $user === 'parent' ? $this->users['admin'] : $this->users[$user];
            $target = $relatedUser === 'parent' ? $this->users['admin'] : $this->users[$relatedUser];
            UserRelationship::query()->updateOrCreate(['user_id' => $source->id, 'related_user_id' => $target->id, 'relationship_type' => $type->value]);
        }
    }

    private function seedContentAndInteractions(): void
    {
        $post = Post::query()->updateOrCreate(
            ['slug' => 'boas-vindas-comunidade'],
            ['church_id' => $this->church->id, 'author_id' => $this->users['media']->id, 'title' => 'Boas-vindas à nossa comunidade', 'content' => 'Uma postagem de demonstração com comentários, reações, mídia e categorias.', 'published_at' => now()->subDay()],
        );
        $event = Event::query()->updateOrCreate(
            ['slug' => 'encontro-da-comunidade'],
            ['church_id' => $this->church->id, 'author_id' => $this->users['leader']->id, 'title' => 'Encontro da Comunidade', 'description' => 'Celebração, comunhão e inscrição através de formulário.', 'tags' => ['comunidade', 'teste'], 'start_time' => now()->addWeek(), 'end_time' => now()->addWeek()->addHours(3)],
        );
        $media = Media::query()->updateOrCreate(
            ['file_path' => 'seeders/comunidade.jpg'],
            ['uploader_id' => $this->users['media']->id, 'church_id' => $this->church->id, 'mimetype' => 'image/jpeg', 'size' => 204800, 'gallery' => true, 'status' => MediaStatus::APPROVED->value],
        );

        $post->categories()->syncWithoutDetaching($this->categoryIds(CategoryType::POST));
        $event->categories()->syncWithoutDetaching($this->categoryIds(CategoryType::EVENT));
        $media->categories()->syncWithoutDetaching($this->categoryIds(CategoryType::MEDIA));
        $post->medias()->syncWithoutDetaching([$media->id]);
        $event->medias()->syncWithoutDetaching([$media->id]);
        Address::query()->updateOrCreate(
            ['addressable_type' => Event::class, 'addressable_id' => $event->id],
            ['country' => 'Brasil', 'state' => 'SP', 'city' => 'Sao Paulo', 'neighborhood' => 'Centro', 'street' => 'Rua da Comunidade', 'number' => '100', 'zipcode' => '01000-000'],
        );

        $comment = $post->comments()->updateOrCreate(['user_id' => $this->users['member']->id, 'content' => 'Que alegria fazer parte desta casa!']);
        $comment->replies()->updateOrCreate(['user_id' => $this->users['leader']->id, 'content' => 'Seja muito bem-vindo(a)!']);
        $post->reactions()->updateOrCreate(['user_id' => $this->users['member']->id, 'content' => '👍', 'type' => 'emoji']);
        $media->reactions()->updateOrCreate(['user_id' => $this->users['leader']->id, 'content' => '❤️', 'type' => 'emoji']);

        Highlight::query()->updateOrCreate(['highlightable_type' => Event::class, 'highlightable_id' => $event->id], ['church_id' => $this->church->id, 'order' => 1]);
        Highlight::query()->updateOrCreate(['highlightable_type' => Post::class, 'highlightable_id' => $post->id], ['church_id' => $this->church->id, 'order' => 2]);
        Calendar::query()->updateOrCreate(['calendarable_type' => Event::class, 'calendarable_id' => $event->id], ['church_id' => $this->church->id, 'date' => $event->start_time]);

        Translation::query()->updateOrCreate(['translatable_type' => Post::class, 'translatable_id' => $post->id, 'translatable_column' => 'title', 'locale' => 'en'], ['content_original' => $post->title, 'content' => 'Welcome to our community']);
    }

    private function seedMinistries(): void
    {
        $event = Event::query()->where('slug', 'encontro-da-comunidade')->firstOrFail();
        $post = Post::query()->where('slug', 'boas-vindas-comunidade')->firstOrFail();
        $form = Form::query()->updateOrCreate(
            ['church_id' => $this->church->id, 'title' => 'Inscrição para o encontro'],
            ['description' => 'Dados do participante.', 'schema' => ['fields' => [
                ['id' => 'heading', 'type' => 'heading', 'label' => 'Dados pessoais'],
                ['id' => 'name', 'type' => 'text', 'name' => 'full_name', 'label' => 'Nome completo', 'required' => true, 'width' => 'full', 'size' => 'auto'],
                ['id' => 'email', 'type' => 'email', 'name' => 'email', 'label' => 'E-mail', 'required' => true, 'width' => 'half', 'size' => 'auto'],
                ['id' => 'divider', 'type' => 'divider'],
            ]]],
        );
        $form->categories()->syncWithoutDetaching($this->categoryIds(CategoryType::FORM));
        $form->events()->syncWithoutDetaching([$event->id]);
        $form->posts()->syncWithoutDetaching([$post->id]);
        FormResponse::query()->updateOrCreate(['form_id' => $form->id, 'user_id' => $this->users['member']->id], ['answers' => ['full_name' => 'Member Teste', 'email' => 'member@nossacasa.test']]);
        $event->users()->syncWithoutDetaching([$this->users['member']->id]);
        $event->confirmations()->updateOrCreate(['user_id' => $this->users['member']->id], ['check_in_at' => now()->subMinutes(30), 'check_out_at' => now()]);

        $kids = Classroom::query()->updateOrCreate(['church_id' => $this->church->id, 'name' => 'Sala Kids 7 a 10'], ['description' => 'Turma infantil com retirada protegida por PIN.', 'min_age' => 7, 'max_age' => 10, 'is_kids' => true, 'max_members' => 20, 'teacher_id' => $this->users['leader']->id]);
        $adults = Classroom::query()->updateOrCreate(['church_id' => $this->church->id, 'name' => 'Fundamentos da Fé'], ['description' => 'Turma para adultos e novos membros.', 'min_age' => 18, 'max_age' => null, 'is_kids' => false, 'max_members' => 30, 'teacher_id' => $this->users['leader']->id]);
        $kids->categories()->syncWithoutDetaching($this->categoryIds(CategoryType::CLASSROOM));
        $adults->categories()->syncWithoutDetaching($this->categoryIds(CategoryType::CLASSROOM));
        $women = Classroom::query()->updateOrCreate(
            ['church_id' => $this->church->id, 'name' => 'Encontro de Mulheres'],
            ['min_age' => 18, 'max_age' => 65, 'gender_restriction' => 'female', 'is_kids' => false, 'max_members' => 20, 'teacher_id' => $this->users['leader']->id],
        );
        $women->categories()->syncWithoutDetaching($this->categoryIds(CategoryType::CLASSROOM));
        $kids->members()->syncWithoutDetaching([$this->users['child']->id]);
        $adults->members()->syncWithoutDetaching([$this->users['member']->id]);
        $women->members()->syncWithoutDetaching([$this->users['admin']->id]);
        $adults->posts()->syncWithoutDetaching([$post->id]);
        ClassroomPresence::query()->updateOrCreate(['classroom_id' => $kids->id, 'user_id' => $this->users['child']->id, 'check_out' => null], ['check_in' => now(), 'checkout_pin' => Hash::make('123456'), 'pin_generated_at' => now()]);
        ClassroomPresence::query()->updateOrCreate(['classroom_id' => $adults->id, 'user_id' => $this->users['member']->id], ['check_in' => now()->subHour(), 'check_out' => now()]);

        PrayerRequest::query()->updateOrCreate(['user_id' => $this->users['member']->id, 'content' => 'Ore pela minha família e pelo nosso bairro.'], ['church_id' => $this->church->id]);
        PrayerRequest::query()->updateOrCreate(['user_id' => null, 'content' => 'Pedido anônimo de intercessão.'], ['church_id' => $this->church->id]);
    }

    private function seedSupportingModules(): void
    {
        $library = Library::query()->updateOrCreate(['church_id' => $this->church->id, 'title' => 'Evangelho de João'], ['description' => 'Leitura guiada para a comunidade.', 'type' => 'book', 'file_path' => 'seeders/evangelho-joao.pdf']);
        $library->categories()->syncWithoutDetaching($this->categoryIds(CategoryType::LIBRARY));
        Vercicle::query()->updateOrCreate(['library_id' => $library->id, 'book' => 'João', 'chapter' => '3', 'verse' => '16'], ['content' => 'Porque Deus amou o mundo de tal maneira...', 'version' => 'NAA']);
        AiQuery::query()->updateOrCreate(['church_id' => $this->church->id, 'provider' => 'openai', 'model' => 'gpt-4.1-mini', 'input' => 'Traduzir o título da postagem de boas-vindas.'], ['response' => 'Welcome to our community', 'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8, 'total_tokens' => 20], 'status' => 'completed', 'type' => 'translation']);
    }

    /** @return array<int, string> */
    private function categoryIds(CategoryType $type): array
    {
        return Category::query()->where('church_id', $this->church->id)->where('type', $type->value)->pluck('id')->take(2)->all();
    }
}
