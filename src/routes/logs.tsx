import { createFileRoute } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";
import { Card, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { Badge } from "@/components/ui/badge";
import { api } from "@/lib/api";
import { cn, fmtDate } from "@/lib/utils";

interface LogItem {
  id: string;
  created_at: string;
  level: string;
  event: string;
  message: string;
}

const levelColors: Record<string, string> = {
  INFO: "bg-blue-500/15 text-blue-600 border-blue-500/25",
  WARNING: "bg-amber-500/15 text-amber-600 border-amber-500/25",
  ERROR: "bg-red-500/15 text-red-600 border-red-500/25",
};

export const Route = createFileRoute("/logs")({
  head: () => ({ meta: [{ title: "Logs · InterlinkedLog" }] }),
  component: LogsPage,
});

function LogsPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["system-logs"],
    queryFn: async () => {
      try {
        const res = await api.get<{ data: LogItem[] }>("/system-logs");
        return Array.isArray(res?.data) ? res.data : [];
      } catch {
        return [];
      }
    },
  });

  const items = data ?? [];

  return (
    <div className="space-y-5">
      <PageHeader title="Logs" subtitle="Eventos do sistema e atividade dos usuários." />

      <Card className="border-border/70 shadow-none">
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead>Data</TableHead>
                <TableHead>Nível</TableHead>
                <TableHead>Evento</TableHead>
                <TableHead>Mensagem</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={4} className="text-center py-8 text-muted-foreground">Carregando...</TableCell>
                </TableRow>
              ) : items.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={4} className="text-center py-8 text-muted-foreground">Nenhum log encontrado.</TableCell>
                </TableRow>
              ) : (
                items.map((l) => (
                  <TableRow key={l.id}>
                    <TableCell className="text-muted-foreground tabular-nums whitespace-nowrap">{fmtDate(l.created_at)}</TableCell>
                    <TableCell>
                      <Badge variant="outline" className={cn("font-medium border px-2 py-0.5 text-[11px]", levelColors[l.level] ?? "bg-muted text-muted-foreground")}>
                        {l.level}
                      </Badge>
                    </TableCell>
                    <TableCell className="font-medium">{l.event}</TableCell>
                    <TableCell className="text-muted-foreground max-w-[400px] truncate">{l.message}</TableCell>
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
