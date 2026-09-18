export interface Review {
    id: number;
    author_name: string;
    rating: number;
    text: string | null;
    review_date: string;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface PaginatedReviews {
    data: Review[];
    meta: PaginationMeta;
}
