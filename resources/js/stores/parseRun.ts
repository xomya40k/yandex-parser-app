import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { fetchLatestParseRun, queueParseRun } from '@/api/parse-run';
import { getErrorMessage } from '@/lib/http-error';
import { isActiveParseRunStatus, type ParseRun } from '@/types/parse-run';

const POLL_INTERVAL_MS = 2500;

export const useParseRunStore = defineStore('parseRun', () => {
    const parseRun = ref<ParseRun | null>(null);
    const isPolling = ref(false);
    const isTriggering = ref(false);
    const triggerError = ref<string | null>(null);

    let pollTimeoutId: ReturnType<typeof setTimeout> | null = null;
    let pollGeneration = 0;

    const isActive = computed(() => {
        const status = parseRun.value?.status;
        return status !== undefined && isActiveParseRunStatus(status);
    });

    function clearScheduledPoll(): void {
        if (pollTimeoutId !== null) {
            clearTimeout(pollTimeoutId);
            pollTimeoutId = null;
        }
    }

    function stopPolling(): void {
        pollGeneration += 1;
        clearScheduledPoll();
        isPolling.value = false;
    }

    function scheduleNextPoll(generation: number): void {
        clearScheduledPoll();
        pollTimeoutId = setTimeout(() => {
            void pollOnce(generation);
        }, POLL_INTERVAL_MS);
    }

    async function pollOnce(generation: number): Promise<void> {
        if (generation !== pollGeneration) {
            return;
        }

        try {
            parseRun.value = await fetchLatestParseRun();
        } catch {
            // Keep last known state; retry on the next tick while still active.
        }

        if (generation !== pollGeneration) {
            return;
        }

        if (parseRun.value && isActiveParseRunStatus(parseRun.value.status)) {
            isPolling.value = true;
            scheduleNextPoll(generation);
            return;
        }

        isPolling.value = false;
        pollTimeoutId = null;
    }

    async function refresh(): Promise<void> {
        stopPolling();
        const generation = pollGeneration;

        parseRun.value = await fetchLatestParseRun();

        if (generation !== pollGeneration) {
            return;
        }

        if (parseRun.value && isActiveParseRunStatus(parseRun.value.status)) {
            isPolling.value = true;
            scheduleNextPoll(generation);
        }
    }

    async function trigger(): Promise<void> {
        isTriggering.value = true;
        triggerError.value = null;
        try {
            stopPolling();
            const generation = pollGeneration;
            parseRun.value = await queueParseRun();

            if (
                generation === pollGeneration &&
                isActiveParseRunStatus(parseRun.value.status)
            ) {
                isPolling.value = true;
                scheduleNextPoll(generation);
            }
        } catch (error) {
            triggerError.value = getErrorMessage(
                error,
                'Не удалось запустить парсинг.',
            );
            throw error;
        } finally {
            isTriggering.value = false;
        }
    }

    function reset(): void {
        stopPolling();
        parseRun.value = null;
        triggerError.value = null;
    }

    return {
        parseRun,
        isActive,
        isPolling,
        isTriggering,
        triggerError,
        refresh,
        trigger,
        stopPolling,
        reset,
    };
});
