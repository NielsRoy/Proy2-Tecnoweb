export type User = {
    id: number;
    name: string;
    email: string;
    ci: string | null;
    telefono: string | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    // Nombres de los roles vigentes del usuario autenticado ([] si no hay sesión).
    roles: string[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */
