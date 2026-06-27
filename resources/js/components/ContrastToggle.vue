<script setup lang="ts">
import { Contrast } from '@lucide/vue';
import { useContrast } from '@/composables/useContrast';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

const { highContrast, updateHighContrast } = useContrast();
</script>

<template>
    <div
        class="flex items-center justify-between gap-4 rounded-lg border border-border p-4"
    >
        <div class="flex items-start gap-3">
            <Contrast class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />
            <div class="space-y-1">
                <Label for="high-contrast" class="text-sm font-medium">
                    Alto contraste
                </Label>
                <p class="text-sm text-muted-foreground">
                    Aumenta el contraste de colores para mejorar la legibilidad.
                    Funciona tanto en modo día como noche.
                </p>
            </div>
        </div>

        <Switch
            id="high-contrast"
            :model-value="highContrast"
            @update:model-value="updateHighContrast"
        />
    </div>
</template>
