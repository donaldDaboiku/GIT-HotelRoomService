<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Room;
use App\Services\QrCodeService;
use Illuminate\Console\Command;

class PrintRoomLinks extends Command
{
    protected $signature = 'hotel:links';

    protected $description = 'Print active guest QR links for every room';

    public function handle(QrCodeService $qr): int
    {
        $rooms = Room::query()->with(['hotel', 'activeQr'])->whereHas('activeQr')->orderBy('hotel_id')->orderBy('number')->get();
        if ($rooms->isEmpty()) {
            $this->warn('No active room QR codes yet.');

            return self::SUCCESS;
        }

        foreach ($rooms as $room) {
            $this->line($room->hotel->name.' · room '.$room->number.' · '.$qr->url($room->hotel, $room->activeQr));
        }

        return self::SUCCESS;
    }
}
