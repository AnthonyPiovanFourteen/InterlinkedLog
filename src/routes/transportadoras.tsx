import { createFileRoute } from "@tanstack/react-router";
import { Fragment, useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, ChevronDown, ChevronRight, Eye, FileUp, Plug, PlugZap, Trash2 } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { StatusBadge } from "@/shared/components/atoms/StatusBadge";
import { api } from "@/lib/api";
import { toast } from "sonner";

interface CarrierItem {
  id: string;
  name: string;
  cnpj: string;
  origin_city: string;
  origin_state: string;
  status: string;
  contact_name: string;
  contact_phone: string;
  contact_email: string;
  /** Gateway que atende esta transportadora, ou null se não há integração. */
  gateway: string | null;
  /** Se este tenant já configurou credencial para ela. */
  integrated: boolean;
}
interface GatewayInfo {
  name: string;
  required_secrets: string[];
}
interface CredentialItem {
  id: string;
  carrier_id: string;
  gateway: string;
  active: boolean;
  configured_secrets: string[];
}
interface FreightTableItem {
  id: string;
  carrier_id: string;
  name: string;
  start_date: string;
  end_date: string;
  status: string;
  routes_count?: number;
}

export const Route = createFileRoute("/transportadoras")({
  head: () => ({ meta: [{ title: "Transportadoras · InterlinkedLog" }] }),
  component: TransportadorasPage,
});

