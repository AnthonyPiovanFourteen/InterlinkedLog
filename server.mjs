import { createServer } from "node:http";
import { createReadStream, statSync } from "node:fs";
import { extname, join, normalize } from "node:path";
import ssr from "./dist/server/server.js";

// O bundle do TanStack Start exporta um objeto { fetch }, não uma função.
const serverHandler = typeof ssr === "function" ? ssr : ssr.fetch.bind(ssr);

const PORT = Number(process.env.PORT || 3000);
const API_TARGET = process.env.VITE_API_URL || "http://backend:8000";
const CLIENT_DIR = new URL("./dist/client/", import.meta.url).pathname;

const MIME = {
  ".js": "text/javascript",
  ".mjs": "text/javascript",
  ".css": "text/css",
  ".json": "application/json",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".webp": "image/webp",
  ".ico": "image/x-icon",
  ".woff": "font/woff",
  ".woff2": "font/woff2",
  ".map": "application/json",
};

// O handler SSR não serve o build do cliente: os assets de dist/client
// precisam ser entregues antes de cair no render.
function serveStatic(pathname, res) {
  const rel = normalize(decodeURIComponent(pathname)).replace(/^(\.\.[/\\])+/, "");
  const file = join(CLIENT_DIR, rel);
  if (!file.startsWith(CLIENT_DIR)) return false;

  let stat;
  try {
    stat = statSync(file);
  } catch {
    return false;
  }
  if (!stat.isFile()) return false;

  res.writeHead(200, {
    "content-type": MIME[extname(file)] ?? "application/octet-stream",
    "content-length": stat.size,
    "cache-control": rel.startsWith("assets/") ? "public, max-age=31536000, immutable" : "no-cache",
  });
  createReadStream(file).pipe(res);
  return true;
}

function normalizeHeaders(headers) {
  return Object.fromEntries(
    Object.entries(headers)
      .filter(([, v]) => v != null)
      .map(([k, v]) => [k, Array.isArray(v) ? v.join(", ") : String(v)]),
  );
}

const server = createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host ?? "localhost"}`);

  if (url.pathname.startsWith("/api/")) {
    try {
      const headers = normalizeHeaders(req.headers);
      const xff = headers["x-forwarded-for"];
      headers["x-forwarded-for"] = xff
        ? `${xff}, ${req.socket.remoteAddress}`
        : req.socket.remoteAddress;
      headers["x-forwarded-proto"] = "http";
      headers["x-forwarded-host"] = req.headers.host;

      const upstream = await fetch(API_TARGET + url.pathname + url.search, {
        method: req.method,
        headers,
        body: ["GET", "HEAD"].includes(req.method) ? undefined : req,
        redirect: "manual",
      });
      res.writeHead(upstream.status, Object.fromEntries(upstream.headers));
      res.end(Buffer.from(await upstream.arrayBuffer()));
    } catch {
      res.writeHead(502, { "content-type": "text/plain" });
      res.end("Bad Gateway");
    }
    return;
  }

  if (req.method === "GET" && serveStatic(url.pathname, res)) {
    return;
  }

  try {
    const request = new Request(url, {
      method: req.method,
      headers: normalizeHeaders(req.headers),
      body: ["GET", "HEAD"].includes(req.method) ? undefined : req,
    });
    const response = await serverHandler(request);
    res.writeHead(response.status, Object.fromEntries(response.headers));
    res.end(Buffer.from(await response.arrayBuffer()));
  } catch (error) {
    console.error(error);
    res.writeHead(500, { "content-type": "text/plain" });
    res.end("Internal Server Error");
  }
});

server.listen(PORT, () => console.log(`InterlinkedLog frontend em :${PORT}`));
