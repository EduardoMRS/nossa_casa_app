<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\SystemBackupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
});

it('provides translated backup messages for each supported locale', function () {
    app()->setLocale('en');
    expect(__('backup.operation_failed'))->toBe('The backup operation could not be completed.');

    app()->setLocale('pt');
    expect(__('backup.operation_failed'))->toBe('Não foi possível concluir a operação de backup.');

    app()->setLocale('en');
});

it('allows only users above admin to access the backup screen during maintenance', function () {
    $superadmin = User::factory()->create(['role' => UserRole::SUPERADMIN]);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    app()->maintenanceMode()->activate(['status' => 503]);

    try {
        $this->actingAs($superadmin)
            ->get(route('admin.logsMetrics.index'))
            ->assertSuccessful();

        $this->actingAs($admin)
            ->get(route('admin.logsMetrics.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertStatus(503);
    } finally {
        app()->maintenanceMode()->deactivate();
    }
});

it('exports the database and configured media without changing database rows', function () {
    Storage::fake('media');
    Storage::fake('recordings');
    Storage::disk('media')->put('church/example.txt', 'example media');
    $user = User::factory()->create(['role' => UserRole::SYSTEM]);
    $count = User::query()->count();

    $response = $this->actingAs($user)->get(route('admin.logsMetrics.backup.export'));

    $response->assertSuccessful()
        ->assertDownload();
    expect(User::query()->count())->toBe($count);
});

it('imports a backup and restores its media', function () {
    Storage::fake('media');
    Storage::fake('recordings');
    Storage::disk('media')->put('church/example.txt', 'example media');
    User::factory()->create(['role' => UserRole::SYSTEM]);

    $archive = app(SystemBackupService::class)->export();
    Storage::disk('media')->delete('church/example.txt');

    $upload = UploadedFile::fake()->createWithContent(
        'backup.zip',
        File::get($archive),
    );

    app(SystemBackupService::class)->import($upload);

    expect(Storage::disk('media')->get('church/example.txt'))->toBe('example media');
    File::delete($archive);
});
test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
