// 'schedule' = día/noche automático según la hora del dispositivo del usuario.
export type Appearance = 'light' | 'dark' | 'system' | 'schedule';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};
