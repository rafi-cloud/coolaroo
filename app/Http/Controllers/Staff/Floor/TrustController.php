<?php

namespace App\Http\Controllers\Staff\Floor;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\TrustService;
use Illuminate\Http\JsonResponse;

class TrustController extends Controller
{
    public function __construct(private TrustService $trustService) {}

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($this->trustService->profile($customer));
    }
}
