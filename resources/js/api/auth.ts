import api from '@/api/client';
import { ensureCsrfCookie } from '@/api/csrf';
import type { LoginPayload, User } from '@/types/auth';

interface ResourceResponse<T> {
    data: T;
}

export async function login(payload: LoginPayload): Promise<User> {
    await ensureCsrfCookie();
    const { data } = await api.post<ResourceResponse<User>>('/login', payload);
    return data.data;
}

export async function logout(): Promise<void> {
    await api.post('/logout');
}

export async function fetchCurrentUser(): Promise<User> {
    const { data } = await api.get<ResourceResponse<User>>('/me');
    return data.data;
}
