let lastError: Error | null = null;
if (typeof globalThis !== "undefined") {
  globalThis.addEventListener?.("error", (e: Event) => {
    if (e instanceof ErrorEvent) lastError = e.error;
  });
  globalThis.addEventListener?.("unhandledrejection", (e: PromiseRejectionEvent) => {
    lastError = e.reason instanceof Error ? e.reason : new Error(String(e.reason));
  });
}
export function consumeLastCapturedError(): Error | null {
  const e = lastError;
  lastError = null;
  return e;
}
