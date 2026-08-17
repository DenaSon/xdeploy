<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Models\SupportMessageAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ShowSupportMessageAttachmentController
{
    public function __invoke(
        Request $request,
        SupportMessageAttachment $attachment,
    ): StreamedResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $attachment->loadMissing(
            'supportMessage.supportRequest',
        );

        $supportRequest = $attachment
            ->supportMessage
            ->supportRequest;

        abort_unless(
            $user->isAdmin()
            || $supportRequest->isOwnedBy($user),
            404,
        );

        $disk = Storage::disk($attachment->disk);

        abort_unless(
            $disk->exists($attachment->path),
            404,
        );

        return $disk->response(
            $attachment->path,
            null,
            [
                'Content-Type' => $attachment->mime_type,
                'Cache-Control' => 'no-store, private, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
