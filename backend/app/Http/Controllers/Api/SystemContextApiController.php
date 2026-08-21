<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\System\GetSystemContextAction;
use App\Actions\System\GetTranslationsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SystemContextApiController extends Controller
{
    public function __construct(
        private readonly GetSystemContextAction $getSystemContextAction,
        private readonly GetTranslationsAction $getTranslationsAction
    ) {}

    /**
     * Get consolidated bootstrap context for Vue 3 SPA initialization
     */
    public function context(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401);
        }

        $data = $this->getSystemContextAction->execute($user, $request);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Get translation dictionary for active or requested locale
     */
    public function translations(Request $request): JsonResponse
    {
        $locale = $request->query('locale') ?: $request->header('X-Locale') ?: app()->getLocale();
        $translations = $this->getTranslationsAction->execute($locale);

        return response()->json([
            'success' => true,
            'locale'  => $locale,
            'data'    => $translations,
        ], 200);
    }
}
