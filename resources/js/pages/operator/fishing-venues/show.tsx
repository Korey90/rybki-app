import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    ChevronUp,
    ImageIcon,
    MapPinned,
    Plus,
    Star,
    Trash2,
    Upload,
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
import { index } from '@/routes/operator/fishing-venues';

type FishingSpot = {
    uuid: string;
    id: number;
    name: string;
    description: string | null;
    capacity: number;
    base_price_amount: number;
    currency: string;
    status: 'ACTIVE' | 'INACTIVE';
    sort_order: number;
    media: MediaItem[];
    availability_rules: {
        id: number;
        day_of_week: number;
        opens_at: string;
        closes_at: string;
        valid_from: string | null;
        valid_until: string | null;
        minimum_booking_minutes: number;
        maximum_booking_minutes: number | null;
    }[];
    availability_blocks: {
        id: number;
        starts_at: string;
        ends_at: string;
        reason: string | null;
    }[];
    pricing_rules: {
        id: number;
        starts_at: string | null;
        ends_at: string | null;
        day_of_week: number | null;
        price_amount: number;
        currency: string;
        minimum_duration_minutes: number | null;
        priority: number;
    }[];
};

type MediaItem = {
    id: number;
    url: string | null;
    storage_path: string;
    type?: 'IMAGE' | 'DOCUMENT';
    sort_order: number;
    is_primary: boolean;
};

type FishingVenue = {
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    venue_type: 'PRIVATE' | 'PZW' | 'WODY_POLSKIE' | 'OTHER_PUBLIC' | 'OTHER';
    commercial_status: 'INFORMATION_ONLY' | 'CLAIMABLE' | 'BOOKABLE';
    latitude: string | null;
    longitude: string | null;
    address: string | null;
    city: string | null;
    postal_code: string | null;
    voivodeship: string | null;
    country: string;
    phone: string | null;
    email: string | null;
    website: string | null;
    rules: string | null;
    check_in_instructions: string | null;
    status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED' | 'SUSPENDED';
    published_at: string | null;
    media: MediaItem[];
    organization: {
        uuid: string;
        name: string;
    };
    spots: FishingSpot[];
};

const venueStatusLabels: Record<FishingVenue['status'], string> = {
    DRAFT: 'Szkic',
    PUBLISHED: 'Opublikowane',
    ARCHIVED: 'Archiwum',
    SUSPENDED: 'Zawieszone',
};

const spotStatusLabels: Record<FishingSpot['status'], string> = {
    ACTIVE: 'Aktywne',
    INACTIVE: 'Nieaktywne',
};

const dayLabels: Record<number, string> = {
    1: 'Poniedziałek',
    2: 'Wtorek',
    3: 'Środa',
    4: 'Czwartek',
    5: 'Piątek',
    6: 'Sobota',
    7: 'Niedziela',
};

