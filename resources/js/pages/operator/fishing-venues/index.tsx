import { Form, Head, Link } from '@inertiajs/react';
import { Building2, MapPinned, Plus } from 'lucide-react';
import FishingVenueController from '@/actions/App/Http/Controllers/Operator/FishingVenueController';
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

type OperatorOrganization = {
    id: number;
    uuid: string;
    name: string;
    verification_status: 'pending' | 'verified' | 'rejected' | 'suspended';
};

type FishingVenue = {
    uuid: string;
    name: string;
    slug: string;
    city: string | null;
    voivodeship: string | null;
    venue_type: 'PRIVATE' | 'PZW' | 'WODY_POLSKIE' | 'OTHER_PUBLIC' | 'OTHER';
    commercial_status: 'INFORMATION_ONLY' | 'CLAIMABLE' | 'BOOKABLE';
    status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED' | 'SUSPENDED';
    published_at: string | null;
    spots_count: number;
    organization: {
        uuid: string;
        name: string;
    };
};

const venueTypeLabels: Record<FishingVenue['venue_type'], string> = {
    PRIVATE: 'Prywatne',
    PZW: 'PZW',
    WODY_POLSKIE: 'Wody Polskie',
    OTHER_PUBLIC: 'Inne publiczne',
    OTHER: 'Inne',
};

const statusLabels: Record<FishingVenue['status'], string> = {
    DRAFT: 'Szkic',
    PUBLISHED: 'Opublikowane',
    ARCHIVED: 'Archiwum',
    SUSPENDED: 'Zawieszone',
};

const commercialStatusLabels: Record<
    FishingVenue['commercial_status'],
    string
> = {
    INFORMATION_ONLY: 'Informacyjne',
    CLAIMABLE: 'Do przejęcia',
    BOOKABLE: 'Rezerwowalne',
};

export default function FishingVenuesIndex({
    organizations,
    venues,
}: {
    organizations: OperatorOrganization[];
    venues: FishingVenue[];
}) {
    const canCreateVenue = organizations.length > 0;

    return (
        <>
            <Head title="Łowiska" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Łowiska"
                    description="Miejsca zarządzane przez Twoje organizacje"
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_26rem]">
                    <section className="space-y-3">
                        {venues.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak łowisk.
                            </div>
                        ) : (
                            venues.map((venue) => (
                                <Card key={venue.uuid} className="py-5">
                                    <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="space-y-1">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <MapPinned className="size-4 text-muted-foreground" />
                                                {venue.name}
                                            </CardTitle>
                                            <CardDescription>
                                                {venue.city ?? 'Bez miasta'}
                                                {venue.voivodeship
                                                    ? `, ${venue.voivodeship}`
                                                    : ''}
                                            </CardDescription>
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            <Badge variant="secondary">
                                                {statusLabels[venue.status]}
                                            </Badge>
                                            <Badge variant="outline">
                                                {
                                                    commercialStatusLabels[
                                                        venue.commercial_status
                                                    ]
                                                }
                                            </Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="space-y-4">
                                        <div className="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                            <div>
                                                <span className="block text-xs text-muted-foreground">
                                                    Organizacja
                                                </span>
                                                <span>
                                                    {venue.organization.name}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="block text-xs text-muted-foreground">
                                                    Typ
                                                </span>
                                                <span>
                                                    {
                                                        venueTypeLabels[
                                                            venue.venue_type
                                                        ]
                                                    }
                                                </span>
                                            </div>
                                            <div>
                                                <span className="block text-xs text-muted-foreground">
                                                    Stanowiska
                                                </span>
                                                <span>{venue.spots_count}</span>
                                            </div>
                                            <div>
                                                <span className="block text-xs text-muted-foreground">
                                                    Slug
                                                </span>
                                                <span>{venue.slug}</span>
                                            </div>
                                        </div>

                                        <Button asChild variant="outline">
                                            <Link
                                                href={`/operator/fishing-venues/${venue.uuid}`}
                                            >
                                                Otwórz
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </section>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Dodaj łowisko</CardTitle>
                            <CardDescription>
                                Podstawowe dane profilu
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!canCreateVenue ? (
                                <div className="space-y-3 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                    <Building2 className="size-4" />
                                    <p>
                                        Najpierw utwórz organizację operatora.
                                    </p>
                                </div>
                            ) : (
                                <Form
                                    {...FishingVenueController.store.form()}
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
                                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                                    required
                                                    defaultValue={
                                                        organizations[0]?.id
                                                    }
                                                >
                                                    {organizations.map(
                                                        (organization) => (
                                                            <option
                                                                key={
                                                                    organization.uuid
                                                                }
                                                                value={
                                                                    organization.id
                                                                }
                                                            >
                                                                {
                                                                    organization.name
                                                                }
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <InputError
                                                    message={
                                                        errors.organization_id
                                                    }
                                                />
                                            </div>

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
                                                <Label htmlFor="venue_type">
                                                    Typ
                                                </Label>
                                                <select
                                                    id="venue_type"
                                                    name="venue_type"
                                                    defaultValue="PRIVATE"
                                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                                    required
                                                >
                                                    <option value="PRIVATE">
                                                        Prywatne
                                                    </option>
                                                    <option value="PZW">
                                                        PZW
                                                    </option>
                                                    <option value="WODY_POLSKIE">
                                                        Wody Polskie
                                                    </option>
                                                    <option value="OTHER_PUBLIC">
                                                        Inne publiczne
                                                    </option>
                                                    <option value="OTHER">
                                                        Inne
                                                    </option>
                                                </select>
                                                <InputError
                                                    message={errors.venue_type}
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
                                                    <Label htmlFor="city">
                                                        Miasto
                                                    </Label>
                                                    <Input
                                                        id="city"
                                                        name="city"
                                                    />
                                                    <InputError
                                                        message={errors.city}
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="voivodeship">
                                                        Województwo
                                                    </Label>
                                                    <Input
                                                        id="voivodeship"
                                                        name="voivodeship"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.voivodeship
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="address">
                                                    Adres
                                                </Label>
                                                <Input
                                                    id="address"
                                                    name="address"
                                                />
                                                <InputError
                                                    message={errors.address}
                                                />
                                            </div>

                                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="latitude">
                                                        Szerokość
                                                    </Label>
                                                    <Input
                                                        id="latitude"
                                                        name="latitude"
                                                        inputMode="decimal"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.latitude
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="longitude">
                                                        Długość
                                                    </Label>
                                                    <Input
                                                        id="longitude"
                                                        name="longitude"
                                                        inputMode="decimal"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.longitude
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="country">
                                                    Kraj
                                                </Label>
                                                <Input
                                                    id="country"
                                                    name="country"
                                                    defaultValue="PL"
                                                    maxLength={2}
                                                    required
                                                />
                                                <InputError
                                                    message={errors.country}
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                <Plus className="size-4" />
                                                Utwórz
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

FishingVenuesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Łowiska',
            href: index(),
        },
    ],
};
