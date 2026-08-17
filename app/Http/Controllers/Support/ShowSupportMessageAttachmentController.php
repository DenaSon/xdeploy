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
    public function owner(
        Request $request,
        SupportMessageAttachment $attachment,
    ): StreamedResponse {
        $user = $this->user($request);

        $attachment->loadMissing(
            'supportMessage.supportRequest',
        );

        abort_unless(
            $attachment
                ->supportMessage
                ->supportRequest
                ->isOwnedBy($user),
            404,
        );

        return $this->stream($attachment);
    }

    public function admin(
        Request $request,
        SupportMessageAttachment $attachment,
    ): StreamedResponse {
        abort_unless(
            $this->user($request)->isAdmin(),
            404,
        );

        return $this->stream($attachment);
    }

    private function stream(
        SupportMessageAttachment $attachment,
    ): StreamedResponse {
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

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
