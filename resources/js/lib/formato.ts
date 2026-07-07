const formatoCop = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

export function cop(valor: number | null | undefined): string {
    return valor == null ? '—' : formatoCop.format(valor);
}

export function pct(valor: number | null | undefined, decimales = 1): string {
    return valor == null ? '—' : `${(valor * 100).toFixed(decimales)} %`;
}
