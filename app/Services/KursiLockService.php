<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KursiLockService
{
    public static function lock(int $idJadwal, array $noKursi, int $menit = 15)
    {
        $lockedUntil = Carbon::now()->addMinutes($menit);

        $kursiMap = DB::table('kursi')
            ->whereIn('no_kursi', $noKursi)
            ->pluck('id_kursi', 'no_kursi');

        foreach ($noKursi as $no) {
            if (!isset($kursiMap[$no])) continue;

            DB::table('kursi_locks')->updateOrInsert(
                [
                    'id_jadwal' => $idJadwal,
                    'id_kursi'  => $kursiMap[$no],
                ],
                [
                    'locked_until' => $lockedUntil,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return $lockedUntil;
    }

    public static function unlock(int $idJadwal, array $noKursi = [])
    {
        $query = DB::table('kursi_locks')->where('id_jadwal', $idJadwal);

        if (!empty($noKursi)) {
            $ids = DB::table('kursi')
                ->whereIn('no_kursi', $noKursi)
                ->pluck('id_kursi');

            $query->whereIn('id_kursi', $ids);
        }

        return $query->delete();
    }

    public static function unlockExpired()
    {
        return DB::table('kursi_locks')
            ->where('locked_until', '<', now())
            ->delete();
    }
}
