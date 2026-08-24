import type { KeyValueStorage, NetworkStatus } from '../platform/contracts.ts';

export interface CachedValue<T> {
    data: T;
    storedAt: string;
    expiresAt: string;
}

export interface StoreSnapshot<T> {
    data: T | null;
    offline: boolean;
    stale: boolean;
    storedAt: string | null;
}

export class HydratedStore<T> {
    private readonly storage: KeyValueStorage;
    private readonly network: NetworkStatus;
    private readonly key: string;
    private readonly ttlMilliseconds: number;
    private snapshotValue: StoreSnapshot<T> = {
        data: null,
        offline: false,
        stale: false,
        storedAt: null,
    };

    public constructor(
        storage: KeyValueStorage,
        network: NetworkStatus,
        key: string,
        ttlMilliseconds: number,
    ) {
        this.storage = storage;
        this.network = network;
        this.key = key;
        this.ttlMilliseconds = ttlMilliseconds;
    }

    public snapshot(): StoreSnapshot<T> {
        return this.snapshotValue;
    }

    public async hydrate(initialData?: T): Promise<StoreSnapshot<T>> {
        if (initialData !== undefined) {
            await this.persist(initialData);

            return this.snapshotValue;
        }

        const cached = await this.storage.get(this.key);

        if (!cached) {
            this.snapshotValue = {
                data: null,
                offline: !this.network.isOnline(),
                stale: false,
                storedAt: null,
            };

            return this.snapshotValue;
        }

        const value = JSON.parse(cached) as CachedValue<T>;
        this.snapshotValue = {
            data: value.data,
            offline: !this.network.isOnline(),
            stale: Date.parse(value.expiresAt) <= Date.now(),
            storedAt: value.storedAt,
        };

        return this.snapshotValue;
    }

    public async refresh(loader: () => Promise<T>): Promise<StoreSnapshot<T>> {
        if (!this.network.isOnline()) {
            return this.hydrate();
        }

        return this.persist(await loader());
    }

    private async persist(data: T): Promise<StoreSnapshot<T>> {
        const storedAt = new Date();
        const value: CachedValue<T> = {
            data,
            storedAt: storedAt.toISOString(),
            expiresAt: new Date(
                storedAt.getTime() + this.ttlMilliseconds,
            ).toISOString(),
        };
        await this.storage.set(this.key, JSON.stringify(value));
        this.snapshotValue = {
            data,
            offline: false,
            stale: false,
            storedAt: value.storedAt,
        };

        return this.snapshotValue;
    }
}
