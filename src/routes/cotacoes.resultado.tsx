import { createFileRoute, Link } from "@tanstack/react-router";
import { Trophy, Zap, Sparkles } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { StatusBadge } from "@/shared/components/atoms/StatusBadge";
import { api } from "@/lib/api";
import { cn, fmtCurr } from "@/lib/utils";
import { useMutation } from "@tanstack/react-query";
import { toast } from "sonner";

interface ResultItem {
  carrier_id: string;
  carrier_name: string;
  deadline: number;
  freight_value: number;
  fees: number;
  final_value: number;
  fees_breakdown: { type: string; amount: number }[];
  best_price?: boolean;
  best_deadline?: boolean;
  best_cost_benefit?: boolean;
}

interface QuotationDetail {
  id: string;
  nf_number: string;
  origin_city: string;
  destination_city: string;
  destination_state: string;
  weight: number;
  cargo_value: number;
  status: string;
  valid_until: string;
  results: ResultItem[];
}

export const Route = createFileRoute("/cotacoes/resultado")({
  head: () => ({ meta: [{ title: "Resultado · InterlinkedLog" }] }),
  component: Resultado,
  validateSearch: (s: Record<string, unknown>) => ({ id: s.id as string }),
});

function Resultado() {
  const { id } = Route.useSearch();

  const { data, isLoading } = useQuery({
    queryKey: ["quotation", id],
    queryFn: () => api.get<{ data: QuotationDetail }>(`/quotations/${id}`).then((r) => r.data),
    enabled: !!id,
  });

  const contractMutation = useMutation({
    mutationFn: (carrier_id: string) => api.post("/contracts", { quotation_id: id, carrier_id }),
    onSuccess: () => toast.success("Contratação realizada!"),
    onError: (err: Error) => toast.error(err.message),
  });

  if (isLoading) return <div className="py-12 text-center text-muted-foreground">Carregando resultados...</div>;
  if (!data) return <div className="py-12 text-center text-muted-foreground">Cotação não encontrada.</div>;

  const isContractable = data.status === "VALIDA";
  const statusLabel: Record<string, string> = {
    VALIDA: "Válida", CONTRATADA: "Contratada", CANCELADA: "Cancelada",
    EXPIRADA: "Expirada", RASCUNHO: "Rascunho",
  };

  const resultados = data.results ?? [];
  const melhorPreco = [...resultados].sort((a, b) => a.final_value - b.final_value)[0];
  const melhorPrazo = [...resultados].sort((a, b) => a.deadline - b.deadline)[0];
  const melhorCB = [...resultados].sort((a, b) => (a.final_value / Math.max(1, a.deadline)) - (b.final_value / Math.max(1, b.deadline)))[0];

  const resultadosComFlag = resultados.map((r) => ({
    ...r,
    best_price: r.carrier_id === melhorPreco?.carrier_id,
    best_deadline: r.carrier_id === melhorPrazo?.carrier_id,
    best_cost_benefit: r.carrier_id === melhorCB?.carrier_id,
  }));

  return (
    <div className="space-y-5">
      <PageHeader
        title="Resultado da Cotação"
        subtitle={`NF ${data.nf_number} · ${data.origin_city} → ${data.destination_city}, ${data.destination_state} · ${data.weight}kg · ${fmtCurr(data.cargo_value)}`}
        actions={
          <div className="flex items-center gap-2">
            {!isContractable && <StatusBadge status={statusLabel[data.status] ?? data.status} />}
            <Button variant="outline" asChild><Link to="/cotacoes">Voltar</Link></Button>
          </div>
        }
      />

      {!isContractable && (
        <div className="rounded-md bg-muted/50 border border-border px-4 py-3 text-sm text-muted-foreground">
          Esta cotação está <span className="font-medium text-foreground">{statusLabel[data.status] ?? data.status}</span> e não aceita novas contratações.
        </div>
      )}

      <div className="grid gap-3 sm:grid-cols-3">
        <HighlightCard icon={Trophy} title="Melhor preço" value={melhorPreco?.carrier_name ?? "—"} hint={melhorPreco ? fmtCurr(melhorPreco.final_value) : ""} tone="success" />
        <HighlightCard icon={Zap} title="Melhor prazo" value={melhorPrazo?.carrier_name ?? "—"} hint={melhorPrazo ? `${melhorPrazo.deadline} dias úteis` : ""} tone="info" />
        <HighlightCard icon={Sparkles} title="Melhor custo-benefício" value={melhorCB?.carrier_name ?? "—"} hint={melhorCB ? `${fmtCurr(melhorCB.final_value)} · ${melhorCB.deadline}d` : ""} tone="primary" />
      </div>

      <Card className="border-border/70 shadow-none">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>Transportadora</TableHead>
                <TableHead>Prazo</TableHead>
                <TableHead className="text-right">Frete</TableHead>
                <TableHead className="text-right">Taxas</TableHead>
                <TableHead className="text-right">Valor Final</TableHead>
                <TableHead className="text-right">Ação</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {resultadosComFlag.map((r) => (
                <TableRow key={r.carrier_id} className={cn(r.best_price && "bg-success/5")}>
                  <TableCell className="font-medium flex items-center gap-2">
                    {r.carrier_name}
                    {r.best_price && <Badge variant="outline" className="border-success/30 bg-success/10 text-success text-[10px]">Melhor preço</Badge>}
                    {r.best_deadline && !r.best_price && <Badge variant="outline" className="border-info/30 bg-info/10 text-info text-[10px]">Melhor prazo</Badge>}
                  </TableCell>
                  <TableCell>{r.deadline} dias</TableCell>
                  <TableCell className="text-right tabular-nums">{fmtCurr(r.freight_value)}</TableCell>
                  <TableCell className="text-right tabular-nums">{fmtCurr(r.fees)}</TableCell>
                  <TableCell className="text-right font-semibold tabular-nums">{fmtCurr(r.final_value)}</TableCell>
                  <TableCell className="text-right">
                    {isContractable ? (
                      <Button size="sm" variant={r.best_price ? "default" : "outline"} onClick={() => contractMutation.mutate(r.carrier_id)} disabled={contractMutation.isPending}>
                        Selecionar
                      </Button>
                    ) : (
                      <span className="text-xs text-muted-foreground">—</span>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

function HighlightCard({ icon: Icon, title, value, hint, tone }: { icon: React.ComponentType<{ className?: string }>; title: string; value: string; hint: string; tone: "success" | "info" | "primary" }) {
  const tones = { success: "border-success/30 bg-success/5 text-success", info: "border-info/30 bg-info/5 text-info", primary: "border-primary/30 bg-primary/5 text-primary" };
  return (
    <Card className={cn("shadow-none", tones[tone])}>
      <CardContent className="p-4 flex items-center gap-3">
        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-background"><Icon className="h-5 w-5" /></div>
        <div>
          <p className="text-[11px] uppercase tracking-wider font-medium">{title}</p>
          <p className="text-base font-semibold text-foreground">{value}</p>
          <p className="text-xs text-muted-foreground">{hint}</p>
        </div>
      </CardContent>
    </Card>
  );
}
