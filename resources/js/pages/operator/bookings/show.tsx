import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CreditCard,
    ReceiptText,
    UserRound,
    XCircle,
} from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    bookingStatusLabels,
    formatDateTime,
    formatMoney,
    paymentStatusLabels,
    reservationStatusLabels,
} from '../utils';
import type { BookingStatus, PaymentStatus, ReservationStatus } from '../utils';

type BookingDetail = {
    uuid: string;
    booking_number: string;
    status: BookingStatus;
    currency: string;
    subtotal_amount: number;
    discount_amount: number;
    total_amount: number;
    commission_bps: number;
    platform_fee_amount: number;
    operator_amount: number;
    customer_name: string;
    customer_email: string;
    customer_phone: string | null;
    created_at: string | null;
    confirmed_at: string | null;
    cancelled_at: string | null;
    venue: {
        uuid: string;
        name: string;
        city: string | null;
    };
    reservations: {
        uuid: string;
        status: ReservationStatus;
        starts_at: string;
        ends_at: string;
        hold_expires_at: string | null;
        spot: {
            uuid: string;
            name: string;
        };
    }[];
    price_lines: {
        type: string;
        description: string;
        quantity: number;
        unit_amount: number;
        total_amount: number;
    }[];
    payments: {
        uuid: string;
        provider: string;
        provider_payment_id: string | null;
        status: PaymentStatus;
        amount: number;
        currency: string;
        paid_at: string | null;
        failed_at: string | null;
        created_at: string | null;
    }[];
    refunds: {
        provider_refund_id: string | null;
        amount: number;
        currency: string;
        reason: string | null;
        status: string;
        created_at: string | null;
        initiated_by: {
            name: string;
            email: string;
        } | null;
    }[];
    cancellation_policy: {
        name: string;
        free_cancellation_hours: number;
        late_refund_percentage: number;
    };
    cancellation: {
        can_cancel: boolean;
        reason: string | null;
        refund_amount: number;
        refund_percentage: number;
        currency: string;
        free_cancellation_until: string | null;
    };
};

type PageProps = {
    errors?: Record<string, string>;
};

