import { Link, usePage } from '@inertiajs/react';
import {
    Briefcase,
    Building2,
    KeyRound,
    LayoutDashboard,
    LogOut,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';

interface AdminLayoutProps {
    children: ReactNode;
    title: string;
}

const navItems = [
    { label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
    { label: 'Job Postings', href: '/admin/job-postings', icon: Briefcase },
    { label: 'Companies', href: '/admin/companies', icon: Building2 },
    { label: 'Candidates', href: '/admin/candidates', icon: Users },
];

function initials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

export default function AdminLayout({ children, title }: AdminLayoutProps) {
    const { auth, sidebarOpen } = usePage().props;
    const currentPath =
        typeof window !== 'undefined' ? window.location.pathname : '';

    return (
        <div className="admin-layout">
            <SidebarProvider defaultOpen={sidebarOpen}>
                <Sidebar collapsible="icon">
                    <SidebarHeader>
                        <Link
                            href="/admin/dashboard"
                            className="flex items-center gap-2.5 px-2 py-1.5"
                        >
                            <img
                                src="/images/logo_without_text.png"
                                alt="Pradhi Associates"
                                className="h-7 w-auto"
                            />
                            <span className="text-sm font-bold text-sidebar-foreground group-data-[collapsible=icon]:hidden">
                                Pradhi Admin
                            </span>
                        </Link>
                    </SidebarHeader>

                    <SidebarContent>
                        <SidebarGroup>
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {navItems.map((item) => (
                                        <SidebarMenuItem key={item.href}>
                                            <SidebarMenuButton
                                                asChild
                                                isActive={currentPath.startsWith(
                                                    item.href,
                                                )}
                                                tooltip={item.label}
                                            >
                                                <Link href={item.href}>
                                                    <item.icon />
                                                    <span>{item.label}</span>
                                                </Link>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ))}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    </SidebarContent>

                    <SidebarFooter>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton
                                    size="lg"
                                    className="data-[state=open]:bg-sidebar-accent"
                                >
                                    <Avatar className="h-7 w-7 rounded-md">
                                        <AvatarFallback className="rounded-md text-xs">
                                            {initials(auth.user.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="grid flex-1 text-left text-sm leading-tight">
                                        <span className="truncate font-semibold">
                                            {auth.user.name}
                                        </span>
                                        <span className="truncate text-xs text-sidebar-foreground/60">
                                            {auth.user.email}
                                        </span>
                                    </div>
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                side="top"
                                className="w-56"
                            >
                                <DropdownMenuLabel className="font-normal">
                                    Signed in as {auth.user.email}
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href="/admin/settings/password">
                                        <KeyRound />
                                        Change password
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        className="w-full"
                                    >
                                        <LogOut />
                                        Log out
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </SidebarFooter>
                </Sidebar>

                <SidebarInset>
                    <header className="flex h-14 shrink-0 items-center gap-3 border-b border-border px-4">
                        <SidebarTrigger />
                        <h1 className="text-base font-semibold">{title}</h1>
                    </header>

                    <main className="flex-1 p-6">{children}</main>
                </SidebarInset>
            </SidebarProvider>
        </div>
    );
}
