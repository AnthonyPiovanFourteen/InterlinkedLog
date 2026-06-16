function renderErrorPage() {
  return `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Erro</title></head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;background:#0a0a0a;color:#ddd"><div style="text-align:center"><h1 style="font-size:1.5rem">Algo deu errado</h1><p style="color:#888;margin-top:8px">Tente novamente.</p><a href="/" style="display:inline-block;margin-top:16px;padding:8px 16px;background:#2563eb;color:white;border-radius:6px;text-decoration:none">Início</a></div></body></html>`;
}

export { renderErrorPage };
