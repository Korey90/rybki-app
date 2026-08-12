import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ReceiptText } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type AccountBooking = {
    uuid: string;
    booking_number: string;
    status: BookingStatus;
    total_amount: number;
    currency: string;
    created_at: string | null;
    confirmed_at: string | null;
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
    latest_payment: {
        status: PaymentStatus;
        amount: number;
        currency: string;
    } | null;
};

export default function AccountBookingsIndex({
    bookings,
}: {
    bookings: AccountBooking[];
}) {
    return (
        <>
            <Head title="Moje rezerwacje" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Moje rezerwacje"
                        description="Historia terminów i płatności przypisanych do konta"
                    />

                    <Button asChild variant="outline">
                        <Link href="/lowiska">
                            <CalendarDays className="size-4" />
                            Szukaj łowiska
                        </Link>
                    </Button>
                </div>

                <section className="space-y-3">
                    {bookings.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                            Brak rezerwacji przypisanych do konta.
                        </div>
                    ) : (
                        bookings.map((booking) => (
                            <Link
                                key={booking.uuid}
                                href={`/konto/rezerwacje/${booking.uuid}`}
                                className="block rounded-lg border bg-card p-4 shadow-xs transition-[background-color,box-shadow] hover:bg-muted/40 hover:shadow-sm"
                            >
                                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_14rem_10rem]">
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
                                            {booking.venue.name}
                                            {booking.reservation
                                                ? ` · ${booking.reservation.spot_name}`
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
                                    <div className="text-sm font-medium lg:text-right">
                                        {formatMoney(
                                            booking.total_amount,
                                            booking.currency,
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

AccountBookingsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Moje rezerwacje',
            href: '/konto/rezerwacje',
        },
    ],
};
