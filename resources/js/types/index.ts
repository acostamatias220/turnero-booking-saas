import { LucideIcon } from 'lucide-react';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'staff';
    role_label: string;
    is_owner: boolean;
    avatar?: string;
    email_verified_at: string | null;
    [key: string]: unknown;
}

export interface AuthBusiness {
    name: string;
    slug: string;
    accent_color: string;
    public_url: string;
}

export interface Auth {
    user: AuthUser;
    business: AuthBusiness | null;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    ownerOnly?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}

/** Kept for starter-kit components. */
export type User = AuthUser;

export type AppointmentStatus = 'pending_payment' | 'confirmed' | 'completed' | 'no_show' | 'cancelled' | 'expired';

export interface Appointment {
    id: string;
    reference: string;
    status: AppointmentStatus;
    status_label: string;
    starts_at: string;
    ends_at: string;
    local_date: string;
    local_start: string;
    local_end: string;
    duration_minutes: number;
    price: string;
    price_cents: number;
    deposit: string;
    deposit_cents: number;
    hold_expires_at: string | null;
    notes: string | null;
    service?: { id: number; name: string };
    staff?: { id: number; name: string };
    customer?: { id: number; name: string; email: string; phone: string | null };
    payment?: { status: string; status_label: string; amount: string } | null;
}

export interface Service {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    buffer_minutes: number;
    price_cents: number;
    price: string;
    is_active: boolean;
    staff_ids?: number[];
}

export interface StaffMember {
    id: number;
    name: string;
    title: string | null;
    initials: string;
}

export interface PublicBusiness {
    name: string;
    slug: string;
    category: string;
    tagline: string | null;
    address: string | null;
    phone: string | null;
    timezone: string;
    accent_color: string;
    deposit_percent: number;
    cancellation_notice_hours: number;
    hold_minutes: number;
}

export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}
