import { Form, Head } from '@inertiajs/react';
import {
    Banknote,
    CalendarClock,
    CheckCircle2,
    Download,
    FileText,
    Percent,
    ReceiptText,
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
import {
    formatDate,
    formatDateTime,
    formatMoney,
} from '@/pages/marketplace/utils';

type CommissionSettings = {
    default_commission_bps: number;
};

type SettlementFilters = {
    organization_uuid: string | null;
    period_start: string;
    period_end: string;
};

type SettlementSummary = {
    gross_amount: number;
    platform_fee_amount: number;
    refund_amount: number;
    net_payout_amount: number;
    pending_payouts_count: number;
};

type OrganizationOption = {
    uuid: string;
    name: string;
};

type SettlementPeriodStatus =
    'DRAFT' | 'APPROVED' | 'PROCESSING' | 'PAID' | 'FAILED' | 'CANCELLED';

type OperatorPayoutStatus =
    'PENDING' | 'APPROVED' | 'PROCESSING' | 'PAID' | 'FAILED' | 'CANCELLED';

type SettlementPeriod = {
    uuid: string;
    organization: {
        uuid: string;
        name: string;
    };
    provider: string | null;
    provider_account: {
        external_account_id: string | null;
        payouts_enabled: boolean;
    };
    period_start: string;
    period_end: string;
    status: SettlementPeriodStatus;
    currency: string;
    gross_amount: number;
    platform_fee_amount: number;
    refund_amount: number;
    net_payout_amount: number;
    bookings_count: number;
    refunds_count: number;
    generated_at: string | null;
    payout: {
        uuid: string | null;
        status: OperatorPayoutStatus | null;
        amount: number | null;
        provider_payout_id: string | null;
        paid_at: string | null;
    };
    exports: {
        csv: string;
        pdf: string;
    };
};

const settlementStatusLabels: Record<SettlementPeriodStatus, string> = {
    DRAFT: 'Robocze',
    APPROVED: 'Zatwierdzone',
    PROCESSING: 'Wypłata w toku',
    PAID: 'Wypłacone',
    FAILED: 'Błąd wypłaty',
    CANCELLED: 'Anulowane',
};

const payoutStatusLabels: Record<OperatorPayoutStatus, string> = {
    PENDING: 'Oczekuje',
    APPROVED: 'Zatwierdzona',
    PROCESSING: 'W toku',
    PAID: 'Wypłacona',
    FAILED: 'Nieudana',
    CANCELLED: 'Anulowana',
};

export default function AdminSettlementsIndex({
    commission_settings,
    filters,
    summary,
    organizations,
    settlement_periods,
    payout_statuses,
}: {
    commission_settings: CommissionSettings;
    filters: SettlementFilters;
    summary: SettlementSummary;
    organizations: OrganizationOption[];
    settlement_periods: SettlementPeriod[];
    payout_statuses: OperatorPayoutStatus[];
}) {
    return (
        <>
            <Head title="Rozliczenia operatorów" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Rozliczenia operatorów"
                    description="Okresy rozliczeniowe, prowizje i statusy wypłat"
                />

                <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        title="Brutto"
                        value={formatMoney(summary.gross_amount)}
                        note="w wygenerowanych okresach"
                        icon={ReceiptText}
                    />
                    <SummaryCard
                        title="Prowizja"
                        value={formatMoney(summary.platform_fee_amount)}
                        note={`stawka ${formatCommission(commission_settings.default_commission_bps)}`}
                        icon={Percent}
                    />
                    <SummaryCard
                        title="Zwroty"
                        value={formatMoney(summary.refund_amount)}
                        note="po dacie refundu"
                        icon={ReceiptText}
                    />
                    <SummaryCard
                        title="Do wypłaty"
                        value={formatMoney(summary.net_payout_amount)}
                        note={`${summary.pending_payouts_count} oczekujących`}
                        icon={Banknote}
                    />
                </section>

                <section className="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Percent className="size-4 text-muted-foreground" />
                                    Prowizja platformy
                                </CardTitle>
                                <CardDescription>
                                    Snapshot trafia do nowych bookingów
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action="/admin/settlements/commission"
                                    method="patch"
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="default_commission_bps">
                                                    Basis points
                                                </Label>
                                                <Input
                                                    id="default_commission_bps"
                                                    name="default_commission_bps"
                                                    type="number"
                                                    min={0}
                                                    max={10000}
                                                    step={1}
                                                    defaultValue={
                                                        commission_settings.default_commission_bps
                                                    }
                                                />
                                                <div className="text-xs text-muted-foreground">
                                                    Aktualnie:{' '}
                                                    {formatCommission(
                                                        commission_settings.default_commission_bps,
                                                    )}
                                                </div>
                                                <InputError
                                                    message={
                                                        errors.default_commission_bps
                                                    }
                                                />
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                <CheckCircle2 className="size-4" />
                                                Zapisz
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarClock className="size-4 text-muted-foreground" />
                                    Generuj okres
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action="/admin/settlements"
                                    method="post"
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="organization_uuid">
                                                    Organizacja
                                                </Label>
                                                <select
                                                    id="organization_uuid"
                                                    name="organization_uuid"
                                                    defaultValue={
                                                        filters.organization_uuid ??
                                                        ''
                                                    }
                                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                                >
                                                    {organizations.map(
                                                        (organization) => (
                                                            <option
                                                                key={
                                                                    organization.uuid
                                                                }
                                                                value={
                                                                    organization.uuid
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
                                                        errors.organization_uuid
                                                    }
                                                />
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="period_start">
                                                        Od
                                                    </Label>
                                                    <Input
                                                        id="period_start"
                                                        name="period_start"
                                                        type="date"
                                                        defaultValue={
                                                            filters.period_start
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.period_start
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="period_end">
                                                        Do
                                                    </Label>
                                                    <Input
                                                        id="period_end"
                                                        name="period_end"
                                                        type="date"
                                                        defaultValue={
                                                            filters.period_end
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.period_end
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    organizations.length === 0
                                                }
                                                className="w-full"
                                            >
                                                <ReceiptText className="size-4" />
                                                Generuj
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Okresy i wypłaty</CardTitle>
                            <CardDescription>
                                Lista ostatnich rozliczeń wszystkich operatorów
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {settlement_periods.length === 0 ? (
                                <div className="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                    Brak okresów rozliczeniowych.
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b text-left text-xs text-muted-foreground">
                                            <tr>
                                                <th className="py-2 pr-4">
                                                    Operator
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Okres
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Kwoty
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Provider
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Wypłata
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Akcje
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {settlement_periods.map(
                                                (period) => (
                                                    <SettlementPeriodRow
                                                        key={period.uuid}
                                                        period={period}
                                                        payoutStatuses={
                                                            payout_statuses
                                                        }
                                                    />
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}

function SettlementPeriodRow({
    period,
    payoutStatuses,
}: {
    period: SettlementPeriod;
    payoutStatuses: OperatorPayoutStatus[];
}) {
    return (
        <tr className="border-b align-top last:border-b-0">
            <td className="py-3 pr-4">
                <div className="font-medium">{period.organization.name}</div>
                <div className="text-xs text-muted-foreground">
                    {period.bookings_count} płatności / {period.refunds_count}{' '}
                    zwrotów
                </div>
            </td>
            <td className="py-3 pr-4">
                <div>
                    {formatDate(period.period_start)} -{' '}
                    {formatDate(period.period_end)}
                </div>
                <div className="mt-1 flex flex-wrap gap-2">
                    <Badge variant={statusVariant(period.status)}>
                        {settlementStatusLabels[period.status]}
                    </Badge>
                    {period.generated_at && (
                        <span className="text-xs text-muted-foreground">
                            {formatDateTime(period.generated_at)}
                        </span>
                    )}
                </div>
            </td>
            <td className="py-3 pr-4">
                <div className="font-medium">
                    {formatMoney(period.net_payout_amount, period.currency)}
                </div>
                <div className="text-xs text-muted-foreground">
                    brutto {formatMoney(period.gross_amount, period.currency)}
                    <br />
                    prowizja{' '}
                    {formatMoney(period.platform_fee_amount, period.currency)}
                    <br />
                    zwroty {formatMoney(period.refund_amount, period.currency)}
                </div>
            </td>
            <td className="py-3 pr-4">
                <div>{period.provider ?? '-'}</div>
                <div className="text-xs text-muted-foreground">
                    {period.provider_account.external_account_id ?? '-'}
                    <br />
                    payout:{' '}
                    {period.provider_account.payouts_enabled ? 'tak' : 'nie'}
                </div>
            </td>
            <td className="py-3 pr-4">
                <Form
                    action={`/admin/settlements/${period.uuid}/payout`}
                    method="patch"
                    className="grid min-w-48 gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <select
                                name="status"
                                defaultValue={period.payout.status ?? 'PENDING'}
                                className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                            >
                                {payoutStatuses.map((status) => (
                                    <option key={status} value={status}>
                                        {payoutStatusLabels[status]}
                                    </option>
                                ))}
                            </select>
                            <Input
                                name="provider_payout_id"
                                defaultValue={
                                    period.payout.provider_payout_id ?? ''
                                }
                                placeholder="provider payout id"
                            />
                            <InputError message={errors.status} />
                            <InputError message={errors.provider_payout_id} />
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                            >
                                <CheckCircle2 className="size-4" />
                                Zapisz
                            </Button>
                        </>
                    )}
                </Form>
            </td>
            <td className="py-3 pr-4">
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <a href={period.exports.csv}>
                            <Download className="size-4" />
                            CSV
                        </a>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <a href={period.exports.pdf}>
                            <FileText className="size-4" />
                            PDF
                        </a>
                    </Button>
                </div>
            </td>
        </tr>
    );
}

function SummaryCard({
    title,
    value,
    note,
    icon: Icon,
}: {
    title: string;
    value: string;
    note: string;
    icon: typeof ReceiptText;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
                <span className="flex size-8 items-center justify-center rounded-md bg-accent text-accent-foreground">
                    <Icon className="size-4" />
                </span>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold">{value}</div>
                <p className="text-xs text-muted-foreground">{note}</p>
            </CardContent>
        </Card>
    );
}

function statusVariant(status: SettlementPeriodStatus) {
    if (status === 'FAILED' || status === 'CANCELLED') {
        return 'destructive';
    }

    if (status === 'PAID') {
        return 'secondary';
    }

    return 'outline';
}

function formatCommission(commissionBps: number): string {
    return `${(commissionBps / 100).toLocaleString('pl-PL', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })}%`;
}

AdminSettlementsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Rozliczenia operatorów',
            href: '/admin/settlements',
        },
    ],
};
