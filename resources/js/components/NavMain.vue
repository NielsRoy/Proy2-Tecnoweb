<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { iconoMenu } from '@/lib/menuIcons';
import type { MenuModulo } from '@/types';

defineProps<{
    items: MenuModulo[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Menú</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="modulo in items" :key="modulo.clave">
                <!-- Módulo con submódulos: grupo colapsable -->
                <Collapsible
                    v-if="modulo.hijos.length"
                    as-child
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :tooltip="modulo.nombre">
                                <component :is="iconoMenu(modulo.icono)" />
                                <span>{{ modulo.nombre }}</span>
                                <ChevronRight
                                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="hijo in modulo.hijos"
                                    :key="hijo.clave"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="hijo.href ? isCurrentUrl(hijo.href) : false"
                                    >
                                        <Link v-if="hijo.href" :href="hijo.href">
                                            <span>{{ hijo.nombre }}</span>
                                        </Link>
                                        <span v-else class="cursor-not-allowed opacity-50">
                                            {{ hijo.nombre }}
                                        </span>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <!-- Módulo simple: enlace directo (o no-clicable si la ruta aún no existe) -->
                <SidebarMenuItem v-else>
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
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
