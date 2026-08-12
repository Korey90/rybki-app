import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    CalendarDays,
    Clock3,
    Fish,
    MapPinned,
    Search,
    ShieldCheck,
    Waves,
} from 'lucide-react';
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
import { cn } from '@/lib/utils';
import type { PublicVenue } from './types';
import { formatMoney, venueCommercialStatusLabels } from './utils';

type Props = {
    featured_venues: PublicVenue[];
    stats: {
        venues_count: number;
        spots_count: number;
        confirmed_bookings_count: number;
    };
};

const quickSearches = [
    { label: 'Mazury', href: '/lowiska?q=Mazury' },
    { label: 'Karp', href: '/lowiska?q=karp' },
    { label: 'Wolne terminy', href: '/lowiska?available_only=1' },
    { label: 'Publiczne profile', href: '/lowiska?q=publiczne' },
];

const venueScenes = [
    {
        background:
            'linear-gradient(135deg, #d9f7ff 0%, #e6f7ee 42%, #2f7d68 43%, #145a51 100%)',
        accent: 'bg-teal-600',
    },
    {
        background:
            'linear-gradient(135deg, #f8ead2 0%, #dbeafe 38%, #3b6a45 39%, #0f5132 100%)',
        accent: 'bg-emerald-700',
    },
    {
        background:
            'linear-gradient(135deg, #e7eefb 0%, #d8f3dc 45%, #8b6f47 46%, #284b63 100%)',
        accent: 'bg-sky-700',
    },
    {
        background:
            'linear-gradient(135deg, #f4f1de 0%, #cde7f0 40%, #52796f 41%, #2f3e46 100%)',
        accent: 'bg-cyan-700',
    },
];

