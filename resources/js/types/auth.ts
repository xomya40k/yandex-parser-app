export interface User {
    id: number;
    name: string;
    email: string;
    current_organization_id: number | null;
}

export interface LoginPayload {
    email: string;
    password: string;
}
