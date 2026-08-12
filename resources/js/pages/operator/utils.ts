export type BookingStatus =
    | 'PAYMENT_PENDING'
    | 'CONFIRMED'
    | 'CANCELLED'
    | 'PAYMENT_FAILED'
    | 'PARTIALLY_REFUNDED'
    | 'REFUNDED';

export type ReservationStatus = 'HOLD' | 'CONFIRMED' | 'CANCELLED' | 'EXPIRED';

export type PaymentStatus =
    | 'CREATED'
    | 'PROCESSING'
    | 'SUCCEEDED'
    | 'FAILED'
    | 'CANCELLED'
    | 'REFUNDED'
    | 'PARTIALLY_REFUNDED';

export const bookingStatusLabels: Record<BookingStatus, string> = {
    PAYMENT_PENDING: 'Oczekuje na płatność',
    CONFIRMED: 'Potwierdzona',
    CANCELLED: 'Anulowana',
    PAYMENT_FAILED: 'Płatność nieudana',
    PARTIALLY_REFUNDED: 'Częściowy zwrot',
    REFUNDED: 'Zwrócona',
};

export const reservationStatusLabels: Record<ReservationStatus, string> = {
    HOLD: 'Hold',
    CONFIRMED: 'Potwierdzona',
    CANCELLED: 'Anulowana',
    EXPIRED: 'Wygasła',
};

export const paymentStatusLabels: Record<PaymentStatus, string> = {
    CREATED: 'Utworzona',
    PROCESSING: 'W toku',
    SUCCEEDED: 'Opłacona',
    FAILED: 'Nieudana',
    CANCELLED: 'Anulowana',
    REFUNDED: 'Zwrócona',
    PARTIALLY_REFUNDED: 'Częściowy zwrot',
};

export function formatMoney(amount: number, currency = 'PLN') {
    return new Intl.NumberFormat('pl-PL', {
        style: 'currency',
        currency,
    }).format(amount / 100);
}

export function formatDate(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pl-PL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

export function formatDateTime(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pl-PL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

export function formatTime(value: string | null) {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('pl-PL', {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