function TransportadorasPage() {
  const queryClient = useQueryClient();
  // Transportadora cuja credencial está sendo configurada, e os segredos
  // digitados. Os campos vêm do gateway — cada um exige chaves diferentes.
  const [integrating, setIntegrating] = useState<CarrierItem | null>(null);
  const [secrets, setSecrets] = useState<Record<string, string>>({});
  // Gateway escolhido à mão, quando o nome da transportadora não casa com
  // nenhum adaptador — uma empresa pode cadastrá-la com nome comercial próprio.
  const [pickedGateway, setPickedGateway] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["carriers"],
    queryFn: () => api.get<{ data: CarrierItem[] }>("/carriers").then((r) => r.data),
  });

  const { data: gatewaysData } = useQuery({
    queryKey: ["carrier-gateways"],
    queryFn: () =>
      api.get<{ data: GatewayInfo[] }>("/carrier-credentials/gateways").then((r) => r.data),
  });

  const { data: credentialsData } = useQuery({
    queryKey: ["carrier-credentials"],
    queryFn: () => api.get<{ data: CredentialItem[] }>("/carrier-credentials").then((r) => r.data),
  });

  const saveCredential = useMutation({
    mutationFn: (body: { carrier_id: string; gateway: string; secrets: Record<string, string> }) =>
      api.post("/carrier-credentials", body),
    onSuccess: () => {
      toast.success("Integração configurada");
      queryClient.invalidateQueries({ queryKey: ["carriers"] });
      queryClient.invalidateQueries({ queryKey: ["carrier-credentials"] });
      setIntegrating(null);
      setSecrets({});
      setPickedGateway("");
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const removeCredential = useMutation({
    mutationFn: (id: string) => api.delete(`/carrier-credentials/${id}`),
    onSuccess: () => {
      toast.success("Integração removida");
      queryClient.invalidateQueries({ queryKey: ["carriers"] });
      queryClient.invalidateQueries({ queryKey: ["carrier-credentials"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const { data: freightTables } = useQuery({
    queryKey: ["freight-tables"],
    queryFn: () => api.get<{ data: FreightTableItem[] }>("/freight-tables").then((r) => r.data),
  });

  const createMutation = useMutation({
    mutationFn: (body: Record<string, string>) => api.post("/carriers", body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["carriers"] });
      toast.success("Transportadora criada");
    },
    onError: (err: Error) => toast.error(err.message),
  });

  const [open, setOpen] = useState(false);
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [form, setForm] = useState({
    name: "",
    cnpj: "",
    origin_city: "",
    origin_state: "",
    contact_name: "",
    contact_phone: "",
  });

  const toggleExpand = (id: string) => setExpandedId((prev) => (prev === id ? null : id));

  const freightByCarrier = (carrierId: string) =>
    (freightTables ?? []).filter((ft) => ft.carrier_id === carrierId);

  return (
    <div className="space-y-5">
      <PageHeader
        title="Transportadoras"
        subtitle="Gestão das transportadoras parceiras e suas tabelas de frete."
        actions={
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
              <Button>
                <Plus className="mr-1 h-4 w-4" /> Nova
              </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
              <DialogHeader>
                <DialogTitle>Nova Transportadora</DialogTitle>
                <DialogDescription>Cadastre uma transportadora parceira.</DialogDescription>
              </DialogHeader>
              <div className="space-y-3 py-2">
                <div className="space-y-1.5">
                  <Label>Nome</Label>
                  <Input
                    value={form.name}
                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>CNPJ</Label>
                  <Input
                    value={form.cnpj}
                    onChange={(e) => setForm({ ...form, cnpj: e.target.value })}
                  />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <Label>Cidade Origem</Label>
                    <Input
                      value={form.origin_city}
                      onChange={(e) => setForm({ ...form, origin_city: e.target.value })}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>Estado (UF)</Label>
                    <Input
                      value={form.origin_state}
                      maxLength={2}
                      onChange={(e) => setForm({ ...form, origin_state: e.target.value })}
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <Label>Nome do Contato</Label>
                    <Input
                      value={form.contact_name}
                      onChange={(e) => setForm({ ...form, contact_name: e.target.value })}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>Telefone</Label>
                    <Input
                      value={form.contact_phone}
                      onChange={(e) => setForm({ ...form, contact_phone: e.target.value })}
                    />
                  </div>
                </div>
                <div className="space-y-1.5 border rounded-md p-3 bg-muted/30">
                  <Label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <FileUp className="h-3.5 w-3.5" /> Upload de Tabela (opcional)
                  </Label>
                  <Input type="file" accept=".xlsx,.xls" className="text-xs" />
                  <p className="text-[11px] text-muted-foreground">
                    Envie uma planilha .xlsx com a tabela de frete da transportadora.
                  </p>
                </div>
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setOpen(false)}>
                  Cancelar
                </Button>
                <Button
                  onClick={() => {
                    createMutation.mutate(form);
                    setOpen(false);
                    setForm({
                      name: "",
                      cnpj: "",
                      origin_city: "",
                      origin_state: "",
                      contact_name: "",
                      contact_phone: "",
                    });
                  }}
                >
                  Salvar
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        }
      />
      <Card className="border-border/70 shadow-none">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="w-8" />
                <TableHead>Nome</TableHead>
                <TableHead>CNPJ</TableHead>
                <TableHead>Cidade origem</TableHead>
                <TableHead>Contato</TableHead>
                <TableHead>Integração</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                    Carregando...
                  </TableCell>
                </TableRow>
              ) : (
                (data ?? []).map((t) => {
                  const tables = freightByCarrier(t.id);
                  const isExpanded = expandedId === t.id;
                  return (
                    <Fragment key={t.id}>
                      <TableRow
                        className="cursor-pointer hover:bg-muted/50"
                        onClick={() => toggleExpand(t.id)}
                      >
                        <TableCell className="w-8">
                          {isExpanded ? (
                            <ChevronDown className="h-4 w-4 text-muted-foreground" />
                          ) : (
                            <ChevronRight className="h-4 w-4 text-muted-foreground" />
                          )}
                        </TableCell>
                        <TableCell className="font-medium">{t.name}</TableCell>
                        <TableCell className="text-muted-foreground tabular-nums">
                          {t.cnpj}
                        </TableCell>
                        <TableCell>
                          {t.origin_city}, {t.origin_state}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-xs">
                          {t.contact_name}
                          {t.contact_phone ? ` · ${t.contact_phone}` : ""}
                        </TableCell>
                        <TableCell>
                          {/* Integração é opcional e por transportadora: dá para
                              integrar só a Rodonaves e deixar as demais na tabela. */}
                          {t.integrated ? (
                            <div className="flex items-center gap-1.5">
                              <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                <PlugZap className="h-3.5 w-3.5" /> Integrada
                              </span>
                              <Button
                                variant="ghost"
                                size="icon"
                                className="h-6 w-6"
                                title="Remover integração"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  const cred = credentialsData?.find((c) => c.carrier_id === t.id);
                                  if (cred) removeCredential.mutate(cred.id);
                                }}
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </Button>
                            </div>
                          ) : (
                            <Button
                              variant="outline"
                              size="sm"
                              className="h-7 text-xs"
                              onClick={(e) => {
                                e.stopPropagation();
                                setSecrets({});
                                setPickedGateway(t.gateway ?? "");
                                setIntegrating(t);
                              }}
                            >
                              <Plug className="mr-1 h-3.5 w-3.5" />
                              {t.gateway ? "Conectar" : "Conectar API"}
                            </Button>
                          )}
                        </TableCell>
                        <TableCell>
                          <StatusBadge status={t.status} />
                        </TableCell>
                      </TableRow>
                      {isExpanded && (
                        <TableRow className="hover:bg-transparent bg-muted/20">
                          <TableCell colSpan={7} className="p-0">
                            <div className="px-6 py-3">
                              <p className="text-xs font-semibold text-muted-foreground mb-2 uppercase tracking-wide">
                                Tabelas de Frete
                              </p>
                              {tables.length === 0 ? (
                                <p className="text-xs text-muted-foreground py-2">
                                  Nenhuma tabela cadastrada.
                                </p>
                              ) : (
                                <table className="w-full text-sm">
                                  <thead>
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                      <th className="py-1.5 pr-3 font-medium">Nome Tabela</th>
                                      <th className="py-1.5 pr-3 font-medium">Vigência</th>
                                      <th className="py-1.5 pr-3 font-medium">Rotas</th>
                                      <th className="py-1.5 pr-3 font-medium">Status</th>
                                      <th className="py-1.5 font-medium">Ações</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {tables.map((ft) => (
                                      <tr key={ft.id} className="border-b last:border-0">
                                        <td className="py-2 pr-3 font-medium">{ft.name}</td>
                                        <td className="py-2 pr-3 text-muted-foreground text-xs">
                                          {ft.start_date} a {ft.end_date}
                                        </td>
                                        <td className="py-2 pr-3 tabular-nums">
                                          {ft.routes_count ?? "-"}
                                        </td>
                                        <td className="py-2 pr-3">
                                          <StatusBadge status={ft.status} />
                                        </td>
                                        <td className="py-2">
                                          <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-7 text-xs"
                                            asChild
                                          >
                                            <a href={`/tabelas/${ft.id}`}>
                                              <Eye className="mr-1 h-3.5 w-3.5" /> Ver
                                            </a>
                                          </Button>
                                        </td>
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      )}
                    </Fragment>
                  );
                })
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      {/* Os campos vêm do gateway: cada API exige segredos diferentes, e o
          backend valida contra a mesma lista. */}
      <Dialog
        open={integrating !== null}
        onOpenChange={(o) => {
          if (!o) {
            setIntegrating(null);
            setSecrets({});
            setPickedGateway("");
          }
        }}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Conectar {integrating?.name}</DialogTitle>
            <DialogDescription>
              Informe as credenciais de API fornecidas pela transportadora. Elas ficam
              criptografadas e nunca são exibidas depois de salvas.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-3 py-2">
            {!integrating?.gateway && (
              <div className="space-y-1.5">
                <Label>Qual API esta transportadora usa?</Label>
                <select
                  className="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                  value={pickedGateway}
                  onChange={(e) => {
                    setPickedGateway(e.target.value);
                    setSecrets({});
                  }}
                >
                  <option value="">Selecione...</option>
                  {(gatewaysData ?? []).map((g) => (
                    <option key={g.name} value={g.name}>
                      {g.name}
                    </option>
                  ))}
                </select>
                <p className="text-muted-foreground text-xs">
                  Não identificamos a API pelo nome cadastrado. Escolha qual delas a transportadora
                  usa.
                </p>
              </div>
            )}
            {(
              gatewaysData?.find((g) => g.name === (integrating?.gateway ?? pickedGateway))
                ?.required_secrets ?? []
            ).map((key) => (
              <div key={key} className="space-y-1.5">
                <Label className="capitalize">{key.replace(/_/g, " ")}</Label>
                <Input
                  type={key.includes("password") || key.includes("secret") ? "password" : "text"}
                  value={secrets[key] ?? ""}
                  autoComplete="off"
                  onChange={(e) => setSecrets({ ...secrets, [key]: e.target.value })}
                />
              </div>
            ))}
          </div>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => {
                setIntegrating(null);
                setSecrets({});
              }}
            >
              Cancelar
            </Button>
            <Button
              disabled={saveCredential.isPending || !(integrating?.gateway ?? pickedGateway)}
              onClick={() => {
                const gateway = integrating?.gateway ?? pickedGateway;
                if (!integrating || !gateway) return;
                saveCredential.mutate({ carrier_id: integrating.id, gateway, secrets });
              }}
            >
              {saveCredential.isPending ? "Salvando..." : "Conectar"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
