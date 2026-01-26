<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TimezoneController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ]);

        $user = Auth::user();
        $user->timezone = $validated['timezone'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Timezone updated successfully',
            'timezone' => $user->timezone,
        ]);
    }
}
