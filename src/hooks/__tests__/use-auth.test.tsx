import { act, renderHook, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { AuthProvider, useAuth } from "../use-auth";

const USER = {
  id: "u1",
  name: "Admin",
  email: "admin@interlinked.io",
  role: "Admin",
  company_id: "c1",
  status: "Ativo",
};

function mockFetch(response: { ok: boolean; status?: number; json: unknown }) {
  vi.stubGlobal(
    "fetch",
    vi.fn().mockResolvedValue({
      ok: response.ok,
      status: response.status ?? (response.ok ? 200 : 500),
      json: async () => response.json,
    }),
  );
}

function renderAuth() {
  return renderHook(() => useAuth(), { wrapper: AuthProvider });
}

describe("useAuth", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("login stores token and user in state and localStorage", async () => {
    mockFetch({ ok: true, json: { token: "token-1", user: USER } });
    const { result } = renderAuth();

    await act(async () => {
      await result.current.login("admin@interlinked.io", "admin123");
    });

    expect(result.current.user).toEqual(USER);
    expect(result.current.token).toBe("token-1");
    expect(result.current.isAdmin).toBe(true);
    expect(localStorage.getItem("token")).toBe("token-1");
    expect(JSON.parse(localStorage.getItem("user") ?? "{}")).toEqual(USER);
  });

  it("logout clears state and localStorage", async () => {
    localStorage.setItem("token", "token-1");
    localStorage.setItem("user", JSON.stringify(USER));
    mockFetch({ ok: true, json: USER });

    const { result } = renderAuth();
    await waitFor(() => expect(result.current.user).toEqual(USER));

    mockFetch({ ok: true, json: {} });
    await act(async () => {
      await result.current.logout();
    });

    expect(result.current.user).toBeNull();
    expect(result.current.token).toBeNull();
    expect(localStorage.getItem("token")).toBeNull();
  });

  it("restores session from stored token via /me", async () => {
    localStorage.setItem("token", "token-1");
    mockFetch({ ok: true, json: USER });

    const { result } = renderAuth();

    await waitFor(() => expect(result.current.user).toEqual(USER));
    expect(result.current.loading).toBe(false);
    expect(JSON.parse(localStorage.getItem("user") ?? "{}")).toEqual(USER);
  });

  it("clears everything when stored token is rejected", async () => {
    localStorage.setItem("token", "token-invalido");
    localStorage.setItem("user", JSON.stringify(USER));
    mockFetch({ ok: false, status: 401, json: { message: "Token inválido ou expirado" } });

    const { result } = renderAuth();

    await waitFor(() => expect(result.current.user).toBeNull());
    expect(result.current.token).toBeNull();
    expect(result.current.loading).toBe(false);
    expect(localStorage.getItem("token")).toBeNull();
    expect(localStorage.getItem("user")).toBeNull();
  });

  it("isAdmin is false for non-admin role", async () => {
    mockFetch({ ok: true, json: { token: "token-1", user: { ...USER, role: "Usuário" } } });
    const { result } = renderAuth();

    await act(async () => {
      await result.current.login("marina@interlinked.io", "admin123");
    });

    expect(result.current.isAdmin).toBe(false);
  });
});
