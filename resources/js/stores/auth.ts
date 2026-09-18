import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import {
    fetchCurrentUser,
    login as loginRequest,
    logout as logoutRequest,
} from '@/api/auth';
import { getErrorMessage } from '@/lib/http-error';
import type { LoginPayload, User } from '@/types/auth';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const isInitializing = ref(false);
    const isLoggingIn = ref(false);
    const loginError = ref<string | null>(null);
    let initPromise: Promise<void> | null = null;

    const isAuthenticated = computed(() => user.value !== null);

    async function initialize(): Promise<void> {
        isInitializing.value = true;
        try {
            user.value = await fetchCurrentUser();
        } catch {
            user.value = null;
        } finally {
            isInitializing.value = false;
        }
    }

    function ensureInitialized(): Promise<void> {
        initPromise ??= initialize();
        return initPromise;
    }

    async function login(payload: LoginPayload): Promise<void> {
        isLoggingIn.value = true;
        loginError.value = null;
        try {
            user.value = await loginRequest(payload);
        } catch (error) {
            loginError.value = getErrorMessage(
                error,
                'Неверный email или пароль.',
            );
            throw error;
        } finally {
            isLoggingIn.value = false;
        }
    }

    async function logout(): Promise<void> {
        try {
            await logoutRequest();
        } finally {
            clearSession();
        }
    }

    function clearSession(): void {
        user.value = null;
    }

    return {
        user,
        isAuthenticated,
        isInitializing,
        isLoggingIn,
        loginError,
        ensureInitialized,
        login,
        logout,
        clearSession,
    };
});