export default function FishingVenueShow({ venue }: { venue: FishingVenue }) {
    const hasSpots = venue.spots.length > 0;

    return (
        <>
            <Head title={venue.name} />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-3">
                        <Button asChild variant="ghost" className="w-fit px-0">
                            <Link href={index()}>
                                <ArrowLeft className="size-4" />
                                Łowiska
                            </Link>
                        </Button>

                        <Heading
                            title={venue.name}
                            description={venue.organization.name}
                        />
                    </div>

                    {venue.status !== 'PUBLISHED' && (
                        <Form
                            action={`/operator/fishing-venues/${venue.uuid}/publish`}
                            method="post"
                            className="contents"
                        >
                            <input type="hidden" name="_method" value="PATCH" />
                            <Button type="submit">
                                <Check className="size-4" />
                                Publikuj
                            </Button>
                        </Form>
                    )}
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <section className="space-y-6">
                        <Card>
                            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <MapPinned className="size-4 text-muted-foreground" />
                                        Profil łowiska
                                    </CardTitle>
                                    <CardDescription>
                                        {venue.city ?? 'Bez miasta'}
                                        {venue.voivodeship
                                            ? `, ${venue.voivodeship}`
                                            : ''}
                                    </CardDescription>
                                </div>

                                <Badge variant="secondary">
                                    {venueStatusLabels[venue.status]}
                                </Badge>
                            </CardHeader>

                            <CardContent className="grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-3">
                                <Info label="Slug" value={venue.slug} />
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
                                <Info
                                    label="Szerokość"
                                    value={venue.latitude ?? '-'}
                                />
                                <Info
                                    label="Długość"
                                    value={venue.longitude ?? '-'}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ImageIcon className="size-4 text-muted-foreground" />
                                    Galeria łowiska
                                </CardTitle>
                                <CardDescription>
                                    Zdjęcia widoczne w katalogu i profilu
                                    publicznym
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <MediaManager
                                    media={venue.media}
                                    uploadAction={`/operator/fishing-venues/${venue.uuid}/media`}
                                    reorderAction={`/operator/fishing-venues/${venue.uuid}/media/order`}
                                    primaryAction={(media) =>
                                        `/operator/fishing-venues/${venue.uuid}/media/${media.id}/primary`
                                    }
                                    deleteAction={(media) =>
                                        `/operator/fishing-venues/${venue.uuid}/media/${media.id}`
                                    }
                                    inputId="venue_images"
                                    emptyText="Brak zdjęć łowiska."
                                />
                            </CardContent>
                        </Card>

                        <div className="space-y-3">
                            <h2 className="text-base font-semibold">
                                Stanowiska
                            </h2>

                            {venue.spots.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                    Brak stanowisk.
                                </div>
                            ) : (
                                venue.spots.map((spot) => (
                                    <Card key={spot.uuid} className="py-5">
                                        <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <CardTitle className="text-base">
                                                    {spot.name}
                                                </CardTitle>
                                                {spot.description && (
                                                    <CardDescription>
                                                        {spot.description}
                                                    </CardDescription>
                                                )}
                                            </div>

                                            <Badge variant="outline">
                                                {spotStatusLabels[spot.status]}
                                            </Badge>
                                        </CardHeader>

                                        <CardContent className="space-y-4">
                                            <div className="grid gap-3 text-sm sm:grid-cols-4">
                                                <Info
                                                    label="Pojemność"
                                                    value={spot.capacity}
                                                />
                                                <Info
                                                    label="Cena"
                                                    value={`${spot.base_price_amount} gr`}
                                                />
                                                <Info
                                                    label="Waluta"
                                                    value={spot.currency}
                                                />
                                                <Info
                                                    label="Kolejność"
                                                    value={spot.sort_order}
                                                />
                                            </div>

                                            <div className="grid gap-3 text-sm text-muted-foreground sm:grid-cols-3">
                                                <span>
                                                    Reguły:{' '}
                                                    {
                                                        spot.availability_rules
                                                            .length
                                                    }
                                                </span>
                                                <span>
                                                    Blokady:{' '}
                                                    {
                                                        spot.availability_blocks
                                                            .length
                                                    }
                                                </span>
                                                <span>
                                                    Cenniki:{' '}
                                                    {spot.pricing_rules.length}
                                                </span>
                                            </div>

                                            <div className="border-t pt-4">
                                                <div className="mb-3 flex items-center gap-2 text-sm font-medium">
                                                    <ImageIcon className="size-4 text-muted-foreground" />
                                                    Zdjęcia stanowiska
                                                </div>
                                                <MediaManager
                                                    media={spot.media}
                                                    uploadAction={`/operator/fishing-venues/${venue.uuid}/spots/${spot.uuid}/media`}
                                                    reorderAction={`/operator/fishing-venues/${venue.uuid}/spots/${spot.uuid}/media/order`}
                                                    primaryAction={(media) =>
                                                        `/operator/fishing-venues/${venue.uuid}/spots/${spot.uuid}/media/${media.id}/primary`
                                                    }
                                                    deleteAction={(media) =>
                                                        `/operator/fishing-venues/${venue.uuid}/spots/${spot.uuid}/media/${media.id}`
                                                    }
                                                    inputId={`spot_images_${spot.uuid}`}
                                                    emptyText="Brak zdjęć stanowiska."
                                                />
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))
                            )}
                        </div>
                    </section>

                    <aside className="space-y-4">
                        <Card className="h-fit">
                            <CardHeader>
                                <CardTitle>Dodaj stanowisko</CardTitle>
                                <CardDescription>
                                    Podstawowe parametry sprzedażowe
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={`/operator/fishing-venues/${venue.uuid}/spots`}
                                    method="post"
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="name">
                                                    Nazwa
                                                </Label>
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="description">
                                                    Opis
                                                </Label>
                                                <Textarea
                                                    id="description"
                                                    name="description"
                                                />
                                                <InputError
                                                    message={errors.description}
                                                />
                                            </div>

                                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="capacity">
                                                        Pojemność
                                                    </Label>
                                                    <Input
                                                        id="capacity"
                                                        name="capacity"
                                                        type="number"
                                                        min={1}
                                                        defaultValue={1}
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.capacity
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="base_price_amount">
                                                        Cena bazowa (grosze)
                                                    </Label>
                                                    <Input
                                                        id="base_price_amount"
                                                        name="base_price_amount"
                                                        type="number"
                                                        min={0}
                                                        defaultValue={0}
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.base_price_amount
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="currency">
                                                        Waluta
                                                    </Label>
                                                    <Input
                                                        id="currency"
                                                        name="currency"
                                                        defaultValue="PLN"
                                                        maxLength={3}
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.currency
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="sort_order">
                                                        Kolejność
                                                    </Label>
                                                    <Input
                                                        id="sort_order"
                                                        name="sort_order"
                                                        type="number"
                                                        min={0}
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.sort_order
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="ACTIVE"
                                            />

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                <Plus className="size-4" />
                                                Dodaj
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        {hasSpots && (
                            <>
                                <AvailabilityRuleForm venue={venue} />
                                <AvailabilityBlockForm venue={venue} />
                                <PricingRuleForm venue={venue} />
                            </>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

function MediaManager({
    media,
    uploadAction,
    reorderAction,
    primaryAction,
    deleteAction,
    inputId,
    emptyText,
}: {
    media: MediaItem[];
    uploadAction: string;
    reorderAction: string;
    primaryAction: (media: MediaItem) => string;
    deleteAction: (media: MediaItem) => string;
    inputId: string;
    emptyText: string;
}) {
    return (
        <div className="space-y-4">
            <Form
                action={uploadAction}
                method="post"
                encType="multipart/form-data"
                className="grid gap-3 rounded-md border border-dashed p-3 sm:grid-cols-[minmax(0,1fr)_auto]"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor={inputId}>Zdjęcia</Label>
                            <Input
                                id={inputId}
                                name="images[]"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                            />
                            <InputError
                                message={
                                    errors.images ??
                                    errors['images.0'] ??
                                    errors['images.*']
                                }
                            />
                        </div>
                        <div className="flex items-end">
                            <Button type="submit" disabled={processing}>
                                <Upload className="size-4" />
                                Dodaj
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {media.length === 0 ? (
                <div className="rounded-md border border-dashed p-5 text-sm text-muted-foreground">
                    {emptyText}
                </div>
            ) : (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {media.map((item, index) => (
                        <div
                            key={item.id}
                            className="overflow-hidden rounded-md border bg-background"
                        >
                            <MediaPreview media={item} />
                            <div className="space-y-3 p-3">
                                <div className="flex items-center justify-between gap-2">
                                    <Badge
                                        variant={
                                            item.is_primary
                                                ? 'default'
                                                : 'secondary'
                                        }
                                        className="rounded-md"
                                    >
                                        {item.is_primary
                                            ? 'Główne'
                                            : `#${index + 1}`}
                                    </Badge>
                                    <span className="text-xs text-muted-foreground">
                                        {item.sort_order}
                                    </span>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <OrderButton
                                        direction="up"
                                        media={media}
                                        index={index}
                                        action={reorderAction}
                                    />
                                    <OrderButton
                                        direction="down"
                                        media={media}
                                        index={index}
                                        action={reorderAction}
                                    />

                                    {!item.is_primary && (
                                        <Form
                                            action={primaryAction(item)}
                                            method="post"
                                            className="contents"
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="_method"
                                                        value="PATCH"
                                                    />
                                                    <Button
                                                        type="submit"
                                                        size="icon"
                                                        variant="outline"
                                                        disabled={processing}
                                                        title="Ustaw jako główne"
                                                    >
                                                        <Star className="size-4" />
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    )}

                                    <Form
                                        action={deleteAction(item)}
                                        method="post"
                                        className="contents"
                                    >
                                        {({ processing }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="_method"
                                                    value="DELETE"
                                                />
                                                <Button
                                                    type="submit"
                                                    size="icon"
                                                    variant="outline"
                                                    disabled={processing}
                                                    title="Usuń zdjęcie"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function MediaPreview({ media }: { media: MediaItem }) {
    if (media.url) {
        return (
            <img
                src={media.url}
                alt=""
                className="aspect-[4/3] w-full object-cover"
                loading="lazy"
            />
        );
    }

    return (
        <div className="flex aspect-[4/3] w-full items-center justify-center bg-muted">
            <ImageIcon className="size-8 text-muted-foreground" />
        </div>
    );
}

function OrderButton({
    direction,
    media,
    index,
    action,
}: {
    direction: 'up' | 'down';
    media: MediaItem[];
    index: number;
    action: string;
}) {
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    const disabled = targetIndex < 0 || targetIndex >= media.length;
    const order = moveMedia(media, index, targetIndex).map((item) => item.id);
    const Icon = direction === 'up' ? ChevronUp : ChevronDown;

    return (
        <Form action={action} method="post" className="contents">
            {({ processing }) => (
                <>
                    <input type="hidden" name="_method" value="PATCH" />
                    {order.map((mediaId) => (
                        <input
                            key={mediaId}
                            type="hidden"
                            name="media_ids[]"
                            value={mediaId}
                        />
                    ))}
                    <Button
                        type="submit"
                        size="icon"
                        variant="outline"
                        disabled={disabled || processing}
                        title={
                            direction === 'up'
                                ? 'Przesuń wyżej'
                                : 'Przesuń niżej'
                        }
                    >
                        <Icon className="size-4" />
                    </Button>
                </>
            )}
        </Form>
    );
}

function moveMedia(media: MediaItem[], from: number, to: number) {
    const next = [...media];

    if (to < 0 || to >= next.length) {
        return next;
    }

    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
}

function SpotSelect({ spots, id }: { spots: FishingSpot[]; id: string }) {
    return (
        <select
            id={id}
            name="fishing_spot_id"
            className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
            required
            defaultValue={spots[0]?.id}
        >
            {spots.map((spot) => (
                <option key={spot.uuid} value={spot.id}>
                    {spot.name}
                </option>
            ))}
        </select>
    );
}

function DaySelect({
    id,
    name = 'day_of_week',
    includeEmpty = false,
}: {
    id: string;
    name?: string;
    includeEmpty?: boolean;
}) {
    return (
        <select
            id={id}
            name={name}
            className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
            defaultValue={includeEmpty ? '' : 1}
            required={!includeEmpty}
        >
            {includeEmpty && <option value="">Dowolny dzień</option>}
            {Object.entries(dayLabels).map(([value, label]) => (
                <option key={value} value={value}>
                    {label}
                </option>
            ))}
        </select>
    );
}

function AvailabilityRuleForm({ venue }: { venue: FishingVenue }) {
    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Reguła dostępności</CardTitle>
                <CardDescription>Godziny działania stanowiska</CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    action={`/operator/fishing-venues/${venue.uuid}/availability-rules`}
                    method="post"
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="availability_spot">
                                    Stanowisko
                                </Label>
                                <SpotSelect
                                    id="availability_spot"
                                    spots={venue.spots}
                                />
                                <InputError message={errors.fishing_spot_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="day_of_week">Dzień</Label>
                                <DaySelect id="day_of_week" />
                                <InputError message={errors.day_of_week} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                <div className="grid gap-2">
                                    <Label htmlFor="opens_at">Od</Label>
                                    <Input
                                        id="opens_at"
                                        name="opens_at"
                                        type="time"
                                        defaultValue="06:00"
                                        required
                                    />
                                    <InputError message={errors.opens_at} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="closes_at">Do</Label>
                                    <Input
                                        id="closes_at"
                                        name="closes_at"
                                        type="time"
                                        defaultValue="22:00"
                                        required
                                    />
                                    <InputError message={errors.closes_at} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                <div className="grid gap-2">
                                    <Label htmlFor="minimum_booking_minutes">
                                        Minimum minut
                                    </Label>
                                    <Input
                                        id="minimum_booking_minutes"
                                        name="minimum_booking_minutes"
                                        type="number"
                                        min={1}
                                        defaultValue={60}
                                        required
                                    />
                                    <InputError
                                        message={errors.minimum_booking_minutes}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="maximum_booking_minutes">
                                        Maksimum minut
                                    </Label>
                                    <Input
                                        id="maximum_booking_minutes"
                                        name="maximum_booking_minutes"
                                        type="number"
                                        min={1}
                                    />
                                    <InputError
                                        message={errors.maximum_booking_minutes}
                                    />
                                </div>
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                <Plus className="size-4" />
                                Dodaj
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function AvailabilityBlockForm({ venue }: { venue: FishingVenue }) {
    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Blokada terminu</CardTitle>
                <CardDescription>Ręczne wyłączenie stanowiska</CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    action={`/operator/fishing-venues/${venue.uuid}/availability-blocks`}
                    method="post"
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="block_spot">Stanowisko</Label>
                                <SpotSelect
                                    id="block_spot"
                                    spots={venue.spots}
                                />
                                <InputError message={errors.fishing_spot_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="starts_at">Początek</Label>
                                <Input
                                    id="starts_at"
                                    name="starts_at"
                                    type="datetime-local"
                                    required
                                />
                                <InputError message={errors.starts_at} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="ends_at">Koniec</Label>
                                <Input
                                    id="ends_at"
                                    name="ends_at"
                                    type="datetime-local"
                                    required
                                />
                                <InputError message={errors.ends_at} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reason">Powód</Label>
                                <Input id="reason" name="reason" />
                                <InputError message={errors.reason} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                <Plus className="size-4" />
                                Dodaj
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function PricingRuleForm({ venue }: { venue: FishingVenue }) {
    return (
        <Card className="h-fit">
            <CardHeader>
                <CardTitle>Reguła ceny</CardTitle>
                <CardDescription>Nadpisanie ceny bazowej</CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    action={`/operator/fishing-venues/${venue.uuid}/pricing-rules`}
                    method="post"
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="pricing_spot">Stanowisko</Label>
                                <SpotSelect
                                    id="pricing_spot"
                                    spots={venue.spots}
                                />
                                <InputError message={errors.fishing_spot_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="pricing_day_of_week">
                                    Dzień
                                </Label>
                                <DaySelect
                                    id="pricing_day_of_week"
                                    includeEmpty
                                />
                                <InputError message={errors.day_of_week} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                <div className="grid gap-2">
                                    <Label htmlFor="price_amount">
                                        Cena (grosze)
                                    </Label>
                                    <Input
                                        id="price_amount"
                                        name="price_amount"
                                        type="number"
                                        min={0}
                                        required
                                    />
                                    <InputError message={errors.price_amount} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="price_currency">
                                        Waluta
                                    </Label>
                                    <Input
                                        id="price_currency"
                                        name="currency"
                                        defaultValue="PLN"
                                        maxLength={3}
                                        required
                                    />
                                    <InputError message={errors.currency} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                <div className="grid gap-2">
                                    <Label htmlFor="pricing_starts_at">
                                        Od
                                    </Label>
                                    <Input
                                        id="pricing_starts_at"
                                        name="starts_at"
                                        type="datetime-local"
                                    />
                                    <InputError message={errors.starts_at} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="pricing_ends_at">Do</Label>
                                    <Input
                                        id="pricing_ends_at"
                                        name="ends_at"
                                        type="datetime-local"
                                    />
                                    <InputError message={errors.ends_at} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                <div className="grid gap-2">
                                    <Label htmlFor="minimum_duration_minutes">
                                        Min. minut
                                    </Label>
                                    <Input
                                        id="minimum_duration_minutes"
                                        name="minimum_duration_minutes"
                                        type="number"
                                        min={1}
                                    />
                                    <InputError
                                        message={
                                            errors.minimum_duration_minutes
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="priority">Priorytet</Label>
                                    <Input
                                        id="priority"
                                        name="priority"
                                        type="number"
                                        min={0}
                                        defaultValue={0}
                                    />
                                    <InputError message={errors.priority} />
                                </div>
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                <Plus className="size-4" />
                                Dodaj
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
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

FishingVenueShow.layout = {
    breadcrumbs: [
        {
            title: 'Łowiska',
            href: index(),
        },
    ],
};
