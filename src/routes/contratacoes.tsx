import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { FileDown, Truck, Barcode } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Separator } from "@/components/ui/separator";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { StatusBadge } from "@/shared/components/atoms/StatusBadge";
import { api } from "@/lib/api";
import { fmtCurr } from "@/lib/utils";
import { toast } from "sonner";

interface ContractItem {
  id: string; nf_number: string; carrier_name: string;
  final_value: number; status: string; document_number: string;
  cte_number: string | null;
  origin_city: string; destination_city: string; deadline: number;
  created_at: string;
}

export const Route = createFileRoute("/contratacoes")({
  head: () => ({ meta: [{ title: "Contratações · InterlinkedLog" }] }),
  component: ContratacoesPage,
});

function ContratacoesPage() {
  const [selected, setSelected] = useState<ContractItem | null>(null);
  const [cteInput, setCteInput] = useState("");
  const queryClient = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ["contracts"],
    queryFn: () => api.get<{ data: ContractItem[] }>("/contracts").then((r) => r.data),
  });

  const cancelMutation = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) => api.post(`/contracts/${id}/cancel`, { reason }),
    onSuccess: () => {
      setSelected(null);
      queryClient.invalidateQueries({ queryKey: ["contracts"] });
      toast.success("Cancelado");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const cteMutation = useMutation({
    mutationFn: ({ id, cte_number }: { id: string; cte_number: string }) =>
      api.patch(`/contracts/${id}/cte`, { cte_number }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["contracts"] });
      setCteInput("");
      toast.success("CT-e registrado");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const handleDownload = async (contractId: string) => {
    const token = localStorage.getItem("token");
    const res = await fetch(`/api/v1/contracts/${contractId}/pdf`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!res.ok) return toast.error("Erro ao gerar PDF");
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `solicitacao-coleta-${contractId.slice(0, 8)}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  };

  return (
    <div className="space-y-5">
      <PageHeader title="Contratações" subtitle="Fretes contratados a partir das cotações aprovadas." />
      <Card className="border-border/70 shadow-none">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>NF</TableHead>
                <TableHead>Transportadora</TableHead>
                <TableHead>Origem → Destino</TableHead>
                <TableHead className="text-right">Valor</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>CT-e</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow><TableCell colSpan={7} className="text-center py-8 text-muted-foreground">Carregando...</TableCell></TableRow>
              ) : (data ?? []).map((c) => (
                <TableRow key={c.id} className="cursor-pointer" onClick={() => { setCteInput(""); setSelected(c); }}>
                  <TableCell className="font-medium">{c.nf_number}</TableCell>
                  <TableCell>{c.carrier_name}</TableCell>
                  <TableCell className="text-muted-foreground text-xs">{c.origin_city} → {c.destination_city}</TableCell>
                  <TableCell className="text-right font-medium tabular-nums">{fmtCurr(c.final_value)}</TableCell>
                  <TableCell><StatusBadge status={c.status} /></TableCell>
                  <TableCell>
                    {c.cte_number ? (
                      <Badge variant="outline" className="text-[10px]">{c.cte_number}</Badge>
                    ) : (
                      <span className="text-[10px] text-muted-foreground italic">Aguardando</span>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      <Sheet open={!!selected} onOpenChange={(o) => !o && setSelected(null)}>
        <SheetContent className="w-full sm:max-w-xl p-0">
          {selected && (
            <>
              <SheetHeader className="border-b border-border px-6 py-4 space-y-1">
                <SheetTitle className="text-lg">Contrato {selected.id.slice(0,8)}</SheetTitle>
                <SheetDescription>NF {selected.nf_number} · {selected.carrier_name}</SheetDescription>
              </SheetHeader>
              <div className="px-6 py-5 space-y-6 overflow-y-auto h-[calc(100vh-130px)]">
                <section>
                  <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Dados da contratação</h4>
                  <dl className="grid grid-cols-2 gap-y-2.5 gap-x-4 text-sm">
                    <dt className="text-muted-foreground">Transportadora</dt><dd className="font-medium flex items-center gap-1.5"><Truck className="h-3.5 w-3.5" />{selected.carrier_name}</dd>
                    <dt className="text-muted-foreground">Origem</dt><dd>{selected.origin_city}</dd>
                    <dt className="text-muted-foreground">Destino</dt><dd>{selected.destination_city}</dd>
                    <dt className="text-muted-foreground">Valor</dt><dd className="font-semibold tabular-nums">{fmtCurr(selected.final_value)}</dd>
                    <dt className="text-muted-foreground">Prazo</dt><dd>{selected.deadline} dias</dd>
                    <dt className="text-muted-foreground">Status</dt><dd><StatusBadge status={selected.status} /></dd>
                  </dl>
                </section>
                <Separator />
                <section>
                  <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">CT-e</h4>
                  {selected.cte_number ? (
                    <div className="flex items-center gap-2 rounded-md border border-border bg-muted/40 px-3 py-2.5">
                      <Barcode className="h-4 w-4 text-primary" />
                      <span className="font-medium text-sm">{selected.cte_number}</span>
                    </div>
                  ) : (
                    <form onSubmit={(e) => { e.preventDefault(); if (cteInput) cteMutation.mutate({ id: selected.id, cte_number: cteInput }); }} className="space-y-2">
                      <p className="text-xs text-muted-foreground">Aguardando a transportadora informar o número do CT-e.</p>
                      <div className="flex gap-2">
                        <Input
                          placeholder="Nº do CT-e"
                          value={cteInput}
                          onChange={(e) => setCteInput(e.target.value)}
                          className="h-9"
                        />
                        <Button type="submit" size="sm" disabled={cteMutation.isPending || !cteInput}>
                          Registrar
                        </Button>
                      </div>
                    </form>
                  )}
                </section>
                <Separator />
                <section>
                  <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Documento</h4>
                  <div className="flex items-center justify-between rounded-md border border-border bg-muted/40 px-3 py-2.5">
                    <div className="flex items-center gap-2 text-sm">
                      <FileDown className="h-4 w-4 text-primary" />
                      <span className="font-medium">{selected.document_number}.pdf</span>
                    </div>
                    <Button size="sm" variant="outline" onClick={() => handleDownload(selected.id)}>
                      Baixar
                    </Button>
                  </div>
                </section>
                <Separator />
                <section>
                  <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Cancelar</h4>
                  <form onSubmit={(e) => { e.preventDefault(); const reason = (e.currentTarget.elements.namedItem("reason") as HTMLInputElement)?.value; if (reason) cancelMutation.mutate({ id: selected.id, reason }); }}>
                    <div className="flex gap-2">
                      <Input name="reason" placeholder="Motivo do cancelamento" className="h-9" required />
                      <Button type="submit" variant="destructive" size="sm" disabled={cancelMutation.isPending}>Cancelar</Button>
                    </div>
                  </form>
                </section>
              </div>
            </>
          )}
        </SheetContent>
      </Sheet>
    </div>
  );
}
