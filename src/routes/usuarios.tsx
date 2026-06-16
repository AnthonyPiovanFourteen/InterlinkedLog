import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus } from "lucide-react";
import { toast } from "sonner";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { StatusBadge } from "@/shared/components/atoms/StatusBadge";
import { useAuth } from "@/hooks/use-auth";
import { api } from "@/lib/api";

export const Route = createFileRoute("/usuarios")({
  head: () => ({ meta: [{ title: "Usuários · InterlinkedLog" }] }),
  component: UsuariosPage,
});

interface UserItem { id: string; name: string; email: string; role: string; status: string; last_access_at: string | null; }

function UsuariosPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const { data, isLoading } = useQuery({
    queryKey: ["users"],
    queryFn: () => api.get<{ data: UserItem[] }>("/users").then((r) => r.data),
  });

  return (
    <div className="space-y-5">
      <PageHeader title="Usuários" subtitle="Membros da sua organização." actions={
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild><Button><Plus className="mr-1 h-4 w-4" /> Novo Usuário</Button></DialogTrigger>
          <DialogContent className="sm:max-w-md">
            <DialogHeader><DialogTitle>Convidar usuário</DialogTitle><DialogDescription>Defina nome, e-mail e papel de acesso.</DialogDescription></DialogHeader>
            <div className="space-y-3 py-2">
              <div className="space-y-1.5"><Label>Nome</Label><Input id="uname" placeholder="Nome completo" /></div>
              <div className="space-y-1.5"><Label>E-mail</Label><Input id="uemail" type="email" placeholder="nome@empresa.com" /></div>
              <div className="space-y-1.5"><Label>Senha</Label><Input id="upass" type="password" placeholder="Mínimo 6 caracteres" /></div>
              <div className="space-y-1.5">
                <Label>Role</Label>
                <select id="urole" className="w-full h-9 rounded-md border border-input bg-background px-3 text-sm">
                  <option value="Operacional">Operacional</option>
                  <option value="Admin">Admin</option>
                  <option value="Financeiro">Financeiro</option>
                  <option value="Consulta">Consulta</option>
                </select>
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setOpen(false)}>Cancelar</Button>
              <Button onClick={async () => {
                const name = (document.getElementById("uname") as HTMLInputElement)?.value;
                const email = (document.getElementById("uemail") as HTMLInputElement)?.value;
                const password = (document.getElementById("upass") as HTMLInputElement)?.value;
                const role = (document.getElementById("urole") as HTMLSelectElement)?.value;
                try {
                  await api.post("/users", { name, email, password, role });
                  setOpen(false);
                  queryClient.invalidateQueries({ queryKey: ["users"] });
                  toast.success("Usuário criado com sucesso.");
                } catch (err: any) { toast.error(err.message); }
              }}>Enviar convite</Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      } />
      <Card className="border-border/70 shadow-none">
        <CardContent className="p-0">
          <Table>
            <TableHeader><TableRow className="hover:bg-transparent"><TableHead>Nome</TableHead><TableHead>E-mail</TableHead><TableHead>Role</TableHead><TableHead>Status</TableHead><TableHead>Último acesso</TableHead></TableRow></TableHeader>
            <TableBody>
              {isLoading ? <TableRow><TableCell colSpan={5} className="text-center py-8 text-muted-foreground">Carregando...</TableCell></TableRow> :
                (data ?? []).map((u) => (
                  <TableRow key={u.id}><TableCell><span className="font-medium">{u.name}</span></TableCell><TableCell className="text-muted-foreground">{u.email}</TableCell><TableCell>{u.role}</TableCell><TableCell><StatusBadge status={u.status} /></TableCell><TableCell className="text-muted-foreground tabular-nums">{u.last_access_at ?? "—"}</TableCell></TableRow>
                ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
