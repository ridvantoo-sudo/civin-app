<?php

namespace App\Http\Middleware;

use App\Support\SupportedLocales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        // Resolve Sanctum user early so locale applies before route auth middleware.
        $user = $request->user() ?? Auth::guard('sanctum')->user();
        if ($user !== null) {
            $stored = $user->settings()->where('key', 'locale')->value('value');
            $candidate = null;
            if (is_string($stored)) {
                $candidate = $stored;
            } elseif (is_scalar($stored)) {
                $candidate = (string) $stored;
            }

            if (SupportedLocales::isSupported($candidate)) {
                return SupportedLocales::normalize($candidate);
            }
        }

        $header = $request->header('Accept-Language');
        if (is_string($header) && $header !== '') {
            foreach (explode(',', $header) as $part) {
                $candidate = trim(explode(';', $part)[0]);
                if (SupportedLocales::isSupported($candidate)) {
                    return SupportedLocales::normalize($candidate);
                }
            }
        }

        $queryLocale = $request->query('locale');
        if (is_string($queryLocale) && SupportedLocales::isSupported($queryLocale)) {
            return SupportedLocales::normalize($queryLocale);
        }

        return SupportedLocales::normalize((string) config('app.locale', 'en'));
    }
}
