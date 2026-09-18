export type OrganizationStatus = 'pending' | 'parsing' | 'ready' | 'failed';

export interface Organization {
    id: number;
    name: string | null;
    yandex_maps_url: string;
    rating: string | null;
    total_ratings: number;
    total_reviews: number;
    status: OrganizationStatus;
    last_parsed_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}
