import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarCheck,
    CalendarDays,
    ClipboardList,
    CreditCard,
    Database,
    Gauge,
    Heart,
    LayoutGrid,
    MapPinned,
    MessageSquare,
    RefreshCw,
    RotateCcw,
    Search,
    ShieldCheck,
    ScrollText,
    Wallet,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as operatorFishingVenues } from '@/routes/operator/fishing-venues';
import { index as operatorOrganizations } from '@/routes/operator/organizations';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Start',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Marketplace',
        href: '/lowiska',
        icon: Search,
    },
    {
        title: 'Moje rezerwacje',
        href: '/konto/rezerwacje',
        icon: CalendarCheck,
    },
    {
        title: 'Ulubione',
        href: '/konto/ulubione',
        icon: Heart,
    },
];

const operatorNavItems: NavItem[] = [
    {
        title: 'Panel operatora',
        href: '/operator',
        icon: Gauge,
    },
    {
        title: 'Organizacje',
        href: operatorOrganizations(),
        icon: Building2,
    },
    {
        title: 'Łowiska',
        href: operatorFishingVenues(),
        icon: MapPinned,
    },
    {
        title: 'Rezerwacje',
        href: '/operator/bookings',
        icon: ClipboardList,
    },
    {
        title: 'Kalendarz',
        href: '/operator/calendar',
        icon: CalendarDays,
    },
    {
        title: 'Opinie',
        href: '/operator/reviews',
        icon: MessageSquare,
    },
    {
        title: 'Rozliczenia',
        href: '/operator/settlements',
        icon: Wallet,
    },
    {
        title: 'Anulacje',
        href: '/operator/cancellation-policies',
        icon: RotateCcw,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Szukaj łowisk',
        href: '/lowiska',
        icon: Search,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const baseNavItems = auth.user?.can_use_operator_panel
        ? [...mainNavItems, ...operatorNavItems]
        : mainNavItems;

    const navItems = auth.user?.is_admin
        ? [
              ...baseNavItems,
              {
                  title: 'Użytkownicy',
                  href: '/admin/users',
                  icon: Users,
              },
              {
                  title: 'Operatorzy',
                  href: '/admin/operators',
                  icon: Building2,
              },
              {
                  title: 'Organizacje',
                  href: '/admin/organizations',
                  icon: Building2,
              },
              {
                  title: 'Łowiska admin',
                  href: '/admin/fishing-venues',
                  icon: MapPinned,
              },
              {
                  title: 'Rezerwacje admin',
                  href: '/admin/bookings',
                  icon: ClipboardList,
              },
              {
                  title: 'Płatności admin',
                  href: '/admin/payments',
                  icon: CreditCard,
              },
              {
                  title: 'Audit log',
                  href: '/admin/audit-logs',
                  icon: ScrollText,
              },
              {
                  title: 'Dane publiczne',
                  href: '/admin/government-data',
                  icon: Database,
              },
              {
                  title: 'Providery płatności',
                  href: '/admin/payment-providers',
                  icon: CreditCard,
              },
              {
                  title: 'Reconciliation',
                  href: '/admin/payment-reconciliation',
                  icon: RefreshCw,
              },
              {
                  title: 'Rozliczenia',
                  href: '/admin/settlements',
                  icon: Wallet,
              },
              {
                  title: 'Claimy łowisk',
                  href: '/admin/venue-claims',
                  icon: ShieldCheck,
              },
              {
                  title: 'Moderacja opinii',
                  href: '/admin/reviews',
                  icon: MessageSquare,
              },
          ]
        : baseNavItems;

    const footerItems = auth.user?.can_use_operator_panel
        ? [
              ...footerNavItems,
              {
                  title: 'Operator',
                  href: '/operator',
                  icon: Gauge,
              },
          ]
        : footerNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
