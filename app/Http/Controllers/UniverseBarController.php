<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UniverseBarController extends Controller
{
    public function render(Request $request)
    {
        $allowedOrigins = [
            'magento-opensource.com',
            'docs.magento-opensource.com',
            'magentoassociation.org',
            '*.magentoassociation.org',
            'meet-magento.com',
            'forger.magento-opensource.com',
            '*.ddev.site',
        ];

        $origin = $request->headers->get('Origin');

        if (!$this->isOriginAllowed($origin, $allowedOrigins, $request)) {
            return response('Forbidden', 403);
        }

        $html = view('components.universe-bar')->render();

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('Access-Control-Allow-Origin', $origin);
    }

    private function isOriginAllowed(?string $origin, array $allowedOrigins, Request $request): bool
    {
        // DDEV Fallback
        if (str_contains($request->getHost(), 'ddev.site')) {
            return true;
        }
        if (!$origin) {
            return false;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        foreach ($allowedOrigins as $allowed) {
            if ($allowed === $host) {
                return true;
            }
            if (Str::startsWith($allowed, '*.') && Str::endsWith($host, substr($allowed, 1))) {
                return true;
            }
        }

        return false;
    }
}
