export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    email_verified_at?: string | null;
    role_label?: string | null;
};

export type Auth = {
    user: User | null;
    roles: string[];
    permissions: string[];
    isAdmin: boolean;
    readOnly: boolean;
    afiliadoId: number | null;
};
