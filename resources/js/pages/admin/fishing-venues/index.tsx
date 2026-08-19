import { Form, Head, Link } from '@inertiajs/react';
import {
    Download,
    Eye,
    EyeOff,
    ImageIcon,
    MapPinned,
    Search,
    ShieldAlert,
} from 'lucide-react';
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
import {
    formatDateTime,
    venueCommercialStatusLabels,
} from '@/pages/marketplace/utils';

type VenueStatus = 'DRAFT' | 'PUBLISHED' | 'ARCHIVED' | 'SUSPENDED';
type CommercialStatus = 'INFORMATION_ONLY' | 'CLAIMABLE' | 'BOOKABLE';

type AdminMedia = {
    id: number;
    url: string | null;
    storage_path: string;
    hidden_at: string | null;
    hidden_reason: string | null;
    spot_name?: string;
};

type AdminVenue = {
    uuid: string;
    name: string;
    slug: string;
    status: VenueStatus;
    commercial_status: CommercialStatus;
    city: string | null;
    voivodeship: string | null;
    moderation_reason: string | null;
    moderated_at: string | null;
    organization: {
        uuid: string;
        name: string;
    } | null;
    moderator: {
        name: string;
        email: string;
    } | null;
    spots_count: number;
    bookings_count: number;
    reviews_count: number;
    visible_media_count: number;
    hidden_media_count: number;
    media: AdminMedia[];
    spot_media: AdminMedia[];
    created_at: string | null;
};

const venueStatusLabels: Record<VenueStatus, string> = {
    DRAFT: 'Robocze',
    PUBLISHED: 'Opublikowane',
    ARCHIVED: 'Archiwum',
    SUSPENDED: 'Zablokowane',
};

export default function AdminFishingVenuesIndex({
    venues,
    filters,
    statuses,
    commercial_statuses,
    export_url,
}: {
    venues: AdminVenue[];
    filters: {
        q: string | null;
        status: VenueStatus | null;
        commercial_status: CommercialStatus | null;
    };
    statuses: VenueStatus[];
    commercial_statuses: CommercialStatus[];
    export_url: string;
}) {
    return (
        <>
            <Head title="Łowiska admin" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Łowiska"
                        description="Moderacja profili, publikacji i zdjęć"
                    />
                    <Button asChild variant="outline">
                        <a href={export_url}>
                            <Download className="size-4" />
                            CSV
                        </a>
                    </Button>
                </div>

                <FilterCard
                    filters={filters}
                    statuses={statuses}
                    commercialStatuses={commercial_statuses}
                />

                <section className="space-y-4">
                    {venues.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                            Brak łowisk dla wybranych filtrów.
                        </div>
                    ) : (
                        venues.map((venue) => (
                            <VenueCard
                                key={venue.uuid}
                                venue={venue}
                                statuses={statuses}
                            />
                        ))
                    )}
                </section>
            </div>
        </>
    );
}

