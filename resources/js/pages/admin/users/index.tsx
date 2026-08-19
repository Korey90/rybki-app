import { Head } from '@inertiajs/react';
import { Download, Search, Users } from 'lucide-react';
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

type AccountType = 'ANGLER' | 'VENUE_OWNER';

type AdminUser = {
    uuid: string;
    name: string;
    email: string;
    phone: string | null;
    account_type: AccountType;
    is_admin: boolean;
    bookings_count: number;
    favorites_count: number;
    reviews_count: number;
    memberships_count: number;
    created_at: string | null;
};

const accountTypeLabels: Record<AccountType, string> = {
    ANGLER: 'Wędkarz',
    VENUE_OWNER: 'Właściciel łowiska',
};

export default function AdminUsersIndex({
    users,
    filters,
    account_types,
    export_url,
}: {
    users: AdminUser[];
    filters: {
        q: string | null;
        account_type: AccountType | null;
        is_admin: string | null;
    };
    account_types: AccountType[];
    export_url: string;
}) {
    return (
        <>
            <Head title="Użytkownicy" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Użytkownicy"
                        description="Wyszukiwanie kont, ról i aktywności użytkowników"
                    />
                    <Button asChild variant="outline">
                        <a href={export_url}>
                            <Download className="size-4" />
                            CSV
                        </a>
                    </Button>
                </div>

                <FilterCard filters={filters} accountTypes={account_types} />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="size-4 text-muted-foreground" />
                            Wyniki
                        </CardTitle>
                        <CardDescription>
                            Maksymalnie 100 najnowszych dopasowań
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {users.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak użytkowników dla wybranych filtrów.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-4">
                                                Użytkownik
                                            </th>
                                            <th className="py-2 pr-4">Typ</th>
                                            <th className="py-2 pr-4">
                                                Aktywność
                                            </th>
                                            <th className="py-2 pr-4">
                                                Utworzono
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {users.map((user) => (
                                            <tr
                                                key={user.uuid}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="py-3 pr-4 align-top">
                                                    <div className="font-medium">
                                                        {user.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {user.email}
                                                    </div>
                                                    {user.phone && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {user.phone}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    <div className="flex flex-wrap gap-2">
                                                        <Badge variant="secondary">
                                                            {
                                                                accountTypeLabels[
                                                                    user
                                                                        .account_type
                                                                ]
                                                            }
                                                        </Badge>
                                                        {user.is_admin && (
                                                            <Badge variant="outline">
                                                                Admin
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    {user.bookings_count}{' '}
                                                    rezerwacji ·{' '}
                                                    {user.memberships_count}{' '}
                                                    org. · {user.reviews_count}{' '}
                                                    opinii
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    {formatDateTime(
                                                        user.created_at,
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

function FilterCard({
    filters,
    accountTypes,
}: {
    filters: {
        q: string | null;
        account_type: AccountType | null;
        is_admin: string | null;
    };
    accountTypes: AccountType[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Filtry</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    action="/admin/users"
                    method="get"
                    className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_13rem_10rem_8rem]"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="q">Szukaj</Label>
                        <Input
                            id="q"
                            name="q"
                            defaultValue={filters.q ?? ''}
                            placeholder="Imię, email, telefon, UUID"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="account_type">Typ konta</Label>
                        <select
                            id="account_type"
                            name="account_type"
                            defaultValue={filters.account_type ?? ''}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                        >
                            <option value="">Wszystkie</option>
                            {accountTypes.map((type) => (
                                <option key={type} value={type}>
                                    {accountTypeLabels[type]}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="is_admin">Admin</Label>
                        <select
                            id="is_admin"
                            name="is_admin"
                            defaultValue={filters.is_admin ?? ''}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                        >
                            <option value="">Wszyscy</option>
                            <option value="1">Tak</option>
                            <option value="0">Nie</option>
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

AdminUsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Użytkownicy',
            href: '/admin/users',
        },
    ],
};
