<?php

declare(strict_types=1);

namespace App\DTOs\Expenses;

final class ExpenseDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $category,
        public readonly string $cost_center,
        public readonly string $amount,
        public readonly string $expense_date,
        public readonly string $payment_method = 'cash',
        public readonly ?int $store_id = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromArray(array $data, ?int $storeId = null): self
    {
        return new self(
            title: (string)$data['title'],
            category: (string)$data['category'],
            cost_center: (string)($data['cost_center'] ?? 'operational'),
            amount: (string)$data['amount'],
            expense_date: (string)$data['expense_date'],
            payment_method: (string)($data['payment_method'] ?? 'cash'),
            store_id: isset($data['store_id']) ? (int)$data['store_id'] : $storeId,
            notes: isset($data['notes']) && $data['notes'] !== '' ? (string)$data['notes'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'title'          => $this->title,
            'category'       => $this->category,
            'cost_center'    => $this->cost_center,
            'amount'         => $this->amount,
            'expense_date'   => $this->expense_date,
            'payment_method' => $this->payment_method,
            'store_id'       => $this->store_id,
            'notes'          => $this->notes,
        ];
    }
}
