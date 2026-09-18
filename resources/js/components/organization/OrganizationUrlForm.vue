<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { isValidYandexMapsOrganizationUrl } from '@/lib/yandex-url';

const props = defineProps<{
    initialUrl?: string | null;
    isSubmitting: boolean;
    submitError: string | null;
}>();

const emit = defineEmits<{
    submit: [url: string];
}>();

const url = ref(props.initialUrl ?? '');

watch(
    () => props.initialUrl,
    (value) => {
        if (value !== undefined && value !== null && value !== url.value) {
            url.value = value;
        }
    },
);

const clientHint = computed(() => {
    const trimmed = url.value.trim();
    if (!trimmed) {
        return null;
    }
    if (!isValidYandexMapsOrganizationUrl(trimmed)) {
        return 'Вставьте ссылку на карточку организации в Яндекс.Картах.';
    }
    return null;
});

const canSubmit = computed(
    () =>
        url.value.trim().length > 0 &&
        !props.isSubmitting &&
        clientHint.value === null,
);

function onSubmit(): void {
    if (!canSubmit.value) {
        return;
    }
    emit('submit', url.value.trim());
}
</script>

<template>
    <form class="space-y-3" @submit.prevent="onSubmit">
        <div class="space-y-1">
            <label
                for="yandex-maps-url"
                class="block text-sm font-medium text-neutral-700"
            >
                Ссылка на организацию в Яндекс.Картах
            </label>
            <input
                id="yandex-maps-url"
                v-model="url"
                type="url"
                placeholder="https://yandex.ru/maps/org/…"
                autocomplete="off"
                required
                class="w-full rounded border border-neutral-300 px-3 py-2 outline-none focus:border-neutral-500"
                :disabled="isSubmitting"
            />
            <p v-if="clientHint" class="text-sm text-amber-700">
                {{ clientHint }}
            </p>
            <p v-if="submitError" class="text-sm text-red-600" role="alert">
                {{ submitError }}
            </p>
        </div>

        <button
            type="submit"
            class="rounded bg-neutral-900 px-4 py-2 text-sm text-white disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="!canSubmit"
        >
            {{ isSubmitting ? 'Сохранение…' : 'Подключить' }}
        </button>
    </form>
</template>
