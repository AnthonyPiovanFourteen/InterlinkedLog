<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\CarrierCredential;
use App\Domain\Entities\Role;
use App\Domain\Repositories\CarrierCredentialRepository;
use App\Domain\Repositories\CarrierRepository;
use App\Domain\Services\CarrierGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Credenciais de API das transportadoras, por tenant.
 *
 * Regra que atravessa todo este controller: os segredos NUNCA voltam numa
 * resposta. A listagem informa apenas QUAIS chaves estão configuradas, para a
 * interface poder mostrar o estado sem expor valor.
 */
class CarrierCredentialController extends Controller
{
    /** @param iterable<CarrierGateway> $gateways */
    public function __construct(
        private CarrierCredentialRepository $repository,
        private CarrierRepository $carrierRepository,
        private iterable $gateways,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');

        $data = array_map(fn (CarrierCredential $c) => $this->present($c), $this->repository->allForCompany($companyId));

        return response()->json(['data' => $data]);
    }

    /** Gateways disponíveis e os segredos que cada um exige. */
    public function gateways(): JsonResponse
    {
        $data = [];
        foreach ($this->gateways as $gateway) {
            $data[] = [
                'name' => $gateway->name(),
                'required_secrets' => $gateway->requiredSecrets(),
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return response()->json(['message' => 'Apenas administradores podem configurar credenciais'], 403);
        }

        $validator = Validator::make($request->all(), [
            'carrier_id' => 'required|string',
            'gateway' => 'required|string|in:'.implode(',', $this->gatewayNames()),
            'secrets' => 'required|array',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $companyId = $request->attributes->get('company_id');
        $gateway = $request->input('gateway');

        if (! $this->carrierRepository->findById($request->input('carrier_id'))) {
            return response()->json(['message' => 'Transportadora não encontrada'], 404);
        }

        if ($this->repository->findForCarrier($companyId, $request->input('carrier_id'))) {
            return response()->json(['message' => 'Já existe credencial para esta transportadora'], 422);
        }

        if ($missing = $this->missingSecrets($gateway, $request->input('secrets'))) {
            return response()->json([
                'message' => 'Segredos obrigatórios ausentes para o gateway '.$gateway,
                'errors' => ['secrets' => $missing],
            ], 422);
        }

        $credential = new CarrierCredential(
            id: Str::orderedUuid()->toString(),
            companyId: $companyId,
            carrierId: $request->input('carrier_id'),
            gateway: $gateway,
            secrets: $this->sanitize($request->input('secrets')),
            active: (bool) $request->input('active', true),
        );

        $this->repository->save($credential);

        return response()->json(['data' => $this->present($credential)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return response()->json(['message' => 'Apenas administradores podem configurar credenciais'], 403);
        }

        $companyId = $request->attributes->get('company_id');
        $current = $this->repository->findById($companyId, $id);

        if (! $current) {
            return response()->json(['message' => 'Credencial não encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'secrets' => 'sometimes|array',
            'active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Quando 'secrets' vem, SUBSTITUI o conjunto inteiro — mesclagem
        // parcial deixaria ambíguo se chave omitida significa manter ou apagar.
        $secrets = $current->secrets;

        if ($request->has('secrets')) {
            if ($missing = $this->missingSecrets($current->gateway, $request->input('secrets'))) {
                return response()->json([
                    'message' => 'Segredos obrigatórios ausentes para o gateway '.$current->gateway,
                    'errors' => ['secrets' => $missing],
                ], 422);
            }
            $secrets = $this->sanitize($request->input('secrets'));
        }

        $updated = new CarrierCredential(
            id: $current->id,
            companyId: $current->companyId,
            carrierId: $current->carrierId,
            gateway: $current->gateway,
            secrets: $secrets,
            active: (bool) $request->input('active', $current->active),
            createdAt: $current->createdAt,
        );

        $this->repository->save($updated);

        return response()->json(['data' => $this->present($updated)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return response()->json(['message' => 'Apenas administradores podem configurar credenciais'], 403);
        }

        $companyId = $request->attributes->get('company_id');

        if (! $this->repository->findById($companyId, $id)) {
            return response()->json(['message' => 'Credencial não encontrada'], 404);
        }

        $this->repository->delete($companyId, $id);

        return response()->json(['message' => 'Credencial removida']);
    }

    /** Sem segredo algum: apenas quais chaves estão preenchidas. */
    private function present(CarrierCredential $credential): array
    {
        return [
            'id' => $credential->id,
            'carrier_id' => $credential->carrierId,
            'gateway' => $credential->gateway,
            'active' => $credential->active,
            'configured_secrets' => array_keys($credential->secrets),
            'created_at' => $credential->createdAt,
            'updated_at' => $credential->updatedAt,
        ];
    }

    /** @return array<int,string> chaves exigidas que vieram ausentes ou vazias */
    private function missingSecrets(string $gateway, mixed $secrets): array
    {
        $secrets = is_array($secrets) ? $secrets : [];

        foreach ($this->gateways as $candidate) {
            if ($candidate->name() !== $gateway) {
                continue;
            }

            return array_values(array_filter(
                $candidate->requiredSecrets(),
                fn (string $key) => ! isset($secrets[$key]) || trim((string) $secrets[$key]) === ''
            ));
        }

        return [];
    }

    /** @return array<string,string> */
    private function sanitize(mixed $secrets): array
    {
        $clean = [];

        foreach (is_array($secrets) ? $secrets : [] as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $clean[$key] = (string) $value;
            }
        }

        return $clean;
    }

    /** @return array<int,string> */
    private function gatewayNames(): array
    {
        $names = [];
        foreach ($this->gateways as $gateway) {
            $names[] = $gateway->name();
        }

        return $names;
    }

    private function isAdmin(Request $request): bool
    {
        return $request->attributes->get('user_role') === Role::ADMIN;
    }
}
