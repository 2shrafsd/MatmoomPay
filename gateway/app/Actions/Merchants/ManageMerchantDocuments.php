<?php

declare(strict_types=1);

namespace App\Actions\Merchants;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\MerchantRole;
use App\Exceptions\GatewayException;
use App\Models\Merchant;
use App\Models\MerchantDocument;
use App\Models\User;
use App\Notifications\DocumentRejected;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Upload, removal and review of merchant KYC documents.
 */
final readonly class ManageMerchantDocuments
{
    public const MAX_DOCUMENTS = 15;

    /**
     * Content types we accept after sniffing the file itself, mapped to the extension we store.
     *
     * @var array<string, string>
     */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function upload(Merchant $merchant, DocumentType $type, UploadedFile $file, User $actor): MerchantDocument
    {
        if ($merchant->documents()->count() >= self::MAX_DOCUMENTS) {
            throw GatewayException::cannotUpdate(__('You reached the limit of :count documents. Remove an old one first.', ['count' => self::MAX_DOCUMENTS]));
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());
        $extension = self::ALLOWED_MIME_TYPES[$mime] ?? throw GatewayException::invalid('file', __('Upload a PDF, JPG or PNG file.'));

        $path = $file->storeAs('kyc/'.$merchant->public_id, Str::random(40).'.'.$extension, MerchantDocument::disk());

        if ($path === false) {
            throw GatewayException::cannotUpdate(__('The file could not be saved. Try again.'));
        }

        $document = MerchantDocument::create([
            'merchant_id' => $merchant->id,
            'type' => $type,
            'original_name' => Str::limit(preg_replace('/[^\pL\pN ._-]/u', '', $file->getClientOriginalName()) ?: 'document', 110, ''),
            'path' => $path,
            'mime_type' => $mime,
            'size' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', (string) $file->getRealPath()) ?: '',
            'uploaded_by' => $actor->id,
        ]);

        $this->recordAuditLog->handle($actor, 'merchant.document_uploaded', $merchant, $document, ['type' => $type->value]);

        return $document;
    }

    public function remove(MerchantDocument $document, User $actor): void
    {
        if ($document->status === DocumentStatus::Approved) {
            throw GatewayException::cannotUpdate(__('Accepted documents can no longer be removed.'));
        }

        Storage::disk(MerchantDocument::disk())->delete($document->path);
        $document->delete();

        $this->recordAuditLog->handle($actor, 'merchant.document_removed', $document->merchant, null, ['type' => $document->type->value]);
    }

    public function review(MerchantDocument $document, DocumentStatus $status, ?string $note, User $admin): MerchantDocument
    {
        if (! $admin->isPlatformAdmin() || $status === DocumentStatus::Pending) {
            throw new InvalidArgumentException('Only platform administrators can accept or reject documents.');
        }

        $document->status = $status;
        $document->review_note = $note;
        $document->reviewed_by = $admin->id;
        $document->reviewed_at = now();
        $document->save();

        $this->recordAuditLog->handle($admin, 'merchant.document_reviewed', $document->merchant, $document, ['status' => $status->value]);

        if ($status === DocumentStatus::Rejected) {
            $managers = $document->merchant->users()->wherePivotIn('role', [MerchantRole::Owner->value, MerchantRole::Admin->value])->get();
            Notification::send($managers, new DocumentRejected($document));
        }

        return $document;
    }
}
