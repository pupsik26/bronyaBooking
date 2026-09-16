<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService
    ) {}

    public function attach(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $currentMaster = $request->attributes->get('current_master');

        if (!$currentMaster) {
            return response()->json(['error' => 'Мастер не найден'], 404);
        }

        $referral = $this->referralService->registerReferral($currentMaster, $data['code']);

        if (!$referral) {
            return response()->json(['error' => 'Реферальный код не найден или нельзя быть рефералом самого себя'], 400);
        }

        return response()->json([
            'message' => $referral->wasRecentlyCreated ? 'Мастер успешно привязан' : 'Мастер уже был привязан ранее',
            'is_new' => $referral->wasRecentlyCreated,
        ], $referral->wasRecentlyCreated ? 201 : 200);
    }

    public function my(Request $request)
    {
        $currentMaster = $request->attributes->get('current_master');

        if (!$currentMaster) {
            return response()->json(['error' => 'Мастер не найден'], 404);
        }

        $referrals = Referral::where('referrer_master_id', $currentMaster->id)
            ->with(['referredMaster', 'earning'])
            ->get()
            ->map(fn (Referral $ref) => [
                'name' => $ref->referredMaster->name,
                'attached_at' => $ref->created_at->toDateTimeString(),
                'is_counted' => $ref->status === Referral::STATUS_REWARDED,
                'accrued_amount' => $ref->earning?->amount ?? 0,
            ]);

        return response()->json(['data' => $referrals]);
    }

    public function earnings(Request $request)
    {
        $currentMaster = $request->attributes->get('current_master');

        if (!$currentMaster) {
            return response()->json(['error' => 'Мастер не найден'], 404);
        }

        $totalAccrued = ReferralEarning::where('referrer_master_id', $currentMaster->id)->sum('amount');
        $pending = ReferralEarning::where('referrer_master_id', $currentMaster->id)
            ->where('status', ReferralEarning::STATUS_PENDING)->sum('amount');
        $paid = ReferralEarning::where('referrer_master_id', $currentMaster->id)
            ->where('status', ReferralEarning::STATUS_PAID)->sum('amount');

        $countedCount = Referral::where('referrer_master_id', $currentMaster->id)
            ->where('status', Referral::STATUS_REWARDED)->count();

        return response()->json([
            'data' => [
                'total_accrued' => (int) $totalAccrued,
                'pending' => (int) $pending,
                'paid' => (int) $paid,
                'counted_referrals_count' => (int) $countedCount,
            ]
        ]);
    }
}