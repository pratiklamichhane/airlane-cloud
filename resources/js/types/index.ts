import type { Component } from 'vue';

type AnyProps = { [key: string]: unknown };

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: Component;
    isActive?: boolean;
}

export interface AppPageCoreProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
}

export type AppPageProps<TProps extends AnyProps = AnyProps> = AppPageCoreProps & TProps;

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

