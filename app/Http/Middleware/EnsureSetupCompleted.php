<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to the app until the setup wizard has been completed for the
 * current app version.
 *
 * - If setup is NOT complete and the user is not already on the wizard, they
 *   are redirected to the setup wizard.
 * - If setup IS complete and they land on the wizard, they are redirected to
 *   the chat route (the post-setup home).
 *
 * This guarantees the app cannot reach a usable "ready to use" state before
 * the wizard's dependency checks/installation have finished.
 */
class EnsureSetupCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        $setupComplete = AppSetting::isSetupComplete();

        if (! $setupComplete && $routeName !== 'setup-wizard') {
            return redirect()->route('setup-wizard');
        }

        if ($setupComplete && $routeName === 'setup-wizard') {
            return redirect()->route('chat');
        }

        return $next($request);
    }
}
