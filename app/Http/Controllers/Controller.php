<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Support\ChurchDomainContext;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function ensureChurchAccess(Request $request, string $churchId): void
    {
        $role = $request->user()->role;

        if (in_array($role, [UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return;
        }

        abort_unless($request->user()->church?->id === $churchId, 403);
    }

    protected function ensurePublicChurchResource(string $churchId): void
    {
        $domainChurchId = app(ChurchDomainContext::class)->churchId();

        // if ($domainChurchId !== null && app(ChurchDomainContext::class)->source() !== 'membership') {
        //     abort_if($domainChurchId !== $churchId, 404);

        //     return;
        // } // TODO: Corrigir não esta validando alunos corretamente

        $user = request()->user();

        abort_unless($user?->role === UserRole::SYSTEM || $user?->church?->id === $churchId, 403);
    }

    protected function ensureOwnerOrChurchModerator(Request $request, string $ownerId, string $churchId): void
    {
        $role = $request->user()->role;
        $isChurchModerator = $request->user()->church?->id === $churchId
            && in_array($role, [UserRole::LEADER, UserRole::MEDIA, UserRole::CHURCH_LEADER, UserRole::SUPERADMIN], true);

        abort_unless($request->user()->id === $ownerId || $isChurchModerator || $role === UserRole::SYSTEM, 403);
    }

    protected function ensureOwnerOrModerator(Request $request, string $ownerId): void
    {
        $role = $request->user()->role;

        if (in_array($role, [UserRole::LEADER, UserRole::MEDIA, UserRole::CHURCH_LEADER, UserRole::SUPERADMIN, UserRole::SYSTEM], true)) {
            return;
        }

        abort_unless($request->user()->id === $ownerId, 403);
    }
}
