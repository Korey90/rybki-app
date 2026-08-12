import { Form, Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    Database,
    ExternalLink,
    FileText,
    GitMerge,
    RefreshCw,
    ShieldAlert,
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
import { formatDateTime } from '@/pages/marketplace/utils';

type DataSource = {
    id: number;
    provider: string;
    dataset_name: string;
    dataset_url: string | null;
    external_dataset_id: string | null;
    default_format: string | null;
    sync_enabled: boolean;
    last_successful_sync_at: string | null;
    records_count: number;
    venue_sources_count: number;
    sync_runs_count: number;
};

type SyncRun = {
    id: number;
    status: 'RUNNING' | 'COMPLETED' | 'FAILED';
    resource_external_id: string | null;
    resource_title: string | null;
    resource_url: string | null;
    started_at: string;
    finished_at: string | null;
    records_seen: number;
    records_staged: number;
    records_needing_review: number;
    records_with_candidates: number;
    records_skipped: number;
    error: string | null;
    data_source: {
        provider: string;
        dataset_name: string;
    };
};

type GovernmentVenueRecord = {
    id: number;
    external_id: string;
    name: string;
    city: string | null;
    voivodeship: string | null;
    venue_type: 'PRIVATE' | 'PZW' | 'WODY_POLSKIE' | 'OTHER_PUBLIC' | 'OTHER';
    status: 'PENDING' | 'NEEDS_REVIEW' | 'IMPORTED' | 'MATCHED' | 'SKIPPED';
    source_updated_at: string | null;
    quality_flags: string[];
    quality_score: number;
    match_candidates: MatchCandidate[];
    match_confidence: number;
    reviewed_at: string | null;
    reviewed_by: string | null;
    data_source: {
        provider: string;
        dataset_name: string;
    };
    venue: {
        uuid: string;
        name: string;
        slug: string;
        commercial_status: 'INFORMATION_ONLY' | 'CLAIMABLE' | 'BOOKABLE';
    } | null;
};

type MatchCandidate = {
    venue_uuid: string;
    name: string;
    city: string | null;
    voivodeship: string | null;
    commercial_status: string;
    organization_id: number | null;
    score: number;
};

type CandidateVenue = {
    uuid: string;
    name: string;
    city: string | null;
    voivodeship: string | null;
    commercial_status: string;
    organization_id: number | null;
};

type StatusCounts = Record<GovernmentVenueRecord['status'], number>;

const recordStatusLabels: Record<GovernmentVenueRecord['status'], string> = {
    PENDING: 'Oczekuje',
    NEEDS_REVIEW: 'Do decyzji',
    IMPORTED: 'Zaimportowany',
    MATCHED: 'Dopasowany',
    SKIPPED: 'Pominięty',
};

const syncStatusLabels: Record<SyncRun['status'], string> = {
    RUNNING: 'W toku',
    COMPLETED: 'Zakończony',
    FAILED: 'Błąd',
};

const qualityFlagLabels: Record<string, string> = {
    missing_coordinates: 'brak współrzędnych',
    missing_city: 'brak miasta',
    missing_address: 'brak adresu',
    missing_description: 'brak opisu',
    weak_name: 'słaba nazwa',
};

export default function AdminGovernmentDataIndex({
    sources,
    sync_runs,
    status_counts,
    records,
    candidate_venues,
}: {
    sources: DataSource[];
    sync_runs: SyncRun[];
    status_counts: StatusCounts;
    records: GovernmentVenueRecord[];
    candidate_venues: CandidateVenue[];
}) {
    const reviewCount =
        (status_counts.PENDING ?? 0) + (status_counts.NEEDS_REVIEW ?? 0);

    return (
        <>
            <Head title="Dane publiczne" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Dane publiczne"
                    description="Źródła, synchronizacje, matching i decyzje administracyjne"
                />

                <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        title="Do decyzji"
                        value={reviewCount}
                        note="pending i needs review"
                        icon={ShieldAlert}
                    />
                    <MetricCard
                        title="Połączone"
                        value={
                            (status_counts.IMPORTED ?? 0) +
                            (status_counts.MATCHED ?? 0)
                        }
                        note="profile lub match"
                        icon={GitMerge}
                    />
                    <MetricCard
                        title="Pominięte"
                        value={status_counts.SKIPPED ?? 0}
                        note="decyzje admina"
                        icon={FileText}
                    />
                    <MetricCard
                        title="Sync runs"
                        value={sync_runs.length}
                        note="ostatnie uruchomienia"
                        icon={RefreshCw}
                    />
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="grid gap-4 xl:grid-cols-2">
                        {sources.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground xl:col-span-2">
                                Brak skonfigurowanych źródeł danych.
                            </div>
                        ) : (
                            sources.map((source) => (
                                <Card key={source.id}>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Database className="size-4 text-muted-foreground" />
                                            {source.provider}
                                        </CardTitle>
                                        <CardDescription>
                                            {source.dataset_name}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-3 text-sm">
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <Info
                                                label="Dataset"
                                                value={
                                                    source.external_dataset_id ??
                                                    '-'
                                                }
                                            />
                                            <Info
                                                label="Format"
                                                value={
                                                    source.default_format ?? '-'
                                                }
                                            />
                                            <Info
                                                label="Rekordy"
                                                value={source.records_count}
                                            />
                                            <Info
                                                label="Połączenia"
                                                value={
                                                    source.venue_sources_count
                                                }
                                            />
                                            <Info
                                                label="Sync"
                                                value={
                                                    source.sync_enabled
                                                        ? 'aktywny'
                                                        : 'wyłączony'
                                                }
                                            />
                                            <Info
                                                label="Ostatni sync"
                                                value={formatDateTime(
                                                    source.last_successful_sync_at,
                                                )}
                                            />
                                        </div>
                                        {source.dataset_url && (
                                            <a
                                                href={source.dataset_url}
                                                className="inline-flex items-center gap-2 text-sm underline-offset-4 hover:underline"
                                            >
                                                <ExternalLink className="size-4" />
                                                Źródło
                                            </a>
                                        )}
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <RefreshCw className="size-4 text-muted-foreground" />
                                Sync dane.gov.pl
                            </CardTitle>
                            <CardDescription>
                                Wody Polskie, wykaz obwodów rybackich
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/admin/government-data/sync"
                                method="post"
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="limit">
                                                Limit rekordów
                                            </Label>
                                            <Input
                                                id="limit"
                                                name="limit"
                                                type="number"
                                                min={1}
                                                max={5000}
                                                defaultValue={50}
                                            />
                                            <InputError
                                                message={errors.limit}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            <RefreshCw className="size-4" />
                                            Uruchom sync
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Raport synchronizacji</CardTitle>
                        <CardDescription>
                            Ostatnie pobrania realnych zasobów publicznych
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {sync_runs.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak uruchomień synchronizacji.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-4">Run</th>
                                            <th className="py-2 pr-4">Zasób</th>
                                            <th className="py-2 pr-4">
                                                Rekordy
                                            </th>
                                            <th className="py-2 pr-4">Czas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {sync_runs.map((run) => (
                                            <tr
                                                key={run.id}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="py-3 pr-4 align-top">
                                                    <Badge
                                                        variant={syncVariant(
                                                            run.status,
                                                        )}
                                                    >
                                                        {
                                                            syncStatusLabels[
                                                                run.status
                                                            ]
                                                        }
                                                    </Badge>
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        #{run.id}{' '}
                                                        {
                                                            run.data_source
                                                                .provider
                                                        }
                                                    </div>
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    <div>
                                                        {run.resource_title ??
                                                            '-'}
                                                    </div>
                                                    {run.resource_url && (
                                                        <a
                                                            href={
                                                                run.resource_url
                                                            }
                                                            className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                                        >
                                                            {
                                                                run.resource_external_id
                                                            }
                                                        </a>
                                                    )}
                                                    {run.error && (
                                                        <div className="mt-1 text-xs text-destructive">
                                                            {run.error}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    pobrane: {run.records_seen}
                                                    <br />
                                                    staging:{' '}
                                                    {run.records_staged}
                                                    <br />
                                                    do decyzji:{' '}
                                                    {run.records_needing_review}
                                                    <br />
                                                    kandydaci:{' '}
                                                    {
                                                        run.records_with_candidates
                                                    }
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    {formatDateTime(
                                                        run.started_at,
                                                    )}
                                                    <br />
                                                    {formatDateTime(
                                                        run.finished_at,
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

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="size-4 text-muted-foreground" />
                            Rekordy publiczne
                        </CardTitle>
                        <CardDescription>
                            Wynik normalizacji, jakości danych i matchingu
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {records.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak rekordów importu.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-4">
                                                Rekord
                                            </th>
                                            <th className="py-2 pr-4">
                                                Jakość
                                            </th>
                                            <th className="py-2 pr-4">
                                                Źródło
                                            </th>
                                            <th className="py-2 pr-4">
                                                Status
                                            </th>
                                            <th className="py-2 pr-4">
                                                Profil
                                            </th>
                                            <th className="py-2 pr-4">
                                                Decyzja
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {records.map((record) => (
                                            <RecordRow
                                                key={record.id}
                                                record={record}
                                                candidateVenues={
                                                    candidate_venues
                                                }
                                            />
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

function RecordRow({
    record,
    candidateVenues,
}: {
    record: GovernmentVenueRecord;
    candidateVenues: CandidateVenue[];
}) {
    const defaultCandidate =
        record.match_candidates[0]?.venue_uuid ??
        candidateVenues[0]?.uuid ??
        '';

    return (
        <tr className="border-b last:border-b-0">
            <td className="py-3 pr-4 align-top">
                <div className="font-medium">{record.name}</div>
                <div className="text-xs text-muted-foreground">
                    {record.external_id}
                </div>
                <div className="mt-1 text-xs text-muted-foreground">
                    {record.city ?? 'Bez miasta'}
                    {record.voivodeship ? `, ${record.voivodeship}` : ''}
                </div>
            </td>
            <td className="py-3 pr-4 align-top">
                <div className="font-medium">{record.quality_score}/100</div>
                <div className="mt-1 flex max-w-56 flex-wrap gap-1">
                    {record.quality_flags.length === 0 ? (
                        <Badge variant="secondary">kompletne</Badge>
                    ) : (
                        record.quality_flags.map((flag) => (
                            <Badge key={flag} variant="outline">
                                {qualityFlagLabels[flag] ?? flag}
                            </Badge>
                        ))
                    )}
                </div>
                {record.match_confidence > 0 && (
                    <div className="mt-2 text-xs text-muted-foreground">
                        match {record.match_confidence}%
                    </div>
                )}
            </td>
            <td className="py-3 pr-4 align-top">
                <div>{record.data_source.provider}</div>
                <div className="text-xs text-muted-foreground">
                    {record.data_source.dataset_name}
                    <br />
                    {formatDateTime(record.source_updated_at)}
                </div>
            </td>
            <td className="py-3 pr-4 align-top">
                <Badge variant={recordVariant(record.status)}>
                    {recordStatusLabels[record.status]}
                </Badge>
                {record.reviewed_at && (
                    <div className="mt-1 text-xs text-muted-foreground">
                        {record.reviewed_by ?? 'admin'} ·{' '}
                        {formatDateTime(record.reviewed_at)}
                    </div>
                )}
            </td>
            <td className="py-3 pr-4 align-top">
                {record.venue ? (
                    <Link
                        href={`/lowiska/${record.venue.slug}`}
                        className="underline-offset-4 hover:underline"
                    >
                        {record.venue.name}
                    </Link>
                ) : (
                    <span className="text-muted-foreground">-</span>
                )}
                {record.match_candidates.length > 0 && !record.venue && (
                    <div className="mt-2 space-y-1 text-xs text-muted-foreground">
                        {record.match_candidates
                            .slice(0, 2)
                            .map((candidate) => (
                                <div key={candidate.venue_uuid}>
                                    {candidate.name} · {candidate.score}%
                                </div>
                            ))}
                    </div>
                )}
            </td>
            <td className="py-3 pr-4 align-top">
                <div className="grid min-w-56 gap-2">
                    <Form
                        action={`/admin/government-data/records/${record.id}/import`}
                        method="post"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="sm"
                                disabled={
                                    processing || record.status === 'IMPORTED'
                                }
                                className="w-full"
                            >
                                <CheckCircle2 className="size-4" />
                                Utwórz profil
                            </Button>
                        )}
                    </Form>

                    <Form
                        action={`/admin/government-data/records/${record.id}/match`}
                        method="patch"
                        className="grid gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <select
                                    name="fishing_venue_uuid"
                                    defaultValue={defaultCandidate}
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                >
                                    {candidateVenues.map((venue) => (
                                        <option
                                            key={venue.uuid}
                                            value={venue.uuid}
                                        >
                                            {venue.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.fishing_venue_uuid}
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    disabled={
                                        processing ||
                                        candidateVenues.length === 0 ||
                                        record.status === 'MATCHED'
                                    }
                                >
                                    <GitMerge className="size-4" />
                                    Połącz
                                </Button>
                            </>
                        )}
                    </Form>

                    <Form
                        action={`/admin/government-data/records/${record.id}/skip`}
                        method="patch"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                disabled={
                                    processing || record.status === 'SKIPPED'
                                }
                                className="w-full"
                            >
                                Pomiń
                            </Button>
                        )}
                    </Form>
                </div>
            </td>
        </tr>
    );
}

function MetricCard({
    title,
    value,
    note,
    icon: Icon,
}: {
    title: string;
    value: string | number;
    note: string;
    icon: typeof Database;
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

function Info({ label, value }: { label: string; value: string | number }) {
    return (
        <div>
            <span className="block text-xs text-muted-foreground">{label}</span>
            <span>{value}</span>
        </div>
    );
}

function recordVariant(status: GovernmentVenueRecord['status']) {
    if (status === 'SKIPPED') {
        return 'destructive';
    }

    if (status === 'IMPORTED' || status === 'MATCHED') {
        return 'secondary';
    }

    return 'outline';
}

function syncVariant(status: SyncRun['status']) {
    if (status === 'FAILED') {
        return 'destructive';
    }

    if (status === 'COMPLETED') {
        return 'secondary';
    }

    return 'outline';
}

AdminGovernmentDataIndex.layout = {
    breadcrumbs: [
        {
            title: 'Dane publiczne',
            href: '/admin/government-data',
        },
    ],
};
