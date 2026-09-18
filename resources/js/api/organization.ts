import api from '@/api/client';
import type { Organization } from '@/types/organization';
import axios from 'axios';

interface ResourceResponse<T> {
    data: T;
}

export async function fetchCurrentOrganization(): Promise<Organization | null> {
    try {
        const { data } =
            await api.get<ResourceResponse<Organization>>('/organization');
        return data.data;
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) {
            return null;
        }
        throw error;
    }
}

export async function saveOrganizationUrl(
    yandexMapsUrl: string,
): Promise<Organization> {
    const { data } = await api.post<ResourceResponse<Organization>>(
        '/organization',
        { yandex_maps_url: yandexMapsUrl },
    );
    return data.data;
}
