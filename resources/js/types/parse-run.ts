export type ParseRunStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface ParseRun {
    id: number;
    status: ParseRunStatus;
    processed_reviews: number;
    total_reviews: number | null;
    processed_pages: number;
    progress_percent: number | null;
    attempt: number;
    max_attempts: number;
    error_code: string | null;
    error_message: string | null;
    queued_at: string | null;
    started_at: string | null;
    finished_at: string | null;
}

export function isActiveParseRunStatus(status: ParseRunStatus): boolean {
    return status === 'pending' || status === 'processing';
}
