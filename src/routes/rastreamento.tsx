import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useQuery, useMutation } from "@tanstack/react-query";
import { MapPin, Truck, CheckCircle2, Clock, Circle, Plus, AlertTriangle } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from "@/components/ui/sheet";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { StatusBadge } from "@/shared/components/atoms/StatusBadge";
import { api } from "@/lib/api";
import { cn } from "@/lib/utils";
import { toast } from "sonner";

interface TrackingEvent { id: string; title: string; date: string; time: string; observation: string; }
interface TrackedCargo { contract_id: string; nf_number: string; carrier_name: string; origin_city: string; destination_city: string; status: string; deadline: number; events: TrackingEvent[]; }

const PREDEFINED = ["Coleta Agendada","Coletado","Em Rota","Em Transferência","Chegou ao Destino","Saiu para Entrega","Entregue","Ocorrência"];

function computeDelivery(events: TrackingEvent[], deadline: number) {
  const coletado = events.find((e) => e.title.toLowerCase().includes("coletado"));
  const entregue = events.find((e) => e.title.toLowerCase().includes("entregue"));
  if (!coletado || !entregue) return null;

  const start = new Date(coletado.date + "T" + (coletado.time || "00:00"));
  const end = new Date(entregue.date + "T" + (entregue.time || "00:00"));
  const days = Math.max(1, Math.round((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)));
  const onTime = days <= deadline;

  return { days, deadline, onTime };
}

export const Route = createFileRoute("/rastreamento")({
  head: () => ({ meta: [{ title: "Rastreamento · InterlinkedLog" }] }),
  component: RastreamentoPage,
});

function RastreamentoPage() {
  const [selected, setSelected] = useState<TrackedCargo | null>(null);
  const { data, isLoading, refetch } = useQuery({
    queryKey: ["tracking"],
    queryFn: () => api.get<{ data: TrackedCargo[] }>("/tracking").then((r) => r.data),
  });

  return (
    <div className="space-y-5">
      <PageHeader title="Rastreamento" subtitle="Controle operacional. Acompanhe as cargas em trânsito e registre eventos." />
      <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {isLoading ? (
          <p className="text-muted-foreground text-sm col-span-full py-8 text-center">Carregando...</p>
        ) : (data ?? []).length === 0 ? (
          <p className="text-muted-foreground text-sm col-span-full py-8 text-center">Nenhuma carga em rastreamento.</p>
        ) : (
          (data ?? []).map((c) => {
            const delivery = computeDelivery(c.events, c.deadline);
            return (
              <Card key={c.contract_id} className="border-border/70 shadow-none cursor-pointer transition-colors hover:border-primary/40" onClick={() => setSelected(c)}>
                <CardHeader className="pb-2 flex flex-row items-start justify-between space-y-0">
                  <div>
                    <CardTitle className="text-sm font-semibold">NF {c.nf_number}</CardTitle>
                    <p className="text-xs text-muted-foreground mt-0.5">{c.carrier_name}</p>
                  </div>
                  <StatusBadge status={c.status} />
                </CardHeader>
                <CardContent className="text-xs space-y-2">
                  <div className="flex items-start gap-1.5">
                    <MapPin className="h-3.5 w-3.5 mt-0.5 text-primary" />
                    <div><p>{c.origin_city}</p><p className="text-muted-foreground">→ {c.destination_city}</p></div>
                  </div>
                  <div className="pt-1 text-muted-foreground border-t border-border">
                    Prazo: <span className="text-foreground font-medium">{c.deadline}d</span>
                    {delivery && (
                      <span className={cn("ml-2 text-[10px] font-medium inline-flex items-center gap-0.5", delivery.onTime ? "text-success" : "text-destructive")}>
                        {delivery.onTime ? (
                          <><CheckCircle2 className="h-3.5 w-3.5 inline text-success" /> {delivery.days}/{delivery.deadline}d</>
                        ) : (
                          <><AlertTriangle className="h-3.5 w-3.5 inline text-destructive" /> {delivery.days}/{delivery.deadline}d</>
                        )}
                      </span>
                    )}
                  </div>
                </CardContent>
              </Card>
            );
          })
        )}
      </div>
      <Sheet open={!!selected} onOpenChange={(o) => !o && setSelected(null)}>
        <SheetContent className="w-full sm:max-w-lg p-0">
          {selected && (
            <>
              <SheetHeader className="border-b border-border px-6 py-4 space-y-1">
                <SheetTitle className="flex items-center justify-between">
                  NF {selected.nf_number}
                  <AddEventButton contractId={selected.contract_id} onAdded={() => refetch()} />
                </SheetTitle>
                <SheetDescription>{selected.carrier_name} · {selected.origin_city} → {selected.destination_city}</SheetDescription>
              </SheetHeader>
              <div className="px-6 py-5 overflow-y-auto h-[calc(100vh-80px)]">
                {(() => {
                  const delivery = computeDelivery(selected.events, selected.deadline);
                  return delivery ? (
                    <div className={cn("rounded-md px-3 py-2 mb-5 text-sm", delivery.onTime ? "bg-success/10 border border-success/30" : "bg-destructive/10 border border-destructive/30")}>
                      {delivery.onTime ? (
                        <p className="text-success font-medium"><CheckCircle2 className="h-3.5 w-3.5 inline text-success" /> No prazo — realizado em {delivery.days} dias (prometido: {delivery.deadline} dias)</p>
                      ) : (
                        <p className="text-destructive font-medium"><AlertTriangle className="h-3.5 w-3.5 inline text-destructive" /> Atraso de {delivery.days - delivery.deadline} dias — realizado em {delivery.days} dias (prometido: {delivery.deadline} dias)</p>
                      )}
                    </div>
                  ) : (
                    <div className="rounded-md px-3 py-2 mb-5 text-sm bg-muted/50 border border-border">
                      <p className="text-muted-foreground">Prazo contratado: <span className="font-medium text-foreground">{selected.deadline} dias</span> — aguardando eventos de coleta e entrega.</p>
                    </div>
                  );
                })()}
                <ol className="relative space-y-5">
                  {selected.events.length === 0 ? (
                    <p className="text-sm text-muted-foreground py-4 text-center">Nenhum evento registrado ainda.</p>
                  ) : (
                    selected.events.map((e, i) => {
                      const isLast = i === selected.events.length - 1;
                      const isEntregue = e.title.toLowerCase().includes("entregue");
                      const isOcorrencia = e.title.toLowerCase().includes("ocorrência") || e.title.toLowerCase().includes("ocorrencia");
                      const isConcluded = isEntregue || isOcorrencia || !isLast;
                      const Icon2 = isOcorrencia ? AlertTriangle : isConcluded ? CheckCircle2 : Clock;
                      const colorClass = isOcorrencia ? "bg-destructive/15 border-destructive text-destructive" : isConcluded ? "bg-success/15 border-success text-success" : "bg-info/15 border-info text-info animate-pulse";
                      return (
                        <li key={e.id ?? i} className="relative pl-9">
                          {!isLast && <span className={cn("absolute left-[14px] top-7 bottom-[-22px] w-px", isOcorrencia ? "bg-destructive/60" : isConcluded ? "bg-success/60" : "bg-border")} />}
                          <span className={cn("absolute left-0 top-0 flex h-7 w-7 items-center justify-center rounded-full border-2", colorClass)}>
                            <Icon2 className="h-3.5 w-3.5" />
                          </span>
                          <div className="space-y-0.5">
                            <p className="text-sm font-medium">{e.title}</p>
                            <p className="text-xs text-muted-foreground tabular-nums">{e.date} · {e.time}</p>
                            {e.observation && <p className="text-xs text-muted-foreground">{e.observation}</p>}
                          </div>
                        </li>
                      );
                    })
                  )}
                </ol>
              </div>
            </>
          )}
        </SheetContent>
      </Sheet>
    </div>
  );
}

