<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Locale
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        /*LOCALE SET */
        if ($request->has('lang') && is_string($request->input('lang'))) {
            $locale = $request->string('lang','en');
            App::setLocale($locale);
        } elseif (auth()->guard('contact')->user()) {
            App::setLocale(auth()->guard('contact')->user()->client()->setEagerLoads([])->first()->locale());
        } elseif (auth()->user()) {
            try {
                App::setLocale(auth()->user()->company()->getLocale());
            } catch (\Exception $e) {
            }
        } else {
            App::setLocale(config('ninja.i18n.locale'));
        }

        return $next($request);
    }
}
