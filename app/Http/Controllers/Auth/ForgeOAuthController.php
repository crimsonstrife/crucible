<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Controller for handling Forge OAuth authentication.
 *
 * @see https://github.com/crimsonstrife/forge
 */
class ForgeOAuthController extends Controller
{
    /**
     * Redirect the user to the Forge OAuth authorization page.
     */
    public function redirect(): RedirectResponse
    {
        // TODO: Implement Forge OAuth redirect logic
        // This is a placeholder for the Forge OAuth integration
        // See: https://github.com/crimsonstrife/forge

        return redirect()->route('login')
            ->with('info', 'Forge OAuth is not yet configured.');
    }

    /**
     * Handle the callback from Forge OAuth.
     */
    public function callback(Request $request): RedirectResponse
    {
        // TODO: Implement Forge OAuth callback logic
        // This is a placeholder for the Forge OAuth integration
        // See: https://github.com/crimsonstrife/forge

        return redirect()->route('login')
            ->with('info', 'Forge OAuth is not yet configured.');
    }
}
