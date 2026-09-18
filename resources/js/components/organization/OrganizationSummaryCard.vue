<script setup lang="ts">
import { computed } from 'vue';
import { formatDate, formatRating } from '@/lib/format';
import type { Organization } from '@/types/organization';

const props = defineProps<{
    organization: Organization;
}>();

const isReady = computed(() => props.organization.status === 'ready');

const displayName = computed(
    () => props.organization.name?.trim() || 'Организация',
);
</script>

<template>
    <section class="space-y-3">
        <h2 class="text-lg font-semibold">{{ displayName }}</h2>
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500">
                    Рейтинг
                </dt>
                <dd class="mt-1 text-xl font-semibold">
                    {{ isReady ? formatRating(organization.rating) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500">
                    Оценок
                </dt>
                <dd class="mt-1 text-xl font-semibold">
                    {{ isReady ? organization.total_ratings : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500">
                    Отзывов
                </dt>
                <dd class="mt-1 text-xl font-semibold">
                    {{ isReady ? organization.total_reviews : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500">
                    Обновлено
                </dt>
                <dd class="mt-1 text-sm font-medium">
                    {{
                        isReady ? formatDate(organization.last_parsed_at) : '—'
                    }}
                </dd>
            </div>
        </dl>
    </section>
</template>
