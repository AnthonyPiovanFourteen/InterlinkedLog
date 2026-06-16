import { createFileRoute, Link, useNavigate } from "@tanstack/react-router";
import { Search, ArrowRight, Loader2, Upload, Check, AlertTriangle } from "lucide-react";
import { useState, useRef } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { useMutation } from "@tanstack/react-query";
import { api } from "@/lib/api";

export const Route = createFileRoute("/cotacoes/nova")({
  head: () => ({ meta: [{ title: "Nova Cotação · InterlinkedLog" }] }),
  component: NovaCotacao,
});

function Section({ title, hint, children }: { title: string; hint?: string; children: React.ReactNode }) {
  return (
    <Card className="border-border/70 shadow-none">
      <CardHeader className="pb-3">
        <CardTitle className="text-base font-semibold">{title}</CardTitle>
        {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
      </CardHeader>
      <CardContent>{children}</CardContent>
    </Card>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <div className="space-y-1.5"><Label className="text-xs font-medium text-muted-foreground">{label}</Label>{children}</div>;
}

function NovaCotacao() {
  const navigate = useNavigate();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [error, setError] = useState("");
  const [xmlStatus, setXmlStatus] = useState<"idle" | "success" | "error">("idle");
  const [xmlMessage, setXmlMessage] = useState("");

  const initialForm = {
    nf_number: "", sender_cnpj: "", receiver_cnpj: "",
    origin_cep: "", destination_cep: "",
    weight: "", boxes: "", volume: "", cargo_value: "",
  };
  const [form, setForm] = useState(initialForm);

  const parseMutation = useMutation({
    mutationFn: async (file: File) => {
      const fd = new FormData();
      fd.append("xml", file);
      return api.post<{ data: Record<string, string | number> }>("/quotations/parse-xml", fd);
    },
    onSuccess: (res) => {
      const d = res.data;
      setForm({
        nf_number: String(d.nf_number ?? ""),
        sender_cnpj: String(d.sender_cnpj ?? ""),
        receiver_cnpj: String(d.receiver_cnpj ?? ""),
        origin_cep: String(d.origin_cep ?? ""),
        destination_cep: String(d.destination_cep ?? ""),
        weight: String(d.weight ?? ""),
        boxes: String(d.boxes ?? ""),
        volume: String(d.volume ?? ""),
        cargo_value: String(d.cargo_value ?? ""),
      });
      setXmlStatus("success");
      setXmlMessage("XML processado com sucesso!");
    },
    onError: (err: Error) => {
      setXmlStatus("error");
      setXmlMessage(err.message);
    },
  });

  const createMutation = useMutation({
    mutationFn: (data: Record<string, unknown>) => api.post<{ data: { id: string } }>("/quotations", data),
    onSuccess: (res) => {
      navigate({ to: "/cotacoes/resultado", search: { id: res.data.id } });
    },
    onError: (err: Error) => setError(err.message),
  });

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setXmlStatus("idle");
    setXmlMessage("");
    parseMutation.mutate(file);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    const payload = Object.fromEntries(
      Object.entries(form).map(([k, v]) => [k, ["weight","boxes","volume","cargo_value"].includes(k) ? Number(v) || 0 : v])
    );
    createMutation.mutate(payload);
  };

  const update = (field: string) => (e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, [field]: e.target.value });

  const limpar = () => {
    setForm(initialForm);
    setXmlStatus("idle");
    setXmlMessage("");
    setError("");
    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  return (
    <div className="space-y-5 max-w-5xl">
      <PageHeader title="Nova Cotação" subtitle="Faça upload de um XML de NF-e ou preencha manualmente." actions={
        <Button variant="ghost" asChild><Link to="/cotacoes">Cancelar</Link></Button>
      } />
      <form onSubmit={handleSubmit}>
        <Card className="border-border/70 shadow-none">
          <CardContent className="p-6">
            <div
              className="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-border p-8 text-center cursor-pointer hover:border-primary/50 transition-colors"
              onClick={() => fileInputRef.current?.click()}
            >
              {parseMutation.isPending ? (
                <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
              ) : xmlStatus === "success" ? (
                <Check className="h-8 w-8 text-success" />
              ) : xmlStatus === "error" ? (
                <AlertTriangle className="h-8 w-8 text-destructive" />
              ) : (
                <Upload className="h-8 w-8 text-muted-foreground" />
              )}
              <div>
                <p className="text-sm font-medium">
                  {parseMutation.isPending
                    ? "Processando XML..."
                    : "Arraste um XML de NF-e ou clique para selecionar"}
                </p>
                <p className="text-xs text-muted-foreground mt-1">Arquivos .xml</p>
              </div>
              {xmlMessage && (
                <p className={`text-xs ${xmlStatus === "success" ? "text-success" : "text-destructive"}`}>
                  {xmlMessage}
                </p>
              )}
              <input
                ref={fileInputRef}
                type="file"
                accept=".xml"
                className="hidden"
                onChange={handleFileChange}
              />
            </div>
          </CardContent>
        </Card>

        <div className="my-5 flex items-center gap-3">
          <div className="h-px flex-1 bg-border" />
          <p className="text-xs text-muted-foreground font-medium whitespace-nowrap">OU preencha manualmente</p>
          <div className="h-px flex-1 bg-border" />
        </div>

        <Section title="Dados da Nota" hint="Informações fiscais do embarque.">
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Número NF"><Input placeholder="000.000.000" value={form.nf_number} onChange={update("nf_number")} required /></Field>
            <Field label="CNPJ Remetente"><Input placeholder="00.000.000/0000-00" value={form.sender_cnpj} onChange={update("sender_cnpj")} required /></Field>
            <Field label="CNPJ Destinatário"><Input placeholder="00.000.000/0000-00" value={form.receiver_cnpj} onChange={update("receiver_cnpj")} required /></Field>
          </div>
        </Section>
        <div className="mt-4">
          <Section title="Dados Logísticos" hint="Endereços, peso e valor da mercadoria.">
            <div className="grid gap-4 sm:grid-cols-3">
              <Field label="CEP Origem"><Input placeholder="00000-000" value={form.origin_cep} onChange={update("origin_cep")} required /></Field>
              <Field label="CEP Destino"><Input placeholder="00000-000" value={form.destination_cep} onChange={update("destination_cep")} required /></Field>
              <Field label="Peso (kg)"><Input type="number" step="0.01" placeholder="0,00" value={form.weight} onChange={update("weight")} required /></Field>
              <Field label="Caixas"><Input type="number" placeholder="0" value={form.boxes} onChange={update("boxes")} required /></Field>
              <Field label="Cubagem (m³)"><Input type="number" step="0.001" placeholder="0,000" value={form.volume} onChange={update("volume")} required /></Field>
              <Field label="Valor da Mercadoria"><Input type="number" step="0.01" placeholder="0,00" value={form.cargo_value} onChange={update("cargo_value")} required /></Field>
            </div>
          </Section>
        </div>
        {error && <p className="text-sm text-destructive mt-3">{error}</p>}
        <div className="flex items-center justify-end gap-2 mt-5">
          <Button variant="outline" type="button" onClick={limpar}>Limpar</Button>
          <Button type="submit" disabled={createMutation.isPending}>
            {createMutation.isPending ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Search className="mr-1.5 h-4 w-4" />}
            Buscar Cotações
            <ArrowRight className="ml-1.5 h-4 w-4" />
          </Button>
        </div>
      </form>
    </div>
  );
}
