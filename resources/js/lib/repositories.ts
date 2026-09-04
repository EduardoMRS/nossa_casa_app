import { createWebPlatform } from '@/shared/platform/web.ts';
import { createRepositories } from '@/shared/repositories/createRepositories.ts';
import type { Repositories } from '@/shared/repositories/createRepositories.ts';

let repositories: Repositories | undefined;

export function useRepositories(): Repositories {
    repositories ??= createRepositories(createWebPlatform());

    return repositories;
}
