<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { iconoMenu } from '@/lib/menuIcons';
import { inicio } from '@/routes';
import type { MenuModulo } from '@/types';

// Sección "Mi cuenta" (perspectiva cliente), separada por una línea del menú admin. Recibe los
// módulos cliente del menú dinámico (mis_compras/mis_pagos) y antepone un enlace fijo a la Tienda.
defineProps<{ items: MenuModulo[] }>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarSeparator class="my-1" />
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Mi cuenta</SidebarGroupLabel>
        <SidebarMenu>
            <!-- Tienda: ruta fija (libre acceso), fuera de la matriz. -->
            <SidebarMenuItem>
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(inicio().url)"
                    tooltip="Tienda"
                >
                    <Link :href="inicio()">
                        <ShoppingCart />
                        <span>Tienda</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <!-- Módulos cliente del menú dinámico (mis_compras / mis_pagos). -->
            <SidebarMenuItem v-for="modulo in items" :key="modulo.clave">
                <SidebarMenuButton
                    as-child
                    :is-active="modulo.href ? isCurrentUrl(modulo.href) : false"
                    :tooltip="modulo.nombre"
                >
                    <Link v-if="modulo.href" :href="modulo.href">
                        <component :is="iconoMenu(modulo.icono)" />
                        <span>{{ modulo.nombre }}</span>
                    </Link>
                    <span
                        v-else
                        class="cursor-not-allowed opacity-50"
                        title="Próximamente"
                    >
                        <component :is="iconoMenu(modulo.icono)" />
                        <span>{{ modulo.nombre }}</span>
                    </span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
