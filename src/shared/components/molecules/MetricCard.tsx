import { cn } from "@/lib/utils";
import { Card, CardContent } from "@/components/ui/card";
import { TrendingUp, TrendingDown } from "lucide-react";
import type { LucideIcon } from "lucide-react";

interface MetricCardProps {
  label: string;
  value: string;
  delta?: { value: string; positive?: boolean };
  icon?: LucideIcon;
  hint?: string;
  className?: string;
}

export function MetricCard({ label, value, delta, icon: Icon, hint, className }: MetricCardProps) {
  return (
    <Card className={cn("border-border/70 shadow-none", className)}>
      <CardContent className="p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-1.5">
            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
              {label}
            </p>
            <p className="text-2xl font-semibold tracking-tight text-foreground">{value}</p>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
          </div>
          {Icon && (
            <div className="flex h-9 w-9 items-center justify-center rounded-md bg-primary/10 text-primary">
              <Icon className="h-4 w-4" />
            </div>
          )}
        </div>
        {delta && (
          <div
            className={cn(
              "mt-3 inline-flex items-center gap-1 text-xs font-medium",
              delta.positive ? "text-success" : "text-destructive",
            )}
          >
            {delta.positive ? (
              <TrendingUp className="h-3 w-3" />
            ) : (
              <TrendingDown className="h-3 w-3" />
            )}
            {delta.value}
            <span className="text-muted-foreground font-normal">vs mês anterior</span>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
