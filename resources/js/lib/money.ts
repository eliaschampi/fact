const moneyFormatter = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
    minimumFractionDigits: 2,
});

function cents(value: string | number): number {
    const normalized = String(value).trim();
    const match = normalized.match(/^-?(\d+)(?:\.(\d{1,2}))?$/);
    if (!match) return 0;

    const amount = Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));

    return normalized.startsWith('-') ? -amount : amount;
}

function decimal(value: number): string {
    const sign = value < 0 ? '-' : '';
    const absolute = Math.abs(value);

    return `${sign}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`;
}

export function money(value: string | number): string {
    return moneyFormatter.format(Number(decimal(cents(value))));
}
