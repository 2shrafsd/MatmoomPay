<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A KYC file kept on the private disk. It is only ever served through an authorized download.
 *
 * @property int $id
 * @property string $public_id
 * @property int $merchant_id
 * @property DocumentType $type
 * @property string $original_name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property string $sha256
 * @property DocumentStatus $status
 * @property string|null $review_note
 * @property int|null $uploaded_by
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property-read Merchant $merchant
 */
#[Fillable(['merchant_id', 'type', 'original_name', 'path', 'mime_type', 'size', 'sha256', 'uploaded_by'])]
#[Hidden(['path'])]
class MerchantDocument extends Model
{
    use HasPublicId;

    /**
     * Private disk for identity documents. Use an S3-compatible disk when the app runs on more than one server.
     */
    public static function disk(): string
    {
        return (string) config('gateway.documents_disk');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function publicIdPrefix(): string
    {
        return 'doc_';
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * A download name built from our own data, never from the uploaded file name.
     */
    public function downloadName(): string
    {
        return $this->type->value.'-'.$this->public_id.'.'.pathinfo($this->path, PATHINFO_EXTENSION);
    }
}
