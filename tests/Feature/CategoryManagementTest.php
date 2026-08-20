<?php

use App\Enums\CategoryType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Church;
use App\Models\User;

test('admin can manage categories for the current church', function () {
    $admin = User::factory()->create(['role' => UserRole::CHURCH_LEADER]);
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa', 'status' => 'active']);
    $church->assignMember($admin);

    $this->actingAs($admin)->get('/dashboard/categorias')->assertSuccessful();

    $this->actingAs($admin)->postJson('/api/categories', [
        'name' => 'Devocionais',
        'slug' => 'devocionais',
        'type' => CategoryType::POST->value,
    ])->assertCreated();

    $category = Category::query()->firstOrFail();

    expect($category->church_id)->toBe($church->id);
    expect($category->type?->value ?? $category->type)->toBe(CategoryType::POST->value);

    $this->actingAs($admin)->putJson('/api/categories/'.$category->id, [
        'name' => 'Devocionais Atualizado',
        'slug' => 'devocionais-atualizado',
        'type' => CategoryType::POST->value,
    ])->assertSuccessful();

    expect($category->refresh()->name)->toBe('Devocionais Atualizado');

    $this->actingAs($admin)->deleteJson('/api/categories/'.$category->id)->assertNoContent();
    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

test('member cannot access the category management screen', function () {
    $member = User::factory()->create(['role' => UserRole::MEMBER]);
    $church = Church::create(['name' => 'Nossa Casa', 'slug' => 'nossa-casa', 'status' => 'active']);
    $church->assignMember($member);

    $this->actingAs($member)->get('/dashboard/categorias')->assertForbidden();
});
