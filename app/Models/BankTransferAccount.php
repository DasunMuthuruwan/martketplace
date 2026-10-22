<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Where sellers in one country send a bank transfer for a boost (ADR-026, D3: manual transfer
 * first). Shown with the order reference; off until the Owner switches it on.
 *
 * @property int $id
 * @property int $country_id
 * @property string $bank_name
 * @property string $account_name
 * @property string $account_number
 * @property string|null $branch
 * @property string|null $swift_code
 * @property string|null $instructions
 * @property bool $is_active
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BankTransferAccount extends Model
{
    /** @var list<string> */
    protected $fillable = ['country_id', 'bank_name', 'account_name', 'account_number', 'branch', 'swift_code', 'instructions', 'is_active'];

    protected function casts(): array
    {
        return ['country_id' => 'integer', 'is_active' => 'boolean'];
    }
}
