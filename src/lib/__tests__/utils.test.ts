import { describe, expect, it } from "vitest";
import { cn, fmtCurr, fmtDate } from "../utils";

describe("utils", () => {
  it("cn merges conditional classes", () => {
    const cond = false;
    expect(cn("a", cond && "b", "c")).toBe("a c");
    expect(cn("a", null, undefined, "b")).toBe("a b");
  });

  it("fmtCurr formats BRL", () => {
    expect(fmtCurr(1250.5)).toBe("R$\u00a01.250,50");
  });

  it("fmtDate formats pt-BR date (ISO date shifts to local timezone)", () => {
    expect(fmtDate("2026-08-25")).toBe("24/08/2026");
    expect(fmtDate("2026-08-25T12:00:00")).toBe("25/08/2026");
  });
});
