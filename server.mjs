import { createServer } from "node:http";
import serverHandler from "./dist/server/server.js";

const PORT = Number(process.env.PORT || 3000);
const API_TARGET = process.env.VITE_API_URL || "http://backend:8000";

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
