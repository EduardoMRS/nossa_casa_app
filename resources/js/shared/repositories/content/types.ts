export type CanonicalPayload = Record<string, unknown>;

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedPayload<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
}

export type RepositoryQuery =
    | string
    | URLSearchParams
    | Record<string, string | number | boolean | null | undefined>;

export function appendQuery(path: string, query?: RepositoryQuery): string {
    if (!query) {
        return path;
    }

    if (typeof query === 'string') {
        return `${path}${query.startsWith('?') ? query : `?${query}`}`;
    }

    const parameters =
        query instanceof URLSearchParams
            ? query
            : new URLSearchParams(
                  Object.entries(query).flatMap(([key, value]) =>
                      value === null || value === undefined
                          ? []
                          : [[key, String(value)]],
                  ),
              );
    const serialized = parameters.toString();

    return serialized ? `${path}?${serialized}` : path;
}

export function queryFromPaginationLink(url: string): URLSearchParams {
    return new URL(url, 'http://localhost').searchParams;
}
