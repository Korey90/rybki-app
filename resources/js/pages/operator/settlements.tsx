import { Form, Head } from '@inertiajs/react';
import {
    CalendarClock,
    CreditCard,
    Download,
    FileText,
    ReceiptText,
    Wallet,
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
    paymentStatusLabels,
} from './utils';
import type { PaymentStatus } from './utils';

type SettlementSummary = {
    paid_gross_amount: number;
    platform_fee_amount: number;
    operator_amount: number;
    refunds_amount: number;
    estimated_payout_amount: number;
    succeeded_payments_count: number;
    pending_payments_count: number;
    failed_payments_count: number;
};

type OrganizationSettlement = {
    uuid: string;
    name: string;
    paid_gross_amount: number;
    platform_fee_amount: number;
    operator_amount: number;
    refunds_amount: number;
    estimated_payout_amount: number;
    succeeded_payments_count: number;
};

type SettlementFilters = {
    organization_uuid: string | null;
    period_start: string;
    period_end: string;
};

type CommissionSettings = {
    default_commission_bps: number;
};

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

type SettlementPayment = {
    uuid: string;
    provider: string;
    provider_payment_id: string | null;
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
    };
};

type ProviderAccount = {
    provider: string;
    external_account_id: string | null;
    onboarding_status: string;
    payments_enabled: boolean;
    payouts_enabled: boolean;
    last_synced_at: string | null;
};

type SettlementPeriodStatus =
    'DRAFT' | 'APPROVED' | 'PROCESSING' | 'PAID' | 'FAILED' | 'CANCELLED';

type OperatorPayoutStatus =
    'PENDING' | 'APPROVED' | 'PROCESSING' | 'PAID' | 'FAILED' | 'CANCELLED';

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

