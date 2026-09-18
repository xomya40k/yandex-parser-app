<script setup lang="ts">
import { reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({
    email: '',
    password: '',
});

function resolveRedirectPath(): string {
    const redirect = route.query.redirect;
    if (typeof redirect === 'string' && redirect.startsWith('/')) {
        return redirect;
    }
    return '/';
}

async function onSubmit(): Promise<void> {
    try {
        await authStore.login({
            email: form.email,
            password: form.password,
        });
        await router.push(resolveRedirectPath());
    } catch {
        // loginError is set in the store
    }
}
</script>

<template>
    <main class="mx-auto flex min-h-screen max-w-md items-center p-8">
        <form class="w-full space-y-4" @submit.prevent="onSubmit">
            <h1 class="text-2xl font-semibold">Вход</h1>

            <div class="space-y-1">
                <label
                    for="email"
                    class="block text-sm font-medium text-neutral-700"
                >
                    Email
                </label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    class="w-full rounded border border-neutral-300 px-3 py-2 outline-none focus:border-neutral-500"
                />
            </div>

            <div class="space-y-1">
                <label
                    for="password"
                    class="block text-sm font-medium text-neutral-700"
                >
                    Пароль
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="w-full rounded border border-neutral-300 px-3 py-2 outline-none focus:border-neutral-500"
                />
            </div>

            <p
                v-if="authStore.loginError"
                class="text-sm text-red-600"
                role="alert"
            >
                {{ authStore.loginError }}
            </p>

            <button
                type="submit"
                class="w-full rounded bg-neutral-900 px-4 py-2 text-white disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="authStore.isLoggingIn"
            >
                {{ authStore.isLoggingIn ? 'Вход…' : 'Войти' }}
            </button>
        </form>
    </main>
</template>
