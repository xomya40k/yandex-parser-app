import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from '@/App.vue';
import router from '@/router';
import { setUnauthorizedHandler } from '@/api/client';
import { useAuthStore } from '@/stores/auth';

const app = createApp(App);
app.use(createPinia());
app.use(router);

const authStore = useAuthStore();

setUnauthorizedHandler(() => {
    authStore.clearSession();
    const current = router.currentRoute.value;
    if (current.meta.requiresAuth) {
        router
            .push({ name: 'login', query: { redirect: current.fullPath } })
            .catch(() => {});
    }
});

app.mount('#app');
