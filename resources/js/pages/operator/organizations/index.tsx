import { Form, Head } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import OrganizationController from '@/actions/App/Http/Controllers/Operator/OrganizationController';
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
import { index } from '@/routes/operator/organizations';

type Organization = {
    uuid: string;
    name: string;
    legal_name: string | null;
    tax_id: string | null;
    regon: string | null;
    country: string;
    address: string | null;
    verification_status: 'pending' | 'verified' | 'rejected' | 'suspended';
    role: 'OWNER' | 'MANAGER' | 'STAFF';
    created_at: string | null;
};

const verificationStatusLabels: Record<
    Organization['verification_status'],
    string
> = {
    pending: 'Oczekuje',
    verified: 'Zweryfikowana',
    rejected: 'Odrzucona',
    suspended: 'Zawieszona',
};

const roleLabels: Record<Organization['role'], string> = {
    OWNER: 'Owner',
    MANAGER: 'Manager',
    STAFF: 'Staff',
};

export default function OrganizationsIndex({
    organizations,
}: {
    organizations: Organization[];
}) {
    return (
        <>
            <Head title="Organizacje" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Organizacje"
                    description="Operatorzy i firmy zarządzające łowiskami"
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <section className="space-y-3">
                        {organizations.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                                Brak organizacji.
                            </div>
                        ) : (
                            organizations.map((organization) => (
                                <Card key={organization.uuid} className="py-5">
                                    <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="space-y-1">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <Building2 className="size-4 text-muted-foreground" />
                                                {organization.name}
                                            </CardTitle>
                                            {organization.legal_name && (
                                                <CardDescription>
                                                    {organization.legal_name}
                                                </CardDescription>
                                            )}
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            <Badge variant="secondary">
                                                {roleLabels[organization.role]}
                                            </Badge>
                                            <Badge variant="outline">
                                                {
                                                    verificationStatusLabels[
                                                        organization
                                                            .verification_status
                                                    ]
                                                }
                                            </Badge>
                                        </div>
                                    </CardHeader>

                                    <CardContent className="grid gap-3 text-sm text-muted-foreground sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <span className="block text-xs text-muted-foreground/70">
                                                Kraj
                                            </span>
                                            <span className="text-foreground">
                                                {organization.country}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="block text-xs text-muted-foreground/70">
                                                NIP
                                            </span>
                                            <span className="text-foreground">
                                                {organization.tax_id ?? '-'}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="block text-xs text-muted-foreground/70">
                                                REGON
                                            </span>
                                            <span className="text-foreground">
                                                {organization.regon ?? '-'}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="block text-xs text-muted-foreground/70">
                                                Adres
                                            </span>
                                            <span className="text-foreground">
                                                {organization.address ?? '-'}
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        )}
                    </section>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Dodaj organizację</CardTitle>
                            <CardDescription>
                                Podstawowe dane operatora
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...OrganizationController.store.form()}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Nazwa</Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                autoComplete="organization"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="legal_name">
                                                Nazwa prawna
                                            </Label>
                                            <Input
                                                id="legal_name"
                                                name="legal_name"
                                                autoComplete="organization"
                                            />
                                            <InputError
                                                message={errors.legal_name}
                                            />
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                                            <div className="grid gap-2">
                                                <Label htmlFor="tax_id">
                                                    NIP
                                                </Label>
                                                <Input
                                                    id="tax_id"
                                                    name="tax_id"
                                                    inputMode="numeric"
                                                />
                                                <InputError
                                                    message={errors.tax_id}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="regon">
                                                    REGON
                                                </Label>
                                                <Input
                                                    id="regon"
                                                    name="regon"
                                                    inputMode="numeric"
                                                />
                                                <InputError
                                                    message={errors.regon}
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="country">
                                                Kraj
                                            </Label>
                                            <Input
                                                id="country"
                                                name="country"
                                                defaultValue="PL"
                                                maxLength={2}
                                                required
                                            />
                                            <InputError
                                                message={errors.country}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="address">
                                                Adres
                                            </Label>
                                            <Input
                                                id="address"
                                                name="address"
                                                autoComplete="street-address"
                                            />
                                            <InputError
                                                message={errors.address}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            <Plus className="size-4" />
                                            Utwórz
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

OrganizationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Organizacje',
            href: index(),
        },
    ],
};
