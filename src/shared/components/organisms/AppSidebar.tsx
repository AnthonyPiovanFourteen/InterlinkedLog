import { Link, useRouterState } from "@tanstack/react-router";
import {
  LayoutDashboard, FileText, ClipboardCheck, MapPin,
  Truck, Users, ShieldCheck, ScrollText,
  LogOut, PackageSearch,
} from "lucide-react";
import {
  Sidebar, SidebarContent, SidebarFooter, SidebarGroup,
  SidebarGroupContent, SidebarGroupLabel, SidebarHeader,
  SidebarMenu, SidebarMenuButton, SidebarMenuItem,
} from "@/components/ui/sidebar";
import { useAuth } from "@/hooks/use-auth";

const principais = [
  { title: "Painel", url: "/", icon: LayoutDashboard },
  { title: "Cotações", url: "/cotacoes", icon: FileText },
  { title: "Contratações", url: "/contratacoes", icon: ClipboardCheck },
  { title: "Rastreamento", url: "/rastreamento", icon: MapPin },
];

const cadastros = [
  { title: "Transportadoras", url: "/transportadoras", icon: Truck },
];

const sistema = [
  { title: "Usuários", url: "/usuarios", icon: Users },
  { title: "Auditoria", url: "/auditoria", icon: ShieldCheck },
  { title: "Logs", url: "/logs", icon: ScrollText },
];

export function AppSidebar() {
  const { user, logout, isAdmin } = useAuth();
  const currentPath = useRouterState({ select: (s) => s.location.pathname });
  const isActive = (path: string) => path === "/" ? currentPath === "/" : currentPath.startsWith(path);

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader className="border-b border-sidebar-border px-3 py-3">
        <Link to="/" className="flex items-center gap-2">
          <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
            <PackageSearch className="h-4 w-4" />
          </div>
          <div className="flex flex-col leading-tight group-data-[collapsible=icon]:hidden">
            <span className="text-sm font-semibold tracking-tight">InterlinkedLog</span>
            <span className="text-[10px] uppercase tracking-wider text-muted-foreground">Painel de Controle</span>
          </div>
        </Link>
      </SidebarHeader>

      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Principal</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {principais.map((item) => (
                <SidebarMenuItem key={item.url}>
                  <SidebarMenuButton asChild isActive={isActive(item.url)} tooltip={item.title}>
                    <Link to={item.url}><item.icon /><span>{item.title}</span></Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <SidebarGroup>
          <SidebarGroupLabel>Cadastros</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {cadastros.map((item) => (
                <SidebarMenuItem key={item.url}>
                  <SidebarMenuButton asChild isActive={isActive(item.url)} tooltip={item.title}>
                    <Link to={item.url}><item.icon /><span>{item.title}</span></Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        {isAdmin && (
          <SidebarGroup>
            <SidebarGroupLabel>Administração</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {sistema.map((item) => (
                  <SidebarMenuItem key={item.url}>
                    <SidebarMenuButton asChild isActive={isActive(item.url)} tooltip={item.title}>
                      <Link to={item.url}><item.icon /><span>{item.title}</span></Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}
      </SidebarContent>

      <SidebarFooter className="border-t border-sidebar-border">
        <div className="px-3 py-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
          {user?.name} · {user?.role}
        </div>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton onClick={logout} tooltip="Sair">
              <LogOut />
              <span>Sair</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
    </Sidebar>
  );
}
