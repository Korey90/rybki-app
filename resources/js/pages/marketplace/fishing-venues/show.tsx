import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Check,
    Database,
    FileText,
    ImageIcon,
    LogIn,
    MapPinned,
} from 'lucide-react';
import { GoogleVenueMap } from '@/components/google-venue-map';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PublicMedia, PublicVenue } from '../types';
import {
    formatDateTime,
    formatMoney,
    venueCommercialStatusLabels,
    venueTypeLabels,
} from '../utils';

type PublicSpot = {
    uuid: string;
    name: string;
    description: string | null;
    capacity: number;
    base_price_amount: number;
    currency: string;
    media: PublicMedia[];
    availability: {
        available: boolean;
        reason: string | null;
    } | null;
    price: {
        currency: string;
        unit_amount: number;
        total_amount: number;
        duration_minutes: number;
        charged_days: number;
        pricing_rule_id: number | null;
    } | null;
};

type VenueDetail = PublicVenue & {
    address: string | null;
    postal_code: string | null;
    country: string;
    phone: string | null;
    email: string | null;
    website: string | null;
    rules: string | null;
    check_in_instructions: string | null;
    can_be_claimed: boolean;
    media: PublicMedia[];
    sources: VenueSource[];
    spots: PublicSpot[];
};

type VenueSource = {
    provider: string;
    dataset_name: string;
    dataset_url: string | null;
    external_id: string;
    source_updated_at: string | null;
    last_synced_at: string | null;
};

type ClaimOrganization = {
    id: number;
    name: string;
};

type PageProps = {
    auth: {
        user: {
            name: string;
            email: string;
            phone?: string | null;
        } | null;
    };
};

