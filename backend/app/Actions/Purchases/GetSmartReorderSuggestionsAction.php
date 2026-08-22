<?php

declare(strict_types=1);

namespace App\Actions\Purchases;

use App\Services\ReorderAssistantService;

final class GetSmartReorderSuggestionsAction
{
    public function __construct(
        private readonly ReorderAssistantService $reorderAssistantService
    ) {}

    /**
     * Get Smart Reorder Suggestions and Analytics
     */
    public function execute(?int $storeId = null, int $analysisDays = 14, int $targetCoverDays = 15, string $urgency = 'all', string $search = ''): array
    {
        $data = $this->reorderAssistantService->getReorderSuggestions(
            storeId: $storeId,
            analysisDays: $analysisDays,
            targetCoverDays: $targetCoverDays
        );

        $suggestions = collect($data['suggestions'] ?? []);

        if ($search !== '') {
            $suggestions = $suggestions->filter(fn($it) => 
                str_contains(mb_strtolower($it['name'] ?? ''), mb_strtolower($search)) ||
                str_contains(mb_strtolower($it['code'] ?? ''), mb_strtolower($search))
            );
        }

        if ($urgency !== 'all') {
            $suggestions = $suggestions->where('urgency', $urgency);
        }

        return [
            'critical_count'       => $data['critical_count'] ?? 0,
            'warning_count'        => $data['warning_count'] ?? 0,
            'safe_count'           => $data['safe_count'] ?? 0,
            'total_estimated_cost' => (float)($data['total_estimated_cost'] ?? 0),
            'suggestions'          => $suggestions->values()->all(),
        ];
    }
}
