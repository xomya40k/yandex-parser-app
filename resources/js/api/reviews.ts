import api from '@/api/client';
import type { PaginatedReviews, Review } from '@/types/review';

interface LaravelPaginatedResponse {
    data: Review[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

export async function fetchReviews(
    page: number,
    perPage = 50,
): Promise<PaginatedReviews> {
    const { data } = await api.get<LaravelPaginatedResponse>(
        '/organization/reviews',
        {
            params: { page, per_page: perPage },
        },
    );

    return {
        data: data.data,
        meta: {
            current_page: data.meta.current_page,
            last_page: data.meta.last_page,
            per_page: data.meta.per_page,
            total: data.meta.total,
        },
    };
}
