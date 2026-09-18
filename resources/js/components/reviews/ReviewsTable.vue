<script setup lang="ts">
import { formatDate } from '@/lib/format';
import type { Review } from '@/types/review';

defineProps<{
    reviews: Review[];
    isLoading: boolean;
}>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] border-collapse text-left text-sm">
            <thead>
                <tr class="border-b border-neutral-200 text-neutral-500">
                    <th class="px-2 py-2 font-medium">Автор</th>
                    <th class="px-2 py-2 font-medium">Дата</th>
                    <th class="px-2 py-2 font-medium">Оценка</th>
                    <th class="px-2 py-2 font-medium">Текст</th>
                </tr>
            </thead>
            <tbody>
                <template v-if="isLoading">
                    <tr
                        v-for="n in 5"
                        :key="`skeleton-${n}`"
                        class="border-b border-neutral-100"
                    >
                        <td class="px-2 py-3" colspan="4">
                            <div
                                class="h-4 animate-pulse rounded bg-neutral-100"
                            />
                        </td>
                    </tr>
                </template>
                <template v-else-if="reviews.length === 0">
                    <tr>
                        <td
                            colspan="4"
                            class="px-2 py-8 text-center text-neutral-500"
                        >
                            Отзывов пока нет.
                        </td>
                    </tr>
                </template>
                <template v-else>
                    <tr
                        v-for="review in reviews"
                        :key="review.id"
                        class="border-b border-neutral-100 align-top"
                    >
                        <td class="px-2 py-3 font-medium text-neutral-900">
                            {{ review.author_name }}
                        </td>
                        <td
                            class="whitespace-nowrap px-2 py-3 text-neutral-600"
                        >
                            {{ formatDate(review.review_date) }}
                        </td>
                        <td class="px-2 py-3 text-neutral-900">
                            {{ review.rating }}
                        </td>
                        <td class="px-2 py-3 text-neutral-700">
                            {{ review.text?.trim() || '—' }}
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</template>
