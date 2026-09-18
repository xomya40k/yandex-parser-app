import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { fetchReviews } from '@/api/reviews';
import { getErrorMessage } from '@/lib/http-error';
import type { PaginationMeta, Review } from '@/types/review';

const PER_PAGE = 50;

export const useReviewsStore = defineStore('reviews', () => {
    const reviews = ref<Review[]>([]);
    const meta = ref<PaginationMeta | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    const currentPage = computed(() => meta.value?.current_page ?? 1);
    const lastPage = computed(() => meta.value?.last_page ?? 1);
    const total = computed(() => meta.value?.total ?? 0);

    async function fetchPage(page: number): Promise<void> {
        isLoading.value = true;
        error.value = null;
        try {
            const result = await fetchReviews(page, PER_PAGE);
            reviews.value = result.data;
            meta.value = result.meta;
        } catch (err) {
            error.value = getErrorMessage(err, 'Не удалось загрузить отзывы.');
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    function reset(): void {
        reviews.value = [];
        meta.value = null;
        error.value = null;
    }

    return {
        reviews,
        meta,
        isLoading,
        error,
        currentPage,
        lastPage,
        total,
        fetchPage,
        reset,
    };
});
