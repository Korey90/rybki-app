import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    Clock,
    CreditCard,
    Gauge,
    MapPinned,
    ReceiptText,
    Wallet,
} from 'lucide-react';
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
    formatDate,
    formatDateTime,
    formatMoney,
    reservationStatusLabels,
} from './utils';
import type { BookingStatus, ReservationStatus } from './utils';

type DashboardStats = {
    organizations_count: number;
    venues_count: number;
    spots_count: number;
    today_reservations_count: number;
    active_holds_count: number;
    gross_revenue_amount: number;
    platform_fee_amount: number;
    operator_revenue_amount: number;
    pending_payments_count: number;
};

type UpcomingReservation = {
    uuid: string;
    status: ReservationStatus;
    starts_at: string;
    ends_at: string;
    hold_expires_at: string | null;
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

type RecentBooking = {
    uuid: string;
    booking_number: string;
    status: BookingStatus;
    customer_name: string;
    total_amount: number;
    currency: string;
    created_at: string | null;
    venue: {
        uuid: string;
        name: string;
    };
    reservation: {
        starts_at: string;
        ends_at: string;
        spot_name: string | null;
    } | null;
};

type DailySchedule = {
    date: string;
    reservations_count: number;
    revenue_amount: number;
};

export default function OperatorDashboard({
    stats,
    upcoming_reservations,
    recent_bookings,
    daily_schedule,
}: {
    organizations: {
        uuid: string;
        name: string;
        role: 'OWNER' | 'MANAGER';
    }[];
    stats: DashboardStats;
    upcoming_reservations: UpcomingReservation[];
    recent_bookings: RecentBooking[];
    daily_schedule: DailySchedule[];
}) {
    const maxDayReservations = Math.max(
        1,
        ...daily_schedule.map((day) => day.reservations_count),
    );

    return (
        <>
            <Head title="Panel operatora" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Panel operatora"
                        description="Rezerwacje, obłożenie i płatności Twoich łowisk"
                    />

                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/operator/bookings">
                                <ReceiptText className="size-4" />
                                Rezerwacje
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="/operator/calendar">
                                <CalendarDays className="size-4" />
                                Kalendarz
                            </Link>
                        </Button>
                    </div>
                </div>

                <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        title="Dzisiaj"
                        value={stats.today_reservations_count}
                        note="potwierdzone pobyty"
                        icon={CalendarDays}
                    />
                    <StatCard
                        title="Aktywne holdy"
                        value={stats.active_holds_count}
                        note="czekają na płatność"
                        icon={Clock}
                    />
                    <StatCard
                        title="Przychód brutto"
                        value={formatMoney(stats.gross_revenue_amount)}
                        note="potwierdzone rezerwacje"
                        icon={CreditCard}
                    />
                    <StatCard
                        title="Dla operatora"
                        value={formatMoney(stats.operator_revenue_amount)}
                        note={`${formatMoney(stats.platform_fee_amount)} prowizji`}
                        icon={Wallet}
                    />
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_25rem]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Gauge className="size-4 text-muted-foreground" />
                                    Najbliższe dni
                                </CardTitle>
                                <CardDescription>
                                    Obłożenie i przychód według daty
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {daily_schedule.map((day) => (
                                    <div
                                        key={day.date}
                                        className="grid gap-3 rounded-md border bg-muted/20 p-3 sm:grid-cols-[8rem_minmax(0,1fr)_8rem]"
                                    >
                                        <div className="text-sm font-medium">
                                            {formatDate(day.date)}
                                        </div>
                                        <div className="flex min-w-0 items-center gap-3">
                                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary"
                                                    style={{
                                                        width: `${Math.max(8, (day.reservations_count / maxDayReservations) * 100)}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="w-12 text-right text-sm text-muted-foreground">
                                                {day.reservations_count}
                                            </span>
                                        </div>
                                        <div className="text-sm font-medium sm:text-right">
                                            {formatMoney(day.revenue_amount)}
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>Ostatnie rezerwacje</CardTitle>
                                    <CardDescription>
                                        Najnowsze zamówienia w Twoich łowiskach
                                    </CardDescription>
                                </div>
                                <Button asChild variant="outline" size="sm">
                                    <Link href="/operator/bookings">
                                        Wszystkie
                                    </Link>
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {recent_bookings.length === 0 ? (
                                    <EmptyState value="Brak rezerwacji." />
                                ) : (
                                    recent_bookings.map((booking) => (
                                        <Link
                                            key={booking.uuid}
                                            href={`/operator/bookings/${booking.uuid}`}
                                            className="block rounded-md border bg-card p-3 transition-colors hover:bg-muted/40"
                                        >
                                            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="min-w-0">
                                                    <div className="font-medium">
                                                        {booking.booking_number}
                                                    </div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {booking.customer_name}
                                                        {' · '}
                                                        {booking.venue.name}
                                                    </div>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                                    <Badge variant="outline">
                                                        {
                                                            bookingStatusLabels[
                                                                booking.status
                                                            ]
                                                        }
                                                    </Badge>
                                                    <span className="text-sm font-medium">
                                                        {formatMoney(
                                                            booking.total_amount,
                                                            booking.currency,
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                        </Link>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MapPinned className="size-4 text-muted-foreground" />
                                    Zasięg operatora
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <MetricRow
                                    label="Organizacje"
                                    value={stats.organizations_count}
                                />
                                <MetricRow
                                    label="Łowiska"
                                    value={stats.venues_count}
                                />
                                <MetricRow
                                    label="Stanowiska"
                                    value={stats.spots_count}
                                />
                                <MetricRow
                                    label="Płatności w toku"
                                    value={stats.pending_payments_count}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Najbliższe rezerwacje</CardTitle>
                                <CardDescription>
                                    Holdy i potwierdzone pobyty
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {upcoming_reservations.length === 0 ? (
                                    <EmptyState value="Brak nadchodzących terminów." />
                                ) : (
                                    upcoming_reservations.map((reservation) => (
                                        <Link
                                            key={reservation.uuid}
                                            href={`/operator/bookings/${reservation.booking.uuid}`}
                                            className="block rounded-md border bg-card p-3 transition-colors hover:bg-muted/40"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0 space-y-1">
                                                    <div className="truncate text-sm font-medium">
                                                        {reservation.spot.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {reservation.venue.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {formatDateTime(
                                                            reservation.starts_at,
                                                        )}
                                                    </div>
                                                </div>
                                                <Badge variant="secondary">
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
                    </aside>
                </section>
            </div>
        </>
    );
}

function StatCard({
    title,
    value,
    note,
    icon: Icon,
}: {
    title: string;
    value: string | number;
    note: string;
    icon: typeof CalendarDays;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
                <span className="flex size-8 items-center justify-center rounded-md bg-accent text-accent-foreground">
                    <Icon className="size-4" />
                </span>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold">{value}</div>
                <p className="text-xs text-muted-foreground">{note}</p>
            </CardContent>
        </Card>
    );
}

function MetricRow({
    label,
    value,
}: {
    label: string;
    value: string | number;
}) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
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

OperatorDashboard.layout = {
    breadcrumbs: [
        {
            title: 'Panel operatora',
            href: '/operator',
        },
    ],
};
