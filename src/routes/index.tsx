import { createFileRoute } from "@tanstack/react-router";
import { FileText, ClipboardCheck, PiggyBank, Award, DollarSign, Clock, Percent } from "lucide-react";
import { useQueries } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { PageHeader } from "@/shared/components/molecules/PageHeader";
import { MetricCard } from "@/shared/components/molecules/MetricCard";
import { api } from "@/lib/api";
import { fmtCurr } from "@/lib/utils";
import { PieChart, Pie, Cell, BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";
import { ComposableMap, Geographies, Geography, Line, Marker } from "react-simple-maps";

const BRAZIL_GEO_ID = "076";

const STATUS_COLORS: Record<string, string> = {
  RASCUNHO: "#facc15",
  VALIDA: "#3b82f6",
  EXPIRADA: "#9ca3af",
  CONTRATADA: "#22c55e",
  CANCELADA: "#ef4444",
};

const DIST_COLORS = ["#3b82f6", "#22c55e", "#f59e0b", "#8b5cf6", "#ec4899"];

interface DashboardData {
  quotations_count: number;
  contracts_count: number;
  total_savings: number;
  total_contracted: number;
  avg_delivery_time: number;
  top_carrier: string;
  carrier_distribution: Record<string, number>;
  conversion_rate: number;
  avg_ticket: number;
  quotations_by_status: Record<string, number>;
}

interface DetailedData {
  carrier_ranking: { carrier: string; contracts: number; total_value: number }[];
  top_routes: { route: string; count: number }[];
}

const cityCoords: Record<string, [number, number]> = {
  "São Paulo": [-46.63, -23.55],
  "São Paulo/SP": [-46.63, -23.55],
  "Marília": [-49.94, -22.21],
  "Marília/SP": [-49.94, -22.21],
  "Londrina": [-51.16, -23.31],
  "Londrina/PR": [-51.16, -23.31],
  "Curitiba": [-49.27, -25.42],
  "Curitiba/PR": [-49.27, -25.42],
  "Campinas": [-47.06, -22.90],
  "Campinas/SP": [-47.06, -22.90],
  "Bauru": [-49.06, -22.31],
  "Bauru/SP": [-49.06, -22.31],
  "Presidente Prudente": [-51.38, -22.12],
  "Presidente Prudente/SP": [-51.38, -22.12],
  "Ribeirão Preto": [-47.81, -21.17],
  "Ribeirão Preto/SP": [-47.81, -21.17],
  "São José do Rio Preto": [-49.37, -20.81],
  "Maringá": [-51.93, -23.42],
  "Maringá/PR": [-51.93, -23.42],
  "Uberlândia": [-48.27, -18.91],
  "Uberlândia/MG": [-48.27, -18.91],
  "Goiânia": [-49.26, -16.68],
  "Goiânia/GO": [-49.26, -16.68],
  "Campo Grande": [-54.64, -20.44],
  "Campo Grande/MS": [-54.64, -20.44],
  "Belo Horizonte": [-43.93, -19.91],
  "Belo Horizonte/MG": [-43.93, -19.91],
  "Rio de Janeiro": [-43.20, -22.90],
  "Rio de Janeiro/RJ": [-43.20, -22.90],
  "Porto Alegre": [-51.22, -30.03],
  "Porto Alegre/RS": [-51.22, -30.03],
  "Florianópolis": [-48.54, -27.59],
  "Florianópolis/SC": [-48.54, -27.59],
  "Salvador": [-38.50, -12.97],
  "Salvador/BA": [-38.50, -12.97],
  "Brasília": [-47.88, -15.79],
  "Brasília/DF": [-47.88, -15.79],
  "Santos": [-46.33, -23.95],
  "Santos/SP": [-46.33, -23.95],
  "Uberaba": [-47.93, -19.75],
  "Uberaba/MG": [-47.93, -19.75],
};

function getCoords(city: string): [number, number] {
  if (cityCoords[city]) return cityCoords[city];
  const hash = city.split("").reduce((acc, c) => acc + c.charCodeAt(0), 0);
  return [-52 + (hash % 16) - 8, -18 + ((hash * 7) % 16) - 8];
}

export const Route = createFileRoute("/")({
  head: () => ({ meta: [{ title: "Painel · InterlinkedLog" }] }),
  component: PainelPage,
});

function PainelPage() {
  const results = useQueries({
    queries: [
      {
        queryKey: ["dashboard"],
        queryFn: () => api.get<{ data: DashboardData }>("/reports/dashboard").then((r) => r.data),
      },
      {
        queryKey: ["detailed"],
        queryFn: () => api.get<{ data: DetailedData }>("/reports/detailed").then((r) => r.data),
      },
    ],
  });

  const dashboard = results[0].data;
  const detailed = results[1].data;
  const isLoading = results[0].isLoading || results[1].isLoading;

  const statusPie = dashboard?.quotations_by_status
    ? Object.entries(dashboard.quotations_by_status)
        .filter(([, v]) => v > 0)
        .map(([k, v]) => ({ name: k, value: v }))
    : [];

  const topCarriers = dashboard?.carrier_distribution
    ? Object.entries(dashboard.carrier_distribution)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5)
        .map(([name, count]) => ({ name, count }))
    : [];

  return (
    <div className="space-y-6">
      <PageHeader title="Painel de Controle" subtitle="Visão completa da operação logística." />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard
          label="Cotações no mês"
          value={isLoading ? "..." : String(dashboard?.quotations_count ?? 0)}
          icon={FileText}
        />
        <MetricCard
          label="Contratações"
          value={isLoading ? "..." : String(dashboard?.contracts_count ?? 0)}
          icon={ClipboardCheck}
        />
        <MetricCard
          label="Valor em frete"
          value={isLoading ? "..." : fmtCurr(dashboard?.total_contracted ?? 0)}
          icon={DollarSign}
        />
        <MetricCard
          label="Transportadora top"
          value={isLoading ? "..." : (dashboard?.top_carrier ?? "—")}
          icon={Award}
        />
      </div>

      <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <Card className="shadow-none border-border/70">
          <CardContent className="p-4">
            <div className="flex items-center gap-2 text-xs text-muted-foreground uppercase tracking-wider mb-1">
              <Percent className="h-3.5 w-3.5" /> Taxa de conversão
            </div>
            <div className="text-xl font-semibold">
              {isLoading ? "..." : `${dashboard?.conversion_rate ?? 0}%`}
            </div>
            <div className="mt-2 h-1.5 w-full bg-muted rounded-full overflow-hidden">
              <div
                className="h-full bg-blue-500 rounded-full transition-all"
                style={{ width: `${Math.min(100, dashboard?.conversion_rate ?? 0)}%` }}
              />
            </div>
          </CardContent>
        </Card>
        <Card className="shadow-none border-border/70">
          <CardContent className="p-4">
            <div className="flex items-center gap-2 text-xs text-muted-foreground uppercase tracking-wider mb-1">
              <DollarSign className="h-3.5 w-3.5" /> Ticket médio
            </div>
            <div className="text-xl font-semibold">
              {isLoading ? "..." : fmtCurr(dashboard?.avg_ticket ?? 0)}
            </div>
            <div className="text-xs text-muted-foreground mt-1">por frete contratado</div>
          </CardContent>
        </Card>
        <Card className="shadow-none border-border/70">
          <CardContent className="p-4">
            <div className="flex items-center gap-2 text-xs text-muted-foreground uppercase tracking-wider mb-1">
              <PiggyBank className="h-3.5 w-3.5" /> Economia obtida
            </div>
            <div className="text-xl font-semibold">
              {isLoading ? "..." : fmtCurr(dashboard?.total_savings ?? 0)}
            </div>
            <div className="text-xs text-muted-foreground mt-1">maior cotação − escolhida</div>
          </CardContent>
        </Card>
        <Card className="shadow-none border-border/70">
          <CardContent className="p-4">
            <div className="flex items-center gap-2 text-xs text-muted-foreground uppercase tracking-wider mb-1">
              <Clock className="h-3.5 w-3.5" /> Prazo médio
            </div>
            <div className="text-xl font-semibold">
              {isLoading ? "..." : `${dashboard?.avg_delivery_time ?? 0} dias`}
            </div>
            <div className="text-xs text-muted-foreground mt-1">entre coleta e entrega</div>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="shadow-none border-border/70">
          <CardHeader className="pb-2">
            <CardTitle className="text-base font-semibold">Cotações por Status</CardTitle>
          </CardHeader>
          <CardContent>
            {statusPie.length > 0 ? (
              <div className="flex items-center">
                <ResponsiveContainer width="55%" height={200}>
                  <PieChart>
                    <Pie
                      data={statusPie}
                      cx="50%"
                      cy="50%"
                      innerRadius={50}
                      outerRadius={85}
                      dataKey="value"
                      strokeWidth={2}
                    >
                      {statusPie.map((entry, i) => (
                        <Cell key={i} fill={STATUS_COLORS[entry.name] ?? DIST_COLORS[i % DIST_COLORS.length]} />
                      ))}
                    </Pie>
                  </PieChart>
                </ResponsiveContainer>
                <div className="flex-1 space-y-2 text-sm">
                  {statusPie.map((entry, i) => (
                    <div key={i} className="flex items-center gap-2">
                      <span
                        className="h-2.5 w-2.5 rounded-full shrink-0"
                        style={{ backgroundColor: STATUS_COLORS[entry.name] ?? DIST_COLORS[i % DIST_COLORS.length] }}
                      />
                      <span className="text-muted-foreground">{entry.name}</span>
                      <span className="ml-auto font-medium">{entry.value}</span>
                    </div>
                  ))}
                </div>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground py-8 text-center">Nenhum dado disponível.</p>
            )}
          </CardContent>
        </Card>

        <Card className="shadow-none border-border/70">
          <CardHeader className="pb-2">
            <CardTitle className="text-base font-semibold">Top 5 Transportadoras</CardTitle>
          </CardHeader>
          <CardContent>
            {topCarriers.length > 0 ? (
              <ResponsiveContainer width="100%" height={220}>
                <BarChart data={topCarriers} layout="vertical" margin={{ left: 20, right: 20 }}>
                  <XAxis type="number" hide />
                  <YAxis
                    type="category"
                    dataKey="name"
                    tickLine={false}
                    axisLine={false}
                    tick={{ fontSize: 12, fill: "var(--muted-foreground)" }}
                    width={130}
                  />
                  <Tooltip
                    contentStyle={{ borderRadius: 8, fontSize: 12 }}
                    formatter={(v: number) => [`${v} fretes`, "Volume"]}
                  />
                  <Bar dataKey="count" radius={[0, 4, 4, 0]} barSize={24}>
                    {topCarriers.map((_, i) => (
                      <Cell key={i} fill={DIST_COLORS[i % DIST_COLORS.length]} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            ) : (
              <p className="text-sm text-muted-foreground py-8 text-center">Nenhum dado disponível.</p>
            )}
          </CardContent>
        </Card>
      </div>

      <Card className="shadow-none border-border/70">
        <CardHeader className="pb-2">
          <CardTitle className="text-base font-semibold">Mapa de Rotas</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="h-[320px] w-full">
            <ComposableMap
              projection="geoMercator"
              projectionConfig={{ center: [-55, -15], scale: 500 }}
              style={{ width: "100%", height: "100%" }}
            >
              <Geographies geography="/brazil-topo.json">
                {({ geographies }) =>
                  geographies
                    .filter((geo: any) => geo.id === BRAZIL_GEO_ID)
                    .map((geo: any) => (
                      <Geography
                        key={geo.rsmKey}
                        geography={geo}
                        fill="#e5e7eb"
                        stroke="#d1d5db"
                        strokeWidth={0.5}
                        style={{
                          default: { outline: "none" },
                          hover: { fill: "#93c5fd", outline: "none" },
                          pressed: { outline: "none" },
                        }}
                      />
                    ))
                }
              </Geographies>
              {detailed?.top_routes?.slice(0, 10).map((route, i) => {
                const [orig, dest] = route.route.split(" → ");
                const coordsA = getCoords(orig);
                const coordsB = getCoords(dest);
                return (
                  <g key={i}>
                    <Line
                      from={coordsA}
                      to={coordsB}
                      stroke={DIST_COLORS[i % DIST_COLORS.length]}
                      strokeWidth={2}
                      strokeLinecap="round"
                    />
                    <Marker coordinates={coordsA}>
                      <circle r={4} fill={DIST_COLORS[i % DIST_COLORS.length]} stroke="#fff" strokeWidth={1} />
                    </Marker>
                    <Marker coordinates={coordsB}>
                      <circle r={4} fill={DIST_COLORS[i % DIST_COLORS.length]} stroke="#fff" strokeWidth={1} />
                    </Marker>
                  </g>
                );
              })}
            </ComposableMap>
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card className="shadow-none border-border/70">
          <CardHeader className="pb-2">
            <CardTitle className="text-base font-semibold">Top Transportadoras</CardTitle>
          </CardHeader>
          <CardContent>
            {detailed?.carrier_ranking && detailed.carrier_ranking.length > 0 ? (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left text-xs text-muted-foreground">
                    <th className="pb-2 font-medium">#</th>
                    <th className="pb-2 font-medium">Transportadora</th>
                    <th className="pb-2 font-medium text-right">Fretes</th>
                    <th className="pb-2 font-medium text-right">Valor</th>
                  </tr>
                </thead>
                <tbody>
                  {detailed.carrier_ranking.slice(0, 5).map((c, i) => (
                    <tr key={i} className="border-b last:border-0">
                      <td className="py-2 text-muted-foreground w-6">{i + 1}</td>
                      <td className="py-2 truncate max-w-[160px]">{c.carrier}</td>
                      <td className="py-2 text-right tabular-nums">{c.contracts}</td>
                      <td className="py-2 text-right tabular-nums">{fmtCurr(c.total_value)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <p className="text-sm text-muted-foreground py-8 text-center">Nenhum dado disponível.</p>
            )}
          </CardContent>
        </Card>

        <Card className="shadow-none border-border/70">
          <CardHeader className="pb-2">
            <CardTitle className="text-base font-semibold">Rotas Mais Utilizadas</CardTitle>
          </CardHeader>
          <CardContent>
            {detailed?.top_routes && detailed.top_routes.length > 0 ? (
              <div className="space-y-3">
                {detailed.top_routes.slice(0, 5).map((r, i) => {
                  const max = detailed.top_routes[0]?.count ?? 1;
                  return (
                    <div key={i} className="flex items-center gap-3">
                      <span className="w-48 text-sm truncate">{r.route}</span>
                      <div className="flex-1 h-3 bg-muted rounded-full overflow-hidden">
                        <div
                          className="h-full rounded-full"
                          style={{
                            width: `${Math.max(5, (r.count / max) * 100)}%`,
                            backgroundColor: DIST_COLORS[i % DIST_COLORS.length],
                          }}
                        />
                      </div>
                      <span className="text-sm tabular-nums font-medium w-8 text-right">{r.count}</span>
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className="text-sm text-muted-foreground py-8 text-center">Nenhum dado disponível.</p>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
