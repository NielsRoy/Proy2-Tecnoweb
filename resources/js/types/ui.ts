// 'schedule' = día/noche automático según la hora del dispositivo del usuario.
export type Appearance = 'light' | 'dark' | 'system' | 'schedule';
export type ResolvedAppearance = 'light' | 'dark';

// Accesibilidad: tamaño de letra base. Escala todo el UI (Tailwind usa rem).
// 'sm' = 14px · 'base' = 16px (por defecto) · 'lg' = 18px · 'xl' = 20px.
export type FontSize = 'sm' | 'base' | 'lg' | 'xl';

// Accesibilidad: alto contraste. 'normal' (por defecto) o 'high'. Es ortogonal al
// tema día/noche: el alto contraste tiene su variante para claro y para oscuro.
export type Contrast = 'normal' | 'high';

// Temas por edad. Un tema = una paleta de colores + una fuente. El tamaño de letra
// (FontSize) es ortogonal y se ajusta en Accesibilidad; día/noche y alto contraste se
// aplican encima de cualquier tema.
export type Palette = 'ninos' | 'jovenes' | 'adultos';
export type FontFamily = 'fredoka' | 'poppins' | 'instrument';
// 'custom' = combinación (paleta, fuente) que no coincide con ningún preset de edad.
export type Theme = 'ninos' | 'jovenes' | 'adultos' | 'custom';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};
