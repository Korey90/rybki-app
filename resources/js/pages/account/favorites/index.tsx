import { Head, Link } from '@inertiajs/react';
import { Heart, MapPinned, Star } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { PublicVenue } from '@/pages/marketplace/types';
import {
    formatMoney,
    venueCommercialStatusLabels,
} from '@/pages/marketplace/utils';

export default function AccountFavoritesIndex({
    venues,
}: {
    venues: PublicVenue[];
}) {
    return (
        <>
            <Head title="Ulubione łowiska" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Ulubione łowiska"
                    description="Zapisane miejsca do szybkiego powrotu"
                />

                {venues.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                        Nie masz jeszcze zapisanych łowisk.
                    </div>
                ) : (
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {venues.map((venue) => (
                            <Link
                                key={venue.uuid}
                                href={`/lowiska/${venue.slug}`}
                                className="group overflow-hidden rounded-lg border bg-card shadow-xs transition-[background-color,box-shadow] hover:bg-muted/40 hover:shadow-sm"
                            >
                                <VenueThumb venue={venue} />
                                <div className="space-y-3 p-4">
                                    <div>
                                        <h2 className="line-clamp-1 font-semibold">
                                            {venue.name}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {venue.city ?? 'Bez miasta'}
                                            {venue.voivodeship
                                                ? `, ${venue.voivodeship}`
                                                : ''}
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <RatingBadge
                                            average={venue.rating_average}
                                            count={venue.reviews_count}
                                        />
                                        <Badge variant="outline">
                                            {
                                                venueCommercialStatusLabels[
                                                    venue.commercial_status
                                                ]
                                            }
                                        </Badge>
                                    </div>

                                    <div className="flex items-center justify-between gap-3 text-sm">
                                        <span className="text-muted-foreground">
                                            {venue.is_bookable
                                                ? `${venue.spots_count} stanowisk`
                                                : 'Profil informacyjny'}
                                        </span>
                                        <span className="font-medium">
                                            {venue.is_bookable &&
                                            venue.starting_price_amount !== null
                                                ? `od ${formatMoney(venue.starting_price_amount, venue.currency)}`
                                                : 'Bez rezerwacji'}
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </section>
                )}

                <Button asChild variant="outline" className="w-fit">
                    <Link href="/lowiska">
                        <MapPinned className="size-4" />
                        Przeglądaj łowiska
                    </Link>
                </Button>
            </div>
        </>
    );
}

function VenueThumb({ venue }: { venue: PublicVenue }) {
    const imageUrl = venue.primary_media?.url;

    return (
        <div className="relative aspect-[16/9] overflow-hidden border-b bg-muted">
            {imageUrl ? (
                <img
                    src={imageUrl}
                    alt=""
                    className="absolute inset-0 size-full object-cover transition-transform group-hover:scale-[1.02]"
                    loading="lazy"
                />
            ) : (
                <div className="absolute inset-0 bg-[linear-gradient(90deg,transparent_23px,var(--border)_24px),linear-gradient(0deg,transparent_23px,var(--border)_24px)] bg-[size:24px_24px] opacity-45" />
            )}
            <div className="absolute top-3 right-3 flex size-9 items-center justify-center rounded-md border bg-card">
                <Heart className="size-4 fill-current text-primary" />
            </div>
        </div>
    );
}

function RatingBadge({
    average,
    count,
}: {
    average: number | null;
    count: number;
}) {
    if (average === null || count === 0) {
        return (
            <Badge variant="outline">
                <Star className="size-3" />
                Brak opinii
            </Badge>
        );
    }

    return (
        <Badge variant="secondary">
            <Star className="size-3 fill-current" />
            {average.toFixed(1)} ({count})
        </Badge>
    );
}

AccountFavoritesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Ulubione łowiska',
            href: '/konto/ulubione',
        },
    ],
};
