import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ReceiptText } from 'lucide-react';
import Heading from '@/components/heading';
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

type BookingListItem = {
    uuid: string;
    booking_number: string;
    status: BookingStatus;
    customer_name: string;
    customer_email: string;
    total_amount: number;
    operator_amount: number;
    platform_fee_amount: number;
    currency: string;
    created_at: string | null;
    confirmed_at: string | null;
    venue: {
        uuid: string;
        name: string;
    };
    reservation: {
        uuid: string;
        status: ReservationStatus;
        starts_at: string;
        ends_at: string;
        hold_expires_at: string | null;
        spot: {
            uuid: string;
            name: string;
        };
    } | null;
    latest_payment: {
        uuid: string;
        provider: string;
        status: PaymentStatus;
        amount: number;
        currency: string;
        paid_at: string | null;
    } | null;
};

const bookingStatuses = Object.keys(bookingStatusLabels) as BookingStatus[];

export default function OperatorBookingsIndex({
    bookings,
    filters,
    status_counts,
}: {
    bookings: BookingListItem[];
    filters: {
        status: BookingStatus | null;
    };
    status_counts: Record<BookingStatus, number>;
}) {
    return (
        <>
            <Head title="Rezerwacje operatora" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Rezerwacje"
                        description="Zamówienia i blokady stanowisk z Twoich łowisk"
                    />

                    <Button asChild variant="outline">
                        <Link href="/operator/calendar">
                            <CalendarDays className="size-4" />
                            Kalendarz
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filtry statusu</CardTitle>
                        <CardDescription>
                            Bieżący stan rezerwacji w module bookingowym
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <Button
                            asChild
                            variant={
                                filters.status === null ? 'default' : 'outline'
                            }
                            size="sm"
                        >
                            <Link href="/operator/bookings">
                                Wszystkie
                                <Badge variant="secondary">
                                    {Object.values(status_counts).reduce(
                                        (sum, value) => sum + value,
                                        0,
                                    )}
                                </Badge>
                            </Link>
                        </Button>

                        {bookingStatuses.map((status) => (
                            <Button
                                key={status}
                                asChild
                                variant={
                                    filters.status === status
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                            >
                                <Link
                                    href={`/operator/bookings?status=${status}`}
                                >
                                    {bookingStatusLabels[status]}
                                    <Badge variant="secondary">
                                        {status_counts[status] ?? 0}
                                    </Badge>
                                </Link>
                            </Button>
                        ))}
                    </CardContent>
                </Card>

                <section className="space-y-3">
                    {bookings.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                            Brak rezerwacji dla wybranego widoku.
                        </div>
                    ) : (
                        bookings.map((booking) => (
                            <Link
                                key={booking.uuid}
                                href={`/operator/bookings/${booking.uuid}`}
                                className="block rounded-lg border bg-card p-4 shadow-xs transition-[background-color,box-shadow] hover:bg-muted/40 hover:shadow-sm"
                            >
                                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_14rem_14rem]">
                                    <div className="min-w-0 space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <ReceiptText className="size-4 text-muted-foreground" />
                                            <span className="font-medium">
                                                {booking.booking_number}
                                            </span>
                                            <Badge variant="outline">
                                                {
                                                    bookingStatusLabels[
                                                        booking.status
                                                    ]
                                                }
                                            </Badge>
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {booking.customer_name}
                                            {' · '}
                                            {booking.customer_email}
                                        </div>
                                        <div className="text-sm">
                                            {booking.venue.name}
                                            {booking.reservation
                                                ? ` · ${booking.reservation.spot.name}`
                                                : ''}
                                        </div>
                                    </div>

                                    <div className="space-y-1 text-sm">
                                        <span className="block text-xs text-muted-foreground">
                                            Termin
                                        </span>
                                        <span>
                                            {formatDateTime(
                                                booking.reservation
                                                    ?.starts_at ?? null,
                                            )}
                                        </span>
                                        {booking.reservation && (
                                            <Badge variant="secondary">
                                                {
                                                    reservationStatusLabels[
                                                        booking.reservation
                                                            .status
                                                    ]
                                                }
                                            </Badge>
                                        )}
                                    </div>

                                    <div className="space-y-1 text-sm xl:text-right">
                                        <span className="block text-xs text-muted-foreground">
                                            Kwota
                                        </span>
                                        <span className="font-medium">
                                            {formatMoney(
                                                booking.total_amount,
                                                booking.currency,
                                            )}
                                        </span>
                                        {booking.latest_payment && (
                                            <div className="text-xs text-muted-foreground">
                                                {
                                                    paymentStatusLabels[
                                                        booking.latest_payment
                                                            .status
                                                    ]
                                                }
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </Link>
                        ))
                    )}
                </section>
            </div>
        </>
    );
}

OperatorBookingsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Rezerwacje',
            href: '/operator/bookings',
        },
    ],
};
