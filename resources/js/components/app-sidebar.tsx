import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, CalendarOff, ExternalLink, LayoutGrid, ListChecks, Scissors, Settings2, Users } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    { title: 'Resumen', url: '/dashboard', icon: LayoutGrid },
    { title: 'Agenda', url: '/agenda', icon: CalendarDays },
    { title: 'Turnos', url: '/turnos', icon: ListChecks },
    { title: 'Servicios', url: '/servicios', icon: Scissors },
    { title: 'Equipo', url: '/equipo', icon: Users, ownerOnly: true },
    { title: 'Ausencias y feriados', url: '/ausencias', icon: CalendarOff, ownerOnly: true },
    { title: 'Reglas de reserva', url: '/settings/business', icon: Settings2, ownerOnly: true },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const items = mainNavItems.filter((item) => !item.ownerOnly || auth.user.is_owner);

    const footerNavItems: NavItem[] = auth.business ? [{ title: 'Ver página de reservas', url: auth.business.public_url, icon: ExternalLink }] : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
