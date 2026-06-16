import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { Plus } from "lucide-react";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { StatusBadge } from "@/shared/components/atoms/StatusBadge";
import { api } from "@/lib/api";
import { fmtCurr } from "@/lib/utils";

interface QuotationItem {
  id: string;
  nf_number: string;
  destination_city: string;
  destination_state: string;
  weight: number;
  cargo_value: number;
  status: string;
  results_count: number;
  best_value: number | null;
  valid_until: string;
  created_at: string;
}

export const Route = createFileRoute("/cotacoes/")({
  head: () => ({ meta: [{ title: "Cotações · InterlinkedLog" }] }),
  component: CotacoesPage,
});

function CotacoesPage() {
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const navigate = useNavigate();

  const { data, isLoading } = useQuery({
    queryKey: ["quotations", status, search],
    queryFn: () => {
      const params = new URLSearchParams();
      if (status !== "all") params.set("status", status);
      if (search) params.set("search", search);
      const qs = params.toString();
      return api.get<{ data: QuotationItem[] }>(`/quotations${qs ? `?${qs}` : ""}`).then((r) => r.data);
    },
  });

  const statusMap: Record<string, string> = {
    RASCUNHO: "Rascunho", VALIDA: "Válida", EXPIRADA: "Expirada",
    CONTRATADA: "Contratada", CANCELADA: "Cancelada",
  };

  return (
    <div className="space-y-5">
      <PageHeader title="Cotações" subtitle="Lista de cotações realizadas." actions={
        <Button asChild><Link to="/cotacoes/nova"><Plus className="mr-1 h-4 w-4" />Nova Cotação</Link></Button>
      } />
      <Card className="border-border/70 shadow-none">
        <CardContent className="p-3 flex flex-wrap items-center gap-2">
          <Input placeholder="Buscar por NF, cidade..." value={search} onChange={(e) => setSearch(e.target.value)} className="h-9 w-72" />
          <Select value={status} onValueChange={setStatus}>
            <SelectTrigger className="h-9 w-44"><SelectValue placeholder="Status" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">Todos status</SelectItem>
              <SelectItem value="VALIDA">Válida</SelectItem>
              <SelectItem value="CONTRATADA">Contratada</SelectItem>
              <SelectItem value="CANCELADA">Cancelada</SelectItem>
              <SelectItem value="EXPIRADA">Expirada</SelectItem>
            </SelectContent>
          </Select>
        </CardContent>
      </Card>
      <Card className="border-border/70 shadow-none">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>NF</TableHead>
                <TableHead>Destino</TableHead>
                <TableHead className="text-right">Peso</TableHead>
                <TableHead className="text-right">Valor Merc.</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Melhor Valor</TableHead>
                <TableHead>Resultados</TableHead>
                <TableHead>Validade</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow><TableCell colSpan={8} className="text-center text-muted-foreground py-8">Carregando...</TableCell></TableRow>
              ) : (data ?? []).length === 0 ? (
                <TableRow><TableCell colSpan={8} className="text-center text-muted-foreground py-8">Nenhuma cotação encontrada</TableCell></TableRow>
              ) : (
                (data ?? []).map((c) => (
                  <TableRow key={c.id} className="cursor-pointer hover:bg-muted/50" onClick={() => navigate({ to: "/cotacoes/resultado", search: { id: c.id } })}>
                    <TableCell className="font-medium">{c.nf_number}</TableCell>
                    <TableCell className="text-muted-foreground">{c.destination_city}, {c.destination_state}</TableCell>
                    <TableCell className="text-right tabular-nums">{c.weight} kg</TableCell>
                    <TableCell className="text-right tabular-nums">{fmtCurr(c.cargo_value)}</TableCell>
                    <TableCell><StatusBadge status={statusMap[c.status] ?? c.status} /></TableCell>
                    <TableCell className="text-right font-medium tabular-nums">{c.best_value ? fmtCurr(c.best_value) : "—"}</TableCell>
                    <TableCell>{c.results_count} transp.</TableCell>
                    <TableCell className="text-muted-foreground tabular-nums">{c.valid_until}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
