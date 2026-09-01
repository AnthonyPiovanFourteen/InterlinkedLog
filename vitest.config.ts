import path from "node:path";
import react from "@vitejs/plugin-react";
import { defineConfig } from "vitest/config";

// Fuso fixo para toda a suíte: o sistema é pt-BR e o backend roda com
// APP_TIMEZONE=America/Sao_Paulo. Sem isso, testes que tocam data variam
// com o relógio da máquina (o runner do GitHub roda em UTC).
process.env.TZ = "America/Sao_Paulo";

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "src"),
    },
  },
  test: {
    environment: "jsdom",
    setupFiles: ["./tests/setup.ts"],
    include: ["src/**/*.test.{ts,tsx}"],
  },
});