export default function OperatorBookingShow({
    booking,
}: {
    booking: BookingDetail;
}) {
    const { errors } = usePage<PageProps>().props;

    return (
        <>
            <Head title={booking.booking_number} />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-3">
                        <Button asChild variant="ghost" className="w-fit px-0">
                            <Link href="/operator/bookings">
                                <ArrowLeft className="size-4" />
                                Rezerwacje
                            </Link>
                        </Button>

                        <Heading
                            title={booking.booking_number}
                            description={`${booking.venue.name} · ${booking.customer_name}`}
                        />
                    </div>

                    <Badge variant="secondary">
                        {bookingStatusLabels[booking.status]}
                    </Badge>
                </div>

                <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <MoneyCard
                        title="Brutto"
                        value={booking.total_amount}
                        currency={booking.currency}
                    />
                    <MoneyCard
                        title="Prowizja"
                        value={booking.platform_fee_amount}
                        currency={booking.currency}
                    />
                    <MoneyCard
                        title="Dla operatora"
                        value={booking.operator_amount}
                        currency={booking.currency}
                    />
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium">
                                Prowizja bps
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold">
                                {booking.commission_bps}
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ReceiptText className="size-4 text-muted-foreground" />
                                    Rezerwacja
                                </CardTitle>
                                <CardDescription>
                                    Termin, stanowisko i cena
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 text-sm md:grid-cols-3">
                                    <Info
                                        label="Łowisko"
                                        value={booking.venue.name}
                                    />
                                    <Info
                                        label="Miasto"
                                        value={booking.venue.city ?? '-'}
                                    />
                                    <Info
                                        label="Utworzono"
                                        value={formatDateTime(
                                            booking.created_at,
                                        )}
                                    />
                                    <Info
                                        label="Potwierdzono"
                                        value={formatDateTime(
                                            booking.confirmed_at,
                                        )}
                                    />
                                    <Info
                                        label="Anulowano"
                                        value={formatDateTime(
                                            booking.cancelled_at,
                                        )}
                                    />
                                    <Info
                                        label="Rabat"
                                        value={formatMoney(
                                            booking.discount_amount,
                                            booking.currency,
                                        )}
                                    />
                                </div>

                                <div className="space-y-3">
                                    {booking.reservations.map((reservation) => (
                                        <div
                                            key={reservation.uuid}
                                            className="rounded-md border p-3"
                                        >
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <div className="font-medium">
                                                        {reservation.spot.name}
                                                    </div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {formatDateTime(
                                                            reservation.starts_at,
                                                        )}
                                                        {' - '}
                                                        {formatDateTime(
                                                            reservation.ends_at,
                                                        )}
                                                    </div>
                                                </div>
                                                <Badge variant="outline">
                                                    {
                                                        reservationStatusLabels[
                                                            reservation.status
                                                        ]
                                                    }
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Zasady anulowania</CardTitle>
                                <CardDescription>
                                    {booking.cancellation_policy.name}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm md:grid-cols-3">
                                <Info
                                    label="Darmowe anulowanie do"
                                    value={formatDateTime(
                                        booking.cancellation
                                            .free_cancellation_until,
                                    )}
                                />
                                <Info
                                    label="Zwrot po tym czasie"
                                    value={`${booking.cancellation_policy.late_refund_percentage}%`}
                                />
                                <Info
                                    label="Przewidywany zwrot"
                                    value={formatMoney(
                                        booking.cancellation.refund_amount,
                                        booking.cancellation.currency,
                                    )}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Linie ceny</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {booking.price_lines.map((line) => (
                                    <div
                                        key={`${line.description}-${line.total_amount}`}
                                        className="grid gap-2 rounded-md border p-3 text-sm sm:grid-cols-[minmax(0,1fr)_6rem_8rem]"
                                    >
                                        <div>
                                            <span className="font-medium">
                                                {line.description}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {line.type}
                                            </span>
                                        </div>
                                        <div>
                                            {line.quantity} x{' '}
                                            {formatMoney(
                                                line.unit_amount,
                                                booking.currency,
                                            )}
                                        </div>
                                        <div className="font-medium sm:text-right">
                                            {formatMoney(
                                                line.total_amount,
                                                booking.currency,
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Anulowanie</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <InputError message={errors?.cancellation} />

                                {booking.cancellation.can_cancel ? (
                                    <Form
                                        action={`/operator/bookings/${booking.uuid}/cancel`}
                                        method="patch"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                <XCircle className="size-4" />
                                                Anuluj rezerwację
                                            </Button>
                                        )}
                                    </Form>
                                ) : (
                                    <EmptyState value="Anulowanie nie jest dostępne dla tego statusu." />
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <UserRound className="size-4 text-muted-foreground" />
                                    Klient
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <Info
                                    label="Imię i nazwisko"
                                    value={booking.customer_name}
                                />
                                <Info
                                    label="Email"
                                    value={booking.customer_email}
                                />
                                <Info
                                    label="Telefon"
                                    value={booking.customer_phone ?? '-'}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CreditCard className="size-4 text-muted-foreground" />
                                    Płatności
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {booking.payments.length === 0 ? (
                                    <EmptyState value="Brak płatności." />
                                ) : (
                                    booking.payments.map((payment) => (
                                        <div
                                            key={payment.uuid}
                                            className="rounded-md border p-3 text-sm"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="font-medium">
                                                        {formatMoney(
                                                            payment.amount,
                                                            payment.currency,
                                                        )}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {payment.provider}
                                                        {' · '}
                                                        {payment.provider_payment_id ??
                                                            '-'}
                                                    </div>
                                                </div>
                                                <Badge variant="secondary">
                                                    {
                                                        paymentStatusLabels[
                                                            payment.status
                                                        ]
                                                    }
                                                </Badge>
                                            </div>
                                            <div className="mt-2 text-xs text-muted-foreground">
                                                {formatDateTime(
                                                    payment.paid_at ??
                                                        payment.failed_at ??
                                                        payment.created_at,
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Zwroty</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {booking.refunds.length === 0 ? (
                                    <EmptyState value="Brak zwrotów." />
                                ) : (
                                    <div className="space-y-3">
                                        {booking.refunds.map((refund) => (
                                            <div
                                                key={`${refund.provider_refund_id}-${refund.amount}`}
                                                className="rounded-md border p-3 text-sm"
                                            >
                                                <div className="font-medium">
                                                    {formatMoney(
                                                        refund.amount,
                                                        refund.currency,
                                                    )}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {refund.status}
                                                    {' · '}
                                                    {refund.reason ?? '-'}
                                                </div>
                                                {refund.initiated_by && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {
                                                            refund.initiated_by
                                                                .name
                                                        }
                                                        {' · '}
                                                        {
                                                            refund.initiated_by
                                                                .email
                                                        }
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </aside>
                </section>
            </div>
        </>
    );
}

function MoneyCard({
    title,
    value,
    currency,
}: {
    title: string;
    value: number;
    currency: string;
}) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold">
                    {formatMoney(value, currency)}
                </div>
            </CardContent>
        </Card>
    );
}

function Info({ label, value }: { label: string; value: string | number }) {
    return (
        <div>
            <span className="block text-xs text-muted-foreground">{label}</span>
            <span>{value}</span>
        </div>
    );
}

function EmptyState({ value }: { value: string }) {
    return (
        <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
            {value}
        </div>
    );
}

OperatorBookingShow.layout = {
    breadcrumbs: [
        {
            title: 'Rezerwacje',
            href: '/operator/bookings',
        },
    ],
};
