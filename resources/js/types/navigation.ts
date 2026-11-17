import type { Component } from 'vue';
import type { Auth } from './auth';

export type BreadcrumbItem = {
    title: string;
    href: string;
};

export type BreadcrumbItemType = BreadcrumbItem;

export type NavItem = {
    title: string;
    href: string;
    icon?: Component;
};

export type SharedData = {
    name: string;
    auth: Auth;
    sidebarOpen?: boolean;
    quote?: {
        message: string;
        author: string;
    };
    [key: string]: unknown;
};
