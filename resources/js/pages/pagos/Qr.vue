<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Loader2, TriangleAlert } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import PagoExitoso, { type PagoDetalle } from '@/components/PagoExitoso.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    titulo: string;
    monto: string;
    retornoUrl: string;
    generarUrl: string;
    estadoUrl: string;
    pollSeconds: number;
    timeoutSeconds: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pago por QR', href: '' }],
    },
});

// Estado del flujo: generando el QR / esperando el pago / pagado / expirado / error.
type Estado = 'generando' | 'esperando' | 'pagado' | 'expirado' | 'error';
const estado = ref<Estado>('generando');
const qrBase64 = ref<string | null>(null);
const mensajeError = ref<string | null>(null);
const pago = ref<PagoDetalle | null>(null);
const segundosRestantes = ref(props.timeoutSeconds);

let pollTimer: ReturnType<typeof setInterval> | null = null;
let countdownTimer: ReturnType<typeof setInterval> | null = null;

function limpiarTimers(): void {
    if (pollTimer) clearInterval(pollTimer);
    if (countdownTimer) clearInterval(countdownTimer);
    pollTimer = null;
    countdownTimer = null;
}

// Lee el token CSRF de la cookie XSRF-TOKEN (Laravel lo descifra desde el header X-XSRF-TOKEN).
function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// Pide (o regenera) el QR al servidor y arranca el polling + la cuenta regresiva.
async function generar(): Promise<void> {
    limpiarTimers();
    estado.value = 'generando';
    mensajeError.value = null;
    qrBase64.value = null;

    try {
        const resp = await fetch(props.generarUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await resp.json().catch(() => ({}));

        if (!resp.ok) {
            // El server (PagoQrController) manda { message, detalle } en errores controlados.
            throw new Error(data.detalle || data.message || 'No se pudo generar el QR.');
        }

        qrBase64.value = data.qr_base64;
        estado.value = 'esperando';
        segundosRestantes.value = props.timeoutSeconds;
        iniciarPolling();
        iniciarCuenta();
    } catch (e) {
        estado.value = 'error';
        mensajeError.value =
            e instanceof Error ? e.message : 'Ocurrió un error al generar el QR.';
    }
}

// Consulta el estado del pago cada pollSeconds; al confirmarse, muestra los datos del pago.
function iniciarPolling(): void {
    pollTimer = setInterval(consultarEstado, Math.max(props.pollSeconds, 2) * 1000);
}

function iniciarCuenta(): void {
    countdownTimer = setInterval(() => {
        segundosRestantes.value -= 1;
        if (segundosRestantes.value <= 0) {
            limpiarTimers();
            // Una última consulta por si el pago entró justo al final.
            consultarEstado(true);
        }
    }, 1000);
}

async function consultarEstado(esUltima = false): Promise<void> {
    try {
        const resp = await fetch(props.estadoUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!resp.ok) {
            // 422 = el pago llegó pero la venta no se pudo registrar (p. ej. stock agotado): mostrarlo.
            if (resp.status === 422) {
                const err = await resp.json().catch(() => ({}));
                limpiarTimers();
                estado.value = 'error';
                mensajeError.value =
                    err.detalle || err.message || 'No se pudo completar el pago.';
            }
            return; // otros fallos son puntuales: se reintenta en el próximo ciclo
        }
        const data = await resp.json();

        if (data.estado === 'pagado') {
            limpiarTimers();
            pago.value = data.pago ?? null;
            estado.value = 'pagado';
        } else if (esUltima) {
            estado.value = 'expirado';
        }
    } catch {
        // Ignorar fallos de red puntuales; el siguiente ciclo reintenta.
    }
}

function volver(): void {
    router.visit(props.retornoUrl);
}

onMounted(generar);
onBeforeUnmount(limpiarTimers);
</script>

<template>
    <Head title="Pago por QR" />

    <div class="flex h-full flex-1 flex-col items-center gap-6 p-6">
        <header class="space-y-1 text-center">
            <h1 class="text-xl font-semibold">Pago por QR</h1>
            <p class="text-sm text-muted-foreground">
                {{ titulo }} · <strong>Bs {{ monto }}</strong>
            </p>
        </header>

        <div
            class="flex w-full max-w-md flex-col items-center gap-4 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <!-- Generando -->
            <div
                v-if="estado === 'generando'"
                class="flex flex-col items-center gap-3 py-10 text-muted-foreground"
            >
                <Loader2 class="size-8 animate-spin" />
                <span class="text-sm">Generando el código QR…</span>
            </div>

            <!-- Esperando el pago -->
            <template v-else-if="estado === 'esperando'">
                <img
                    v-if="qrBase64"
                    :src="'data:image/png;base64,' + qrBase64"
                    alt="Código QR de pago"
                    class="size-80 rounded-lg bg-white p-3"
                />
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Loader2 class="size-4 animate-spin" />
                    <span>Esperando confirmación del pago…</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    Escanea el QR con tu app bancaria. El código expira en
                    {{ segundosRestantes }}s.
                </p>
            </template>

            <!-- Pagado: datos del pago + botón Regresar (misma pantalla que el resto de métodos) -->
            <PagoExitoso
                v-else-if="estado === 'pagado' && pago"
                :pago="pago"
                :retorno-url="retornoUrl"
            />

            <!-- Expirado -->
            <div
                v-else-if="estado === 'expirado'"
                class="flex flex-col items-center gap-3 py-8 text-center"
            >
                <TriangleAlert class="size-10 text-amber-500" />
                <p class="font-medium">El código QR expiró</p>
                <p class="text-sm text-muted-foreground">
                    No se detectó el pago a tiempo. Puedes generar un nuevo QR.
                </p>
                <Button @click="generar">Generar nuevo QR</Button>
            </div>

            <!-- Error -->
            <div v-else class="flex flex-col items-center gap-3 py-8 text-center">
                <TriangleAlert class="size-10 text-destructive" />
                <p class="font-medium">No se pudo generar el QR</p>
                <p class="text-sm text-muted-foreground">{{ mensajeError }}</p>
                <Button @click="generar">Reintentar</Button>
            </div>
        </div>

        <!-- En estados no finales, permitir volver/cancelar sin pagar. -->
        <Button
            v-if="estado !== 'pagado'"
            variant="outline"
            @click="volver"
        >
            Volver
        </Button>
    </div>
</template>
