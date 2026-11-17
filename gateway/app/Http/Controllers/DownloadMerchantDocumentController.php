<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MerchantDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a KYC document as a forced download, so a crafted file can never render in the browser.
 */
class DownloadMerchantDocumentController
{
    public function __invoke(MerchantDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        $disk = Storage::disk(MerchantDocument::disk());

        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, $document->downloadName(), [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
