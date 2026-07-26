<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreasuryTransaction extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'amount_base' => 'decimal:2',
            'tx_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Available = active in − active out. Reserved sits apart until released.
     * Base figures use the locked rates; `currencies` breaks the same pools
     * down by original currency so mixed money is never ambiguous.
     */
    public static function summary(): array
    {
        $rows = static::query()->get();
        $base = Currency::where('is_base', true)->value('code') ?? 'AFN';

        $in = fn ($status) => (float) $rows->where('direction', 'in')->where('status', $status)->sum('amount_base');
        $out = (float) $rows->where('direction', 'out')->where('status', 'active')->sum('amount_base');

        $byCurrency = function ($subset) {
            $map = [];
            foreach ($subset as $r) {
                $map[$r->currency] = round(($map[$r->currency] ?? 0) + ($r->direction === 'in' ? 1 : -1) * (float) $r->amount, 2);
            }

            return array_filter($map, fn ($v) => abs($v) > 0.009);
        };

        return [
            'base' => $base,
            'available' => round($in('active') - $out, 2),
            'reserved' => round($in('reserved'), 2),
            'total' => round($in('active') - $out + $in('reserved'), 2),
            'currencies' => [
                'available' => $byCurrency($rows->where('status', 'active')),
                'reserved' => $byCurrency($rows->where('status', 'reserved')),
            ],
        ];
    }
}
