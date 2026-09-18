<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import OrganizationSummaryCard from '@/components/organization/OrganizationSummaryCard.vue';
import OrganizationUrlForm from '@/components/organization/OrganizationUrlForm.vue';
import ParseProgressBanner from '@/components/organization/ParseProgressBanner.vue';
import PaginationControls from '@/components/reviews/PaginationControls.vue';
import ReviewsTable from '@/components/reviews/ReviewsTable.vue';
import { useAuthStore } from '@/stores/auth';
import { useOrganizationStore } from '@/stores/organization';
import { useParseRunStore } from '@/stores/parseRun';
import { useReviewsStore } from '@/stores/reviews';
import { isActiveParseRunStatus } from '@/types/parse-run';

const authStore = useAuthStore();
const organizationStore = useOrganizationStore();
const parseRunStore = useParseRunStore();
const reviewsStore = useReviewsStore();
const router = useRouter();

onMounted(async () => {
    try {
        await organizationStore.load();
    } catch {
        return;
    }

    if (!organizationStore.organization) {
        return;
    }

    try {
        await parseRunStore.refresh();
    } catch {
        // Progress banner stays empty; user can still submit a new URL.
    }

    if (organizationStore.organization.status === 'ready') {
        try {
            await reviewsStore.fetchPage(1);
        } catch {
            // Error shown via reviewsStore.error
        }
    }
});

watch(
    () => parseRunStore.parseRun?.status,
    async (status, prevStatus) => {
        if (
            !prevStatus ||
            !status ||
            !isActiveParseRunStatus(prevStatus) ||
            isActiveParseRunStatus(status)
        ) {
            return;
        }

        try {
            await organizationStore.load();
        } catch {
            return;
        }

        if (status === 'completed') {
            try {
                await reviewsStore.fetchPage(1);
            } catch {
                // Error shown via reviewsStore.error
            }
        }
    },
);

onUnmounted(() => {
    parseRunStore.stopPolling();
});

async function onLogout(): Promise<void> {
    await authStore.logout();
    await router.push({ name: 'login' });
}

async function onSubmitUrl(url: string): Promise<void> {
    try {
        await organizationStore.submitUrl(url);
    } catch {
        return;
    }

    reviewsStore.reset();

    try {
        await parseRunStore.refresh();
    } catch {
        // Banner may appear on next poll / remount.
    }
}

async function onRetryParsing(): Promise<void> {
    try {
        await parseRunStore.trigger();
    } catch {
        // triggerError is set in the store
    }
}

async function onPageChange(page: number): Promise<void> {
    try {
        await reviewsStore.fetchPage(page);
    } catch {
        // Error shown via reviewsStore.error
    }
}
</script>

<template>
    <main class="mx-auto max-w-5xl space-y-8 p-8">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Настройки</h1>
                <p class="mt-2 text-neutral-600">
                    Вы вошли как
                    <span class="font-medium text-neutral-900">{{
                        authStore.user?.email
                    }}</span
                    >.
                </p>
            </div>
            <button
                type="button"
                class="rounded border border-neutral-300 px-3 py-1.5 text-sm hover:bg-neutral-50"
                @click="onLogout"
            >
                Выйти
            </button>
        </div>

        <p
            v-if="organizationStore.loadError"
            class="text-sm text-red-600"
            role="alert"
        >
            {{ organizationStore.loadError }}
        </p>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Подключение организации</h2>
            <OrganizationUrlForm
                :initial-url="organizationStore.organization?.yandex_maps_url"
                :is-submitting="organizationStore.isSubmitting"
                :submit-error="organizationStore.submitError"
                @submit="onSubmitUrl"
            />
        </section>

        <ParseProgressBanner
            :parse-run="parseRunStore.parseRun"
            :is-triggering="parseRunStore.isTriggering"
            @retry="onRetryParsing"
        />

        <p
            v-if="parseRunStore.triggerError"
            class="text-sm text-red-600"
            role="alert"
        >
            {{ parseRunStore.triggerError }}
        </p>

        <template
            v-if="
                organizationStore.organization &&
                organizationStore.organization.status === 'ready'
            "
        >
            <OrganizationSummaryCard
                :organization="organizationStore.organization"
            />

            <section class="space-y-4">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-lg font-semibold">Отзывы</h2>
                    <p
                        v-if="reviewsStore.total > 0"
                        class="text-sm text-neutral-500"
                    >
                        Всего: {{ reviewsStore.total }}
                    </p>
                </div>

                <p
                    v-if="reviewsStore.error"
                    class="text-sm text-red-600"
                    role="alert"
                >
                    {{ reviewsStore.error }}
                </p>

                <ReviewsTable
                    :reviews="reviewsStore.reviews"
                    :is-loading="reviewsStore.isLoading"
                />

                <PaginationControls
                    :current-page="reviewsStore.currentPage"
                    :last-page="reviewsStore.lastPage"
                    :disabled="reviewsStore.isLoading"
                    @change="onPageChange"
                />
            </section>
        </template>

        <p
            v-else-if="!organizationStore.isLoading"
            class="text-sm text-neutral-500"
        >
            После подключения здесь появятся рейтинг и отзывы.
        </p>
    </main>
</template>
