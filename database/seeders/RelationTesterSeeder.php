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
use App\Models\EventMaterial;
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
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RelationTesterSeeder extends Seeder
{
    private Community $community;

    /** @var array<string, Church> */
    private array $churches = [];

    /** @var array<string, User> */
    private array $users = [];

    public function run(): void
    {
        $this->seedOrganization();
        $this->seedUsers();
        $this->community->update(['owner_id' => $this->users['system']->id]);
        $this->seedBranding();
        $this->seedMachadinhoContent();
        $this->seedPibContent();
        $this->seedAriquemesContent();
        $this->seedSupportingModules();
    }

    private function seedOrganization(): void
    {
        $this->community = Community::query()->updateOrCreate(
            ['slug' => 'comunidade-crista-de-rondonia'],
            [
                'name' => 'Comunidade Cristã de Rondônia',
                'description' => 'Uma rede cristã que aproxima igrejas, congregações e famílias de diferentes cidades de Rondônia.',
                'found_date' => '1998-04-12',
                'bible_versions' => ['pt-br-nvi', 'pt-br-nvt', 'pt-almeida-1911'],
                'default_bible_version' => 'pt-br-nvi',
            ],
        );

        $churches = [
            'ad_headquarters' => [
                'slug' => 'assembleia-de-deus-machadinho-doeste',
                'name' => "Igreja Evangélica Assembleia de Deus - Machadinho d'Oeste",
                'found_date' => '1988-06-19',
                'domain' => domainBase('ad-machadinho'),
                'address' => ["Machadinho d'Oeste", 'Centro', 'Avenida Castelo Branco', '2180', '76868-000', -9.44363, -61.98185],
            ],
            'ad_congregation' => [
                'slug' => 'congregacao-ad-bom-futuro',
                'name' => 'Congregação Assembleia de Deus Bom Futuro',
                'found_date' => '2014-09-07',
                'domain' => domainBase('ad-bom-futuro'),
                'address' => ["Machadinho d'Oeste", 'Bom Futuro', 'Rua Rio de Janeiro', '640', '76868-000', -9.43872, -61.98711],
            ],
            'pib' => [
                'slug' => 'primeira-igreja-batista-ji-parana',
                'name' => 'Primeira Igreja Batista de Ji-Paraná',
                'found_date' => '1978-11-05',
                'domain' => domainBase('pib-jipa'),
                'address' => ['Ji-Paraná', 'Centro', 'Avenida Marechal Rondon', '1320', '76900-082', -10.87772, -61.93218],
            ],
            'ariquemes' => [
                'slug' => 'igreja-batista-esperanca-ariquemes',
                'name' => 'Igreja Batista Esperança de Ariquemes',
                'found_date' => '2006-03-18',
                'domain' => domainBase('batista-ariquemes'),
                'address' => ['Ariquemes', 'Setor 02', 'Avenida Canaã', '2875', '76870-140', -9.91325, -63.04081],
            ],
        ];

        foreach ($churches as $key => $data) {
            $this->churches[$key] = Church::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'community_id' => $this->community->id,
                    'status' => 'active',
                    'found_date' => $data['found_date'],
                    'domain' => $data['domain'],
                ],
            );
            [$city, $neighborhood, $street, $number, $zipcode, $latitude, $longitude] = $data['address'];
            Address::query()->updateOrCreate(
                ['addressable_type' => Church::class, 'addressable_id' => $this->churches[$key]->id],
                compact('city', 'neighborhood', 'street', 'number', 'zipcode', 'latitude', 'longitude') + [
                    'country' => 'Brasil',
                    'state' => 'RO',
                ],
            );
        }

        Network::query()->updateOrCreate(
            [
                'parent_church_id' => $this->churches['ad_headquarters']->id,
                'child_church_id' => $this->churches['ad_congregation']->id,
            ],
            ['community_id' => $this->community->id],
        );
    }

    private function seedUsers(): void
    {
        $users = [
            'guest' => ['visitante@nossacasa.test', 'Visitante', 'Convidado', UserRole::GUEST, 'ad_headquarters', 'female', '1994-03-17', '+55 69 98401-1001'],
            'system' => [config('app.system_user.email'), 'Sistema', 'Nossa Casa', UserRole::SYSTEM, 'ad_headquarters', 'male', '2000-01-01', '+55 69 98401-1002'],
            'superadmin' => ['superadmin@nossacasa.test', 'Eduardo', 'Ferreira', UserRole::SUPERADMIN, 'ad_headquarters', 'male', '1987-05-21', '+55 69 98401-1003'],
            'church_leader' => ['church_leader@nossacasa.test', 'Pr. Daniel', 'Rodrigues', UserRole::CHURCH_LEADER, 'ad_headquarters', 'male', '1975-08-12', '+55 69 98401-1004'],
            'leader' => ['leader@nossacasa.test', 'Ana Paula', 'Martins', UserRole::LEADER, 'ad_headquarters', 'female', '1989-02-24', '+55 69 98401-1005'],
            'media' => ['media@nossacasa.test', 'Lucas', 'Alves', UserRole::MEDIA, 'ad_headquarters', 'male', '1997-10-03', '+55 69 98401-1006'],
            'member' => ['member@nossacasa.test', 'Marcos', 'Santos', UserRole::MEMBER, 'ad_headquarters', 'male', '1991-06-11', '+55 69 98401-1007'],
            'child' => ['child@nossacasa.test', 'Sofia', 'Santos', UserRole::MEMBER, 'ad_headquarters', 'female', now()->subYears(8)->toDateString(), '+55 69 98401-1008'],
            'pib_leader' => ['pastor.pib@nossacasa.test', 'Pr. Rafael', 'Oliveira', UserRole::CHURCH_LEADER, 'pib', 'male', '1981-01-30', '+55 69 98402-2001'],
            'pib_media' => ['midia.pib@nossacasa.test', 'Camila', 'Souza', UserRole::MEDIA, 'pib', 'female', '1995-07-16', '+55 69 98402-2002'],
            'pib_member' => ['membro.pib@nossacasa.test', 'Juliana', 'Costa', UserRole::MEMBER, 'pib', 'female', '1990-12-04', '+55 69 98402-2003'],
            'pib_child' => ['crianca.pib@nossacasa.test', 'Miguel', 'Costa', UserRole::MEMBER, 'pib', 'male', now()->subYears(6)->toDateString(), '+55 69 98402-2004'],
            'ariquemes_leader' => ['pastor.ariquemes@nossacasa.test', 'Pr. André', 'Mendes', UserRole::CHURCH_LEADER, 'ariquemes', 'male', '1983-09-08', '+55 69 98403-3001'],
            'ariquemes_media' => ['midia.ariquemes@nossacasa.test', 'Renata', 'Lima', UserRole::MEDIA, 'ariquemes', 'female', '1993-04-27', '+55 69 98403-3002'],
            'ariquemes_member' => ['membro.ariquemes@nossacasa.test', 'Paulo', 'Nunes', UserRole::MEMBER, 'ariquemes', 'male', '1988-11-19', '+55 69 98403-3003'],
        ];

        foreach ($users as $key => [$email, $firstName, $lastName, $role, $churchKey, $gender, $birthDate, $phone]) {
            $this->users[$key] = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'password' => Hash::make($role === UserRole::SYSTEM ? config('app.system_user.password') : 'password'),
                    'birth_date' => $birthDate,
                    'role' => $role->value,
                ],
            );
            $this->users[$key]->profile()->updateOrCreate(
                ['user_id' => $this->users[$key]->id],
                [
                    'church_id' => $this->churches[$churchKey]->id,
                    'community_id' => $this->community->id,
                    'phone' => $phone,
                    'location_lang' => 'pt-BR',
                    'gender' => $gender,
                ],
            );
        }

        foreach ([
            ['member', 'child', UserRelationships::PARENT],
            ['child', 'member', UserRelationships::CHILD],
            ['member', 'leader', UserRelationships::FRIEND],
        ] as [$user, $relatedUser, $type]) {
            UserRelationship::query()->updateOrCreate([
                'user_id' => $this->users[$user]->id,
                'related_user_id' => $this->users[$relatedUser]->id,
                'relationship_type' => $type->value,
            ]);
        }
    }

    private function seedBranding(): void
    {
        $themes = [
            'ad_headquarters' => [
                'initials' => 'AD', 'primary' => '#123B6D', 'secondary' => '#B78A2B', 'accent' => '#D9B44A', 'surface' => '#F5F1E8', 'icon' => 'Flame',
                'brand' => "Assembleia de Deus Machadinho d'Oeste", 'tagline' => 'Uma igreja pentecostal, acolhedora e missionária',
                'title' => 'Uma família para pertencer, uma missão para viver', 'subtitle' => 'Cultos, discipulado e cuidado com as famílias de Machadinho.',
                'email' => 'contato@admachadinho.test', 'phone' => '+55 69 3581-2100', 'whatsapp' => '+55 69 98401-1004',
                'address' => "Avenida Castelo Branco, 2180 - Centro, Machadinho d'Oeste - RO",
                'schedule' => [['Escola Bíblica Dominical', 0, '08:30', '10:00'], ['Culto de Celebração', 0, '19:00', '21:00'], ['Culto de Ensino', 3, '19:30', '21:00']],
            ],
            'ad_congregation' => [
                'initials' => 'BF', 'primary' => '#174A7E', 'secondary' => '#B78A2B', 'accent' => '#E6BE55', 'surface' => '#F8F4EA', 'icon' => 'FlameKindling',
                'brand' => 'AD Congregação Bom Futuro', 'tagline' => 'Servindo a Deus e ao nosso bairro', 'title' => 'Fé que acolhe e transforma',
                'subtitle' => 'Uma congregação da Assembleia de Deus em Machadinho.', 'email' => 'bomfuturo@admachadinho.test', 'phone' => '+55 69 3581-2140',
                'whatsapp' => '+55 69 98401-1040', 'address' => "Rua Rio de Janeiro, 640 - Bom Futuro, Machadinho d'Oeste - RO",
                'schedule' => [['Culto da Família', 0, '19:00', '20:45'], ['Círculo de Oração', 2, '19:30', '21:00']],
            ],
            'pib' => [
                'initials' => 'PIB', 'primary' => '#3B1F5E', 'secondary' => '#7B4FA3', 'accent' => '#E0A43A', 'surface' => '#F7F3FA', 'icon' => 'Church',
                'brand' => 'PIB Ji-Paraná', 'tagline' => 'Amar a Deus, cuidar de pessoas e servir a cidade', 'title' => 'Você é bem-vindo à nossa família',
                'subtitle' => 'Uma igreja bíblica, simples e presente em Ji-Paraná.', 'email' => 'contato@pibjipa.test', 'phone' => '+55 69 3421-1800',
                'whatsapp' => '+55 69 98402-2001', 'address' => 'Avenida Marechal Rondon, 1320 - Centro, Ji-Paraná - RO',
                'schedule' => [['Celebração', 0, '18:30', '20:30'], ['PG nos lares', 4, '19:30', '21:00']],
            ],
            'ariquemes' => [
                'initials' => 'IBE', 'primary' => '#0D5C4D', 'secondary' => '#2C7A68', 'accent' => '#D69E2E', 'surface' => '#EFF8F4', 'icon' => 'HeartHandshake',
                'brand' => 'Batista Esperança Ariquemes', 'tagline' => 'Esperança para a cidade, graça para cada casa', 'title' => 'Caminhando juntos com Jesus',
                'subtitle' => 'Comunhão, ensino e serviço no coração de Ariquemes.', 'email' => 'contato@batistaesperanca.test', 'phone' => '+55 69 3535-3100',
                'whatsapp' => '+55 69 98403-3001', 'address' => 'Avenida Canaã, 2875 - Setor 02, Ariquemes - RO',
                'schedule' => [['Culto de Comunhão', 0, '19:00', '20:45'], ['Noite de Oração', 3, '19:30', '20:45']],
            ],
        ];

        foreach ($themes as $churchKey => $theme) {
            $church = $this->churches[$churchKey];
            $logoPath = "church/{$church->id}/branding/demo-logo.svg";
            $iconPath = "church/{$church->id}/branding/demo-icon.svg";
            $disk = Storage::disk((string) config('media.disk'));
            $disk->put($logoPath, $this->brandSvg($church->name, $theme['initials'], $theme['primary'], $theme['accent'], false));
            $disk->put($iconPath, $this->brandSvg($church->name, $theme['initials'], $theme['primary'], $theme['accent'], true));
            $setting = $church->settings()->firstOrFail();
            $options = is_array($setting->options) ? $setting->options : [];
            $savedBranding = is_array($options['branding'] ?? null) ? $options['branding'] : [];
            $options['branding'] = array_merge($savedBranding, [
                'brand_name' => $theme['brand'], 'tagline' => $theme['tagline'], 'banner_title' => $theme['title'], 'banner_subtitle' => $theme['subtitle'],
                'primary_color' => $theme['primary'], 'secondary_color' => $theme['secondary'], 'accent_color' => $theme['accent'], 'surface_color' => $theme['surface'],
                'icon_name' => $theme['icon'], 'logo_path' => $logoPath, 'icon_path' => $iconPath, 'contact_email' => $theme['email'],
                'contact_phone' => $theme['phone'], 'contact_whatsapp' => $theme['whatsapp'], 'address' => $theme['address'],
                'weekly_schedule' => array_map(fn (array $item): array => ['title' => $item[0], 'day_of_week' => $item[1], 'start_time' => $item[2], 'end_time' => $item[3]], $theme['schedule']),
            ]);
            $options['bible'] = ['versions' => ['pt-br-nvi', 'pt-br-nvt', 'pt-almeida-1911'], 'default_version' => 'pt-br-nvi'];
            $setting->update(['options' => $options]);
        }
    }

    private function seedMachadinhoContent(): void
    {
        $church = $this->churches['ad_headquarters'];
        $familyMedia = $this->seedMedia($church, 'conferencia-familia', 'Conferência da Família', 'Um fim de semana para fortalecer os lares.', 'media', '#123B6D', '#D9B44A');
        $missionsMedia = $this->seedMedia($church, 'missoes-ribeirinhas', 'Missões Ribeirinhas', 'Registro da ação missionária e social.', 'media', '#173F73', '#F0C75E');
        $familyPost = $this->seedPost($church, 'familias-firmes-em-cristo-machadinho', 'Conferência da Família: lares firmes em Cristo', 'Serão três encontros com louvor, palavra, atividades para crianças e uma conversa especial para casais. Faça sua inscrição pelo formulário desta publicação.', $this->users['media'], $familyMedia, 4);
        $this->seedPost($church, 'acao-missionaria-ribeirinha-machadinho', 'Ação missionária reúne alimentos e voluntários', 'Nossa equipe visitará famílias da zona rural com cestas, atendimento e uma programação para as crianças. Participe doando alimentos ou servindo como voluntário.', $this->users['media'], $missionsMedia, 9);
        $familyEvent = $this->seedEvent($church, 'conferencia-da-familia-machadinho', 'Conferência da Família 2026', 'Um fim de semana de cuidado, ensino e comunhão para todas as gerações.', now()->addDays(18)->setTime(19, 0), now()->addDays(20)->setTime(21, 0), $this->users['leader'], ['família', 'casais', 'crianças'], $familyMedia);
        $this->seedEvent($church, 'culto-de-celebracao-ad-machadinho', 'Culto de Celebração', 'Celebração dominical com louvor, oração, mensagem bíblica e programação infantil.', now()->next('Sunday')->setTime(19, 0), now()->next('Sunday')->setTime(21, 0), $this->users['church_leader'], ['culto', 'família'], $familyMedia);
        $this->seedEvent($church, 'acao-missionaria-rio-machadinho', 'Ação Missionária Rio Machadinho', 'Dia de serviço, evangelismo e apoio a famílias da região.', now()->addMonth()->setTime(7, 30), now()->addMonth()->setTime(17, 0), $this->users['leader'], ['missões', 'ação social'], $missionsMedia);

        $form = $this->seedRegistrationForm($church, 'Inscrição - Conferência da Família 2026', 'Informe os participantes e necessidades de apoio para a equipe preparar sua recepção.');
        $form->events()->syncWithoutDetaching([$familyEvent->id]);
        $form->posts()->syncWithoutDetaching([$familyPost->id]);
        FormResponse::query()->updateOrCreate(
            ['form_id' => $form->id, 'user_id' => $this->users['member']->id],
            ['answers' => ['full_name' => 'Marcos Santos', 'email' => 'member@nossacasa.test', 'phone' => '+55 69 98401-1007', 'participants' => '2 adultos e 1 criança']],
        );
        $familyEvent->users()->syncWithoutDetaching([$this->users['member']->id]);
        $familyEvent->confirmations()->updateOrCreate(['user_id' => $this->users['member']->id], ['check_in_at' => now()->subMinutes(45), 'check_out_at' => now()->subMinutes(5)]);
        $comment = $familyPost->comments()->updateOrCreate(['user_id' => $this->users['member']->id, 'content' => 'Nossa família já está inscrita. Vai ser um tempo muito especial!']);
        $comment->replies()->updateOrCreate(['user_id' => $this->users['leader']->id, 'content' => 'Que alegria, Marcos! Estamos preparando tudo com muito carinho.']);
        $familyPost->reactions()->updateOrCreate(['user_id' => $this->users['member']->id, 'content' => '❤️', 'type' => 'emoji']);
        $familyMedia->reactions()->updateOrCreate(['user_id' => $this->users['leader']->id, 'content' => '🙏', 'type' => 'emoji']);
        Highlight::query()->updateOrCreate(['highlightable_type' => Event::class, 'highlightable_id' => $familyEvent->id], ['church_id' => $church->id, 'order' => 1]);
        Highlight::query()->updateOrCreate(['highlightable_type' => Post::class, 'highlightable_id' => $familyPost->id], ['church_id' => $church->id, 'order' => 2]);
        Translation::query()->updateOrCreate(
            ['translatable_type' => Post::class, 'translatable_id' => $familyPost->id, 'translatable_column' => 'title', 'locale' => 'en'],
            ['content_original' => $familyPost->title, 'content' => 'Family Conference: homes grounded in Christ'],
        );
        $kids = $this->seedClassroom($church, 'AD Kids - 7 a 10 anos', 'Salinha infantil com estudo bíblico e retirada protegida por PIN.', $this->users['leader'], 7, 10, true, 25);
        $discipleship = $this->seedClassroom($church, 'Fundamentos da Fé', 'Classe de integração e discipulado para novos membros.', $this->users['church_leader'], 16, null, false, 35);
        $women = Classroom::query()->updateOrCreate(
            ['church_id' => $church->id, 'name' => 'Mulheres de Fé'],
            ['description' => 'Encontro quinzenal de estudo, oração e comunhão.', 'min_age' => 18, 'max_age' => 75, 'gender_restriction' => 'female', 'is_kids' => false, 'max_members' => 30, 'teacher_id' => $this->users['leader']->id],
        );
        $women->categories()->syncWithoutDetaching($this->categoryIds($church, CategoryType::CLASSROOM));
        $kids->members()->syncWithoutDetaching([$this->users['child']->id]);
        $discipleship->members()->syncWithoutDetaching([$this->users['member']->id]);
        $women->members()->syncWithoutDetaching([$this->users['leader']->id]);
        $discipleship->posts()->syncWithoutDetaching([$familyPost->id]);
        ClassroomPresence::query()->updateOrCreate(['classroom_id' => $kids->id, 'user_id' => $this->users['child']->id, 'check_out' => null], ['check_in' => now(), 'checkout_pin' => Hash::make('123456'), 'pin_generated_at' => now()]);
        ClassroomPresence::query()->updateOrCreate(['classroom_id' => $discipleship->id, 'user_id' => $this->users['member']->id], ['check_in' => now()->subHour(), 'check_out' => now()]);
        PrayerRequest::query()->updateOrCreate(['user_id' => $this->users['member']->id, 'content' => 'Ore por nossa família e pela conferência que se aproxima.'], ['church_id' => $church->id]);
        PrayerRequest::query()->updateOrCreate(['user_id' => null, 'content' => 'Pedido anônimo por uma oportunidade de trabalho.'], ['church_id' => $church->id]);

        $congregation = $this->churches['ad_congregation'];
        $congregationMedia = $this->seedMedia($congregation, 'culto-familia', 'Culto da Família', 'Celebração da congregação Bom Futuro.', 'media', '#174A7E', '#E6BE55');
        $this->seedPost($congregation, 'programacao-congregacao-bom-futuro', 'Programação da Congregação Bom Futuro', 'Confira nossos cultos semanais, círculo de oração e encontros nos lares do bairro.', $this->users['media'], $congregationMedia, 2);
        $this->seedEvent($congregation, 'culto-da-familia-bom-futuro', 'Culto da Família - Bom Futuro', 'Uma noite de louvor, oração e palavra para toda a família.', now()->addDays(10)->setTime(19, 0), now()->addDays(10)->setTime(20, 45), $this->users['leader'], ['família', 'congregação'], $congregationMedia);
    }

    private function seedPibContent(): void
    {
        $church = $this->churches['pib'];
        $kidsMedia = $this->seedMedia($church, 'pib-kids', 'PIB Kids', 'Um espaço seguro para aprender, brincar e crescer na fé.', 'pib_media', '#3B1F5E', '#E0A43A');
        $youthMedia = $this->seedMedia($church, 'conexao-jovem', 'Conexão Jovem', 'Encontro de adolescentes e jovens da PIB.', 'pib_media', '#5B2C83', '#F2B84B');
        $kidsPost = $this->seedPost($church, 'nova-salinha-pib-kids-ji-parana', 'Conheça a nova salinha PIB Kids', 'A salinha Sementinhas recebeu novos materiais, identificação segura e equipes preparadas para acolher as crianças durante a celebração.', $this->users['pib_media'], $kidsMedia, 3);
        $this->seedPost($church, 'conexao-jovem-pib-ji-parana', 'Conexão Jovem: perguntas que importam', 'Uma noite de música, conversa franca e estudo bíblico para adolescentes e jovens. Convide um amigo e confirme presença.', $this->users['pib_media'], $youthMedia, 6);
        $kidsEvent = $this->seedEvent($church, 'tarde-divertida-pib-kids', 'Tarde Divertida PIB Kids', 'Brincadeiras, histórias bíblicas e lanche para crianças de 4 a 10 anos.', now()->addDays(12)->setTime(15, 0), now()->addDays(12)->setTime(18, 0), $this->users['pib_leader'], ['crianças', 'família'], $kidsMedia);
        $this->seedEvent($church, 'encontro-conexao-jovem-jipa', 'Encontro Conexão Jovem', 'Louvor, bate-papo e estudo bíblico para jovens de Ji-Paraná.', now()->addDays(8)->setTime(19, 30), now()->addDays(8)->setTime(22, 0), $this->users['pib_leader'], ['jovens', 'comunhão'], $youthMedia);
        $kidsForm = $this->seedRegistrationForm($church, 'Inscrição - Tarde Divertida PIB Kids', 'Dados da criança e do responsável autorizado para retirada.');
        $kidsForm->events()->syncWithoutDetaching([$kidsEvent->id]);
        $kidsForm->posts()->syncWithoutDetaching([$kidsPost->id]);
        FormResponse::query()->updateOrCreate(
            ['form_id' => $kidsForm->id, 'user_id' => $this->users['pib_member']->id],
            ['answers' => ['full_name' => 'Miguel Costa', 'email' => 'membro.pib@nossacasa.test', 'phone' => '+55 69 98402-2003', 'participants' => '1 criança']],
        );
        $kids = $this->seedClassroom($church, 'Salinha Sementinhas - 4 a 7 anos', 'Ambiente infantil com check-in e retirada segura.', $this->users['pib_leader'], 4, 7, true, 18);
        $youth = $this->seedClassroom($church, 'Conexão Jovem', 'Classe semanal para adolescentes e jovens.', $this->users['pib_leader'], 13, 24, false, 35);
        $kids->members()->syncWithoutDetaching([$this->users['pib_child']->id]);
        $youth->members()->syncWithoutDetaching([$this->users['pib_member']->id]);
        ClassroomPresence::query()->updateOrCreate(['classroom_id' => $kids->id, 'user_id' => $this->users['pib_child']->id, 'check_out' => null], ['check_in' => now(), 'checkout_pin' => Hash::make('654321'), 'pin_generated_at' => now()]);
        $this->seedPibShowcase($church, $kidsEvent);
    }

    private function seedPibShowcase(Church $church, Event $kidsEvent): void
    {
        $familiesMedia = $this->seedMedia($church, 'familias-em-missao', 'Familias em Missao', 'Conteudos e encontros para fortalecer os lares.', 'pib_media', '#402064', '#D99B38');
        $worshipMedia = $this->seedMedia($church, 'celebracao-pib', 'Celebracao PIB', 'Registros das celebracoes e da vida comunitaria.', 'pib_media', '#2E174A', '#E8B557');
        $kidsMedia = $kidsEvent->medias()->firstOrFail();

        $showcasePosts = [
            ['agenda-de-fevereiro-pib', 'Agenda de fevereiro da PIB', 'Confira os cultos, pequenos grupos, encontros de discipulado e oportunidades de servir neste mes.', $this->users['pib_leader'], $worshipMedia, 1],
            ['pequenos-grupos-nos-lares-pib', 'Pequenos grupos: uma mesa para pertencer', 'Os pequenos grupos da PIB estao recebendo novas familias. Encontre uma casa perto de voce e venha caminhar conosco.', $this->users['pib_leader'], $familiesMedia, 4],
            ['voluntarios-pib-kids', 'Voluntarios PIB Kids: treinamento concluido', 'Nossa equipe concluiu o treinamento de acolhimento, primeiros socorros e protocolos de retirada segura das criancas.', $this->users['pib_media'], $kidsMedia, 7],
            ['devocional-semanal-pib', 'Devocional da semana: esperanca que permanece', 'Uma leitura curta para fazer em familia, conversar no pequeno grupo e praticar durante a semana.', $this->users['pib_leader'], $familiesMedia, 9],
            ['acao-social-pib-ji-parana', 'Acao social: cestas e escuta', 'Neste sabado vamos reunir alimentos, roupas e voluntarios para servir familias de Ji-Parana.', $this->users['pib_media'], $familiesMedia, 11],
            ['musica-e-liturgia-pib', 'Musica e liturgia: preparando a celebracao', 'Conheca a cancao da semana e ore pela equipe que conduz cada celebracao.', $this->users['pib_media'], $worshipMedia, 13],
            ['boas-vindas-a-novos-membros-pib', 'Boas-vindas aos novos membros', 'Celebramos cada pessoa que decidiu caminhar com a PIB. Fale com nossa equipe de integracao para conhecer os proximos passos.', $this->users['pib_leader'], $familiesMedia, 15],
        ];

        $posts = [];
        foreach ($showcasePosts as [$slug, $title, $content, $author, $media, $publishedDaysAgo]) {
            $posts[] = $this->seedPost($church, $slug, $title, $content, $author, $media, $publishedDaysAgo);
        }

        $conference = $this->seedEvent($church, 'conferencia-familias-pib-2026', 'Conferencia Familias PIB 2026', 'Um sabado inteiro de cuidado, conversas praticas, louvor e espaco para todas as geracoes.', now()->addDays(24)->setTime(9, 0), now()->addDays(24)->setTime(18, 0), $this->users['pib_leader'], ['familia', 'formacao', 'comunhao'], $familiesMedia);
        $retreat = $this->seedEvent($church, 'retiro-pib-2026', 'Retiro PIB: Presenca e Proposito', 'Um fim de semana para desacelerar, ouvir a Deus e renovar os relacionamentos.', now()->addDays(42)->setTime(18, 0), now()->addDays(44)->setTime(12, 0), $this->users['pib_leader'], ['retiro', 'discipulado'], $worshipMedia);

        $form = Form::query()->updateOrCreate(
            ['church_id' => $church->id, 'title' => 'Inscricao - Conferencia Familias PIB 2026'],
            [
                'description' => 'Conte para nossa equipe quem participara, quais oficinas deseja fazer e como podemos acolher melhor sua familia.',
                'schema' => ['fields' => [
                    ['id' => 'heading', 'type' => 'heading', 'label' => 'Dados do participante'],
                    ['id' => 'name', 'type' => 'text', 'name' => 'full_name', 'label' => 'Nome completo', 'required' => true, 'placeholder' => 'Como devemos chamar voce?', 'width' => 'half', 'mobile_width' => 'full'],
                    ['id' => 'email', 'type' => 'email', 'name' => 'email', 'label' => 'E-mail', 'required' => true, 'width' => 'half', 'mobile_width' => 'full'],
                    ['id' => 'phone', 'type' => 'phone', 'name' => 'phone', 'label' => 'WhatsApp', 'required' => true, 'width' => 'half', 'mobile_width' => 'full'],
                    ['id' => 'city', 'type' => 'text', 'name' => 'city', 'label' => 'Cidade / bairro', 'required' => true, 'width' => 'half', 'mobile_width' => 'full'],
                    ['id' => 'family-heading', 'type' => 'heading', 'label' => 'Organizacao da familia'],
                    ['id' => 'participants', 'type' => 'select', 'name' => 'participants', 'label' => 'Quantas pessoas participarao?', 'required' => true, 'options' => [['label' => '1 pessoa', 'value' => '1'], ['label' => '2 pessoas', 'value' => '2'], ['label' => '3 a 4 pessoas', 'value' => '3-4'], ['label' => '5 ou mais', 'value' => '5+']], 'width' => 'half', 'mobile_width' => 'full'],
                    ['id' => 'children', 'type' => 'text', 'name' => 'children', 'label' => 'Criancas e idades', 'placeholder' => 'Ex.: Miguel, 6 anos', 'width' => 'half', 'mobile_width' => 'full'],
                    ['id' => 'workshop', 'type' => 'radio', 'name' => 'workshop', 'label' => 'Oficina de interesse', 'required' => true, 'options' => [['label' => 'Casamento e dialogo', 'value' => 'casamento'], ['label' => 'Parentalidade', 'value' => 'parentalidade'], ['label' => 'Financas no lar', 'value' => 'financas']], 'width' => 'full', 'mobile_width' => 'full'],
                    ['id' => 'accessibility', 'type' => 'textarea', 'name' => 'accessibility', 'label' => 'Necessidade de acessibilidade ou alimentacao?', 'width' => 'full', 'mobile_width' => 'full', 'size' => 'fixed', 'height' => 3],
                    ['id' => 'consent', 'type' => 'checkbox', 'name' => 'consent', 'label' => 'Autorizacao', 'required' => true, 'placeholder' => 'Li e concordo com o uso dos dados para este evento.', 'width' => 'full', 'mobile_width' => 'full'],
                ]],
            ],
        );
        $form->categories()->syncWithoutDetaching($this->categoryIds($church, CategoryType::FORM));
        $form->events()->syncWithoutDetaching([$conference->id]);
        $form->posts()->syncWithoutDetaching([$posts[0]->id]);
        FormResponse::query()->updateOrCreate(
            ['form_id' => $form->id, 'user_id' => $this->users['pib_member']->id],
            ['answers' => ['full_name' => 'Juliana Costa', 'email' => 'membro.pib@nossacasa.test', 'phone' => '+55 69 98402-2003', 'city' => 'Ji-Parana - Centro', 'participants' => '3-4', 'children' => 'Miguel, 6 anos', 'workshop' => 'parentalidade', 'accessibility' => 'Nenhuma', 'consent' => true]],
        );

        $conference->users()->syncWithoutDetaching([$this->users['pib_member']->id]);
        $retreat->users()->syncWithoutDetaching([$this->users['pib_member']->id => ['status' => 'confirmed']]);
        foreach ([
            ['Mapa e horarios do retiro', 'mapa-horarios', $this->users['pib_leader']],
            ['Lista do que levar', 'lista-do-que-levar', $this->users['pib_media']],
            ['Playlist de preparacao', 'playlist-preparacao', $this->users['pib_media']],
        ] as [$title, $slug, $addedBy]) {
            $path = "seeders/demo/{$church->slug}/events/retiro/{$slug}.svg";
            $contents = $this->posterSvg($title, $church->name, '#3B1F5E', '#E0A43A');
            Storage::disk((string) config('media.disk'))->put($path, $contents);
            EventMaterial::query()->updateOrCreate(
                ['event_id' => $retreat->id, 'title' => $title],
                ['added_by_id' => $addedBy->id, 'type' => 'file', 'file_path' => $path, 'disk' => (string) config('media.disk'), 'mimetype' => 'image/svg+xml', 'size' => strlen($contents)],
            );
        }

        $privatePost = $this->seedPost($church, 'retiro-pib-orientacoes', 'Retiro PIB: orientacoes para participantes', 'Confira a lista de itens, horarios de chegada e o mapa do local do retiro.', $this->users['pib_leader'], $worshipMedia, 1);
        $privatePost->update(['is_event_private' => true]);
        $retreat->privatePosts()->syncWithoutDetaching([$privatePost->id]);

        $this->seedPibLibrary($church);
        $this->seedPostInteractions($posts[0], [$this->users['pib_member'], $this->users['pib_leader'], $this->users['pib_child']], ['A equipe preparou tudo com muito carinho.', 'Que alegria ver a salinha crescendo!', 'Miguel esta contando os dias.']);
        $this->seedPostInteractions($posts[1], [$this->users['pib_member'], $this->users['pib_media']], ['Ja confirmei minha presenca!', 'Vamos levar mais amigos.']);
        $this->seedPostInteractions($privatePost, [$this->users['pib_member'], $this->users['pib_leader']], ['Tudo pronto para o retiro.', 'Nos vemos la!']);
    }

    private function seedPibLibrary(Church $church): void
    {
        $items = [
            ['manual-acolhimento-pib', 'Manual de acolhimento PIB', 'Guia pratico para equipes de recepcao.', 'guide'],
            ['devocionais-familia', 'Devocionais para fazer em familia', 'Leituras curtas para sete encontros em casa.', 'devotional'],
            ['trilha-novos-membros', 'Trilha de novos membros', 'Material de integracao, batismo e vida comunitaria.', 'course'],
            ['guia-pequenos-grupos', 'Guia dos pequenos grupos', 'Roteiros de conversa e oracao para os lares.', 'guide'],
            ['caderno-pib-kids', 'Caderno PIB Kids', 'Atividades para acompanhar as historias biblicas.', 'kids'],
            ['roteiro-musica-liturgia', 'Roteiro de musica e liturgia', 'Orientacoes para equipes de celebracao.', 'worship'],
            ['cartilha-acao-social', 'Cartilha de acao social', 'Como servir com dignidade e responsabilidade.', 'social'],
            ['estudo-presenca-proposito', 'Estudo Presenca e Proposito', 'Preparacao para o retiro PIB 2026.', 'study'],
        ];

        foreach ($items as [$slug, $title, $description, $type]) {
            $path = "seeders/demo/{$church->slug}/library/{$slug}.svg";
            $contents = $this->posterSvg($title, $church->name, '#3B1F5E', '#E0A43A');
            Storage::disk((string) config('media.disk'))->put($path, $contents);
            $library = Library::query()->updateOrCreate(
                ['church_id' => $church->id, 'title' => $title],
                ['description' => $description, 'type' => $type, 'file_path' => $path],
            );
            $library->categories()->syncWithoutDetaching($this->categoryIds($church, CategoryType::LIBRARY));
        }
    }

    /** @param list<User> $users  @param list<string> $comments */
    private function seedPostInteractions(Post $post, array $users, array $comments): void
    {
        foreach ($users as $index => $user) {
            $comment = $post->comments()->updateOrCreate(
                ['user_id' => $user->id, 'content' => $comments[$index] ?? 'Que bom fazer parte desta comunidade!'],
            );
            $comment->reactions()->updateOrCreate(['user_id' => $user->id], ['content' => $index % 2 === 0 ? 'heart' : 'like', 'type' => 'emoji']);
            $post->reactions()->updateOrCreate(['user_id' => $user->id], ['content' => $index % 2 === 0 ? 'heart' : 'celebrate', 'type' => 'emoji']);
        }
    }

    private function seedAriquemesContent(): void
    {
        $church = $this->churches['ariquemes'];
        $media = $this->seedMedia($church, 'cafe-com-esperanca', 'Café com Esperança', 'Uma manhã de acolhimento e serviço à comunidade.', 'ariquemes_media', '#0D5C4D', '#D69E2E');
        $post = $this->seedPost($church, 'cafe-com-esperanca-ariquemes', 'Café com Esperança para novos moradores', 'Uma manhã para conhecer pessoas, apresentar os pequenos grupos e conectar novos moradores a oportunidades de serviço na cidade.', $this->users['ariquemes_media'], $media, 5);
        $event = $this->seedEvent($church, 'cafe-com-esperanca-arq', 'Café com Esperança', 'Café da manhã, música e boas conversas para quem chegou recentemente a Ariquemes.', now()->addDays(15)->setTime(8, 30), now()->addDays(15)->setTime(11, 0), $this->users['ariquemes_leader'], ['acolhimento', 'comunhão'], $media);
        $form = $this->seedRegistrationForm($church, 'Confirmação - Café com Esperança', 'Confirme sua presença e conte como podemos receber você melhor.');
        $form->events()->syncWithoutDetaching([$event->id]);
        $form->posts()->syncWithoutDetaching([$post->id]);
    }

    private function seedSupportingModules(): void
    {
        $church = $this->churches['ad_headquarters'];
        $library = Library::query()->updateOrCreate(
            ['church_id' => $church->id, 'title' => 'Guia de acolhimento e integração'],
            ['description' => 'Material de apoio para equipes que recebem visitantes e novos membros.', 'type' => 'support-material', 'file_path' => 'seeders/demo/guia-acolhimento.pdf'],
        );
        $library->categories()->syncWithoutDetaching($this->categoryIds($church, CategoryType::LIBRARY));
        AiQuery::query()->updateOrCreate(
            ['church_id' => $church->id, 'provider' => 'openrouter', 'model' => 'inclusionai/ling-3.0-flash:free', 'input' => 'Traduzir o título da Conferência da Família.'],
            ['response' => 'Family Conference: homes grounded in Christ', 'usage' => ['prompt_tokens' => 14, 'completion_tokens' => 9, 'total_tokens' => 23], 'status' => 'completed', 'type' => 'translation'],
        );
    }

    private function seedPost(Church $church, string $slug, string $title, string $content, User $author, Media $media, int $publishedDaysAgo): Post
    {
        $post = Post::query()->updateOrCreate(
            ['slug' => $slug],
            ['church_id' => $church->id, 'author_id' => $author->id, 'title' => $title, 'content' => $content, 'published_at' => now()->subDays($publishedDaysAgo)],
        );
        $post->categories()->syncWithoutDetaching($this->categoryIds($church, CategoryType::POST));
        $post->medias()->syncWithoutDetaching([$media->id]);

        return $post;
    }

    /** @param list<string> $tags */
    private function seedEvent(Church $church, string $slug, string $title, string $description, CarbonInterface $startTime, CarbonInterface $endTime, User $author, array $tags, Media $media): Event
    {
        $event = Event::query()->updateOrCreate(
            ['slug' => $slug],
            ['church_id' => $church->id, 'author_id' => $author->id, 'title' => $title, 'description' => $description, 'tags' => $tags, 'start_time' => $startTime, 'end_time' => $endTime, 'cover_path' => $media->file_path],
        );
        $event->categories()->syncWithoutDetaching($this->categoryIds($church, CategoryType::EVENT));
        $event->medias()->syncWithoutDetaching([$media->id]);
        Calendar::query()->updateOrCreate(['calendarable_type' => Event::class, 'calendarable_id' => $event->id], ['church_id' => $church->id, 'date' => $event->start_time]);
        $churchAddress = $church->address()->firstOrFail();
        Address::query()->updateOrCreate(
            ['addressable_type' => Event::class, 'addressable_id' => $event->id],
            $churchAddress->only(['country', 'state', 'city', 'neighborhood', 'street', 'number', 'zipcode', 'latitude', 'longitude']),
        );

        return $event;
    }

    private function seedMedia(Church $church, string $slug, string $title, string $description, string $uploaderKey, string $primaryColor, string $accentColor): Media
    {
        $filePath = "seeders/demo/{$church->slug}/{$slug}.svg";
        $contents = $this->posterSvg($title, $church->name, $primaryColor, $accentColor);
        Storage::disk((string) config('media.disk'))->put($filePath, $contents);
        $media = Media::query()->updateOrCreate(
            ['file_path' => $filePath],
            [
                'uploader_id' => $this->users[$uploaderKey]->id, 'church_id' => $church->id, 'title' => $title, 'description' => $description,
                'mimetype' => 'image/svg+xml', 'size' => strlen($contents), 'gallery' => true, 'status' => MediaStatus::APPROVED->value, 'disk' => (string) config('media.disk'),
            ],
        );
        $media->categories()->syncWithoutDetaching($this->categoryIds($church, CategoryType::MEDIA));

        return $media;
    }

    private function seedRegistrationForm(Church $church, string $title, string $description): Form
    {
        $form = Form::query()->updateOrCreate(
            ['church_id' => $church->id, 'title' => $title],
            [
                'description' => $description,
                'schema' => ['fields' => [
                    ['id' => 'heading', 'type' => 'heading', 'label' => 'Dados para inscrição'],
                    ['id' => 'name', 'type' => 'text', 'name' => 'full_name', 'label' => 'Nome completo', 'required' => true, 'width' => 'full', 'mobile_width' => 'full', 'size' => 'auto'],
                    ['id' => 'email', 'type' => 'email', 'name' => 'email', 'label' => 'E-mail', 'required' => true, 'width' => 'half', 'mobile_width' => 'full', 'size' => 'auto'],
                    ['id' => 'phone', 'type' => 'text', 'name' => 'phone', 'label' => 'Telefone / WhatsApp', 'required' => true, 'width' => 'half', 'mobile_width' => 'full', 'size' => 'auto'],
                    ['id' => 'participants', 'type' => 'textarea', 'name' => 'participants', 'label' => 'Participantes e observações', 'required' => false, 'width' => 'full', 'mobile_width' => 'full', 'size' => 'fixed', 'height' => 3],
                ]],
            ],
        );
        $form->categories()->syncWithoutDetaching($this->categoryIds($church, CategoryType::FORM));

        return $form;
    }

    private function seedClassroom(Church $church, string $name, string $description, User $teacher, int $minimumAge, ?int $maximumAge, bool $isKids, int $maximumMembers): Classroom
    {
        $classroom = Classroom::query()->updateOrCreate(
            ['church_id' => $church->id, 'name' => $name],
            ['description' => $description, 'min_age' => $minimumAge, 'max_age' => $maximumAge, 'is_kids' => $isKids, 'max_members' => $maximumMembers, 'teacher_id' => $teacher->id],
        );
        $classroom->categories()->syncWithoutDetaching($this->categoryIds($church, CategoryType::CLASSROOM));

        return $classroom;
    }

    /** @return list<string> */
    private function categoryIds(Church $church, CategoryType $type): array
    {
        return Category::query()->where('church_id', $church->id)->where('type', $type->value)->pluck('id')->take(2)->all();
    }

    private function brandSvg(string $churchName, string $initials, string $primaryColor, string $accentColor, bool $iconOnly): string
    {
        $safeChurchName = htmlspecialchars($churchName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $safeInitials = htmlspecialchars($initials, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $width = $iconOnly ? 512 : 1280;
        $label = $iconOnly ? $safeInitials : $safeChurchName;
        $labelX = $iconOnly ? 256 : 790;
        $labelY = $iconOnly ? 452 : 278;
        $fontSize = $iconOnly ? 58 : 48;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="512" viewBox="0 0 {$width} 512" role="img" aria-label="{$safeChurchName}">
  <rect width="100%" height="100%" rx="72" fill="{$primaryColor}"/>
  <circle cx="256" cy="236" r="166" fill="{$accentColor}" opacity=".95"/>
  <path d="M256 94v280M188 184h136" stroke="#fff" stroke-width="28" stroke-linecap="round"/>
  <path d="M144 336c42-28 78-32 112-12 34-20 70-16 112 12" fill="none" stroke="#fff" stroke-width="20" stroke-linecap="round"/>
  <text x="{$labelX}" y="{$labelY}" fill="#fff" font-family="Arial, sans-serif" font-size="{$fontSize}" font-weight="700" text-anchor="middle">{$label}</text>
</svg>
SVG;
    }

    private function posterSvg(string $title, string $churchName, string $primaryColor, string $accentColor): string
    {
        $safeTitle = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $safeChurchName = htmlspecialchars($churchName, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900" viewBox="0 0 1600 900" role="img" aria-label="{$safeTitle}">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="{$primaryColor}"/><stop offset="1" stop-color="{$accentColor}"/></linearGradient></defs>
  <rect width="1600" height="900" fill="url(#g)"/><circle cx="1320" cy="160" r="300" fill="#fff" opacity=".1"/><circle cx="170" cy="820" r="360" fill="#fff" opacity=".08"/>
  <path d="M800 130v270M710 220h180" stroke="#fff" stroke-width="28" stroke-linecap="round" opacity=".9"/>
  <text x="800" y="560" fill="#fff" font-family="Arial, sans-serif" font-size="72" font-weight="700" text-anchor="middle">{$safeTitle}</text>
  <text x="800" y="650" fill="#fff" font-family="Arial, sans-serif" font-size="34" text-anchor="middle" opacity=".9">{$safeChurchName}</text>
</svg>
SVG;
    }
}
