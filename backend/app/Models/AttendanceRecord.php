<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use BelongsToCompany, HasAttachments;

    protected $guarded = ['id'];

    // date:Y-m-d so it stores/serialises as a pure date — date-equality and
    // whereBetween range queries (attendance sheet, payroll) rely on this.
    protected function casts(): array { return ['att_date' => 'date:Y-m-d']; }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }

}
