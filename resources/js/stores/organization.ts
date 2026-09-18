import { ref } from 'vue';
import { defineStore } from 'pinia';
import {
    fetchCurrentOrganization,
    saveOrganizationUrl,
} from '@/api/organization';
import { getErrorMessage } from '@/lib/http-error';
import type { Organization } from '@/types/organization';

export const useOrganizationStore = defineStore('organization', () => {
    const organization = ref<Organization | null>(null);
    const isLoading = ref(false);
    const loadError = ref<string | null>(null);
    const isSubmitting = ref(false);
    const submitError = ref<string | null>(null);

    async function load(): Promise<void> {
        isLoading.value = true;
        loadError.value = null;
        try {
            organization.value = await fetchCurrentOrganization();
        } catch (error) {
            loadError.value = getErrorMessage(
                error,
                'Не удалось загрузить организацию.',
            );
            throw error;
        } finally {
            isLoading.value = false;
        }
    }

    async function submitUrl(url: string): Promise<void> {
        isSubmitting.value = true;
        submitError.value = null;
        try {
            organization.value = await saveOrganizationUrl(url);
        } catch (error) {
            submitError.value = getErrorMessage(
                error,
                'Не удалось сохранить ссылку.',
            );
            throw error;
        } finally {
            isSubmitting.value = false;
        }
    }

    function reset(): void {
        organization.value = null;
        loadError.value = null;
        submitError.value = null;
    }

    return {
        organization,
        isLoading,
        loadError,
        isSubmitting,
        submitError,
        load,
        submitUrl,
        reset,
    };
});
