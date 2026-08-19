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

type Operator = {
    uuid: string;
    name: string;
    email: string;
    account_type: 'ANGLER' | 'VENUE_OWNER';
    managed_organizations_count: number;
    organizations: {
        uuid: string;
        name: string;
        role: 'OWNER' | 'MANAGER' | 'STAFF';
    }[];
    created_at: string | null;
};

export default function AdminOperatorsIndex({
    operators,
    filters,
    export_url,
}: {
    operators: Operator[];
    filters: {
        q: string | null;
    };
    export_url: string;
}) {
    return (
        <>
            <Head title="Operatorzy" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Operatorzy"
                        description="Konta zarządzające organizacjami i łowiskami"
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
                            action="/admin/operators"
                            method="get"
                            className="grid gap-4 md:grid-cols-[minmax(0,1fr)_8rem]"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="q">Szukaj</Label>
                                <Input
                                    id="q"
                                    name="q"
                                    defaultValue={filters.q ?? ''}
                                    placeholder="Operator, email, organizacja"
                                />
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
                            Właściciele kont operatora i managerowie organizacji
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {operators.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak operatorów dla wybranych filtrów.
                            </div>
                        ) : (
                            operators.map((operator) => (
                                <div
                                    key={operator.uuid}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div>
                                            <div className="font-medium">
                                                {operator.name}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {operator.email}
                                            </div>
                                        </div>
                                        <div className="text-sm text-muted-foreground md:text-right">
                                            {
                                                operator.managed_organizations_count
                                            }{' '}
                                            organizacji ·{' '}
                                            {formatDateTime(
                                                operator.created_at,
                                            )}
                                        </div>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {operator.organizations.length === 0 ? (
                                            <Badge variant="outline">
                                                Brak organizacji
                                            </Badge>
                                        ) : (
                                            operator.organizations.map(
                                                (organization) => (
                                                    <Badge
                                                        key={`${operator.uuid}-${organization.uuid}`}
                                                        variant="secondary"
                                                    >
                                                        {organization.name} ·{' '}
                                                        {organization.role}
                                                    </Badge>
                                                ),
                                            )
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminOperatorsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Operatorzy',
            href: '/admin/operators',
        },
    ],
};
