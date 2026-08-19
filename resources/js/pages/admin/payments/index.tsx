import { Head, Link } from '@inertiajs/react';
import { CreditCard, Download, Search } from 'lucide-react';
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
import {
    formatDateTime,
    formatMoney,
    paymentStatusLabels,
} from '@/pages/operator/utils';
import type { PaymentStatus } from '@/pages/operator/utils';

type PaymentProvider = 'test' | 'stripe_connect' | 'payu_marketplace';

type AdminPayment = {
    uuid: string;
    provider: PaymentProvider;
    provider_payment_id: string | null;
    checkout_url: string | null;
    status: PaymentStatus;
    amount: number;
    currency: string;
    paid_at: string | null;
    failed_at: string | null;
    created_at: string | null;
    booking: {
        uuid: string;
        booking_number: string;
        customer_name: string;
        customer_email: string;
        organization: {
            uuid: string;
            name: string;
        };
        venue: {
            uuid: string;
            name: string;
            slug: string;
        };
    };
};

const providerLabels: Record<PaymentProvider, string> = {
    test: 'Test',
    stripe_connect: 'Stripe Connect',
    payu_marketplace: 'PayU Marketplace',
};

export default function AdminPaymentsIndex({
    payments,
    filters,
    statuses,
    providers,
    export_url,
}: {
    payments: AdminPayment[];
    filters: {
        q: string | null;
        status: PaymentStatus | null;
        provider: PaymentProvider | null;
    };
    statuses: PaymentStatus[];
    providers: PaymentProvider[];
    export_url: string;
}) {
    return (
        <>
            <Head title="Płatności admin" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Płatności"
                        description="Podgląd transakcji, providerów i powiązanych rezerwacji"
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
                    providers={providers}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CreditCard className="size-4 text-muted-foreground" />
                            Wyniki
                        </CardTitle>
                        <CardDescription>
                            Maksymalnie 100 najnowszych dopasowań
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {payments.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak płatności dla wybranych filtrów.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-4">
                                                Płatność
                                            </th>
                                            <th className="py-2 pr-4">
                                                Rezerwacja
                                            </th>
                                            <th className="py-2 pr-4">
                                                Status
                                            </th>
                                            <th className="py-2 pr-4">Kwota</th>
                                            <th className="py-2 pr-4">Czas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {payments.map((payment) => (
                                            <tr
                                                key={payment.uuid}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="py-3 pr-4 align-top">
                                                    <div className="font-medium">
                                                        {
                                                            providerLabels[
                                                                payment.provider
                                                            ]
                                                        }
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {payment.provider_payment_id ??
                                                            payment.uuid}
                                                    </div>
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    <div className="font-medium">
                                                        {
                                                            payment.booking
                                                                .booking_number
                                                        }
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            payment.booking
                                                                .customer_email
                                                        }
                                                    </div>
                                                    <Link
                                                        href={`/lowiska/${payment.booking.venue.slug}`}
                                                        className="text-xs underline-offset-4 hover:underline"
                                                    >
                                                        {
                                                            payment.booking
                                                                .venue.name
                                                        }
                                                    </Link>
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    <Badge variant="secondary">
                                                        {
                                                            paymentStatusLabels[
                                                                payment.status
                                                            ]
                                                        }
                                                    </Badge>
                                                </td>
                                                <td className="py-3 pr-4 align-top font-medium">
                                                    {formatMoney(
                                                        payment.amount,
                                                        payment.currency,
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    {formatDateTime(
                                                        payment.paid_at ??
                                                            payment.created_at,
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
    statuses,
    providers,
}: {
    filters: {
        q: string | null;
        status: PaymentStatus | null;
        provider: PaymentProvider | null;
    };
    statuses: PaymentStatus[];
    providers: PaymentProvider[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Filtry</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    action="/admin/payments"
                    method="get"
                    className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_13rem_13rem_8rem]"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="q">Szukaj</Label>
                        <Input
                            id="q"
                            name="q"
                            defaultValue={filters.q ?? ''}
                            placeholder="Payment ID, booking, klient, łowisko"
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
                                    {paymentStatusLabels[status]}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="provider">Provider</Label>
                        <select
                            id="provider"
                            name="provider"
                            defaultValue={filters.provider ?? ''}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                        >
                            <option value="">Wszystkie</option>
                            {providers.map((provider) => (
                                <option key={provider} value={provider}>
                                    {providerLabels[provider]}
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

AdminPaymentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Płatności admin',
            href: '/admin/payments',
        },
    ],
};
