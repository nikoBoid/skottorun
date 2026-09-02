export type User = {
    id: number;
    name: string;
    username: string;
    email?: string;
    role: 'dm' | 'player';
    is_active: boolean;
    avatar?: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
