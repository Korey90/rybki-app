import { Head, Link } from '@inertiajs/react';
import { Filter, MapPinned, Search } from 'lucide-react';
import { GoogleVenueMap } from '@/components/google-venue-map';
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
import type { PublicVenue } from '../types';
import { formatMoney, venueCommercialStatusLabels } from '../utils';

export default function MarketplaceFishingVenuesIndex({
    venues,
    filters,
}: {
    venues: PublicVenue[];
    filters: {
        q: string | null;
        city: string | null;
        starts_at: string | null;
        ends_at: string | null;
        available_only: boolean;
        latitude: string | null;
        longitude: string | null;
        radius_km: number | string | null;
        sort: 'name' | 'distance';
    };
}) {
    return (
        <>
            <Head title="Łowiska" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Łowiska"
                    description="Marketplace i katalog informacyjny łowisk. Rezerwacje są dostępne tylko przy profilach oznaczonych jako rezerwowalne."
                />

                <Card className="shadow-xs">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Filter className="size-4 text-muted-foreground" />
                            Filtry
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            action="/lowiska"
                            method="get"
                            className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_11rem_11rem_11rem_8rem]"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="q">Szukaj</Label>
                                <Input
                                    id="q"
                                    name="q"
                                    defaultValue={filters.q ?? ''}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="city">Miasto</Label>
                                <Input
                                    id="city"
                                    name="city"
                                    defaultValue={filters.city ?? ''}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="starts_at">Od</Label>
                                <Input
                                    id="starts_at"
                                    name="starts_at"
                                    type="datetime-local"
                                    defaultValue={filters.starts_at ?? ''}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="ends_at">Do</Label>
                                <Input
                                    id="ends_at"
                                    name="ends_at"
                                    type="datetime-local"
                                    defaultValue={filters.ends_at ?? ''}
                                />
                            </div>
                            <div className="flex items-end">
                                <Button type="submit" className="w-full">
                                    <Search className="size-4" />
                                    Szukaj
                                </Button>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="latitude">Szerokość</Label>
                                <Input
                                    id="latitude"
                                    name="latitude"
                                    inputMode="decimal"
                                    placeholder="np. 53.78"
                                    defaultValue={filters.latitude ?? ''}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="longitude">Długość</Label>
                                <Input
                                    id="longitude"
                                    name="longitude"
                                    inputMode="decimal"
                                    placeholder="np. 20.48"
                                    defaultValue={filters.longitude ?? ''}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="radius_km">Promień</Label>
                                <Input
                                    id="radius_km"
                                    name="radius_km"
                                    type="number"
                                    min={1}
                                    max={300}
                                    defaultValue={filters.radius_km ?? 50}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="sort">Sortowanie</Label>
                                <select
                                    id="sort"
                                    name="sort"
                                    defaultValue={filters.sort}
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                >
                                    <option value="name">Nazwa</option>
                                    <option value="distance">Odległość</option>
                                </select>
                            </div>
                            <label className="flex items-center gap-2 text-sm lg:col-span-full">
                                <input
                                    type="checkbox"
                                    name="available_only"
                                    value="1"
                                    defaultChecked={filters.available_only}
                                    className="size-4 rounded border-input"
                                />
                                Tylko wolne w wybranym terminie
                            </label>
                        </form>
                    </CardContent>
                </Card>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="space-y-3">
                        {venues.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak łowisk dla wybranych filtrów.
                            </div>
                        ) : (
                            venues.map((venue) => (
                                <Link
                                    key={venue.uuid}
                                    href={`/lowiska/${venue.slug}`}
                                    className="block rounded-lg border bg-card p-4 shadow-xs transition-[background-color,box-shadow] hover:bg-muted/40 hover:shadow-sm"
                                >
                                    <div className="grid gap-4 md:grid-cols-[7rem_minmax(0,1fr)_11rem]">
                                        <VenueThumb venue={venue} />
                                        <div className="min-w-0 space-y-2">
                                            <div>
                                                <h2 className="truncate font-semibold">
                                                    {venue.name}
                                                </h2>
                                                <p className="text-sm text-muted-foreground">
                                                    {venue.city ?? 'Bez miasta'}
                                                    {venue.voivodeship
                                                        ? `, ${venue.voivodeship}`
                                                        : ''}
                                                </p>
                                            </div>
                                            <p className="line-clamp-2 text-sm text-muted-foreground">
                                                {venue.description ??
                                                    'Profil łowiska jest w przygotowaniu.'}
                                            </p>
                                            <div className="flex flex-wrap gap-2">
                                                {venue.fish_species
                                                    ?.slice(0, 3)
                                                    .map((species) => (
                                                        <Badge
                                                            key={species.slug}
                                                            variant="secondary"
                                                        >
                                                            {species.name}
                                                        </Badge>
                                                    ))}
                                                {venue.amenities
                                                    ?.slice(0, 3)
                                                    .map((amenity) => (
                                                        <Badge
                                                            key={amenity.slug}
                                                            variant="outline"
                                                        >
                                                            {amenity.name}
                                                        </Badge>
                                                    ))}
                                            </div>
                                        </div>
                                        <div className="space-y-2 text-sm md:text-right">
                                            <div className="flex flex-wrap gap-2 md:justify-end">
                                                <Badge variant="secondary">
                                                    {
                                                        venueCommercialStatusLabels[
                                                            venue
                                                                .commercial_status
                                                        ]
                                                    }
                                                </Badge>
                                                {venue.is_bookable && (
                                                    <Badge variant="outline">
                                                        {venue.spots_count}{' '}
                                                        stanowisk
                                                    </Badge>
                                                )}
                                            </div>
                                            {venue.available_spots_count !==
                                                null && (
                                                <div className="text-muted-foreground">
                                                    Wolne:{' '}
                                                    {
                                                        venue.available_spots_count
                                                    }
                                                </div>
                                            )}
                                            {venue.distance_km !== null && (
                                                <div className="text-muted-foreground">
                                                    Odległość:{' '}
                                                    {venue.distance_km.toFixed(
                                                        1,
                                                    )}{' '}
                                                    km
                                                </div>
                                            )}
                                            {venue.is_bookable ? (
                                                <div className="font-medium">
                                                    od{' '}
                                                    {formatMoney(
                                                        venue.starting_price_amount,
                                                        venue.currency,
                                                    )}
                                                </div>
                                            ) : (
                                                <div className="text-muted-foreground">
                                                    Bez rezerwacji online
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </Link>
                            ))
                        )}
                    </div>

                    <MapPanel venues={venues} />
                </section>
            </div>
        </>
    );
}

