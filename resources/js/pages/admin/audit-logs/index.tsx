import { Head } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDateTime } from '@/pages/marketplace/utils';

type AuditLog = {
    id: number;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string | null;
    actor: {
        name: string;
        email: string;
    } | null;
    organization: {
        name: string;
    } | null;
};

export default function AdminAuditLogsIndex({ logs }: { logs: AuditLog[] }) {
    return (
        <>
            <Head title="Audit log" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Audit log"
                    description="Ostatnie zdarzenia domenowe i administracyjne"
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ScrollText className="size-4 text-muted-foreground" />
                            Zdarzenia
                        </CardTitle>
                        <CardDescription>
                            Najnowsze wpisy audytowe
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {logs.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak wpisów.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-4">Akcja</th>
                                            <th className="py-2 pr-4">Aktor</th>
                                            <th className="py-2 pr-4">
                                                Organizacja
                                            </th>
                                            <th className="py-2 pr-4">
                                                Podmiot
                                            </th>
                                            <th className="py-2 pr-4">Czas</th>
                                            <th className="py-2 pr-4">Dane</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {logs.map((log) => (
                                            <tr
                                                key={log.id}
                                                className="border-b last:border-b-0"
                                            >
                                                <td className="py-3 pr-4 align-top">
                                                    <Badge variant="secondary">
                                                        {log.action}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    {log.actor ? (
                                                        <div>
                                                            <div>
                                                                {log.actor.name}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    log.actor
                                                                        .email
                                                                }
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            System
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 pr-4 align-top">
                                                    {log.organization?.name ??
                                                        '-'}
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    {shortSubject(
                                                        log.subject_type,
                                                    )}
                                                    {log.subject_id
                                                        ? ` #${log.subject_id}`
                                                        : ''}
                                                </td>
                                                <td className="py-3 pr-4 align-top text-muted-foreground">
                                                    {formatDateTime(
                                                        log.created_at,
                                                    )}
                                                </td>
                                                <td className="max-w-xl py-3 pr-4 align-top">
                                                    <pre className="max-h-32 overflow-auto rounded-md bg-muted p-3 text-xs">
                                                        {JSON.stringify(
                                                            log.after ??
                                                                log.before ??
                                                                {},
                                                            null,
                                                            2,
                                                        )}
                                                    </pre>
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

function shortSubject(value: string | null) {
    return value?.split('\\').pop() ?? '-';
}

AdminAuditLogsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Audit log',
            href: '/admin/audit-logs',
        },
    ],
};
