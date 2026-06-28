import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

// Menu dinamico que llega desde el backend (HandleInertiaRequests::menuUsuario).
export type MenuAccion = {
    clave: string;
    nombre: string;
    ruta: string | null;
};

export type MenuModulo = {
    clave: string;
    nombre: string;
    icono: string | null;
    ruta: string | null;
    href: string | null; // URL resuelta (respeta el subdirectorio); null si la ruta no existe aun
    acciones: MenuAccion[];
    hijos: MenuModulo[];
};
