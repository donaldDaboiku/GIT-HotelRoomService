<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QrTokenStatus;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomQrToken;
use App\Models\User;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class QrCodeService
{
    public function issue(Room $room, User $actor, ?string $ip = null): RoomQrToken
    {
        $existing = $room->activeQr()->first();
        if ($existing instanceof RoomQrToken) {
            return $existing;
        }

        return $this->create($room, $actor, 'qr.issued', $ip);
    }

    public function reissue(Room $room, User $actor, ?string $ip = null): RoomQrToken
    {
        return DB::transaction(function () use ($room, $actor, $ip) {
            $this->revokeActive($room, $actor, $ip, false);

            return $this->create($room, $actor, 'qr.reissued', $ip);
        });
    }

    public function revoke(Room $room, User $actor, ?string $ip = null): void
    {
        $this->revokeActive($room, $actor, $ip, true);
    }

    public function url(Hotel $hotel, RoomQrToken $token): string
    {
        return rtrim((string) config('hotel.frontend_url'), '/')
            .'/h/'.$hotel->public_code
            .'/r/'.$token->public_code;
    }

    public function svg(string $contents): string
    {
        $rendered = $this->render($contents, QRMarkupSVG::class);
        if (! str_contains($rendered, '<svg')) {
            throw new RuntimeException('QR renderer did not return SVG.');
        }

        return $rendered;
    }

    public function png(string $contents): string
    {
        $rendered = $this->render($contents, QRGdImagePNG::class);
        if (! str_starts_with($rendered, "\x89PNG")) {
            throw new RuntimeException('QR renderer did not return PNG.');
        }

        return $rendered;
    }

    private function render(string $contents, string $output): string
    {
        $options = new QROptions([
            'outputInterface' => $output,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'scale' => 8,
            'addQuietzone' => true,
        ]);

        $rendered = (new QRCode($options))->render($contents);
        if (! is_string($rendered)) {
            throw new RuntimeException('QR renderer returned an unexpected type.');
        }

        return $rendered;
    }

    private function create(Room $room, User $actor, string $action, ?string $ip): RoomQrToken
    {
        $token = RoomQrToken::query()->create([
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'public_code' => Str::lower(Str::random(32)),
            'status' => QrTokenStatus::Active,
        ]);

        AuditLogger::record($actor, $action, $token, ['room_id' => $room->id], $ip, $room->hotel_id);

        return $token;
    }

    private function revokeActive(Room $room, User $actor, ?string $ip, bool $auditWhenMissing): void
    {
        $active = $room->qrTokens()->where('status', QrTokenStatus::Active->value)->get();
        foreach ($active as $token) {
            $token->forceFill([
                'status' => QrTokenStatus::Revoked,
                'revoked_at' => now(),
            ])->save();
            AuditLogger::record($actor, 'qr.revoked', $token, ['room_id' => $room->id], $ip, $room->hotel_id);
        }

        if ($active->isEmpty() && $auditWhenMissing) {
            AuditLogger::record($actor, 'qr.revoke_noop', $room, [], $ip, $room->hotel_id);
        }
    }
}
