import { Link, useRouterState } from "@tanstack/react-router";
import { Search, Bell, ChevronRight } from "lucide-react";
import { SidebarTrigger } from "@/components/ui/sidebar";
import { Input } from "@/components/ui/input";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { useAuth } from "@/hooks/use-auth";

export function AppHeader() {
  const { user } = useAuth();
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const segments = pathname.split("/").filter(Boolean);
  const initials = user?.name?.split(" ").map((s) => s[0]).slice(0, 2).join("") ?? "?";

  return (
    <header className="sticky top-0 z-10 flex h-14 items-center gap-4 border-b border-border bg-background/80 backdrop-blur px-4">
      <SidebarTrigger className="-ml-2" />
      <nav className="flex items-center gap-1 text-xs text-muted-foreground">
        <Link to="/" className="hover:text-foreground">Home</Link>
        {segments.map((seg, i) => (
          <span key={seg} className="flex items-center gap-1">
            <ChevronRight className="h-3 w-3" />
            <span className={i === segments.length - 1 ? "text-foreground font-medium" : ""}>
              {seg.charAt(0).toUpperCase() + seg.slice(1)}
            </span>
          </span>
        ))}
      </nav>
      <div className="ml-auto flex items-center gap-3">
        <div className="relative hidden sm:block">
          <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input placeholder="Buscar..." className="h-8 w-48 pl-8 text-xs" />
        </div>
        <Bell className="h-4 w-4 text-muted-foreground cursor-pointer" />
        <Avatar className="h-7 w-7">
          <AvatarFallback className="bg-primary/10 text-primary text-[10px] font-semibold">{initials}</AvatarFallback>
        </Avatar>
      </div>
    </header>
  );
}
