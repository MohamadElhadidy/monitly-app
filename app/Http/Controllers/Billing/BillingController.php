<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('billing.index', [
            'user' => $user,
            'team' => $user->currentTeam,
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'plan'  => 'required|in:pro,team',
            'scope' => 'required|in:user,team',
        ]);

        $user  = $request->user();
        $scope = $request->string('scope')->toString();
        $plan  = $request->string('plan')->toString();

        if ($scope === 'team') {
            $team = $user->currentTeam;

            abort_unless($team, 403);
            abort_unless($team->user_id === $user->id, 403);

            $billable  = $team;
            $ownerType = 'team';
            $ownerId   = $team->id;
        } else {
            $billable  = $user;
            $ownerType = 'user';
            $ownerId   = $user->id;
        }

        $priceId = match ($plan) {
            'pro'  => config('billing.plans.pro.price_ids.0'),
            'team' => config('billing.plans.team.price_ids.0'),
        };

        abort_if(empty($priceId), 500, 'Price ID not configured');

        $checkout = $billable->checkout([$priceId => 1])
            ->customData([
                'owner_type' => $ownerType,
                'owner_id'   => $ownerId,
                'plan'       => $plan,
            ])
            ->returnTo(route('billing.success'));

        return view('billing.checkout', [
            'checkout' => $checkout,
            'plan'     => $plan,
            'scope'    => $scope,
        ]);
    }

    public function portal(Request $request)
    {
        return $request->user()->redirectToBillingPortal();
    }

    public function success()
    {
        return view('billing.success');
    }
}