export default function MarketplaceFishingVenueShow({
    venue,
    availability_query,
    claim_organizations,
}: {
    venue: VenueDetail;
    availability_query: {
        starts_at: string | null;
        ends_at: string | null;
    };
    claim_organizations: ClaimOrganization[];
}) {
    const { auth } = usePage<PageProps>().props;
    const hasAvailabilityQuery =
        availability_query.starts_at !== null &&
        availability_query.ends_at !== null;

    return (
        <>
            <Head title={venue.name} />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Button asChild variant="ghost" className="w-fit px-0">
                    <Link href="/lowiska">
                        <ArrowLeft className="size-4" />
                        Łowiska
                    </Link>
                </Button>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="space-y-6">
                        <div className="overflow-hidden rounded-lg border bg-card shadow-xs">
                            <VenueHero media={venue.media} />
                            <div className="p-5 md:p-6">
                                <Heading
                                    title={venue.name}
                                    description={`${venue.city ?? 'Bez miasta'}${venue.voivodeship ? `, ${venue.voivodeship}` : ''}`}
                                />
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Badge variant="secondary">
                                        {
                                            venueCommercialStatusLabels[
                                                venue.commercial_status
                                            ]
                                        }
                                    </Badge>
                                    <Badge variant="outline">
                                        {venueTypeLabels[venue.venue_type]}
                                    </Badge>
                                </div>
                                {venue.media.length > 1 && (
                                    <MediaStrip
                                        media={venue.media}
                                        className="mt-5"
                                    />
                                )}
                            </div>
                        </div>

                        <Card className="shadow-xs">
                            <CardHeader>
                                <CardTitle>Profil łowiska</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 text-sm md:grid-cols-3">
                                <Info
                                    label="Adres"
                                    value={venue.address ?? '-'}
                                />
                                <Info
                                    label="Kod"
                                    value={venue.postal_code ?? '-'}
                                />
                                <Info label="Kraj" value={venue.country} />
                                <Info
                                    label="Telefon"
                                    value={venue.phone ?? '-'}
                                />
                                <Info
                                    label="Email"
                                    value={venue.email ?? '-'}
                                />
                                <Info
                                    label="WWW"
                                    value={venue.website ?? '-'}
                                />
                            </CardContent>
                        </Card>

                        <Card className="shadow-xs">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MapPinned className="size-4 text-muted-foreground" />
                                    Lokalizacja
                                </CardTitle>
                                <CardDescription>
                                    Pozycja łowiska na mapie Google
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <GoogleVenueMap
                                    venues={[venue]}
                                    className="min-h-80"
                                />
                            </CardContent>
                        </Card>

                        {venue.sources.length > 0 && (
                            <Card className="shadow-xs">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Database className="size-4 text-muted-foreground" />
                                        Źródła danych
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {venue.sources.map((source) => (
                                        <div
                                            key={`${source.provider}:${source.external_id}`}
                                            className="rounded-md border bg-muted/30 p-3 text-sm"
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-medium">
                                                        {source.provider}
                                                    </div>
                                                    <div className="text-muted-foreground">
                                                        {source.dataset_name}
                                                    </div>
                                                </div>
                                                <Badge variant="outline">
                                                    {source.external_id}
                                                </Badge>
                                            </div>
                                            <div className="mt-3 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2">
                                                <span>
                                                    Dane:{' '}
                                                    {formatDateTime(
                                                        source.source_updated_at,
                                                    )}
                                                </span>
                                                <span>
                                                    Sync:{' '}
                                                    {formatDateTime(
                                                        source.last_synced_at,
                                                    )}
                                                </span>
                                            </div>
                                            {source.dataset_url && (
                                                <a
                                                    href={source.dataset_url}
                                                    className="mt-3 inline-flex text-sm underline-offset-4 hover:underline"
                                                >
                                                    Otwórz źródło
                                                </a>
                                            )}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {venue.description && (
                            <Card className="shadow-xs">
                                <CardHeader>
                                    <CardTitle>Opis</CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm leading-6 text-muted-foreground">
                                    {venue.description}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <aside className="space-y-6">
                        {venue.is_bookable ? (
                            <Card className="shadow-xs">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CalendarDays className="size-4 text-muted-foreground" />
                                        Termin
                                    </CardTitle>
                                    <CardDescription>
                                        Sprawdź dostępność stanowisk
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        action={`/lowiska/${venue.slug}`}
                                        method="get"
                                        className="space-y-4"
                                    >
                                        <div className="grid gap-2">
                                            <Label htmlFor="starts_at">
                                                Od
                                            </Label>
                                            <Input
                                                id="starts_at"
                                                name="starts_at"
                                                type="datetime-local"
                                                defaultValue={
                                                    availability_query.starts_at ??
                                                    ''
                                                }
                                                required
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="ends_at">Do</Label>
                                            <Input
                                                id="ends_at"
                                                name="ends_at"
                                                type="datetime-local"
                                                defaultValue={
                                                    availability_query.ends_at ??
                                                    ''
                                                }
                                                required
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            className="w-full"
                                        >
                                            Sprawdź
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="shadow-xs">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="size-4 text-muted-foreground" />
                                        Profil informacyjny
                                    </CardTitle>
                                    <CardDescription>
                                        To łowisko nie przyjmuje rezerwacji
                                        online
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        )}

                        {venue.can_be_claimed && (
                            <ClaimPanel
                                venue={venue}
                                claimOrganizations={claim_organizations}
                                isSignedIn={auth.user !== null}
                            />
                        )}

                        {(venue.rules || venue.check_in_instructions) && (
                            <Card className="shadow-xs">
                                <CardHeader>
                                    <CardTitle>Informacje</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 text-sm text-muted-foreground">
                                    {venue.rules && (
                                        <InfoText
                                            label="Regulamin"
                                            value={venue.rules}
                                        />
                                    )}
                                    {venue.check_in_instructions && (
                                        <InfoText
                                            label="Check-in"
                                            value={venue.check_in_instructions}
                                        />
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </aside>
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-semibold">
                        {venue.is_bookable ? 'Stanowiska' : 'Rezerwacje'}
                    </h2>

                    {!venue.is_bookable ? (
                        <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                            Ten profil jest informacyjny. Rezerwacja stanowiska
                            nie jest aktywna.
                        </div>
                    ) : venue.spots.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                            Brak aktywnych stanowisk.
                        </div>
                    ) : (
                        venue.spots.map((spot) => (
                            <Card key={spot.uuid} className="shadow-xs">
                                <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <CardTitle className="text-base">
                                            {spot.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {spot.description ??
                                                `${spot.capacity} miejsca`}
                                        </CardDescription>
                                    </div>
                                    <div className="flex flex-wrap gap-2 sm:justify-end">
                                        <Badge variant="secondary">
                                            {spot.capacity} miejsca
                                        </Badge>
                                        <Badge variant="outline">
                                            {spot.price
                                                ? formatMoney(
                                                      spot.price.total_amount,
                                                      spot.price.currency,
                                                  )
                                                : `od ${formatMoney(spot.base_price_amount, spot.currency)}`}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {spot.media.length > 0 && (
                                        <MediaStrip
                                            media={spot.media}
                                            className="mb-4"
                                        />
                                    )}

                                    {!hasAvailabilityQuery ? (
                                        <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                            Wybierz termin, aby sprawdzić
                                            dostępność.
                                        </div>
                                    ) : spot.availability?.available ? (
                                        <BookingForm
                                            spot={spot}
                                            startsAt={
                                                availability_query.starts_at ??
                                                ''
                                            }
                                            endsAt={
                                                availability_query.ends_at ?? ''
                                            }
                                            customer={auth.user}
                                        />
                                    ) : (
                                        <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                                            Niedostępne w wybranym terminie.
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))
                    )}
                </section>
            </div>
        </>
    );
}

function VenueHero({ media }: { media: PublicMedia[] }) {
    const primaryMedia = media.find((item) => item.is_primary) ?? media[0];
    const imageUrl = primaryMedia?.url;

    return (
        <div className="relative min-h-64 border-b bg-muted p-5 md:p-7">
            {imageUrl ? (
                <img
                    src={imageUrl}
                    alt=""
                    className="absolute inset-0 size-full object-cover"
                />
            ) : (
                <div className="absolute inset-0 bg-[linear-gradient(90deg,transparent_31px,var(--border)_32px),linear-gradient(0deg,transparent_31px,var(--border)_32px)] bg-[size:32px_32px] opacity-40" />
            )}
            <div className="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-card via-card/55 to-transparent" />
            <div className="relative flex min-h-52 flex-col justify-end">
                <div className="mb-5 flex size-12 items-center justify-center rounded-lg border bg-card shadow-xs">
                    {imageUrl ? (
                        <ImageIcon className="size-6 text-primary" />
                    ) : (
                        <MapPinned className="size-6 text-primary" />
                    )}
                </div>
            </div>
        </div>
    );
}

function MediaStrip({
    media,
    className = '',
}: {
    media: PublicMedia[];
    className?: string;
}) {
    return (
        <div className={`grid grid-cols-3 gap-2 sm:grid-cols-4 ${className}`}>
            {media.slice(0, 4).map((item) => (
                <div
                    key={item.id}
                    className="relative aspect-[4/3] overflow-hidden rounded-md border bg-muted"
                >
                    {item.url ? (
                        <img
                            src={item.url}
                            alt=""
                            className="size-full object-cover"
                            loading="lazy"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center">
                            <ImageIcon className="size-5 text-muted-foreground" />
                        </div>
                    )}
                    {item.is_primary && (
                        <Badge className="absolute top-2 left-2 rounded-md">
                            Główne
                        </Badge>
                    )}
                </div>
            ))}
        </div>
    );
}

function ClaimPanel({
    venue,
    claimOrganizations,
    isSignedIn,
}: {
    venue: VenueDetail;
    claimOrganizations: ClaimOrganization[];
    isSignedIn: boolean;
}) {
    if (!isSignedIn) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>To Twoje łowisko?</CardTitle>
                    <CardDescription>
                        Zaloguj się, żeby zgłosić przejęcie profilu
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button asChild className="w-full">
                        <Link href="/login">
                            <LogIn className="size-4" />
                            Zaloguj
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    if (claimOrganizations.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>To Twoje łowisko?</CardTitle>
                    <CardDescription>
                        Najpierw utwórz organizację operatora
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button asChild variant="outline" className="w-full">
                        <Link href="/operator/organizations">
                            Przejdź do organizacji
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>To Twoje łowisko?</CardTitle>
                <CardDescription>
                    Zgłoszenie trafi do weryfikacji administratora
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    action={`/lowiska/${venue.slug}/claim`}
                    method="post"
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="organization_id">
                                    Organizacja
                                </Label>
                                <select
                                    id="organization_id"
                                    name="organization_id"
                                    defaultValue={claimOrganizations[0]?.id}
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                    required
                                >
                                    {claimOrganizations.map((organization) => (
                                        <option
                                            key={organization.id}
                                            value={organization.id}
                                        >
                                            {organization.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.organization_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="evidence">Uzasadnienie</Label>
                                <Textarea
                                    id="evidence"
                                    name="evidence"
                                    rows={4}
                                />
                                <InputError message={errors.evidence} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                Wyślij zgłoszenie
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function BookingForm({
    spot,
    startsAt,
    endsAt,
    customer,
}: {
    spot: PublicSpot;
    startsAt: string;
    endsAt: string;
    customer: PageProps['auth']['user'];
}) {
    return (
        <Form action="/checkout" method="post" className="space-y-4">
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="fishing_spot_uuid"
                        value={spot.uuid}
                    />
                    <input type="hidden" name="starts_at" value={startsAt} />
                    <input type="hidden" name="ends_at" value={endsAt} />

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor={`customer_name_${spot.uuid}`}>
                                Imię i nazwisko
                            </Label>
                            <Input
                                id={`customer_name_${spot.uuid}`}
                                name="customer_name"
                                defaultValue={customer?.name ?? ''}
                                required
                            />
                            <InputError message={errors.customer_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`customer_email_${spot.uuid}`}>
                                Email
                            </Label>
                            <Input
                                id={`customer_email_${spot.uuid}`}
                                name="customer_email"
                                type="email"
                                defaultValue={customer?.email ?? ''}
                                required
                            />
                            <InputError message={errors.customer_email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`customer_phone_${spot.uuid}`}>
                                Telefon
                            </Label>
                            <Input
                                id={`customer_phone_${spot.uuid}`}
                                name="customer_phone"
                                defaultValue={customer?.phone ?? ''}
                            />
                            <InputError message={errors.customer_phone} />
                        </div>
                    </div>

                    <InputError message={errors.fishing_spot_uuid} />
                    <InputError message={errors.payment} />

                    <Button type="submit" disabled={processing}>
                        <Check className="size-4" />
                        Rezerwuj
                    </Button>
                </>
            )}
        </Form>
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

function InfoText({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <span className="block text-xs font-medium text-foreground">
                {label}
            </span>
            <p className="mt-1 leading-6">{value}</p>
        </div>
    );
}

MarketplaceFishingVenueShow.layout = {
    breadcrumbs: [
        {
            title: 'Łowiska',
            href: '/lowiska',
        },
    ],
};
