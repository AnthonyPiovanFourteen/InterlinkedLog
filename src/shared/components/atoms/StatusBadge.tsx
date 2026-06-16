import { cn } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";

type Tone = "success" | "warning" | "destructive" | "info" | "muted" | "primary";

const tones: Record<Tone, string> = {
  success: "bg-success/12 text-success border-success/25",
  warning: "bg-warning/15 text-warning-foreground border-warning/35",
  destructive: "bg-destructive/10 text-destructive border-destructive/25",
  info: "bg-info/12 text-info border-info/25",
  muted: "bg-muted text-muted-foreground border-border",
  primary: "bg-primary/10 text-primary border-primary/25",
};

const statusMap: Record<string, Tone> = {
  Ativo: "success",
  Ativa: "success",
  Entregue: "success",
  Contratada: "success",
  CONTRATADA: "success",
  Pago: "success",
  Pendente: "warning",
  VALIDA: "info",
  "Válida": "info",
  "Em Trânsito": "info",
  Coletado: "info",
  Agendado: "muted",
  Cancelada: "destructive",
  CANCELADA: "destructive",
  Cancelado: "destructive",
  Bloqueado: "destructive",
  Vencida: "destructive",
  Expirada: "destructive",
  EXPIRADA: "destructive",
  Inativa: "muted",
  Inativo: "muted",
  Trial: "info",
  Rascunho: "muted",
  RASCUNHO: "muted",
  "Saiu para Entrega": "info",
  "Unidade de Distribuição": "info",
  "Coleta Agendada": "muted",
  Starter: "info",
  Pro: "primary",
  Enterprise: "success",
  Error: "destructive",
  Warning: "warning",
  Info: "info",
};

export function StatusBadge({ status }: { status: string }) {
  const tone = statusMap[status] ?? "muted";
  return (
    <Badge
      variant="outline"
      className={cn("font-medium border px-2 py-0.5 text-[11px]", tones[tone])}
    >
      <span className={cn("mr-1.5 inline-block h-1.5 w-1.5 rounded-full",
        tone === "success" && "bg-success",
        tone === "warning" && "bg-warning",
        tone === "destructive" && "bg-destructive",
        tone === "info" && "bg-info",
        tone === "muted" && "bg-muted-foreground/60",
        tone === "primary" && "bg-primary",
      )} />
      {status}
    </Badge>
  );
}
