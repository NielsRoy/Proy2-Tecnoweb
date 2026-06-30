import {
    BadgePercent,
    Boxes,
    ChartColumn,
    CreditCard,
    LayoutGrid,
    type LucideIcon,
    Package,
    Receipt,
    ScrollText,
    ShieldCheck,
    ShoppingCart,
    Tags,
    Users,
} from '@lucide/vue';

// La BD guarda el icono como string (columna modulo.icono). Aqui lo mapeamos al
// componente lucide. Para agregar un modulo con icono nuevo: importalo y registralo aqui.
const iconos: Record<string, LucideIcon> = {
    LayoutGrid,
    Users,
    Package,
    Tags,
    ShoppingCart,
    Receipt,
    Boxes,
    BadgePercent,
    CreditCard,
    ChartColumn,
    ScrollText,
    ShieldCheck,
};

export function iconoMenu(nombre: string | null): LucideIcon | undefined {
    return nombre ? iconos[nombre] : undefined;
}
