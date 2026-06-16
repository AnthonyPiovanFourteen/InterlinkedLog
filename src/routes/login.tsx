import { createFileRoute, useNavigate } from "@tanstack/react-router";
import { useState } from "react";
import { PackageSearch, Loader2 } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/hooks/use-auth";

export const Route = createFileRoute("/login")({
  head: () => ({ meta: [{ title: "Entrar · InterlinkedLog" }] }),
  component: LoginPage,
});

function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);
    try {
      await login(email, password);
      navigate({ to: "/" });
    } catch (err) {
      setError(err instanceof Error ? err.message : "Credenciais inválidas");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen grid lg:grid-cols-2 bg-background">
      <div className="hidden lg:flex flex-col justify-between p-10 bg-primary text-primary-foreground relative overflow-hidden">
        <div className="absolute inset-0 opacity-10 [background-image:linear-gradient(var(--primary-foreground)_1px,transparent_1px),linear-gradient(90deg,var(--primary-foreground)_1px,transparent_1px)] [background-size:32px_32px]" />
        <div className="relative flex items-center gap-2 text-sm font-semibold">
          <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary-foreground/15 backdrop-blur">
            <PackageSearch className="h-4 w-4" />
          </div>
          InterlinkedLog
        </div>
        <div className="relative space-y-3">
          <h1 className="text-3xl font-semibold leading-tight tracking-tight">
            A plataforma de fretes para empresas que enviam todos os dias.
          </h1>
          <p className="text-sm text-primary-foreground/80 max-w-md">
            Cote, contrate e rastreie suas cargas em uma única interface.
          </p>
        </div>

      </div>

      <div className="flex items-center justify-center p-6">
        <Card className="w-full max-w-md border-border/70 shadow-none">
          <CardContent className="p-8 space-y-6">
            <div className="space-y-1.5">
              <h2 className="text-xl font-semibold tracking-tight">Entrar na sua conta</h2>
              <p className="text-sm text-muted-foreground">
                Acesse o painel da sua organização.
              </p>
            </div>
            <form className="space-y-4" onSubmit={handleSubmit}>
              <div className="space-y-1.5">
                <Label htmlFor="email">E-mail</Label>
                <Input id="email" type="email" placeholder="voce@empresa.com" value={email} onChange={(e) => setEmail(e.target.value)} required />
              </div>
              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <Label htmlFor="senha">Senha</Label>
                </div>
                <Input id="senha" type="password" placeholder="••••••••" value={password} onChange={(e) => setPassword(e.target.value)} required />
              </div>
              {error && <p className="text-sm text-destructive">{error}</p>}
              <Button type="submit" className="w-full" disabled={loading}>
                {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                Entrar
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
