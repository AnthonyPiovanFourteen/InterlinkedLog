import { createFileRoute } from "@tanstack/react-router";
import { useState, useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { Search } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { Badge } from "@/components/ui/badge";
import { api } from "@/lib/api";
import { fmtDate } from "@/lib/utils";

interface AuditItem {
  id: string;
  created_at: string;
  user_name: string;
  module: string;
  action: string;
  entity: string;
}

export const Route = createFileRoute("/auditoria")({
  head: () => ({ meta: [{ title: "Auditoria · InterlinkedLog" }] }),
  component: AuditoriaPage,
});

function AuditoriaPage() {
  const [filter, setFilter] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["audit-logs"],
    queryFn: async () => {
      try {
        const res = await api.get<{ data: AuditItem[] }>("/audit-logs");
        return Array.isArray(res?.data) ? res.data : [];
      } catch {
        return [];
      }
    },
  });

  const items = useMemo(() => {
    if (!data) return [];
    const sorted = [...data]
      .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
      .slice(0, 50);
    if (!filter) return sorted;
    const q = filter.toLowerCase();
    return sorted.filter(
      (i) =>
        i.user_name?.toLowerCase().includes(q) ||
        i.module?.toLowerCase().includes(q) ||
        i.action?.toLowerCase().includes(q) ||
        i.entity?.toLowerCase().includes(q),
    );
  }, [data, filter]);

  return (
    <div className="space-y-5">
      <PageHeader title="Auditoria" subtitle="Trilha de alterações sensíveis nos dados." />

      <div className="relative max-w-sm">
        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
        <Input
          placeholder="Filtrar..."
          className="pl-8"
          value={filter}
          onChange={(e) => setFilter(e.target.value)}
        />
      </div>

      <Card className="border-border/70 shadow-none">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>Data</TableHead>
                <TableHead>Usuário</TableHead>
                <TableHead>Módulo</TableHead>
                <TableHead>Ação</TableHead>
                <TableHead>Entidade</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">Carregando...</TableCell>
                </TableRow>
              ) : items.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="text-center py-8 text-muted-foreground">
                    Nenhum registro de auditoria encontrado.
                  </TableCell>
                </TableRow>
              ) : (
                items.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell className="text-muted-foreground tabular-nums whitespace-nowrap">{fmtDate(item.created_at)}</TableCell>
                    <TableCell className="font-medium">{item.user_name}</TableCell>
                    <TableCell><Badge variant="outline">{item.module}</Badge></TableCell>
                    <TableCell>{item.action}</TableCell>
                    <TableCell className="text-muted-foreground">{item.entity}</TableCell>
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
