<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function guest(Request $request, int $attachment): StreamedResponse
    {
        $token = $request->attributes->get('guestToken');
        $file = Attachment::query()->whereKey($attachment)->where('hotel_id', $token->hotel_id)->first();
        abort_if($file === null, 404);
        abort_unless((int) $file->request()->value('guest_access_token_id') === (int) $token->id, 404);

        return $this->download($file);
    }

    public function staff(Request $request, int $attachment): StreamedResponse
    {
        $file = Attachment::query()->with('request')->whereKey($attachment)->first();
        abort_if($file === null || $file->request === null, 404);
        abort_unless(Access::canView($request->user(), $file->request), 404);

        return $this->download($file);
    }

    private function download(Attachment $file): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($file->path), 404);
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->original_name) ?: 'attachment';

        return Storage::disk('local')->download($file->path, $name, [
            'Content-Type' => $file->mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
