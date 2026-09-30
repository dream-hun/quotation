import { Link, usePage } from '@inertiajs/react';
import { Building2, LayoutGrid, Settings2, Users } from 'lucide-react';
import { index as configuration } from '@/actions/App/Http/Controllers/AdministrationController';
import { index as procurement } from '@/actions/App/Http/Controllers/ProcurementController';
import { index as suppliers } from '@/actions/App/Http/Controllers/SupplierController';
import { index as users } from '@/actions/App/Http/Controllers/UserAccountController';
import AppLogo from '@/components/app-logo';
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Procurement',
        href: procurement(),
        icon: LayoutGrid,
    },
];

const administrationNavItems: NavItem[] = [
    {
        title: 'Users',
        href: users(),
        icon: Users,
    },
    {
        title: 'Suppliers',
        href: suppliers(),
        icon: Building2,
    },
    {
        title: 'Configuration',
        href: configuration(),
        icon: Settings2,
    },
];

export function AppSidebar() {
    const { isAdministrator } = usePage<{ isAdministrator: boolean }>().props;
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {isAdministrator && (
                    <NavMain
                        label="Administration"
                        items={administrationNavItems}
                    />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
