// Colores para los gráficos del Dashboard (Chart.js). Paleta fija y agradable, más colores de texto y
// grilla que se adaptan al modo día/noche leyendo la clase `dark` del <html>. Se resuelven al montar
// cada gráfico (registro simple, suficiente para este proyecto).

// Paleta base (10 tonos) para barras/segmentos.
export const PALETA = [
    '#6366f1', // indigo
    '#10b981', // emerald
    '#f59e0b', // amber
    '#ef4444', // red
    '#3b82f6', // blue
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#14b8a6', // teal
    '#f97316', // orange
    '#84cc16', // lime
];

/** ¿El tema actual está en modo oscuro? (mismo criterio que el resto del proyecto). */
export function esModoOscuro(): boolean {
    return typeof document !== 'undefined'
        && document.documentElement.classList.contains('dark');
}

/** Color de texto (labels, leyenda, ejes) según el modo. */
export function colorTexto(): string {
    return esModoOscuro() ? '#e5e7eb' : '#374151';
}

/** Color de las líneas de grilla según el modo. */
export function colorGrilla(): string {
    return esModoOscuro() ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)';
}

/** Devuelve N colores de la paleta (cíclica si N > paleta). */
export function coloresPara(n: number): string[] {
    return Array.from({ length: n }, (_, i) => PALETA[i % PALETA.length]);
}
