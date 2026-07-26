<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'basic' => 'decimal:2', 'allowances' => 'decimal:2', 'housing' => 'decimal:2', 'transport' => 'decimal:2',
            'bonus' => 'decimal:2', 'overtime' => 'decimal:2', 'tax' => 'decimal:2', 'loan' => 'decimal:2',
            'advance' => 'decimal:2', 'deductions' => 'decimal:2', 'gross' => 'decimal:2', 'net' => 'decimal:2',
        ];
    }

    public function run(): BelongsTo { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }

}
