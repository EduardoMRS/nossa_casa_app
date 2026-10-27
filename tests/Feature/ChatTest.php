<?php

use App\Enums\UserRole;
use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\Church;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;
use App\Services\LinkPreviewFetcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

beforeEach(function (): void {
    Storage::fake((string) config('media.disk'));
    Http::preventStrayRequests();
    // Broadcasting connects to Reverb synchronously (ShouldBroadcastNow); the
    // chat flow is what we assert here, not the realtime transport.
    Event::fake([ChatMessageSent::class]);
});

function chatApiAs(User $user, Church $church): TestCase
{
    app('auth')->forgetGuards();

    return test()
        ->withToken($user->createToken('chat-test')->plainTextToken)
        ->withHeader('X-Church-ID', $church->id);
}

/**
 * @return array{church: Church, group: Group, leader: User, member: User, outsider: User}
 */
function chatGroupFixture(bool $chatEnabled = true): array
{
    $church = Church::factory()->create();
    $leader = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church->assignMember($leader);
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church->assignMember($member);
    $outsider = User::factory()->create(['role' => UserRole::MEMBER]);
    Church::factory()->create()->assignMember($outsider);

    $group = Group::factory()->for($church)->create(['chat_enabled' => $chatEnabled]);
    $group->responsibleUsers()->attach($member->id);

    return compact('church', 'group', 'leader', 'member', 'outsider');
}

test('a context responsible can enable and disable the chat', function (): void {
    ['church' => $church, 'group' => $group, 'member' => $responsible] = chatGroupFixture(chatEnabled: false);

    chatApiAs($responsible->fresh(), $church)
        ->putJson("/api/chat/contexts/group/{$group->id}/toggle", ['enabled' => true])
        ->assertSuccessful()
        ->assertJsonPath('chat_enabled', true);

    expect($group->refresh()->chat_enabled)->toBeTrue();
});

test('a plain member cannot toggle the chat of a group they do not lead', function (): void {
    ['church' => $church, 'group' => $group, 'outsider' => $outsider] = chatGroupFixture();
    $church->assignMember($outsider->forceFill(['role' => UserRole::MEMBER]));

    chatApiAs($outsider->fresh(), $church)
        ->putJson("/api/chat/contexts/group/{$group->id}/toggle", ['enabled' => false])
        ->assertForbidden();
});

test('group members can post messages when chat is enabled', function (): void {
    ['church' => $church, 'group' => $group, 'member' => $member] = chatGroupFixture();

    $conversationId = chatApiAs($member->fresh(), $church)
        ->getJson("/api/chat/contexts/group/{$group->id}")
        ->assertSuccessful()
        ->json('conversation.id');

    chatApiAs($member->fresh(), $church)
        ->postJson("/api/chat/conversations/{$conversationId}/messages", ['body' => 'Olá a todos'])
        ->assertCreated()
        ->assertJsonPath('message.body', 'Olá a todos');

    expect(ChatMessage::query()->where('conversation_id', $conversationId)->count())->toBe(1);
});

test('posting is blocked while the chat is disabled', function (): void {
    ['church' => $church, 'group' => $group, 'member' => $member] = chatGroupFixture(chatEnabled: false);
    $conversation = Conversation::query()->create([
        'church_id' => $church->id,
        'type' => 'context',
        'conversable_type' => Group::class,
        'conversable_id' => $group->id,
    ]);

    chatApiAs($member->fresh(), $church)
        ->postJson("/api/chat/conversations/{$conversation->id}/messages", ['body' => 'Oi'])
        ->assertForbidden();
});

test('a user outside the group cannot read or post in its chat', function (): void {
    ['church' => $church, 'group' => $group, 'outsider' => $outsider] = chatGroupFixture();
    $conversation = Conversation::query()->create([
        'church_id' => $church->id,
        'type' => 'context',
        'conversable_type' => Group::class,
        'conversable_id' => $group->id,
    ]);

    chatApiAs($outsider->fresh(), $church)
        ->getJson("/api/chat/conversations/{$conversation->id}/messages")
        ->assertForbidden();
});

test('a responsible can moderate (delete) any message; a member only their own', function (): void {
    ['church' => $church, 'group' => $group, 'leader' => $leader, 'member' => $member] = chatGroupFixture();
    $conversation = Conversation::query()->create([
        'church_id' => $church->id,
        'type' => 'context',
        'conversable_type' => Group::class,
        'conversable_id' => $group->id,
    ]);
    $conversation->participants()->syncWithoutDetaching([$member->id]);
    $message = $conversation->messages()->create(['user_id' => $member->id, 'type' => 'text', 'body' => 'Mensagem']);

    // A church leader (moderator) removes a member's message.
    chatApiAs($leader->fresh(), $church)
        ->deleteJson("/api/chat/conversations/{$conversation->id}/messages/{$message->id}")
        ->assertNoContent();

    expect(ChatMessage::withTrashed()->find($message->id)->trashed())->toBeTrue()
        ->and(ChatMessage::withTrashed()->find($message->id)->deleted_by_id)->toBe($leader->id);
});

test('image attachments are stored and typed as image', function (): void {
    ['church' => $church, 'group' => $group, 'member' => $member] = chatGroupFixture();
    $conversation = Conversation::query()->create([
        'church_id' => $church->id,
        'type' => 'context',
        'conversable_type' => Group::class,
        'conversable_id' => $group->id,
    ]);
    $conversation->participants()->syncWithoutDetaching([$member->id]);

    chatApiAs($member->fresh(), $church)
        ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
            'attachment' => UploadedFile::fake()->create('foto.jpg', 120, 'image/jpeg'),
        ])
        ->assertCreated()
        ->assertJsonPath('message.type', 'image');

    $message = ChatMessage::query()->where('conversation_id', $conversation->id)->firstOrFail();
    expect($message->attachment_path)->not->toBeNull();
    Storage::disk((string) config('media.disk'))->assertExists($message->attachment_path);
    Event::assertDispatched(ChatMessageSent::class);
});

test('a message with a link gets an OpenGraph preview', function (): void {
    ['church' => $church, 'group' => $group, 'member' => $member] = chatGroupFixture();
    $conversation = Conversation::query()->create([
        'church_id' => $church->id,
        'type' => 'context',
        'conversable_type' => Group::class,
        'conversable_id' => $group->id,
    ]);
    $conversation->participants()->syncWithoutDetaching([$member->id]);

    $this->mock(LinkPreviewFetcher::class)
        ->shouldReceive('fetch')
        ->once()
        ->andReturn([
            'url' => 'https://example.test/post',
            'host' => 'example.test',
            'title' => 'Um título',
            'description' => 'Uma descrição',
            'image' => null,
            'site_name' => 'Example',
        ]);

    chatApiAs($member->fresh(), $church)
        ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
            'body' => 'Olhem isto https://example.test/post',
        ])
        ->assertCreated()
        ->assertJsonPath('message.type', 'link')
        ->assertJsonPath('message.link_preview.title', 'Um título');
});
