<?php

namespace App\Enums;

enum ConversionStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Antrian',
            self::Processing => 'Memproses',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Tailwind classes for the status badge. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Queued => 'bg-slate-100 text-slate-700 ring-slate-200',
            self::Processing => 'bg-blue-50 text-blue-700 ring-blue-200',
            self::Completed => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::Failed => 'bg-red-50 text-red-700 ring-red-200',
            self::Cancelled => 'bg-amber-50 text-amber-700 ring-amber-200',
        };
    }
}