export default function OperatorSettlements({
    summary,
    filters,
    commission_settings,
    organizations,
    settlement_periods,
    payments,
    provider_accounts,
}: {
    summary: SettlementSummary;
    filters: SettlementFilters;
    commission_settings: CommissionSettings;
    organizations: OrganizationSettlement[];
    settlement_periods: SettlementPeriod[];
    payments: SettlementPayment[];
    provider_accounts: ProviderAccount[];
}) {
    return (
        <>
            <Head title="Rozliczenia operatora" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Rozliczenia"
                    description="Przychody, prowizje i okresy wypłat dla Twoich łowisk"
                />

                <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        title="Opłacone brutto"
                        value={formatMoney(summary.paid_gross_amount)}
                        note={`${summary.succeeded_payments_count} płatności`}
                        icon={CreditCard}
                    />
                    <SummaryCard
                        title="Prowizja"
                        value={formatMoney(summary.platform_fee_amount)}
                        note={`globalnie ${formatCommission(commission_settings.default_commission_bps)}`}
                        icon={ReceiptText}
                    />
                    <SummaryCard
                        title="Dla operatora"
                        value={formatMoney(summary.operator_amount)}
                        note={`${formatMoney(summary.refunds_amount)} zwrotów`}
                        icon={Wallet}
                    />
                    <SummaryCard
                        title="Do wypłaty"
                        value={formatMoney(summary.estimated_payout_amount)}
                        note="estymacja MVP"
                        icon={Wallet}
                    />
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CalendarClock className="size-4 text-muted-foreground" />
                                    Nowy okres rozliczeniowy
                                </CardTitle>
                                <CardDescription>
                                    Wybierz organizację i zakres dat do
                                    przygotowania wypłaty
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action="/operator/settlements"
                                    method="post"
                                    className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_10rem_10rem_auto]"
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
                                                    message={errors.period_end}
                                                />
                                            </div>
                                            <div className="flex items-end">
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        processing ||
                                                        organizations.length ===
                                                            0
                                                    }
                                                    className="w-full"
                                                >
                                                    <ReceiptText className="size-4" />
                                                    Generuj
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Okresy rozliczeniowe</CardTitle>
                                <CardDescription>
                                    Historia naliczonych wypłat z eksportami dla
                                    księgowości
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {settlement_periods.length === 0 ? (
                                    <EmptyState value="Brak wygenerowanych okresów rozliczeniowych." />
                                ) : (
                                    settlement_periods.map((period) => (
                                        <SettlementPeriodRow
                                            key={period.uuid}
                                            period={period}
                                        />
                                    ))
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Organizacje</CardTitle>
                                <CardDescription>
                                    Rozbicie przychodu według operatora
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {organizations.length === 0 ? (
                                    <EmptyState value="Brak organizacji do rozliczenia." />
                                ) : (
                                    organizations.map((organization) => (
                                        <div
                                            key={organization.uuid}
                                            className="grid gap-3 rounded-md border bg-muted/20 p-3 text-sm lg:grid-cols-[minmax(0,1fr)_8rem_8rem_8rem_8rem]"
                                        >
                                            <div>
                                                <div className="font-medium">
                                                    {organization.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {
                                                        organization.succeeded_payments_count
                                                    }{' '}
                                                    opłaconych płatności
                                                </div>
                                            </div>
                                            <Amount
                                                label="Brutto"
                                                value={
                                                    organization.paid_gross_amount
                                                }
                                            />
                                            <Amount
                                                label="Prowizja"
                                                value={
                                                    organization.platform_fee_amount
                                                }
                                            />
                                            <Amount
                                                label="Operator"
                                                value={
                                                    organization.operator_amount
                                                }
                                            />
                                            <Amount
                                                label="Po zwrotach"
                                                value={
                                                    organization.estimated_payout_amount
                                                }
                                            />
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Płatności</CardTitle>
                                <CardDescription>
                                    Ostatnie zdarzenia płatnicze
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {payments.length === 0 ? (
                                    <EmptyState value="Brak płatności." />
                                ) : (
                                    payments.map((payment) => (
                                        <div
                                            key={payment.uuid}
                                            className="grid gap-3 rounded-md border bg-muted/20 p-3 text-sm lg:grid-cols-[minmax(0,1fr)_8rem_8rem]"
                                        >
                                            <div className="min-w-0">
                                                <div className="font-medium">
                                                    {
                                                        payment.booking
                                                            .booking_number
                                                    }
                                                </div>
                                                <div className="truncate text-xs text-muted-foreground">
                                                    {
                                                        payment.booking
                                                            .customer_name
                                                    }
                                                    {' · '}
                                                    {payment.provider_payment_id ??
                                                        '-'}
                                                </div>
                                            </div>
                                            <div>
                                                <Badge variant="outline">
                                                    {
                                                        paymentStatusLabels[
                                                            payment.status
                                                        ]
                                                    }
                                                </Badge>
                                                <div className="mt-1 text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        payment.paid_at ??
                                                            payment.failed_at ??
                                                            payment.created_at,
                                                    )}
                                                </div>
                                            </div>
                                            <div className="font-medium lg:text-right">
                                                {formatMoney(
                                                    payment.amount,
                                                    payment.currency,
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <aside className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Status płatności</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <MetricRow
                                    label="Opłacone"
                                    value={summary.succeeded_payments_count}
                                />
                                <MetricRow
                                    label="W toku"
                                    value={summary.pending_payments_count}
                                />
                                <MetricRow
                                    label="Nieudane"
                                    value={summary.failed_payments_count}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Konta providera</CardTitle>
                                <CardDescription>
                                    Dane techniczne integracji płatności
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {provider_accounts.length === 0 ? (
                                    <EmptyState value="Brak kont providera." />
                                ) : (
                                    provider_accounts.map((account) => (
                                        <div
                                            key={`${account.provider}-${account.external_account_id}`}
                                            className="rounded-md border bg-muted/20 p-3 text-sm"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-medium">
                                                        {account.provider}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {account.external_account_id ??
                                                            '-'}
                                                    </div>
                                                </div>
                                                <Badge variant="secondary">
                                                    {account.onboarding_status}
                                                </Badge>
                                            </div>
                                            <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-muted-foreground">
                                                <span>
                                                    Płatności:{' '}
                                                    {account.payments_enabled
                                                        ? 'tak'
                                                        : 'nie'}
                                                </span>
                                                <span>
                                                    Wypłaty:{' '}
                                                    {account.payouts_enabled
                                                        ? 'tak'
                                                        : 'nie'}
                                                </span>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </aside>
                </section>
            </div>
        </>
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
    icon: typeof CreditCard;
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

function Amount({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <span className="block text-xs text-muted-foreground">{label}</span>
            <span className="font-medium">{formatMoney(value)}</span>
        </div>
    );
}

function SettlementPeriodRow({ period }: { period: SettlementPeriod }) {
    return (
        <div className="rounded-md border bg-muted/20 p-3 text-sm">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">
                            {period.organization.name}
                        </span>
                        <Badge variant={statusVariant(period.status)}>
                            {settlementStatusLabels[period.status]}
                        </Badge>
                        {period.payout.status && (
                            <Badge variant="outline">
                                {payoutStatusLabels[period.payout.status]}
                            </Badge>
                        )}
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {formatDate(period.period_start)} -{' '}
                        {formatDate(period.period_end)}
                        {' · '}
                        {period.provider ?? 'bez providera'}
                        {' · '}
                        {period.provider_account.external_account_id ?? '-'}
                    </div>
                </div>

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
            </div>

            <div className="mt-4 grid gap-3 md:grid-cols-5">
                <Amount label="Brutto" value={period.gross_amount} />
                <Amount label="Prowizja" value={period.platform_fee_amount} />
                <Amount label="Zwroty" value={period.refund_amount} />
                <Amount label="Do wypłaty" value={period.net_payout_amount} />
                <div>
                    <span className="block text-xs text-muted-foreground">
                        Pozycje
                    </span>
                    <span className="font-medium">
                        {period.bookings_count} / {period.refunds_count}
                    </span>
                </div>
            </div>
        </div>
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

function MetricRow({
    label,
    value,
}: {
    label: string;
    value: string | number;
}) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

function EmptyState({ value }: { value: string }) {
    return (
        <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
            {value}
        </div>
    );
}

OperatorSettlements.layout = {
    breadcrumbs: [
        {
            title: 'Rozliczenia',
            href: '/operator/settlements',
        },
    ],
};
