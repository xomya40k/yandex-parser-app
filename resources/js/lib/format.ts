const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

export function formatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return dateFormatter.format(date);
}

export function formatRating(
    rating: string | number | null | undefined,
): string {
    if (rating === null || rating === undefined || rating === '') {
        return '—';
    }

    const numeric = typeof rating === 'number' ? rating : Number(rating);
    if (Number.isNaN(numeric)) {
        return String(rating);
    }

    return numeric.toFixed(1);
}
