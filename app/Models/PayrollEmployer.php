<?php

namespace App\Models;

use Database\Factories\PayrollEmployerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $work_location
 * @property string $jurisdiction
 */
#[Fillable(['id', 'name', 'work_location', 'jurisdiction'])]
class PayrollEmployer extends Model
{
    /** @use HasFactory<PayrollEmployerFactory> */
    use HasFactory;

    public const ID = 1;

    public const JURISDICTIONS = ['Peninsular Malaysia', 'Labuan', 'Sabah', 'Sarawak'];

    /** @return HasMany<PayrollEmployee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(PayrollEmployee::class);
    }

    /** @return HasMany<PayrollRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
