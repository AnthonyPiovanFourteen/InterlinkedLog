import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { api } from "../api";

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

describe("api", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("injects Bearer token from localStorage", async () => {
    localStorage.setItem("token", "token-abc");
    mockFetch({ ok: true, json: { data: [] } });

    await api.get("/quotations");

    const [url, init] = (fetch as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(url).toBe("/api/v1/quotations");
    expect((init.headers as Record<string, string>).Authorization).toBe("Bearer token-abc");
  });

  it("sends JSON body with Content-Type on POST", async () => {
    mockFetch({ ok: true, json: { token: "t" } });

    await api.post("/login", { email: "a@b.com" });

    const [, init] = (fetch as ReturnType<typeof vi.fn>).mock.calls[0];
    expect((init.headers as Record<string, string>)["Content-Type"]).toBe("application/json");
    expect(init.body).toBe(JSON.stringify({ email: "a@b.com" }));
    expect(init.method).toBe("POST");
  });

  it("does not set Content-Type for FormData", async () => {
    mockFetch({ ok: true, json: {} });
    const form = new FormData();
    form.append("xml", new File(["<nfe/>"], "nfe.xml"));

    await api.post("/quotations/parse-xml", form);

    const [, init] = (fetch as ReturnType<typeof vi.fn>).mock.calls[0];
    expect(init.body).toBe(form);
    expect((init.headers as Record<string, string>)["Content-Type"]).toBeUndefined();
  });

  it("throws the API message on error response", async () => {
    mockFetch({ ok: false, status: 422, json: { message: "Cotação não encontrada" } });

    await expect(api.get("/quotations/nao-existe")).rejects.toThrow("Cotação não encontrada");
  });

  it("throws a generic error when the body has no message", async () => {
    mockFetch({ ok: false, status: 500, json: {} });

    await expect(api.get("/quotations")).rejects.toThrow("Erro 500");
  });
});
