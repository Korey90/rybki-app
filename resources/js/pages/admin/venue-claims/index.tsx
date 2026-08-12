import { Form, Head, Link } from '@inertiajs/react';
import { Check, ClipboardCheck, X } from 'lucide-react';
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
import { formatDateTime } from '@/pages/marketplace/utils';

type ClaimStatus = 'PENDING' | 'APPROVED' | 'REJECTED';

type VenueClaim = {
    id: number;
    status: ClaimStatus;
    evidence: string | null;
    reviewed_at: string | null;
    created_at: string | null;
    venue: {
        name: string;
        slug: string;
        commercial_status: 'INFORMATION_ONLY' | 'CLAIMABLE' | 'BOOKABLE';
        organization_id: number | null;
    };
    organization: {
        id: number;
        name: string;
        verification_status: string;
    };
    reviewer: {
        name: string;
        email: string;
    } | null;
};

const claimStatusLabels: Record<ClaimStatus, string> = {
    PENDING: 'Oczekuje',
    APPROVED: 'Zatwierdzone',
    REJECTED: 'Odrzucone',
};

export default function AdminVenueClaimsIndex({
    claims,
}: {
    claims: VenueClaim[];
}) {
    return (
        <>
            <Head title="Claimy łowisk" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Claimy łowisk"
                    description="Review zgłoszeń przejęcia profili z katalogu publicznego"
                />

                {claims.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                        Brak zgłoszeń.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {claims.map((claim) => (
                            <Card key={claim.id}>
                                <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <ClipboardCheck className="size-4 text-muted-foreground" />
                                            {claim.venue.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {claim.organization.name}
                                        </CardDescription>
                                    </div>
                                    <Badge
                                        variant={
                                            claim.status === 'REJECTED'
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                    >
                                        {claimStatusLabels[claim.status]}
                                    </Badge>
                                </CardHeader>

                                <CardContent className="space-y-4">
                                    <div className="grid gap-3 text-sm md:grid-cols-4">
                                        <Info
                                            label="Utworzono"
                                            value={formatDateTime(
                                                claim.created_at,
                                            )}
                                        />
                                        <Info
                                            label="Zweryfikowano"
                                            value={formatDateTime(
                                                claim.reviewed_at,
                                            )}
                                        />
                                        <Info
                                            label="Organizacja"
                                            value={
                                                claim.organization
                                                    .verification_status
                                            }
                                        />
                                        <Info
                                            label="Profil"
                                            value={
                                                claim.venue.organization_id ===
                                                null
                                                    ? 'Bez operatora'
                                                    : `Org #${claim.venue.organization_id}`
                                            }
                                        />
                                    </div>

                                    {claim.evidence && (
                                        <div className="rounded-md border bg-muted/30 p-3 text-sm leading-6 text-muted-foreground">
                                            {claim.evidence}
                                        </div>
                                    )}

                                    <div className="flex flex-wrap gap-2">
                                        <Button asChild variant="outline">
                                            <Link
                                                href={`/lowiska/${claim.venue.slug}`}
                                            >
                                                Otwórz profil
                                            </Link>
                                        </Button>

                                        {claim.status === 'PENDING' && (
                                            <>
                                                <ReviewForm
                                                    claim={claim}
                                                    status="APPROVED"
                                                />
                                                <ReviewForm
                                                    claim={claim}
                                                    status="REJECTED"
                                                />
                                            </>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function ReviewForm({
    claim,
    status,
}: {
    claim: VenueClaim;
    status: 'APPROVED' | 'REJECTED';
}) {
    const isApproved = status === 'APPROVED';

    return (
        <Form
            action={`/admin/venue-claims/${claim.id}`}
            method="patch"
            className="inline-flex"
        >
            {({ processing }) => (
                <>
                    <input type="hidden" name="status" value={status} />
                    <Button
                        type="submit"
                        disabled={processing}
                        variant={isApproved ? 'default' : 'destructive'}
                    >
                        {isApproved ? (
                            <Check className="size-4" />
                        ) : (
                            <X className="size-4" />
                        )}
                        {isApproved ? 'Zatwierdź' : 'Odrzuć'}
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

AdminVenueClaimsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Claimy łowisk',
            href: '/admin/venue-claims',
        },
    ],
};
