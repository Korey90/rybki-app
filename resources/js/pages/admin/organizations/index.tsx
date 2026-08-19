import { Head } from '@inertiajs/react';
import { Building2, Download, Search } from 'lucide-react';
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
import { formatDateTime } from '@/pages/marketplace/utils';

type VerificationStatus = 'pending' | 'verified' | 'rejected' | 'suspended';

type AdminOrganization = {
    uuid: string;
    name: string;
    legal_name: string | null;
    tax_id: string | null;
    regon: string | null;
    country: string;
    verification_status: VerificationStatus;
    members_count: number;
    venues_count: number;
    bookings_count: number;
    provider_accounts_count: number;
    created_at: string | null;
};

const verificationLabels: Record<VerificationStatus, string> = {
    pending: 'Oczekuje',
    verified: 'Zweryfikowana',
    rejected: 'Odrzucona',
    suspended: 'Zawieszona',
};

export default function AdminOrganizationsIndex({
    organizations,
    filters,
    verification_statuses,
    export_url,
}: {
    organizations: AdminOrganization[];
    filters: {
        q: string | null;
        verification_status: VerificationStatus | null;
    };
    verification_statuses: VerificationStatus[];
    export_url: string;
}) {
    return (
        <>
            <Head title="Organizacje" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Organizacje"
                        description="Firmy i podmioty zarządzające łowiskami"
                    />
                    <Button asChild variant="outline">
                        <a href={export_url}>
                            <Download className="size-4" />
                            CSV
                        </a>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filtry</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            action="/admin/organizations"
                            method="get"
                            className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_13rem_8rem]"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="q">Szukaj</Label>
                                <Input
                                    id="q"
                                    name="q"
                                    defaultValue={filters.q ?? ''}
                                    placeholder="Nazwa, NIP, REGON, UUID"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="verification_status">
                                    Status
                                </Label>
                                <select
                                    id="verification_status"
                                    name="verification_status"
                                    defaultValue={
                                        filters.verification_status ?? ''
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                >
                                    <option value="">Wszystkie</option>
                                    {verification_statuses.map((status) => (
                                        <option key={status} value={status}>
                                            {verificationLabels[status]}
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

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="size-4 text-muted-foreground" />
                            Wyniki
                        </CardTitle>
                        <CardDescription>
                            Maksymalnie 100 dopasowanych organizacji
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {organizations.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak organizacji dla wybranych filtrów.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-4">
                                                Organizacja
                                            </th>
                                            <th className="py-2 pr-4">
                                                Status
                                            </th>
                                            <th className="py-2 pr-4">
                                                Aktywność
                                            </th>
                                            <th className="py-2 pr-4">
                                                Utworzono
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {organizations.map((organization) => (
                                            <tr
                                                key={organization.uuid}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="py-3 pr-4 align-top">
                                                    <div className="font-medium">
                                                        {organization.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {organization.legal_name ??
                                                            '-'}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        NIP:{' '}
                                                        {organization.tax_id ??
                                                            '-'}
                                                    </div>
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    <Badge variant="secondary">
                                                        {
                                                            verificationLabels[
                                                                organization
                                                                    .verification_status
                                                            ]
                                                        }
                                                    </Badge>
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    {organization.members_count}{' '}
                                                    członków ·{' '}
                                                    {organization.venues_count}{' '}
                                                    łowisk ·{' '}
                                                    {
                                                        organization.bookings_count
                                                    }{' '}
                                                    rezerwacji ·{' '}
                                                    {
                                                        organization.provider_accounts_count
                                                    }{' '}
                                                    kont płatności
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    {formatDateTime(
                                                        organization.created_at,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminOrganizationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Organizacje',
            href: '/admin/organizations',
        },
    ],
};
