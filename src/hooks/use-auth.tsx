import { createContext, useContext, useState, useCallback, useEffect, type ReactNode } from "react";
import { api, setToken, getStoredUser, setStoredUser } from "@/lib/api";

interface AuthUser {
  id: string;
  name: string;
  email: string;
  role: string;
  company_id: string;
  status: string;
}

interface AuthContextType {
  user: AuthUser | null;
  token: string | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  isAdmin: boolean;
}

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(getStoredUser());
  const [token, setTokenState] = useState<string | null>(() => {
    if (typeof window !== "undefined") return localStorage.getItem("token");
    return null;
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const t = token || (typeof window !== "undefined" ? localStorage.getItem("token") : null);
    if (t) {
      api.get<AuthUser>("/me")
        .then((u) => { setUser(u); setStoredUser(u); })
        .catch(() => { setToken(null); setTokenState(null); setUser(null); setStoredUser(null); })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const res = await api.post<{ token: string; user: AuthUser }>("/login", { email, password });
    setToken(res.token);
    setTokenState(res.token);
    setUser(res.user);
    setStoredUser(res.user);
  }, []);

  const logout = useCallback(async () => {
    try { await api.post("/logout"); } catch {}
    setToken(null);
    setTokenState(null);
    setUser(null);
    setStoredUser(null);
  }, []);

  const isAdmin = user?.role === "Admin";

  return (
    <AuthContext.Provider value={{ user, token, loading, login, logout, isAdmin }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be inside AuthProvider");
  return ctx;
}
