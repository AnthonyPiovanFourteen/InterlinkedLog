<?php

namespace App\Http\Controllers\Api;

use App\Domain\Entities\Quotation;
use App\Domain\Repositories\QuotationRepository;
use App\Domain\Services\QuotationEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class QuotationController extends Controller
{
    public function __construct(
        private QuotationRepository $repository,
        private QuotationEngineService $engine,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $filters = [];

        if ($request->has('status')) $filters['status'] = $request->input('status');
        if ($request->has('search')) $filters['search'] = $request->input('search');

        $quotations = $this->repository->findByCompany($companyId, $filters);

        $data = array_map(fn(Quotation $q) => [
            'id' => $q->id,
            'nf_number' => $q->nfNumber,
            'destination_city' => $q->destinationCity,
            'destination_state' => $q->destinationState,
            'weight' => $q->weight,
            'cargo_value' => $q->cargoValue,
            'status' => $q->status,
            'results_count' => count($q->results),
            'best_value' => !empty($q->results) ? collect($q->results)->min('final_value') : null,
            'valid_until' => $q->validUntil,
            'created_at' => $q->createdAt,
        ], $quotations);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nf_number' => 'required|string|max:20',
            'sender_cnpj' => 'required|string|max:18',
            'receiver_cnpj' => 'required|string|max:18',
            'origin_cep' => 'required|string|max:9',
            'destination_cep' => 'required|string|max:9',
            'weight' => 'required|numeric|min:0.1',
            'boxes' => 'required|integer|min:1',
            'volume' => 'required|numeric|min:0.001',
            'cargo_value' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $companyId = $request->attributes->get('company_id');
        $userId = $request->attributes->get('user_id');

        $originCity = $this->engine->cepToCity($request->input('origin_cep'));
        $destCity = $this->engine->cepToCity($request->input('destination_cep'));

        $quotation = Quotation::create(
            id: uuid_create(),
            companyId: $companyId, userId: $userId,
            nfNumber: $request->input('nf_number'),
            senderCnpj: $request->input('sender_cnpj'),
            receiverCnpj: $request->input('receiver_cnpj'),
            originCep: $request->input('origin_cep'),
            destinationCep: $request->input('destination_cep'),
            originCity: $originCity[0],
            destinationCity: $destCity[0],
            destinationState: $destCity[1],
            weight: (float) $request->input('weight'),
            boxes: (int) $request->input('boxes'),
            volume: (float) $request->input('volume'),
            cargoValue: (float) $request->input('cargo_value'),
            validUntil: now()->addDays(7)->format('Y-m-d'),
        );

        $results = $this->engine->process($quotation);

        $quotation = new Quotation(
            id: $quotation->id, companyId: $quotation->companyId,
            userId: $quotation->userId,
            nfNumber: $quotation->nfNumber, senderCnpj: $quotation->senderCnpj,
            receiverCnpj: $quotation->receiverCnpj,
            originCep: $quotation->originCep, destinationCep: $quotation->destinationCep,
            originCity: $quotation->originCity, destinationCity: $quotation->destinationCity,
            destinationState: $quotation->destinationState,
            weight: $quotation->weight, boxes: $quotation->boxes,
            volume: $quotation->volume, cargoValue: $quotation->cargoValue,
            status: $quotation->status, results: $results,
            validUntil: $quotation->validUntil,
            createdAt: $quotation->createdAt,
            updatedAt: now()->toIso8601String(),
        );

        $this->repository->save($quotation);

        return response()->json([
            'data' => [
                'id' => $quotation->id,
                'nf_number' => $quotation->nfNumber,
                'status' => $quotation->status,
                'valid_until' => $quotation->validUntil,
                'results' => $quotation->results,
            ],
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $quotation = $this->repository->findById($id);

        if (!$quotation || $quotation->companyId !== $companyId) {
            return response()->json(['message' => 'Cotação não encontrada'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $quotation->id, 'nf_number' => $quotation->nfNumber,
                'sender_cnpj' => $quotation->senderCnpj,
                'receiver_cnpj' => $quotation->receiverCnpj,
                'origin_cep' => $quotation->originCep,
                'destination_cep' => $quotation->destinationCep,
                'origin_city' => $quotation->originCity,
                'destination_city' => $quotation->destinationCity,
                'destination_state' => $quotation->destinationState,
                'weight' => $quotation->weight, 'boxes' => $quotation->boxes,
                'volume' => $quotation->volume, 'cargo_value' => $quotation->cargoValue,
                'status' => $quotation->status, 'valid_until' => $quotation->validUntil,
                'results' => $quotation->results,
                'created_at' => $quotation->createdAt,
            ],
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $quotation = $this->repository->findById($id);

        if (!$quotation || $quotation->companyId !== $companyId) {
            return response()->json(['message' => 'Cotação não encontrada'], 404);
        }

        if (!in_array($quotation->status, [Quotation::STATUS_VALID, Quotation::STATUS_DRAFT])) {
            return response()->json(['message' => 'Cotação não pode ser cancelada'], 422);
        }

        $updated = new Quotation(
            id: $quotation->id, companyId: $quotation->companyId,
            userId: $quotation->userId,
            nfNumber: $quotation->nfNumber, senderCnpj: $quotation->senderCnpj,
            receiverCnpj: $quotation->receiverCnpj,
            originCep: $quotation->originCep, destinationCep: $quotation->destinationCep,
            originCity: $quotation->originCity, destinationCity: $quotation->destinationCity,
            destinationState: $quotation->destinationState,
            weight: $quotation->weight, boxes: $quotation->boxes,
            volume: $quotation->volume, cargoValue: $quotation->cargoValue,
            status: Quotation::STATUS_CANCELLED, results: $quotation->results,
            validUntil: $quotation->validUntil,
            createdAt: $quotation->createdAt,
            updatedAt: now()->toIso8601String(),
        );

        $this->repository->save($updated);

        return response()->json(['message' => 'Cotação cancelada']);
    }

    public function parseXml(Request $request): JsonResponse
    {
        $file = $request->file('xml');
        if (!$file || !$file->isValid()) {
            return response()->json(['message' => 'Arquivo XML inválido'], 422);
        }

        $xml = simplexml_load_file($file->getPathname());
        if (!$xml) {
            return response()->json(['message' => 'XML malformado'], 422);
        }

        $ns = $xml->getNamespaces(true);
        $nfe = $xml->NFe ?? $xml->children($ns[''] ?? '')->NFe ?? null;
        if (!$nfe || !$nfe->count()) {
            return response()->json(['message' => 'XML não é uma NF-e válida'], 422);
        }
        $infNFe = $nfe->infNFe ?? $nfe->children($ns[''] ?? '')->infNFe ?? null;
        if (!$infNFe || !$infNFe->count()) {
            return response()->json(['message' => 'NF-e sem infNFe'], 422);
        }

        $extract = function(string $path) use ($infNFe) {
            $parts = explode('/', $path);
            $node = $infNFe;
            foreach ($parts as $part) {
                $node = $node->{$part} ?? null;
                if (!$node) return null;
            }
            return (string) $node;
        };

        $boxes = (int) ($extract('transp/vol/qVol') ?: 1);
        $weight = (float) ($extract('transp/vol/pesoB') ?: 0);
        $volume = $weight > 0 ? round($weight / 300, 3) : 0.001;

        return response()->json([
            'data' => [
                'nf_number' => $extract('ide/nNF') ?: '',
                'sender_cnpj' => $extract('emit/CNPJ') ?: '',
                'receiver_cnpj' => $extract('dest/CNPJ') ?: '',
                'origin_cep' => $extract('emit/enderEmit/CEP') ?: '',
                'destination_cep' => $extract('dest/enderDest/CEP') ?: '',
                'weight' => $weight,
                'boxes' => $boxes,
                'volume' => $volume,
                'cargo_value' => (float) ($extract('total/ICMSTot/vProd') ?: 0),
            ],
        ]);
    }
}
