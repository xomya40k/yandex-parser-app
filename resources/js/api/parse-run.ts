import api from '@/api/client';
import type { ParseRun } from '@/types/parse-run';

interface ResourceResponse<T> {
    data: T;
}

function normalizeParseRun(data: ParseRun | unknown[]): ParseRun | null {
    if (Array.isArray(data)) {
        return null;
    }
    return data;
}

export async function queueParseRun(): Promise<ParseRun> {
    const { data } = await api.post<ResourceResponse<ParseRun>>(
        '/organization/parse-run',
    );
    return data.data;
}

export async function fetchLatestParseRun(): Promise<ParseRun | null> {
    const { data } = await api.get<ResourceResponse<ParseRun | unknown[]>>(
        '/organization/parse-run',
    );
    return normalizeParseRun(data.data);
}
