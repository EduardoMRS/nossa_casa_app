import type { PlatformServices } from '../platform/contracts.ts';
import { EventRepository } from './content/EventRepository.ts';
import { GalleryRepository } from './content/GalleryRepository.ts';
import { LibraryRepository } from './content/LibraryRepository.ts';
import { LiveStreamRepository } from './content/LiveStreamRepository.ts';
import { PortalRepository } from './content/PortalRepository.ts';
import { PostRepository } from './content/PostRepository.ts';
import { FormRepository } from './FormRepository.ts';
import { InteractionRepository } from './InteractionRepository.ts';
import { PrayerRepository } from './PrayerRepository.ts';

export function createRepositories(platform: PlatformServices) {
    return {
        portal: new PortalRepository(platform.http),
        posts: new PostRepository(platform.http),
        events: new EventRepository(platform.http),
        gallery: new GalleryRepository(platform.http),
        library: new LibraryRepository(platform.http),
        liveStreams: new LiveStreamRepository(platform.http),
        interactions: new InteractionRepository(platform.http),
        forms: new FormRepository(platform.http),
        prayers: new PrayerRepository(platform.http),
    };
}

export type Repositories = ReturnType<typeof createRepositories>;
