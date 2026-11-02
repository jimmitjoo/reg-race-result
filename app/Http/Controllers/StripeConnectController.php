<?php

namespace App\Http\Controllers;

use App\Payments\StripeConnect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Where Stripe's onboarding sends the club back. Only for the signed-in user's own organizer. */
class StripeConnectController extends Controller
{
    public function back(Request $request, StripeConnect $stripe): RedirectResponse
    {
        $organizer = $request->user()->organizer;
        abort_unless($organizer, 403);
        $stripe->sync($organizer);

        return redirect()->route('payments.index');
    }

    /** The onboarding link expired or was used: make a new one. */
    public function refresh(Request $request, StripeConnect $stripe): RedirectResponse
    {
        $organizer = $request->user()->organizer;
        abort_unless($organizer, 403);

        return redirect()->away($stripe->onboardingUrl($organizer, route('payments.refresh'), route('payments.return')));
    }
}
