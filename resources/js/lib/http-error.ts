import axios from 'axios';

interface LaravelErrorBody {
    message?: string;
    errors?: Record<string, string[]>;
}

export function getErrorMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError<LaravelErrorBody>(error)) {
        const body = error.response?.data;
        const firstFieldError = body?.errors
            ? Object.values(body.errors)[0]?.[0]
            : undefined;
        return firstFieldError ?? body?.message ?? fallback;
    }
    return fallback;
}
