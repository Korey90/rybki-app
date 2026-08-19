export type User = {
    id: number;
    uuid: string;
    name: string;
    email: string;
    phone?: string | null;
    account_type: 'ANGLER' | 'VENUE_OWNER';
    can_use_operator_panel?: boolean;
    is_admin?: boolean;
    avatar?: string;
    email_verified_at: string | null;
    phone_verified_at?: string | null;
    marketing_consent_at?: string | null;
    marketing_consent_document_version?: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
    notifications: {
        unread_count: number;
        recent: {
            id: string;
            type: string;
            title: string;
            message: string;
            url: string;
            read_at: string | null;
            created_at: string | null;
        }[];
    };
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

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
