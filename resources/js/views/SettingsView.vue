<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const router = useRouter();

async function onLogout(): Promise<void> {
    await authStore.logout();
    await router.push({ name: 'login' });
}
</script>

<template>
    <main class="mx-auto max-w-3xl p-8">
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
                <p class="mt-4 text-sm text-neutral-500">
                
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
    </main>
</template>