function AddEventButton({ contractId, onAdded }: { contractId: string; onAdded: () => void }) {
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState("");
  const [customTitle, setCustomTitle] = useState("");
  const [obs, setObs] = useState("");

  const mutation = useMutation({
    mutationFn: () => api.post(`/tracking/${contractId}/events`, {
      title: customTitle || title,
      date: new Date().toISOString().slice(0, 10),
      time: new Date().toTimeString().slice(0, 5),
      observation: obs,
    }),
    onSuccess: () => { setOpen(false); setTitle(""); setCustomTitle(""); setObs(""); onAdded(); toast.success("Evento adicionado"); },
    onError: (err: Error) => toast.error(err.message),
  });

  return (
    <>
      <Button size="sm" variant="outline" onClick={() => setOpen(true)}><Plus className="h-3 w-3 mr-1" /> Evento</Button>
      {open && (
        <div className="absolute right-6 top-14 z-50 bg-popover border border-border rounded-md shadow-lg p-3 w-72" onClick={(e) => e.stopPropagation()}>
          <label className="text-[10px] uppercase tracking-wider text-muted-foreground mb-1 block">Status (selecione ou digite)</label>
          <select
            className="w-full text-xs border border-border rounded p-1.5 mb-2 bg-background"
            value={title}
            onChange={(e) => { setTitle(e.target.value); setCustomTitle(""); }}
          >
            <option value="">Selecione um status...</option>
            {PREDEFINED.map((ev) => <option key={ev} value={ev}>{ev}</option>)}
          </select>
          <Input
            placeholder="Ou digite um status personalizado..."
            value={customTitle}
            onChange={(e) => { setCustomTitle(e.target.value); setTitle(""); }}
            className="h-7 text-xs mb-2"
          />
          <Input placeholder="Observação (opcional)" value={obs} onChange={(e) => setObs(e.target.value)} className="h-7 text-xs mb-3" />
          <div className="flex gap-1 justify-end">
            <Button size="sm" variant="ghost" onClick={() => setOpen(false)}>Cancelar</Button>
            <Button size="sm" disabled={(!title && !customTitle) || mutation.isPending} onClick={() => mutation.mutate()}>
              {mutation.isPending ? "..." : "Salvar"}
            </Button>
          </div>
        </div>
      )}
    </>
  );
}
