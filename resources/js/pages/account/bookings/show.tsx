import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CreditCard,
    MapPinned,
    ReceiptText,
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
} from '../../marketplace/utils';
import type {
    BookingStatus,
    PaymentStatus,
    ReservationStatus,
} from '../../marketplace/utils';

type AccountBookingDetail = {
    uuid: string;
    booking_number: string;
    status: BookingStatus;
    total_amount: number;
    currency: string;
    customer_name: string;
    customer_email: string;
    customer_phone: string | null;
    created_at: string | null;
    confirmed_at: string | null;
    cancelled_at: string | null;
    venue: {
        uuid: string;
        slug: string;
        name: string;
        city: string | null;
    };
    reservation: {
        uuid: string;
        status: ReservationStatus;
        starts_at: string;
        ends_at: string;
        spot_name: string;
    } | null;
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
        amount: number;
        currency: string;
        reason: string | null;
        status: string;
        created_at: string | null;
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

export default function AccountBookingShow({
    booking,
}: {
    booking: AccountBookingDetail;
}) {
    const { errors } = usePage<PageProps>().props;

    return (
        <>
            <Head title={booking.booking_number} />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="space-y-3">
                    <Button asChild variant="ghost" className="w-fit px-0">
                        <Link href="/konto/rezerwacje">
                            <ArrowLeft className="size-4" />
                            Moje rezerwacje
                        </Link>
                    </Button>

                    <Heading
                        title={booking.booking_number}
                        description={`${booking.venue.name} · ${booking.customer_name}`}
                    />
                </div>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <ReceiptText className="size-4 text-muted-foreground" />
                                        Rezerwacja
                                    </CardTitle>
                                    <CardDescription>
                                        {booking.venue.city ?? 'Bez miasta'}
                                    </CardDescription>
                                </div>
                                <Badge variant="secondary">
                                    {bookingStatusLabels[booking.status]}
                                </Badge>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 text-sm sm:grid-cols-3">
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
                                        label="Status"
                                        value={
                                            bookingStatusLabels[booking.status]
                                        }
                                    />
                                </div>

                                {booking.reservation && (
                                    <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                        <div className="font-medium">
                                            {booking.reservation.spot_name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {formatDateTime(
                                                booking.reservation.starts_at,
                                            )}
                                            {' - '}
                                            {formatDateTime(
                                                booking.reservation.ends_at,
                                            )}
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    {booking.price_lines.map((line) => (
                                        <div
                                            key={`${line.description}-${line.total_amount}`}
                                            className="flex items-center justify-between gap-4 text-sm"
                                        >
                                            <span>{line.description}</span>
                                            <span className="font-medium">
                                                {formatMoney(
                                                    line.total_amount,
                                                    booking.currency,
                                                )}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MapPinned className="size-4 text-muted-foreground" />
                                    Łowisko
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Button asChild variant="outline">
                                    <Link
                                        href={`/lowiska/${booking.venue.slug}`}
                                    >
                                        Profil łowiska
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Anulowanie</CardTitle>
                                <CardDescription>
                                    {booking.cancellation_policy.name}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-3 text-sm">
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
                                </div>

                                <InputError message={errors?.cancellation} />

                                {booking.cancellation.can_cancel ? (
                                    <Form
                                        action={`/konto/rezerwacje/${booking.uuid}/cancel`}
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
                                    <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                        Anulowanie nie jest dostępne dla tego
                                        statusu.
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Kwota</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-semibold">
                                    {formatMoney(
                                        booking.total_amount,
                                        booking.currency,
                                    )}
                                </div>
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
                                    <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                        Brak płatności.
                                    </div>
                                ) : (
                                    booking.payments.map((payment) => (
                                        <div
                                            key={payment.uuid}
                                            className="rounded-md border bg-muted/20 p-3 text-sm"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-medium">
                                                        {formatMoney(
                                                            payment.amount,
                                                            payment.currency,
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {payment.provider}
                                                    </div>
                                                </div>
                                                <Badge variant="outline">
                                                    {
                                                        paymentStatusLabels[
                                                            payment.status
                                                        ]
                                                    }
                                                </Badge>
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
                                    <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                        Brak zwrotów.
                                    </div>
                                ) : (
                                    <div className="space-y-3">
                                        {booking.refunds.map((refund) => (
                                            <div
                                                key={`${refund.status}-${refund.amount}-${refund.created_at}`}
                                                className="rounded-md border bg-muted/20 p-3 text-sm"
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

function Info({ label, value }: { label: string; value: string | number }) {
    return (
        <div>
            <span className="block text-xs text-muted-foreground">{label}</span>
            <span>{value}</span>
        </div>
    );
}

AccountBookingShow.layout = {
    breadcrumbs: [
        {
            title: 'Moje rezerwacje',
            href: '/konto/rezerwacje',
        },
    ],
};
