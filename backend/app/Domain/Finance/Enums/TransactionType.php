<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum TransactionType: string
{
    case Income = 'INCOME';
    case Expense = 'EXPENSE';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Pemasukan',
            self::Expense => 'Pengeluaran',
        };
    }
}
