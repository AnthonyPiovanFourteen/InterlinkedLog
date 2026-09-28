<?php

namespace App\Domain\Services;

/**
 * Tenant ativo na execução corrente.
 *
 * Substitui a leitura direta do binding de request no TenantScope, que ficava
 * inerte fora de HTTP — em worker de fila o escopo não filtrava nada. Agora
 * quem define o tenant é explícito: o middleware em HTTP, o job na fila.
 */
interface TenantContext
{
    public function companyId(): ?string;

    public function setCompanyId(?string $companyId): void;

    /** Executa a operação com o tenant informado e restaura o anterior. */
    public function runAs(string $companyId, callable $operation): mixed;
}
