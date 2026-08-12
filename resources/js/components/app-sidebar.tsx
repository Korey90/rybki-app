import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    CalendarCheck,
    CalendarDays,
    ClipboardList,
    CreditCard,
    Database,
    Gauge,
    LayoutGrid,
    MapPinned,
    RefreshCw,
    RotateCcw,
    Search,
    ShieldCheck,
    ScrollText,
    Wallet,
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
