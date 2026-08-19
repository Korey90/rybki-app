import { Head, Link } from '@inertiajs/react';
import { Download, ReceiptText, Search } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    bookingStatusLabels,
    formatDateTime,
    formatMoney,
    paymentStatusLabels,
    reservationStatusLabels,
} from '@/pages/operator/utils';
import type {
    BookingStatus,
    PaymentStatus,
    ReservationStatus,
} from '@/pages/operator/utils';

type AdminBooking = {
    uuid: string;
    booking_number: string;
    status: BookingStatus;
    customer_name: string;
    customer_email: string;
    customer_phone: string | null;
    total_amount: number;
    operator_amount: number;
    platform_fee_amount: number;
    currency: string;
    confirmed_at: string | null;
    created_at: string | null;
    organization: {
        uuid: string;
        name: string;
    };
    venue: {
        uuid: string;
        name: string;
        slug: string;
    };
    user: {
        uuid: string;
        name: string;
        email: string;
    } | null;
    reservation: {
        uuid: string;
        status: ReservationStatus;
        starts_at: string;
        ends_at: string;
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

export default function AdminBookingsIndex({
    bookings,
    filters,
    statuses,
    export_url,
}: {
    bookings: AdminBooking[];
    filters: {
        q: string | null;
        status: BookingStatus | null;
    };
    statuses: BookingStatus[];
    export_url: string;
}) {
    return (
        <>
            <Head title="Rezerwacje admin" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Rezerwacje"
                        description="Podgląd rezerwacji, klientów, łowisk i płatności"
                    />
                    <Button asChild variant="outline">
                        <a href={export_url}>
                            <Download className="size-4" />
                            CSV
                        </a>
                    </Button>
                </div>

                <FilterCard filters={filters} statuses={statuses} />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ReceiptText className="size-4 text-muted-foreground" />
                            Wyniki
                        </CardTitle>
                        <CardDescription>
                            Maksymalnie 100 najnowszych dopasowań
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {bookings.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak rezerwacji dla wybranych filtrów.
                            </div>
                        ) : (
                            bookings.map((booking) => (
                                <BookingCard
                                    key={booking.uuid}
                                    booking={booking}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function FilterCard({
    filters,
    statuses,
}: {
    filters: {
        q: string | null;
        status: BookingStatus | null;
    };
    statuses: BookingStatus[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Filtry</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    action="/admin/bookings"
                    method="get"
                    className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_13rem_8rem]"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="q">Szukaj</Label>
                        <Input
                            id="q"
                            name="q"
                            defaultValue={filters.q ?? ''}
                            placeholder="Numer, klient, email, łowisko, organizacja"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            name="status"
                            defaultValue={filters.status ?? ''}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                        >
                            <option value="">Wszystkie</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {bookingStatusLabels[status]}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="flex items-end">
                        <Button type="submit" className="w-full">
                            <Search className="size-4" />
                            Szukaj
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function BookingCard({ booking }: { booking: AdminBooking }) {
    return (
        <div className="rounded-lg border p-4">
            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem_16rem]">
                <div className="min-w-0 space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">
                            {booking.booking_number}
                        </span>
                        <Badge variant="outline">
                            {bookingStatusLabels[booking.status]}
                        </Badge>
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {booking.customer_name} · {booking.customer_email}
                    </div>
                    <div className="text-sm">
                        {booking.organization.name} ·{' '}
                        <Link
                            href={`/lowiska/${booking.venue.slug}`}
                            className="underline-offset-4 hover:underline"
                        >
                            {booking.venue.name}
                        </Link>
                    </div>
                </div>

                <div className="space-y-1 text-sm">
                    <span className="block text-xs text-muted-foreground">
                        Termin
                    </span>
                    <span>
                        {formatDateTime(booking.reservation?.starts_at ?? null)}
                    </span>
                    {booking.reservation && (
                        <div>
                            <Badge variant="secondary">
                                {
                                    reservationStatusLabels[
                                        booking.reservation.status
                                    ]
                                }
                            </Badge>
                            <div className="mt-1 text-xs text-muted-foreground">
                                {booking.reservation.spot.name}
                            </div>
                        </div>
                    )}
                </div>

                <div className="space-y-1 text-sm xl:text-right">
                    <span className="block text-xs text-muted-foreground">
                        Kwota
                    </span>
                    <div className="font-medium">
                        {formatMoney(booking.total_amount, booking.currency)}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        prowizja{' '}
                        {formatMoney(
                            booking.platform_fee_amount,
                            booking.currency,
                        )}
                    </div>
                    {booking.latest_payment && (
                        <Badge variant="secondary">
                            {paymentStatusLabels[booking.latest_payment.status]}
                        </Badge>
                    )}
                </div>
            </div>
        </div>
    );
}

AdminBookingsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Rezerwacje admin',
            href: '/admin/bookings',
        },
    ],
};
