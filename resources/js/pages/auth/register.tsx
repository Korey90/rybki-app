import { Form, Head } from '@inertiajs/react';
import { Building2, Fish } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Rejestracja" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-3">
                                <Label>Typ konta</Label>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="relative">
                                        <input
                                            id="account_type_angler"
                                            type="radio"
                                            name="account_type"
                                            value="ANGLER"
                                            required
                                            autoFocus
                                            className="peer sr-only"
                                            tabIndex={1}
                                        />
                                        <Label
                                            htmlFor="account_type_angler"
                                            className="flex min-h-28 cursor-pointer flex-col justify-between rounded-lg border border-border bg-card p-4 shadow-xs transition peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50"
                                        >
                                            <Fish className="h-5 w-5 text-primary" />
                                            <span className="space-y-1">
                                                <span className="block text-sm font-semibold">
                                                    Wędkarz
                                                </span>
                                                <span className="block text-xs leading-5 text-muted-foreground">
                                                    Rezerwuję stanowiska i
                                                    przeglądam łowiska.
                                                </span>
                                            </span>
                                        </Label>
                                    </div>

                                    <div className="relative">
                                        <input
                                            id="account_type_owner"
                                            type="radio"
                                            name="account_type"
                                            value="VENUE_OWNER"
                                            required
                                            className="peer sr-only"
                                            tabIndex={2}
                                        />
                                        <Label
                                            htmlFor="account_type_owner"
                                            className="flex min-h-28 cursor-pointer flex-col justify-between rounded-lg border border-border bg-card p-4 shadow-xs transition peer-checked:border-primary peer-checked:bg-primary/5 peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50"
                                        >
                                            <Building2 className="h-5 w-5 text-primary" />
                                            <span className="space-y-1">
                                                <span className="block text-sm font-semibold">
                                                    Właściciel łowiska
                                                </span>
                                                <span className="block text-xs leading-5 text-muted-foreground">
                                                    Zarządzam organizacją,
                                                    łowiskami i rezerwacjami.
                                                </span>
                                            </span>
                                        </Label>
                                    </div>
                                </div>
                                <InputError message={errors.account_type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Imię i nazwisko</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    tabIndex={3}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Jan Kowalski"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Adres e-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={4}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Hasło</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={5}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Hasło"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Powtórz hasło
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={6}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Powtórz hasło"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={7}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Utwórz konto
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Masz już konto?{' '}
                            <TextLink href={login()} tabIndex={8}>
                                Zaloguj się
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Utwórz konto',
    description: 'Wybierz typ profilu i podaj dane logowania',
};
