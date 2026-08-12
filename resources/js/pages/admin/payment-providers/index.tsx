import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, CreditCard, ShieldAlert } from 'lucide-react';
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
import { Label } from '@/components/ui/label';

type ProviderOption = {
    value: string;
    label: string;
    description: string;
    configured: boolean;
    active: boolean;
};

export default function AdminPaymentProvidersIndex({
    active_provider,
    providers,
    webhooks,
}: {
    active_provider: string;
    providers: ProviderOption[];
    webhooks: Record<string, string>;
}) {
    return (
        <>
            <Head title="Providery płatności" />

            <div className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
                <Heading
                    title="Providery płatności"
                    description="Globalny wybór providera dla nowych checkoutów"
                />

                <section className="grid gap-6 xl:grid-cols-[24rem_minmax(0,1fr)]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="size-4 text-muted-foreground" />
                                Aktywny provider
                            </CardTitle>
                            <CardDescription>
                                Obecnie:{' '}
                                {providerLabel(providers, active_provider)}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/admin/payment-providers"
                                method="patch"
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="active_provider">
                                                Provider
                                            </Label>
                                            <select
                                                id="active_provider"
                                                name="active_provider"
                                                defaultValue={active_provider}
                                                className="h-9 rounded-md border border-input bg-background px-3 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden"
                                            >
                                                {providers.map((provider) => (
                                                    <option
                                                        key={provider.value}
                                                        value={provider.value}
                                                    >
                                                        {provider.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.active_provider}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            <CheckCircle2 className="size-4" />
                                            Zapisz wybór
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <div className="grid gap-4 md:grid-cols-3">
                        {providers.map((provider) => (
                            <Card key={provider.value}>
                                <CardHeader className="gap-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <CardTitle className="text-base">
                                                {provider.label}
                                            </CardTitle>
                                            <CardDescription>
                                                {provider.value}
                                            </CardDescription>
                                        </div>
                                        {provider.active && (
                                            <Badge>Aktywny</Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4 text-sm">
                                    <p className="text-muted-foreground">
                                        {provider.description}
                                    </p>

                                    <div className="flex items-center gap-2">
                                        {provider.configured ? (
                                            <CheckCircle2 className="size-4 text-emerald-600" />
                                        ) : (
                                            <ShieldAlert className="size-4 text-amber-600" />
                                        )}
                                        <span>
                                            {provider.configured
                                                ? 'Konfiguracja gotowa'
                                                : 'Brakuje konfiguracji env'}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Webhooki</CardTitle>
                        <CardDescription>
                            Adresy do wpisania w panelach sandbox providerów
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 md:grid-cols-2">
                            {Object.entries(webhooks).map(([provider, url]) => (
                                <div
                                    key={provider}
                                    className="rounded-md border bg-muted/20 p-3 text-sm"
                                >
                                    <div className="font-medium">
                                        {providerLabel(providers, provider)}
                                    </div>
                                    <div className="mt-1 font-mono text-xs break-all text-muted-foreground">
                                        {url}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function providerLabel(providers: ProviderOption[], value: string): string {
    return (
        providers.find((provider) => provider.value === value)?.label ?? value
    );
}

AdminPaymentProvidersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Providery płatności',
            href: '/admin/payment-providers',
        },
    ],
};
