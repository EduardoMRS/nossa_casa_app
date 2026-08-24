export interface RequestState {
    loading: boolean;
    error: string | null;
}

export function createRequestState(): RequestState {
    return {
        loading: false,
        error: null,
    };
}

export async function runRequest<T>(
    state: RequestState,
    request: () => Promise<T>,
    apply: (payload: T) => void,
    errorMessage: string,
): Promise<boolean> {
    state.loading = true;
    state.error = null;

    try {
        apply(await request());

        return true;
    } catch {
        state.error = errorMessage;

        return false;
    } finally {
        state.loading = false;
    }
}
