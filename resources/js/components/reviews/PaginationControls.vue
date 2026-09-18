<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    currentPage: number;
    lastPage: number;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    change: [page: number];
}>();

const canGoPrev = computed(() => props.currentPage > 1 && !props.disabled);
const canGoNext = computed(
    () => props.currentPage < props.lastPage && !props.disabled,
);

const pageNumbers = computed(() => {
    const total = props.lastPage;
    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const current = props.currentPage;
    const pages = new Set<number>([1, total, current]);
    for (let i = current - 1; i <= current + 1; i++) {
        if (i >= 1 && i <= total) {
            pages.add(i);
        }
    }

    return Array.from(pages).sort((a, b) => a - b);
});

function goTo(page: number): void {
    if (props.disabled || page < 1 || page > props.lastPage) {
        return;
    }
    if (page === props.currentPage) {
        return;
    }
    emit('change', page);
}
</script>

<template>
    <nav
        v-if="lastPage > 1"
        class="flex flex-wrap items-center justify-between gap-3"
        aria-label="Страницы отзывов"
    >
        <p class="text-sm text-neutral-600">
            Страница {{ currentPage }} из {{ lastPage }}
        </p>
        <div class="flex flex-wrap items-center gap-1">
            <button
                type="button"
                class="rounded border border-neutral-300 px-2.5 py-1 text-sm hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canGoPrev"
                @click="goTo(currentPage - 1)"
            >
                Назад
            </button>

            <template v-for="(page, index) in pageNumbers" :key="page">
                <span
                    v-if="index > 0 && page - (pageNumbers[index - 1] ?? 0) > 1"
                    class="px-1 text-neutral-400"
                >
                    …
                </span>
                <button
                    type="button"
                    class="min-w-8 rounded border px-2.5 py-1 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                    :class="
                        page === currentPage
                            ? 'border-neutral-900 bg-neutral-900 text-white'
                            : 'border-neutral-300 hover:bg-neutral-50'
                    "
                    :disabled="disabled"
                    :aria-current="page === currentPage ? 'page' : undefined"
                    @click="goTo(page)"
                >
                    {{ page }}
                </button>
            </template>

            <button
                type="button"
                class="rounded border border-neutral-300 px-2.5 py-1 text-sm hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canGoNext"
                @click="goTo(currentPage + 1)"
            >
                Вперёд
            </button>
        </div>
    </nav>
</template>
