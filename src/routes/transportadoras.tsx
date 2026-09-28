import { createFileRoute } from "@tanstack/react-router";
import { Fragment, useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  Plus,
  ChevronDown,
  ChevronRight,
  Eye,
  FileUp,
  Plug,
  PlugZap,
  Trash2,
  Info,
  ExternalLink,
} from "lucide-react";
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
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
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
  /** O que cada segredo é — vem do adaptador, não do frontend. */
  secret_hints: Record<string, string>;
  /** Documentação oficial da API, quando a transportadora publica. */
  documentation_url: string | null;
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

/**
 * O que a tabela de frete precisa conter. Descreve o contrato real aceito por
 * POST /freight-tables — origem, rotas com prazo, faixas de peso e taxas.
 */
function FreightTableHelp() {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label="O que a tabela de frete precisa conter"
          className="text-muted-foreground hover:text-foreground transition-colors"
        >
          <Info className="h-4 w-4" />
        </button>
      </PopoverTrigger>
      <PopoverContent side="right" align="start" className="max-h-[70vh] w-96 overflow-y-auto">
        <div className="space-y-3 text-xs leading-relaxed">
          <div>
            <p className="text-sm font-semibold">Como deve ser a tabela de frete</p>
            <p className="text-muted-foreground mt-1">
              Transportadora sem API cota por tabela. Depois de cadastrá-la aqui, envie a tabela que
              ela negociou com você — é dela que sai o preço.
            </p>
          </div>

          <div>
            <p className="font-medium">1. Identificação e validade</p>
            <p className="text-muted-foreground">
              Nome da tabela, cidade de origem, e o período em que vale (início e fim). Fora desse
              período a tabela não é usada na cotação.
            </p>
          </div>

          <div>
            <p className="font-medium">2. Rotas atendidas</p>
            <p className="text-muted-foreground">
              Cidade e UF de destino, com o prazo em dias. A transportadora só aparece na cotação se
              a rota estiver listada — destino ausente significa &quot;não atende&quot;.
            </p>
          </div>

          <div>
            <p className="font-medium">3. Faixas de peso</p>
            <p className="text-muted-foreground">
              Peso inicial, peso final e o valor do frete. As faixas são degraus contínuos:
            </p>
            <pre className="bg-muted mt-1.5 rounded p-2 font-mono text-[11px] leading-snug">
              {`0 – 30 kg     R$ 145,00
31 – 100 kg   R$ 260,00
101 – 300 kg  R$ 480,00`}
            </pre>
          </div>

          <div>
            <p className="font-medium">4. Taxas</p>
            <p className="text-muted-foreground">
              Cada taxa tem um tipo e um valor — em reais, ou em percentual sobre o valor da
              mercadoria. Os tipos usuais:
            </p>
            <ul className="text-muted-foreground mt-1.5 space-y-0.5">
              <li>
                <span className="text-foreground font-medium">ad_valorem</span> — percentual sobre a
                nota
              </li>
              <li>
                <span className="text-foreground font-medium">gris</span> — gerenciamento de risco
              </li>
              <li>
                <span className="text-foreground font-medium">pedagio</span> — percentual ou valor
                fixo
              </li>
              <li>
                <span className="text-foreground font-medium">despacho</span>,{" "}
                <span className="text-foreground font-medium">tde</span> — valores fixos
              </li>
              <li>
                <span className="text-foreground font-medium">frete_minimo</span> — piso do frete
              </li>
            </ul>
          </div>

          <p className="text-muted-foreground border-t pt-2">
            Há tabelas de exemplo prontas em <code className="text-foreground">examples/</code>, em
            JSON e XLSX, com a estrutura completa.
          </p>
        </div>
      </PopoverContent>
    </Popover>
  );
}