function FilterCard({
    filters,
    statuses,
    commercialStatuses,
}: {
    filters: {
        q: string | null;
        status: VenueStatus | null;
        commercial_status: CommercialStatus | null;
    };
    statuses: VenueStatus[];
    commercialStatuses: CommercialStatus[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Filtry</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    action="/admin/fishing-venues"
                    method="get"
                    className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_13rem_13rem_8rem]"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="q">Szukaj</Label>
                        <Input
                            id="q"
                            name="q"
                            defaultValue={filters.q ?? ''}
                            placeholder="Nazwa, miasto, organizacja, UUID"
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
                                    {venueStatusLabels[status]}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="commercial_status">Tryb</Label>
                        <select
                            id="commercial_status"
                            name="commercial_status"
                            defaultValue={filters.commercial_status ?? ''}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                        >
                            <option value="">Wszystkie</option>
                            {commercialStatuses.map((status) => (
                                <option key={status} value={status}>
                                    {venueCommercialStatusLabels[status]}
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

function VenueCard({
    venue,
    statuses,
}: {
    venue: AdminVenue;
    statuses: VenueStatus[];
}) {
    return (
        <Card>
            <CardHeader className="gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <MapPinned className="size-4 text-muted-foreground" />
                        {venue.name}
                    </CardTitle>
                    <CardDescription>
                        {venue.organization?.name ?? 'Bez organizacji'} ·{' '}
                        {venue.city ?? 'Bez miasta'}
                        {venue.voivodeship ? `, ${venue.voivodeship}` : ''}
                    </CardDescription>
                </div>
                <div className="flex flex-wrap gap-2 lg:justify-end">
                    <Badge
                        variant={
                            venue.status === 'SUSPENDED'
                                ? 'destructive'
                                : 'secondary'
                        }
                    >
                        {venueStatusLabels[venue.status]}
                    </Badge>
                    <Badge variant="outline">
                        {venueCommercialStatusLabels[venue.commercial_status]}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="grid gap-3 text-sm md:grid-cols-4">
                    <Info label="Stanowiska" value={venue.spots_count} />
                    <Info label="Rezerwacje" value={venue.bookings_count} />
                    <Info label="Opinie" value={venue.reviews_count} />
                    <Info
                        label="Zdjęcia"
                        value={`${venue.visible_media_count} wid. / ${venue.hidden_media_count} ukr.`}
                    />
                </div>

                {venue.moderation_reason && (
                    <div className="rounded-md border bg-muted/30 p-3 text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">
                            Powód moderacji:{' '}
                        </span>
                        {venue.moderation_reason}
                    </div>
                )}

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="space-y-4">
                        <ModerationForm venue={venue} statuses={statuses} />
                        <MediaPanel
                            title="Zdjęcia łowiska"
                            media={venue.media}
                        />
                        <MediaPanel
                            title="Zdjęcia stanowisk"
                            media={venue.spot_media}
                            isSpotMedia
                        />
                    </div>

                    <div className="space-y-3 text-sm">
                        <Info
                            label="Utworzono"
                            value={formatDateTime(venue.created_at)}
                        />
                        <Info
                            label="Moderowano"
                            value={formatDateTime(venue.moderated_at)}
                        />
                        <Info
                            label="Moderator"
                            value={venue.moderator?.email ?? '-'}
                        />
                        <Button asChild variant="outline" className="w-full">
                            <Link href={`/lowiska/${venue.slug}`}>
                                Otwórz profil
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function ModerationForm({
    venue,
    statuses,
}: {
    venue: AdminVenue;
    statuses: VenueStatus[];
}) {
    return (
        <Form
            action={`/admin/fishing-venues/${venue.uuid}`}
            method="patch"
            className="grid gap-3 rounded-md border bg-muted/20 p-4 lg:grid-cols-[13rem_minmax(0,1fr)_10rem]"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor={`status_${venue.uuid}`}>Status</Label>
                        <select
                            id={`status_${venue.uuid}`}
                            name="status"
                            defaultValue={venue.status}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                        >
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {venueStatusLabels[status]}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.status} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`reason_${venue.uuid}`}>
                            Powód / notatka
                        </Label>
                        <Input
                            id={`reason_${venue.uuid}`}
                            name="moderation_reason"
                            defaultValue={venue.moderation_reason ?? ''}
                            maxLength={500}
                        />
                        <InputError message={errors.moderation_reason} />
                    </div>
                    <div className="flex items-end">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full"
                        >
                            <ShieldAlert className="size-4" />
                            Zapisz
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function MediaPanel({
    title,
    media,
    isSpotMedia = false,
}: {
    title: string;
    media: AdminMedia[];
    isSpotMedia?: boolean;
}) {
    if (media.length === 0) {
        return (
            <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                {title}: brak zdjęć.
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <h3 className="text-sm font-medium">{title}</h3>
            <div className="grid gap-3 md:grid-cols-2">
                {media.map((item) => (
                    <MediaItem
                        key={`${isSpotMedia ? 'spot' : 'venue'}-${item.id}`}
                        item={item}
                        isSpotMedia={isSpotMedia}
                    />
                ))}
            </div>
        </div>
    );
}

function MediaItem({
    item,
    isSpotMedia,
}: {
    item: AdminMedia;
    isSpotMedia: boolean;
}) {
    const hidden = item.hidden_at !== null;
    const action = isSpotMedia
        ? `/admin/fishing-venues/spot-media/${item.id}`
        : `/admin/fishing-venues/media/${item.id}`;

    return (
        <div className="overflow-hidden rounded-md border">
            <div className="relative aspect-[4/3] bg-muted">
                {item.url ? (
                    <img
                        src={item.url}
                        alt=""
                        className="absolute inset-0 size-full object-cover"
                        loading="lazy"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center">
                        <ImageIcon className="size-5 text-muted-foreground" />
                    </div>
                )}
                {hidden && (
                    <Badge
                        variant="destructive"
                        className="absolute top-2 left-2"
                    >
                        Ukryte
                    </Badge>
                )}
            </div>
            <div className="space-y-3 p-3">
                {item.spot_name && (
                    <div className="text-xs text-muted-foreground">
                        {item.spot_name}
                    </div>
                )}
                {item.hidden_reason && (
                    <div className="text-xs text-destructive">
                        {item.hidden_reason}
                    </div>
                )}
                <Form action={action} method="patch" className="space-y-2">
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="hidden"
                                value={hidden ? '0' : '1'}
                            />
                            {!hidden && (
                                <div className="grid gap-2">
                                    <Label htmlFor={`hidden_reason_${item.id}`}>
                                        Powód ukrycia
                                    </Label>
                                    <Textarea
                                        id={`hidden_reason_${item.id}`}
                                        name="hidden_reason"
                                        rows={2}
                                        required
                                    />
                                    <InputError
                                        message={errors.hidden_reason}
                                    />
                                </div>
                            )}
                            <Button
                                type="submit"
                                variant={hidden ? 'secondary' : 'destructive'}
                                size="sm"
                                disabled={processing}
                                className="w-full"
                            >
                                {hidden ? (
                                    <Eye className="size-4" />
                                ) : (
                                    <EyeOff className="size-4" />
                                )}
                                {hidden ? 'Przywróć' : 'Ukryj'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </div>
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

AdminFishingVenuesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Łowiska admin',
            href: '/admin/fishing-venues',
        },
    ],
};
