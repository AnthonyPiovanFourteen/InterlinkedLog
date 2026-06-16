<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Solicitação de Coleta</title>
  <style>
    @page { margin: 20mm 15mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.5; }
    .logo { text-align: center; margin-bottom: 8px; }
    .logo h1 { font-size: 22px; margin: 0; color: #2563eb; }
    .logo span { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 2px; }
    .title { text-align: center; border: 2px solid #2563eb; padding: 6px; margin: 12px 0; }
    .title h2 { font-size: 14px; margin: 0; }
    .title p { font-size: 12px; margin: 2px 0 0; color: #333; }
    .meta { text-align: center; font-size: 10px; color: #666; margin-bottom: 10px; }
    .section { margin-bottom: 10px; }
    .section h4 { font-size: 11px; background: #f0f4ff; padding: 4px 8px; margin: 0 0 5px; border-left: 3px solid #2563eb; }
    .box { border: 1px solid #ddd; padding: 6px 8px; font-size: 10px; }
    .box p { margin: 2px 0; }
    table { width: 100%; border-collapse: collapse; font-size: 10px; margin: 5px 0; }
    th, td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; }
    th { background: #f0f4ff; font-size: 9px; text-transform: uppercase; }
    td.right { text-align: right; }
    .total { font-weight: bold; background: #f0f4ff; }
    .signatures { margin-top: 30px; }
    .sig-line { border-top: 1px solid #333; width: 60%; margin: 40px 0 4px; }
    .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
  </style>
</head>
<body>
  <div class="logo">
    <h1>InterlinkedLog</h1>
    <span>Plataforma de Gestão de Fretes</span>
  </div>

  <div class="title">
    <h2>SOLICITAÇÃO DE COLETA / ORDEM DE COLETA</h2>
    <p>Nº {{ $contract->documentNumber }}</p>
  </div>

  <div class="meta">
    Data da Solicitação: {{ \Carbon\Carbon::parse($contract->createdAt)->format('d/m/Y') }} &nbsp;|&nbsp;
    Validade: {{ \Carbon\Carbon::parse($contract->createdAt)->addDays(7)->format('d/m/Y') }}
  </div>

  <div class="section">
    <h4>1. CONTRATANTE (Empresa Solicitante)</h4>
    <div class="box">
      <p><strong>NF-e:</strong> {{ $contract->nfNumber }}</p>
      <p><strong>Origem:</strong> {{ $contract->originCity }}</p>
      <p><strong>Destino:</strong> {{ $contract->destinationCity }}/{{ $contract->destinationState }}</p>
    </div>
  </div>

  <div class="section">
    <h4>2. TRANSPORTADORA CONTRATADA</h4>
    <div class="box">
      <p><strong>Razão Social:</strong> {{ $contract->carrierName }}</p>
    </div>
  </div>

  <div class="section">
    <h4>3. DADOS DA CARGA</h4>
    @if($quotation)
    <div class="box">
      <p><strong>Peso Total:</strong> {{ number_format($quotation->weight, 2, ',', '.') }} kg</p>
      <p><strong>Volumes:</strong> {{ $quotation->boxes }} caixas</p>
      <p><strong>Cubagem:</strong> {{ number_format($quotation->volume, 3, ',', '.') }} m³</p>
      <p><strong>Valor da Mercadoria:</strong> R$ {{ number_format($quotation->cargoValue, 2, ',', '.') }}</p>
    </div>
    @endif
  </div>

  <div class="section">
    <h4>4. VALORES (Conforme Tabela da Transportadora)</h4>
    <table>
      <thead>
        <tr>
          <th>Descrição</th>
          <th class="right">Valor (R$)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Frete Peso</td>
          <td class="right">{{ number_format($contract->freightValue, 2, ',', '.') }}</td>
        </tr>
        <tr>
          <td>Taxas e Adicionais</td>
          <td class="right">{{ number_format($contract->fees, 2, ',', '.') }}</td>
        </tr>
        <tr class="total">
          <td><strong>VALOR TOTAL DO FRETE</strong></td>
          <td class="right"><strong>{{ number_format($contract->finalValue, 2, ',', '.') }}</strong></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="section">
    <h4>5. CONDIÇÕES</h4>
    <div class="box">
      <p><strong>Prazo de Entrega:</strong> {{ $contract->deadline }} dias úteis a partir da coleta</p>
      <p><strong>Tipo de Frete:</strong> CIF (Transportadora)</p>
      <p><strong>Observações:</strong> A coleta deverá ser realizada no horário comercial (08:00 às 17:00). Favor confirmar recebimento desta solicitação em até 24h.</p>
    </div>
  </div>

  <div class="signatures">
    <div class="sig-line">Contratante (Empresa Solicitante)</div>
    <p style="font-size: 10px; color: #666;">Data: ___/___/_____</p>

    <div class="sig-line" style="margin-top: 30px;">Transportadora (Confirmação)</div>
    <p style="font-size: 10px; color: #666;">Data: ___/___/_____</p>
  </div>

  <div class="footer">
    Documento gerado automaticamente pelo InterlinkedLog &nbsp;|&nbsp;
    Esta solicitação tem validade de 7 dias a partir da emissão &nbsp;|&nbsp;
    Gerado em: {{ \Carbon\Carbon::now()->format('d/m/Y \à\s H:i') }}
  </div>
</body>
</html>
