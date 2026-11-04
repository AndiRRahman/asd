/** Permintaan JSON ke server Laravel dengan token CSRF dari cookie XSRF-TOKEN. */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export class HttpError extends Error {
    constructor(
        public status: number,
        public body: Record<string, unknown>,
    ) {
        super(String(body.message ?? `HTTP ${status}`));
    }
}

export async function jsonRequest<T>(
    url: string,
    options: { method?: 'GET' | 'POST'; body?: unknown; signal?: AbortSignal } = {},
): Promise<T> {
    const response = await fetch(url, {
        method: options.method ?? 'GET',
        signal: options.signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: options.body ? JSON.stringify(options.body) : undefined,
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new HttpError(response.status, body);
    }

    return body as T;
}