function TransportadorasPage() {
  const queryClient = useQueryClient();
  // Transportadora cuja credencial está sendo configurada, e os segredos
  // digitados. Os campos vêm do gateway — cada um exige chaves diferentes.
  const [integrating, setIntegrating] = useState<CarrierItem | null>(null);
  const [secrets, setSecrets] = useState<Record<string, string>>({});

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

  // A explicação de cada campo vem do adaptador, via /carrier-credentials/gateways.
  const gatewayInfo = () => gatewaysData?.find((g) => g.name === integrating?.gateway);
  const hintFor = (key: string) => gatewayInfo()?.secret_hints?.[key] ?? "";

  const saveCredential = useMutation({
    mutationFn: (body: { carrier_id: string; gateway: string; secrets: Record<string, string> }) =>
      api.post("/carrier-credentials", body),
    onSuccess: () => {
      toast.success("Integração configurada");
      queryClient.invalidateQueries({ queryKey: ["carriers"] });
      queryClient.invalidateQueries({ queryKey: ["carrier-credentials"] });
      setIntegrating(null);
      setSecrets({});
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
                <DialogTitle className="flex items-center gap-2">
                  Nova Transportadora
                  <FreightTableHelp />
                </DialogTitle>
                <DialogDescription>
                  Cadastre uma transportadora parceira. Sem integração de API, a cotação dela vem da
                  tabela de frete que você enviar.
                </DialogDescription>
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
                          {!t.gateway ? (
                            <span className="text-muted-foreground text-xs">Por tabela</span>
                          ) : t.integrated ? (
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
                                setIntegrating(t);
                              }}
                            >
                              <Plug className="mr-1 h-3.5 w-3.5" /> Conectar
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
          }
        }}
      >
        <DialogContent className="sm:max-w-md">
          <TooltipProvider delayDuration={150}>
            <DialogHeader>
              <DialogTitle>Conectar {integrating?.name}</DialogTitle>
              <DialogDescription>
                Informe as credenciais de API fornecidas pela transportadora. Elas ficam
                criptografadas e nunca são exibidas depois de salvas.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-3 py-2">
              {(gatewayInfo()?.required_secrets ?? []).map((key) => (
                <div key={key} className="space-y-1.5">
                  <div className="flex items-center gap-1.5">
                    <Label className="capitalize">{key.replace(/_/g, " ")}</Label>
                    {hintFor(key) && (
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <button
                            type="button"
                            aria-label={`O que é ${key.replace(/_/g, " ")}`}
                            className="text-muted-foreground hover:text-foreground transition-colors"
                          >
                            <Info className="h-3.5 w-3.5" />
                          </button>
                        </TooltipTrigger>
                        <TooltipContent side="right" className="max-w-xs text-xs leading-relaxed">
                          {hintFor(key)}
                        </TooltipContent>
                      </Tooltip>
                    )}
                  </div>
                  <Input
                    type={key.includes("password") || key.includes("secret") ? "password" : "text"}
                    value={secrets[key] ?? ""}
                    autoComplete="off"
                    onChange={(e) => setSecrets({ ...secrets, [key]: e.target.value })}
                  />
                  {hintFor(key) && (
                    <p className="text-muted-foreground text-xs leading-relaxed">{hintFor(key)}</p>
                  )}
                </div>
              ))}
            </div>
            {gatewayInfo()?.documentation_url && (
              <p className="text-muted-foreground border-t pt-3 text-xs">
                Para mais instruções, consulte a{" "}
                <a
                  href={gatewayInfo()!.documentation_url!}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-foreground font-medium underline underline-offset-2"
                >
                  documentação oficial da API
                  <ExternalLink className="ml-0.5 inline h-3 w-3 align-[-1px]" />
                </a>
                .
              </p>
            )}
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
                disabled={saveCredential.isPending}
                onClick={() => {
                  if (!integrating?.gateway) return;
                  saveCredential.mutate({
                    carrier_id: integrating.id,
                    gateway: integrating.gateway,
                    secrets,
                  });
                }}
              >
                {saveCredential.isPending ? "Salvando..." : "Conectar"}
              </Button>
            </DialogFooter>
          </TooltipProvider>
        </DialogContent>
      </Dialog>
    </div>
  );
}
