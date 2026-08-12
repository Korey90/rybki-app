import { Head, Link } from '@inertiajs/react';
import { CalendarDays, ReceiptText } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    formatDate,
    formatMoney,
    formatTime,
    reservationStatusLabels,
} from './utils';
import type { ReservationStatus } from './utils';

type CalendarReservation = {
    uuid: string;
    status: ReservationStatus;
    starts_at: string;
    ends_at: string;
    booking: {
        uuid: string;
        booking_number: string;
        customer_name: string;
        total_amount: number;
        currency: string;
    };
    venue: {
        uuid: string;
        name: string;
    };
    spot: {
        uuid: string;
        name: string;
    };
};

type CalendarDay = {
    date: string;
    reservations: CalendarReservation[];
};

export default function OperatorCalendar({
    range,
    days,
}: {
    range: {
        from: string;
        until: string;
        days: number;
    };
    days: CalendarDay[];
}) {
    return (
        <>
            <Head title="Kalendarz operatora" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Kalendarz"
                        description={`${formatDate(range.from)} - ${formatDate(range.until)}`}
                    />

                    <Button asChild variant="outline">
                        <Link href="/operator/bookings">
                            <ReceiptText className="size-4" />
                            Rezerwacje
                        </Link>
                    </Button>
                </div>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    {days.map((day) => (
                        <Card key={day.date}>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <CalendarDays className="size-4 text-muted-foreground" />
                                    {formatDate(day.date)}
                                </CardTitle>
                                <Badge variant="secondary">
                                    {day.reservations.length}
                                </Badge>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {day.reservations.length === 0 ? (
                                    <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                        Brak rezerwacji.
                                    </div>
                                ) : (
                                    day.reservations.map((reservation) => (
                                        <Link
                                            key={reservation.uuid}
                                            href={`/operator/bookings/${reservation.booking.uuid}`}
                                            className="block rounded-md border bg-muted/20 p-3 transition-colors hover:bg-muted/40"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0 space-y-1">
                                                    <div className="truncate text-sm font-medium">
                                                        {reservation.spot.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {formatTime(
                                                            reservation.starts_at,
                                                        )}
                                                        {' - '}
                                                        {formatTime(
                                                            reservation.ends_at,
                                                        )}
                                                    </div>
                                                    <div className="truncate text-xs text-muted-foreground">
                                                        {
                                                            reservation.booking
                                                                .customer_name
                                                        }
                                                        {' · '}
                                                        {reservation.venue.name}
                                                    </div>
                                                    <div className="text-xs font-medium">
                                                        {formatMoney(
                                                            reservation.booking
                                                                .total_amount,
                                                            reservation.booking
                                                                .currency,
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
                                        </Link>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </section>
            </div>
        </>
    );
}

OperatorCalendar.layout = {
    breadcrumbs: [
        {
            title: 'Kalendarz',
            href: '/operator/calendar',
        },
    ],
};
