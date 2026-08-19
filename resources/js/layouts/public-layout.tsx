import { Link, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    Fish,
    Gauge,
    LogIn,
    Menu,
    Search,
    UserPlus,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { login, register } from '@/routes';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

const publicNavItems = [
    {
        title: 'Łowiska',
        href: '/lowiska',
        icon: Search,
    },
];

export default function PublicLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;
    const userPanelLink = user?.can_use_operator_panel
        ? {
              title: 'Panel operatora',
              href: '/operator',
              icon: Gauge,
          }
        : {
              title: 'Moje rezerwacje',
              href: '/konto/rezerwacje',
              icon: CalendarCheck,
          };

    return (
        <div className="min-h-svh bg-background text-foreground">
            <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                <div className="mx-auto flex h-16 w-full max-w-7xl items-center gap-4 px-4 md:px-6">
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="md:hidden"
                            >
                                <Menu className="size-5" />
                                <span className="sr-only">Otwórz menu</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" className="w-72">
                            <SheetTitle className="sr-only">
                                Menu główne
                            </SheetTitle>
                            <div className="flex h-full flex-col gap-6 pt-7">
                                <Link
                                    href="/"
                                    className="flex items-center gap-2 px-1"
                                    prefetch
                                >
                                    <ProductBrand />
                                </Link>

                                <nav className="grid gap-1">
                                    {[
                                        ...publicNavItems,
                                        ...(user ? [userPanelLink] : []),
                                    ].map((item) => (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            className="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                                        >
                                            <item.icon className="size-4" />
                                            {item.title}
                                        </Link>
                                    ))}
                                </nav>

                                {!user && (
                                    <div className="mt-auto grid gap-2">
                                        <Button asChild variant="outline">
                                            <Link href={login()}>
                                                <LogIn className="size-4" />
                                                Logowanie
                                            </Link>
                                        </Button>
                                        <Button asChild>
                                            <Link href={register()}>
                                                <UserPlus className="size-4" />
                                                Rejestracja
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </SheetContent>
                    </Sheet>

                    <Link
                        href="/"
                        className="flex min-w-0 items-center gap-2"
                        prefetch
                    >
                        <ProductBrand />
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex">
                        {publicNavItems.map((item) => (
                            <Button
                                key={item.href}
                                asChild
                                variant="ghost"
                                size="sm"
                            >
                                <Link href={item.href}>
                                    <item.icon className="size-4" />
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>

                    <div className="ml-auto flex items-center gap-2">
                        {user ? (
                            <>
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="hidden sm:inline-flex"
                                >
                                    <Link href={userPanelLink.href}>
                                        <userPanelLink.icon className="size-4" />
                                        {userPanelLink.title}
                                    </Link>
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="h-10 px-2"
                                        >
                                            <UserInfo user={user} />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        className="w-64"
                                        align="end"
                                    >
                                        <UserMenuContent user={user} />
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </>
                        ) : (
                            <>
                                <Button
                                    asChild
                                    variant="ghost"
                                    size="sm"
                                    className="hidden sm:inline-flex"
                                >
                                    <Link href={login()}>
                                        <LogIn className="size-4" />
                                        Logowanie
                                    </Link>
                                </Button>
                                <Button asChild size="sm">
                                    <Link href={register()}>
                                        <UserPlus className="size-4" />
                                        Rejestracja
                                    </Link>
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main>{children}</main>

            <footer className="border-t bg-muted/20">
                <div className="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-6 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between md:px-6">
                    <div>Rybki - marketplace rezerwacji łowisk</div>
                    <nav className="flex flex-wrap gap-x-4 gap-y-2">
                        <Link
                            href="/regulamin"
                            className="transition hover:text-foreground"
                        >
                            Regulamin
                        </Link>
                        <Link
                            href="/polityka-prywatnosci"
                            className="transition hover:text-foreground"
                        >
                            Prywatność
                        </Link>
                        <Link
                            href="/zasady-anulowania"
                            className="transition hover:text-foreground"
                        >
                            Anulowanie
                        </Link>
                        <Link
                            href="/kontakt"
                            className="transition hover:text-foreground"
                        >
                            Kontakt
                        </Link>
                        <Link
                            href="/dla-operatorow"
                            className="transition hover:text-foreground"
                        >
                            Dla operatorów
                        </Link>
                    </nav>
                </div>
            </footer>
        </div>
    );
}

function ProductBrand() {
    return (
        <span className="flex min-w-0 items-center gap-2">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-xs">
                <Fish className="size-5" />
            </span>
            <span className="grid min-w-0 leading-none">
                <span className="truncate text-base font-semibold tracking-normal">
                    Rybki
                </span>
                <span className="hidden text-[11px] font-medium text-muted-foreground sm:block">
                    rezerwacje łowisk
                </span>
            </span>
        </span>
    );
}
