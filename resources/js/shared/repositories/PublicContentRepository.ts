import type { HttpClient } from '../platform/contracts.ts';
import { EventRepository } from './content/EventRepository.ts';
import { GalleryRepository } from './content/GalleryRepository.ts';
import { LibraryRepository } from './content/LibraryRepository.ts';
import { LiveStreamRepository } from './content/LiveStreamRepository.ts';
import { PortalRepository } from './content/PortalRepository.ts';
import { PostRepository } from './content/PostRepository.ts';
import type { CanonicalPayload } from './content/types.ts';

export class PublicContentRepository {
    private readonly portalRepository: PortalRepository;
    private readonly postRepository: PostRepository;
    private readonly eventRepository: EventRepository;
    private readonly galleryRepository: GalleryRepository;
    private readonly libraryRepository: LibraryRepository;
    private readonly liveStreamRepository: LiveStreamRepository;

    public constructor(http: HttpClient) {
        this.portalRepository = new PortalRepository(http);
        this.postRepository = new PostRepository(http);
        this.eventRepository = new EventRepository(http);
        this.galleryRepository = new GalleryRepository(http);
        this.libraryRepository = new LibraryRepository(http);
        this.liveStreamRepository = new LiveStreamRepository(http);
    }

    public portal(): Promise<CanonicalPayload> {
        return this.portalRepository.get();
    }

    public posts(query = ''): Promise<CanonicalPayload> {
        return this.postRepository.list(query);
    }

    public post(slug: string): Promise<CanonicalPayload> {
        return this.postRepository.get(slug);
    }

    public events(query = ''): Promise<CanonicalPayload> {
        return this.eventRepository.list(query);
    }

    public event(slug: string): Promise<CanonicalPayload> {
        return this.eventRepository.get(slug);
    }

    public gallery(query = ''): Promise<CanonicalPayload> {
        return this.galleryRepository.list(query);
    }

    public library(): Promise<CanonicalPayload> {
        return this.libraryRepository.get();
    }

    public bible(): Promise<CanonicalPayload> {
        return this.libraryRepository.bible();
    }

    public liveStream(id: string): Promise<CanonicalPayload> {
        return this.liveStreamRepository.get(id);
    }
}
