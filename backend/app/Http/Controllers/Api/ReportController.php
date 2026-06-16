<?php

namespace App\Http\Controllers\Api;

use App\Domain\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function dashboard(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $data = $this->reportService->dashboard($companyId);

        return response()->json(['data' => $data]);
    }

    public function detailed(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $data = $this->reportService->detailed($companyId);

        return response()->json(['data' => $data]);
    }

    public function carrierPerformance(string $carrierId, Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('company_id');
        $data = $this->reportService->carrierPerformance($carrierId, $companyId);

        return response()->json(['data' => $data]);
    }
}
