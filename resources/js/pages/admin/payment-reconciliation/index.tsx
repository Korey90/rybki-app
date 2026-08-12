import { Form, Head } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
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
import { formatDateTime, formatMoney } from '@/pages/marketplace/utils';

type ReconciliationRun = {
    id: number;
    provider: string;
    status: 'RUNNING' | 'COMPLETED' | 'FAILED';
    checked_count: number;
    mismatch_count: number;
    items_count: number;
    started_at: string;
    finished_at: string | null;
};

type ReconciliationItem = {
    id: number;
    status: 'OK' | 'MISMATCH' | 'NEEDS_REVIEW';
    reason: string;
    details: Record<string, unknown> | null;
    created_at: string | null;
    run: {
        id: number;
        provider: string;
    };
    payment: {
        uuid: string;
        provider_payment_id: string | null;
        status: string;
        amount: number;
        currency: string;
        booking_uuid: string;
        booking_status: string;
    };
};

export default function AdminPaymentReconciliationIndex({
    runs,
    items,
    providers,
    active_provider,
}: {
    runs: ReconciliationRun[];
    items: ReconciliationItem[];
    providers: string[];
    active_provider: string;
}) {
    return (
        <>
            <Head title="Payment reconciliation" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Payment reconciliation"
                    description="Kontrola lokalnej spójności płatności i bookingów"
                />

                <section className="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <RefreshCw className="size-4 text-muted-foreground" />
                                Uruchom
                            </CardTitle>
                            <CardDescription>
                                Aktywny provider: {active_provider}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/admin/payment-reconciliation"
                                method="post"
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="provider">
                                                Provider
                                            </Label>
                                            <select
                                                id="provider"
                                                name="provider"
                                                defaultValue={providers[0]}
                                                className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                            >
                                                {providers.map((provider) => (
                                                    <option
                                                        key={provider}
                                                        value={provider}
                                                    >
                                                        {provider}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.provider}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="stale_minutes">
                                                Stale minutes
                                            </Label>
                                            <Input
                                                id="stale_minutes"
                                                name="stale_minutes"
                                                type="number"
                                                min={1}
                                                max={1440}
                                                defaultValue={30}
                                            />
                                            <InputError
                                                message={errors.stale_minutes}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            <RefreshCw className="size-4" />
                                            Sprawdź
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Ostatnie uruchomienia</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {runs.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                    Brak reconciliation.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b text-left text-xs text-muted-foreground">
                                            <tr>
                                                <th className="py-2 pr-4">
                                                    Run
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Status
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Checked
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Mismatch
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Czas
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {runs.map((run) => (
                                                <tr
                                                    key={run.id}
                                                    className="border-b last:border-b-0"
                                                >
                                                    <td className="py-3 pr-4">
                                                        #{run.id} {run.provider}
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        <Badge variant="secondary">
                                                            {run.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        {run.checked_count}
                                                    </td>
                                                    <td className="py-3 pr-4">
                                                        {run.mismatch_count}
                                                    </td>
                                                    <td className="py-3 pr-4 text-muted-foreground">
                                                        {formatDateTime(
                                                            run.started_at,
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
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Ostatnie pozycje</CardTitle>
                        <CardDescription>
                            `MISMATCH` i `NEEDS_REVIEW` wymagają obsługi
                            operacyjnej
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {items.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak pozycji.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-4">
                                                Payment
                                            </th>
                                            <th className="py-2 pr-4">
                                                Status
                                            </th>
                                            <th className="py-2 pr-4">Powód</th>
                                            <th className="py-2 pr-4">Kwota</th>
                                            <th className="py-2 pr-4">
                                                Booking
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="py-3 pr-4 align-top">
                                                    <div className="font-medium">
                                                        {item.payment.uuid}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            item.payment
                                                                .provider_payment_id
                                                        }
                                                    </div>
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    <Badge
                                                        variant={
                                                            item.status ===
                                                            'MISMATCH'
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {item.status}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    {item.reason}
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    {formatMoney(
                                                        item.payment.amount,
                                                        item.payment.currency,
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    {item.payment.booking_uuid}
                                                    <br />
                                                    {
                                                        item.payment
                                                            .booking_status
                                                    }
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

AdminPaymentReconciliationIndex.layout = {
    breadcrumbs: [
        {
            title: 'Payment reconciliation',
            href: '/admin/payment-reconciliation',
        },
    ],
};
