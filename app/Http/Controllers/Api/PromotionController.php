<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    protected PromotionService $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    /**
     * Validate a promotion code
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'subtotal' => 'nullable|numeric|min:0',
        ]);

        $validation = $this->promotionService->validatePromotionCode($request->code);
        
        if ($validation['valid']) {
            $discount = $this->promotionService->calculateDiscount(
                $validation['coupon'], 
                $request->subtotal ?? 0
            );

            return response()->json([
                'valid' => true,
                'discount' => $discount,
                'coupon' => [
                    'name' => $validation['coupon']->name,
                    'duration' => $validation['coupon']->duration,
                    'amount_off' => $validation['coupon']->amount_off,
                    'percent_off' => $validation['coupon']->percent_off,
                ],
            ]);
        }

        return response()->json([
            'valid' => false,
            'error' => $validation['error'],
        ], 400);
    }
}
