import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Settings2 } from 'lucide-react';
import { index as administration } from '@/actions/App/Http/Controllers/AdministrationController';
import { index as procurement } from '@/actions/App/Http/Controllers/ProcurementController';
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
                <NavMain
                    items={[
                        ...mainNavItems,
                        ...(isAdministrator
                            ? [
                                  {
                                      title: 'Administration',
                                      href: administration(),
                                      icon: Settings2,
                                  },
                              ]
                            : []),
                    ]}
                />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
