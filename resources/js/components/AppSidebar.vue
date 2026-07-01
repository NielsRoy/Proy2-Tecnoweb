<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, FolderGit2 } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavCliente from '@/components/NavCliente.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { inicio } from '@/routes';
import type { MenuModulo, NavItem } from '@/types';

const page = usePage();

// Menu dinamico desde la BD (segun el rol del usuario), compartido por el backend.
const menu = computed<MenuModulo[]>(() => page.props.menu ?? []);

// Los modulos de la perspectiva CLIENTE se muestran aparte (seccion "Mi cuenta"), separados del
// menu admin por una linea. El resto va en el menu principal.
const CLIENTE_CLAVES = ['mis_compras', 'mis_pagos'];
const menuAdmin = computed(() =>
    menu.value.filter((m) => !CLIENTE_CLAVES.includes(m.clave)),
);
const menuCliente = computed(() =>
    menu.value.filter((m) => CLIENTE_CLAVES.includes(m.clave)),
);

const footerNavItems: NavItem[] = [
    {
        title: 'Repositorio',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentación',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="inicio()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="menuAdmin" />
            <NavCliente :items="menuCliente" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
