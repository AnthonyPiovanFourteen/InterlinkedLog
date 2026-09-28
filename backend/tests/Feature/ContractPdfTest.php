<?php

namespace Tests\Feature;

class ContractPdfTest extends ApiTestCase
{
    private function contractId(): string
    {
        $quotation = $this->createQuotation();

        return $this->postJson('/api/v1/contracts', [
            'quotation_id' => $quotation['id'],
            'carrier_id' => $quotation['results'][0]['carrier_id'],
        ], $this->authHeaders())->json('data.id');
    }

    public function test_pdf_is_generated_with_the_logo(): void
    {
        $response = $this->get("/api/v1/contracts/{$this->contractId()}/pdf", $this->authHeaders());

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        // O logo entra como imagem embutida: o dompdf marca o PDF como tendo
        // recurso de imagem. Sem o arquivo, o template cairia no título em texto.
        $this->assertFileExists(public_path('logo.png'));

        // O dompdf devolve o PDF pronto (não streamed). Imagem embutida aparece
        // como objeto /Image no corpo — sem ela, o template cairia no título em
        // texto e o marcador não existiria.
        $this->assertStringContainsString('/Image', $response->getContent());
    }

    public function test_pdf_of_another_tenant_is_not_accessible(): void
    {
        $id = $this->contractId();
        $other = $this->createTenant('pdf@interlinked.io', 'Outro');

        $this->get("/api/v1/contracts/{$id}/pdf", $this->authHeaders($other['token']))
            ->assertStatus(404);
    }
}
