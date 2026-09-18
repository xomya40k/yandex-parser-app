import { createRouter, createWebHistory } from 'vue-router';
import LoginView from '@/views/LoginView.vue';
import SettingsView from '@/views/SettingsView.vue';
import { useAuthStore } from '@/stores/auth';

declare module 'vue-router' {
    interface RouteMeta {
        requiresAuth?: boolean;
        guestOnly?: boolean;
    }
}

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: LoginView,
            meta: { guestOnly: true },
        },
        {
            path: '/',
            name: 'settings',
            component: SettingsView,
            meta: { requiresAuth: true },
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'settings' },
        },
    ],
});

router.beforeEach(async (to) => {
    const authStore = useAuthStore();
    await authStore.ensureInitialized();

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return { name: 'settings' };
    }
    return true;
});

export default router;
