<?php

namespace App\Http\Controllers\Api;

use App\Domain\Repositories\CompanyRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CompanyController extends Controller
{
    public function __construct(private CompanyRepository $companyRepository) {}

    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');

        if ($id !== $companyId) {
            return response()->json(['message' => 'Acesso negado'], 403);
        }

        $company = $this->companyRepository->findById($id);

        if (!$company) {
            return response()->json(['message' => 'Empresa não encontrada'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'cnpj' => $company->cnpj,
                'plan' => $company->plan,
                'status' => $company->status,
            ],
        ]);
    }
}