function MapPanel({ venues }: { venues: PublicVenue[] }) {
    const positionedVenues = venues.filter(
        (venue) => venue.latitude !== null && venue.longitude !== null,
    );

    return (
        <Card className="h-fit shadow-xs xl:sticky xl:top-20">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <MapPinned className="size-4 text-muted-foreground" />
                    Mapa
                </CardTitle>
                <CardDescription>
                    Pozycje łowisk z zapisanymi współrzędnymi
                </CardDescription>
            </CardHeader>
            <CardContent>
                <GoogleVenueMap
                    venues={positionedVenues}
                    className="aspect-[4/5] min-h-96"
                />
            </CardContent>
        </Card>
    );
}

function VenueThumb({ venue }: { venue: PublicVenue }) {
    const imageUrl = venue.primary_media?.url;

    return (
        <div className="relative h-24 overflow-hidden rounded-md border bg-muted">
            {imageUrl ? (
                <img
                    src={imageUrl}
                    alt=""
                    className="absolute inset-0 size-full object-cover"
                    loading="lazy"
                />
            ) : (
                <div className="absolute inset-0 bg-[linear-gradient(90deg,transparent_17px,var(--border)_18px),linear-gradient(0deg,transparent_17px,var(--border)_18px)] bg-[size:18px_18px] opacity-45" />
            )}
            <div className="absolute right-3 bottom-3 flex size-8 items-center justify-center rounded-md border bg-card">
                <MapPinned className="size-4 text-primary" />
            </div>
        </div>
    );
}

MarketplaceFishingVenuesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Łowiska',
            href: '/lowiska',
        },
    ],
};
