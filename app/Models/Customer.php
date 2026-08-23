<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'issuer_code',
    'doc_type',
    'doc_number',
    'name',
])]
class Customer extends Model
{
    public const string DOC_OTHER = '0';

    public const string DOC_DNI = '1';

    public const string DOC_RUC = '6';

    public const string FINAL_CONSUMER_DOC_NUMBER = '00000000';

    /** @var list<string> */
    public const array V1_DOC_TYPES = [
        self::DOC_OTHER,
        self::DOC_DNI,
        self::DOC_RUC,
    ];

    /** @use HasFactory<CustomerFactory> */
    use HasFactory, HasUuids;

    protected $primaryKey = 'code';

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Issuer::class, 'issuer_code', 'code');
    }

    public function isFinalConsumer(): bool
    {
        return $this->doc_type === self::DOC_OTHER
            && $this->doc_number === self::FINAL_CONSUMER_DOC_NUMBER;
    }
}
