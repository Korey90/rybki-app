import { Form, Head } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
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

type OperatorOrganization = {
    id: number;
    name: string;
};

type CancellationPolicy = {
    id: number;
    name: string;
    free_cancellation_hours: number;
    late_refund_percentage: number;
    is_default: boolean;
    organization: {
        id: number;
        name: string;
    };
};

export default function OperatorCancellationPoliciesIndex({
    organizations,
    policies,
}: {
    organizations: OperatorOrganization[];
    policies: CancellationPolicy[];
}) {
    return (
        <>
            <Head title="Polityki anulacji" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Polityki anulacji"
                    description="Reguły zapisywane jako snapshot przy nowych rezerwacjach"
                />

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="space-y-3">
                        {policies.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak polityk anulacji.
                            </div>
                        ) : (
                            policies.map((policy) => (
                                <Card key={policy.id}>
                                    <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <RotateCcw className="size-4 text-muted-foreground" />
                                                {policy.name}
                                            </CardTitle>
                                            <CardDescription>
                                                {policy.organization.name}
                                            </CardDescription>
                                        </div>
                                        {policy.is_default && (
                                            <Badge variant="secondary">
                                                Domyślna
                                            </Badge>
                                        )}
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid gap-3 text-sm sm:grid-cols-2">
                                            <Info
                                                label="Darmowe anulowanie"
                                                value={`${policy.free_cancellation_hours} h`}
                                            />
                                            <Info
                                                label="Zwrot po terminie"
                                                value={`${policy.late_refund_percentage}%`}
                                            />
                                        </div>

                                        {!policy.is_default && (
                                            <Form
                                                action={`/operator/cancellation-policies/${policy.id}/default`}
                                                method="patch"
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        Ustaw jako domyślną
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Dodaj politykę</CardTitle>
                            <CardDescription>
                                Nowe rezerwacje zapiszą snapshot tej
                                konfiguracji
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {organizations.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                    Brak organizacji operatora.
                                </div>
                            ) : (
                                <Form
                                    action="/operator/cancellation-policies"
                                    method="post"
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="organization_id">
                                                    Organizacja
                                                </Label>
                                                <select
                                                    id="organization_id"
                                                    name="organization_id"
                                                    defaultValue={
                                                        organizations[0]?.id
                                                    }
                                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                                    required
                                                >
                                                    {organizations.map(
                                                        (organization) => (
                                                            <option
                                                                key={
                                                                    organization.id
                                                                }
                                                                value={
                                                                    organization.id
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
                                                        errors.organization_id
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="name">
                                                    Nazwa
                                                </Label>
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    required
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="free_cancellation_hours">
                                                    Darmowe anulowanie do
                                                </Label>
                                                <Input
                                                    id="free_cancellation_hours"
                                                    name="free_cancellation_hours"
                                                    type="number"
                                                    min={0}
                                                    max={8760}
                                                    defaultValue={24}
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.free_cancellation_hours
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="late_refund_percentage">
                                                    Zwrot po terminie %
                                                </Label>
                                                <Input
                                                    id="late_refund_percentage"
                                                    name="late_refund_percentage"
                                                    type="number"
                                                    min={0}
                                                    max={100}
                                                    defaultValue={0}
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.late_refund_percentage
                                                    }
                                                />
                                            </div>

                                            <label className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    name="is_default"
                                                    value="1"
                                                    defaultChecked
                                                    className="size-4 rounded border-input"
                                                />
                                                Ustaw jako domyślną
                                            </label>

                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                Dodaj
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
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

OperatorCancellationPoliciesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Polityki anulacji',
            href: '/operator/cancellation-policies',
        },
    ],
};
