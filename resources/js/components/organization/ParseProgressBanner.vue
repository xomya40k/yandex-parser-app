<script setup lang="ts">
import { computed } from 'vue';
import { describeParseRunError } from '@/lib/parse-run-errors';
import { isActiveParseRunStatus, type ParseRun } from '@/types/parse-run';

const props = defineProps<{
    parseRun: ParseRun | null;
    isTriggering?: boolean;
}>();

const emit = defineEmits<{
    retry: [];
}>();

const isActive = computed(() => {
    const status = props.parseRun?.status;
    return status !== undefined && isActiveParseRunStatus(status);
});

const isFailed = computed(() => props.parseRun?.status === 'failed');

const progressLabel = computed(() => {
    const run = props.parseRun;
    if (!run) {
        return '';
    }

    if (run.progress_percent !== null) {
        return `${run.progress_percent}% · ${run.processed_reviews} из ${run.total_reviews ?? '…'} отзывов`;
    }

    if (run.status === 'pending') {
        return 'В очереди…';
    }

    return `Обработано ${run.processed_reviews} отзывов…`;
});

const errorText = computed(() => {
    if (!props.parseRun) {
        return '';
    }
    return (
        describeParseRunError(props.parseRun.error_code) +
        (props.parseRun.error_message
            ? ` (${props.parseRun.error_message})`
            : '')
    );
});

const showBanner = computed(() => isActive.value || isFailed.value);
</script>

<template>
    <div
        v-if="showBanner && parseRun"
        class="rounded border px-4 py-3"
        :class="
            isFailed
                ? 'border-red-200 bg-red-50 text-red-900'
                : 'border-neutral-200 bg-neutral-50 text-neutral-800'
        "
        role="status"
    >
        <template v-if="isActive">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium">Сбор отзывов</p>
                <p class="text-sm text-neutral-600">{{ progressLabel }}</p>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded bg-neutral-200">
                <div
                    v-if="parseRun.progress_percent !== null"
                    class="h-full rounded bg-neutral-800 transition-[width] duration-300"
                    :style="{ width: `${parseRun.progress_percent}%` }"
                />
                <div
                    v-else
                    class="h-full w-1/3 animate-pulse rounded bg-neutral-400"
                />
            </div>
            <p
                v-if="parseRun.attempt > 0"
                class="mt-2 text-xs text-neutral-500"
            >
                Попытка {{ parseRun.attempt }} из {{ parseRun.max_attempts }}
            </p>
        </template>

        <template v-else-if="isFailed">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium">Ошибка парсинга</p>
                    <p class="mt-1 text-sm">{{ errorText }}</p>
                    <p
                        v-if="parseRun.attempt > 0"
                        class="mt-1 text-xs text-red-700/80"
                    >
                        Попытка {{ parseRun.attempt }} из
                        {{ parseRun.max_attempts }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded border border-red-300 bg-white px-3 py-1.5 text-sm hover:bg-red-50 disabled:opacity-60"
                    :disabled="isTriggering"
                    @click="emit('retry')"
                >
                    {{ isTriggering ? 'Запуск…' : 'Повторить' }}
                </button>
            </div>
        </template>
    </div>
</template>
