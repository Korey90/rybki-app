import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Check, CreditCard, ExternalLink, MapPinned } from 'lucide-react';
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

type CheckoutBooking = {
    uuid: string;
    booking_number: string;
    status: BookingStatus;
    currency: string;
    total_amount: number;
    customer_name: string;
    customer_email: string;
    customer_phone: string | null;
    confirmed_at: string | null;
    venue: {
        uuid: string;
        name: string;
        slug: string;
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

type PaymentSession = {
    provider: string;
    provider_payment_id: string;
    checkout_url: string;
    amount: number;
    currency: string;
} | null;

type PageProps = {
    auth: {
        user: {
            uuid: string;
        } | null;
    };
    errors?: Record<string, string>;
};

export default function CheckoutShow({
    booking,
    payment_session,
}: {
    booking: CheckoutBooking;
    payment_session: PaymentSession;
}) {
    const { auth, errors } = usePage<PageProps>().props;
    const reservation = booking.reservations[0] ?? null;
    const isConfirmed = booking.status === 'CONFIRMED';
    const isTestPaymentSession = payment_session?.provider === 'test';

    return (
        <>
            <Head title={`Checkout ${booking.booking_number}`} />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title={booking.booking_number}
                    description={`${booking.venue.name} · ${booking.customer_name}`}
                />

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>Rezerwacja</CardTitle>
                                    <CardDescription>
                                        {booking.venue.city ?? 'Bez miasta'}
                                    </CardDescription>
                                </div>
                                <Badge variant="secondary">
                                    {bookingStatusLabels[booking.status]}
                                </Badge>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {reservation && (
                                    <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <div className="font-medium">
                                                    {reservation.spot.name}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {formatDateTime(
                                                        reservation.starts_at,
                                                    )}
                                                    {' - '}
                                                    {formatDateTime(
                                                        reservation.ends_at,
                                                    )}
                                                </div>
                                                {reservation.hold_expires_at && (
                                                    <div className="text-xs text-muted-foreground">
                                                        Hold do{' '}
                                                        {formatDateTime(
                                                            reservation.hold_expires_at,
                                                        )}
                                                    </div>
                                                )}
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
                                <CardTitle className="flex items-center gap-2">
                                    <MapPinned className="size-4 text-muted-foreground" />
                                    Łowisko
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-2">
                                <Button asChild variant="outline">
                                    <Link
                                        href={`/lowiska/${booking.venue.slug}`}
                                    >
                                        Profil łowiska
                                    </Link>
                                </Button>
                                {auth.user && isConfirmed && (
                                    <Button asChild>
                                        <Link
                                            href={`/konto/rezerwacje/${booking.uuid}`}
                                        >
                                            Moja rezerwacja
                                        </Link>
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CreditCard className="size-4 text-muted-foreground" />
                                    Płatność
                                </CardTitle>
                                <CardDescription>
                                    {formatMoney(
                                        booking.total_amount,
                                        booking.currency,
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {booking.payments.map((payment) => (
                                    <div
                                        key={payment.uuid}
                                        className="rounded-md border bg-muted/20 p-3 text-sm"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <div className="font-medium">
                                                    {payment.provider}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {payment.provider_payment_id ??
                                                        '-'}
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
                                ))}

                                {payment_session &&
                                    !isConfirmed &&
                                    isTestPaymentSession && (
                                        <Form
                                            action={`/checkout/${booking.uuid}/test-payment`}
                                            method="post"
                                            className="space-y-3"
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <InputError
                                                        message={
                                                            errors?.payment
                                                        }
                                                    />
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                        className="w-full"
                                                    >
                                                        <Check className="size-4" />
                                                        Potwierdź płatność
                                                        testową
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    )}

                                {payment_session &&
                                    !isConfirmed &&
                                    !isTestPaymentSession && (
                                        <div className="space-y-3">
                                            <InputError
                                                message={errors?.payment}
                                            />
                                            <Button asChild className="w-full">
                                                <a
                                                    href={
                                                        payment_session.checkout_url
                                                    }
                                                >
                                                    <ExternalLink className="size-4" />
                                                    Przejdź do płatności
                                                </a>
                                            </Button>
                                        </div>
                                    )}

                                {isConfirmed && (
                                    <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                        Rezerwacja jest potwierdzona.
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

CheckoutShow.layout = {
    breadcrumbs: [
        {
            title: 'Checkout',
            href: '/checkout',
        },
    ],
};