export default function MarketplaceHome({ featured_venues, stats }: Props) {
    const leadVenue = featured_venues[0] ?? null;

    return (
        <>
            <Head title="Rezerwacje łowisk" />

            <div className="bg-background">
                <section className="border-b bg-muted/20">
                    <div className="mx-auto grid w-full max-w-7xl gap-8 px-4 py-8 md:px-6 md:py-12 xl:grid-cols-[minmax(0,1fr)_25rem] xl:items-stretch">
                        <div className="flex min-w-0 flex-col justify-center">
                            <Badge
                                variant="secondary"
                                className="mb-5 w-fit rounded-md px-3 py-1"
                            >
                                <BadgeCheck className="size-3.5" />
                                Marketplace rezerwacji wędkarskich
                            </Badge>

                            <div className="max-w-3xl space-y-5">
                                <h1 className="text-4xl leading-tight font-semibold tracking-normal text-balance md:text-5xl">
                                    Rezerwuj sprawdzone łowiska bez dzwonienia i
                                    domysłów
                                </h1>
                                <p className="max-w-2xl text-base leading-7 text-muted-foreground md:text-lg">
                                    Wybierz region, sprawdź dostępne stanowiska
                                    i przejdź do rezerwacji w jednym miejscu.
                                    Rybki łączą katalog łowisk, terminy, cenniki
                                    i płatności.
                                </p>
                            </div>

                            <SearchPanel />

                            <div className="mt-5 flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Popularne:
                                </span>
                                {quickSearches.map((item) => (
                                    <Button
                                        key={item.href}
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="h-8 rounded-md bg-background"
                                    >
                                        <Link href={item.href}>
                                            {item.label}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <HeroPanel venue={leadVenue} stats={stats} />
                    </div>
                </section>

                <section className="mx-auto grid w-full max-w-7xl gap-3 px-4 py-6 md:grid-cols-3 md:px-6">
                    <Stat
                        label="Opublikowane łowiska"
                        value={stats.venues_count}
                        description="profile komercyjne i publiczne"
                        icon={MapPinned}
                    />
                    <Stat
                        label="Aktywne stanowiska"
                        value={stats.spots_count}
                        description="z cennikiem i dostępnością"
                        icon={Waves}
                    />
                    <Stat
                        label="Potwierdzone rezerwacje"
                        value={stats.confirmed_bookings_count}
                        description="przetworzone przez system"
                        icon={CalendarDays}
                    />
                </section>

                <section className="mx-auto w-full max-w-7xl px-4 pb-12 md:px-6 md:pb-16">
                    <div className="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                        <div>
                            <p className="text-sm font-medium text-primary">
                                Polecane w katalogu
                            </p>
                            <h2 className="mt-1 text-2xl font-semibold tracking-normal">
                                Łowiska gotowe do sprawdzenia
                            </h2>
                        </div>
                        <Button asChild variant="outline">
                            <Link href="/lowiska">
                                Zobacz wszystkie
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>

                    {featured_venues.length === 0 ? (
                        <div className="rounded-lg border border-dashed bg-card p-8 text-sm text-muted-foreground">
                            Brak opublikowanych łowisk.
                        </div>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {featured_venues.map((venue, index) => (
                                <VenueCard
                                    key={venue.uuid}
                                    venue={venue}
                                    index={index}
                                />
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function SearchPanel() {
    return (
        <form
            action="/lowiska"
            method="get"
            className="mt-8 rounded-lg border bg-card p-3 shadow-sm"
        >
            <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_12.5rem_12.5rem_9rem]">
                <div className="grid gap-2">
                    <Label htmlFor="q">Czego szukasz?</Label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="q"
                            name="q"
                            className="pl-9"
                            placeholder="Nazwa łowiska, miasto, gatunek"
                        />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="starts_at">Od</Label>
                    <Input
                        id="starts_at"
                        name="starts_at"
                        type="datetime-local"
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="ends_at">Do</Label>
                    <Input id="ends_at" name="ends_at" type="datetime-local" />
                </div>

                <div className="flex items-end">
                    <Button type="submit" className="h-10 w-full">
                        <Search className="size-4" />
                        Szukaj
                    </Button>
                </div>
            </div>
            <input type="hidden" name="available_only" value="1" />
        </form>
    );
}

function HeroPanel({
    venue,
    stats,
}: {
    venue: PublicVenue | null;
    stats: Props['stats'];
}) {
    const imageUrl = venue?.primary_media?.url;

    return (
        <aside className="rounded-lg border bg-card p-4 shadow-sm">
            <div className="relative min-h-72 overflow-hidden rounded-lg border">
                {imageUrl ? (
                    <img
                        src={imageUrl}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                    />
                ) : (
                    <>
                        <div
                            className="absolute inset-0"
                            style={{
                                background:
                                    'linear-gradient(135deg, #dbeafe 0%, #ecfdf5 37%, #0f766e 38%, #0f3d3e 100%)',
                            }}
                        />
                        <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(255,255,255,0.18)_1px,transparent_1px),linear-gradient(0deg,rgba(255,255,255,0.16)_1px,transparent_1px)] bg-[size:34px_34px] opacity-60" />
                    </>
                )}
                <div className="absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-black/55 to-transparent" />

                <div className="absolute top-4 left-4 rounded-md bg-background/90 px-3 py-2 shadow-sm backdrop-blur">
                    <div className="text-xs font-medium text-muted-foreground">
                        Dzisiaj w katalogu
                    </div>
                    <div className="mt-1 text-2xl font-semibold">
                        {stats.venues_count} łowisk
                    </div>
                </div>

                <div className="absolute right-4 bottom-4 left-4 rounded-lg border bg-background/95 p-4 shadow-sm backdrop-blur">
                    <div className="flex items-start justify-between gap-3">
                        <div className="min-w-0">
                            <p className="text-xs font-medium text-muted-foreground">
                                Polecane
                            </p>
                            <h3 className="mt-1 truncate text-base font-semibold">
                                {venue?.name ?? 'Łowisko w katalogu'}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {venue?.city ?? 'Wybierz region'}
                                {venue?.voivodeship
                                    ? `, ${venue.voivodeship}`
                                    : ''}
                            </p>
                        </div>
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <MapPinned className="size-5" />
                        </span>
                    </div>

                    <div className="mt-4 grid grid-cols-2 gap-2 text-sm">
                        <div className="rounded-md bg-muted/60 p-3">
                            <div className="font-semibold">
                                {venue?.is_bookable
                                    ? formatMoney(
                                          venue.starting_price_amount,
                                          venue.currency,
                                      )
                                    : 'Profil'}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                od ceny / status
                            </div>
                        </div>
                        <div className="rounded-md bg-muted/60 p-3">
                            <div className="font-semibold">
                                {venue?.spots_count ?? stats.spots_count}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                stanowiska
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-4 grid gap-3 text-sm">
                <TrustItem
                    icon={ShieldCheck}
                    title="Role i panel operatora"
                    description="Oddzielne konta dla wędkarzy i właścicieli łowisk."
                />
                <TrustItem
                    icon={Clock3}
                    title="Terminy w jednym miejscu"
                    description="Dostępność, blokady i ceny pokazują realną ofertę."
                />
            </div>
        </aside>
    );
}

function TrustItem({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof ShieldCheck;
    title: string;
    description: string;
}) {
    return (
        <div className="flex gap-3 rounded-lg border bg-background p-3">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-accent text-accent-foreground">
                <Icon className="size-4" />
            </span>
            <div>
                <div className="font-medium">{title}</div>
                <p className="mt-1 text-sm leading-5 text-muted-foreground">
                    {description}
                </p>
            </div>
        </div>
    );
}

function VenueCard({ venue, index }: { venue: PublicVenue; index: number }) {
    const fishSpecies = venue.fish_species?.slice(0, 3) ?? [];
    const amenities = venue.amenities?.slice(0, 2) ?? [];

    return (
        <Card className="group overflow-hidden py-0 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <VenuePreview venue={venue} index={index} />
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <CardTitle className="truncate text-lg">
                            {venue.name}
                        </CardTitle>
                        <CardDescription className="mt-1 flex items-center gap-1">
                            <MapPinned className="size-3.5" />
                            <span className="truncate">
                                {venue.city ?? 'Bez miasta'}
                                {venue.voivodeship
                                    ? `, ${venue.voivodeship}`
                                    : ''}
                            </span>
                        </CardDescription>
                    </div>
                    <Badge
                        variant={venue.is_bookable ? 'default' : 'secondary'}
                        className="shrink-0 rounded-md"
                    >
                        {venueCommercialStatusLabels[venue.commercial_status]}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-4 pb-5">
                <p className="line-clamp-2 min-h-10 text-sm leading-5 text-muted-foreground">
                    {venue.description ??
                        'Profil łowiska jest w przygotowaniu.'}
                </p>

                <div className="flex flex-wrap gap-2">
                    {fishSpecies.map((species) => (
                        <Badge key={species.slug} variant="outline">
                            <Fish className="size-3" />
                            {species.name}
                        </Badge>
                    ))}
                    {amenities.map((amenity) => (
                        <Badge key={amenity.slug} variant="secondary">
                            {amenity.name}
                        </Badge>
                    ))}
                </div>

                <div className="flex items-center justify-between gap-3 border-t pt-4">
                    <div>
                        <div className="text-xs font-medium text-muted-foreground">
                            {venue.is_bookable ? 'Cena od' : 'Typ profilu'}
                        </div>
                        <div className="text-base font-semibold">
                            {venue.is_bookable
                                ? formatMoney(
                                      venue.starting_price_amount,
                                      venue.currency,
                                  )
                                : venueCommercialStatusLabels[
                                      venue.commercial_status
                                  ]}
                        </div>
                    </div>
                    <Button asChild size="sm">
                        <Link href={`/lowiska/${venue.slug}`}>
                            Otwórz
                            <ArrowRight className="size-4" />
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function VenuePreview({ venue, index }: { venue: PublicVenue; index: number }) {
    const scene = venueScenes[index % venueScenes.length];
    const imageUrl = venue.primary_media?.url;

    return (
        <div className="relative h-44 overflow-hidden border-b">
            {imageUrl ? (
                <img
                    src={imageUrl}
                    alt=""
                    className="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-105"
                    loading="lazy"
                />
            ) : (
                <>
                    <div
                        className="absolute inset-0 transition-transform duration-500 group-hover:scale-105"
                        style={{ background: scene.background }}
                    />
                    <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(255,255,255,0.16)_1px,transparent_1px),linear-gradient(0deg,rgba(255,255,255,0.16)_1px,transparent_1px)] bg-[size:30px_30px] opacity-45" />
                </>
            )}
            <div className="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/55 to-transparent" />

            <div className="absolute top-4 left-4 flex items-center gap-2 rounded-md bg-background/90 px-2.5 py-1.5 text-xs font-medium shadow-sm backdrop-blur">
                <span className={cn('size-2 rounded-full', scene.accent)} />
                {venue.spots_count > 0
                    ? `${venue.spots_count} stanowisk`
                    : 'profil informacyjny'}
            </div>

            <div className="absolute right-4 bottom-4 rounded-md bg-background/95 px-3 py-2 text-sm font-semibold shadow-sm backdrop-blur">
                {venue.is_bookable
                    ? formatMoney(venue.starting_price_amount, venue.currency)
                    : 'Informacje'}
            </div>
        </div>
    );
}

function Stat({
    label,
    value,
    description,
    icon: Icon,
}: {
    label: string;
    value: number;
    description: string;
    icon: typeof MapPinned;
}) {
    return (
        <Card className="shadow-sm">
            <CardHeader className="flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{label}</CardTitle>
                <span className="flex size-9 items-center justify-center rounded-md bg-accent text-accent-foreground">
                    <Icon className="size-4" />
                </span>
            </CardHeader>
            <CardContent>
                <div className="text-3xl font-semibold">{value}</div>
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            </CardContent>
        </Card>
    );
}

MarketplaceHome.layout = {
    breadcrumbs: [
        {
            title: 'Łowiska',
            href: '/',
        },
    ],
};
