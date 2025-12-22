<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{DetailPemesanan, Jadwal, Kursi, Pembayaran, Pemesanan, Penumpang, Rute};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ApiPenumpangController extends Controller
{
    // Ambil data penumpang
    public function show($id)
    {
        $penumpang = Penumpang::find($id);
        if (!$penumpang) {
            return response()->json(['status' => false, 'message' => 'Penumpang tidak ditemukan'], 404);
        }
        return response()->json([
            'status' => true,
            'message' => 'Data penumpang berhasil diambil',
            'data' => $penumpang
        ]);
    }

    // Ambil semua rute
    public function getRute()
    {
        $rutes = Rute::select('id_rute', 'asal', 'tujuan', 'harga')->get();
        return response()->json([
            'status' => true,
            'message' => 'Data rute berhasil diambil',
            'data' => $rutes
        ]);
    }

    // Ambil jadwal berdasarkan rute dan tanggal
    public function getJam(Request $request)
    {
        // Hapus locks yang sudah expired dulu
        $this->autoUnlockExpiredSeats();

        $id_rute = $request->query('rute');
        $tanggal = $request->query('tanggal'); // format YYYY-MM-DD

        if (!$id_rute || !$tanggal) {
            return response()->json([
                'status' => true,
                'message' => 'Data jadwal tidak ditemukan',
                'data' => []
            ]);
        }

        // Ambil jadwal dengan relasi supir & kendaraan
        $jadwals = Jadwal::with(['supir', 'kendaraan'])
            ->where('id_rute', $id_rute)
            ->get(['id_jadwal', 'jam_keberangkatan', 'id_supir', 'id_kendaraan']);

        if ($jadwals->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'Jadwal tidak tersedia',
                'data' => []
            ]);
        }

        // Ambil kursi yang terisi berdasarkan pemesanan/pembayaran pada tanggal tersebut
        $kursiTerisiPerJadwal = DB::table('detail_pemesanan')
            ->join('pemesanan', 'detail_pemesanan.id_pemesanan', '=', 'pemesanan.id_pemesanan')
            ->leftJoin('pembayaran', 'pemesanan.id_pemesanan', '=', 'pembayaran.id_pemesanan')
            ->where('pemesanan.tanggal_keberangkatan', $tanggal)
            ->whereIn('pemesanan.id_jadwal', $jadwals->pluck('id_jadwal'))
            ->select(
                'pemesanan.id_jadwal',
                'detail_pemesanan.id_kursi',
                'pembayaran.status_konfirmasi'
            )
            ->get()
            ->groupBy('id_jadwal');

        // Ambil locks aktif dari tabel kursi_locks untuk jadwal yang relevan
        $jadwalIds = $jadwals->pluck('id_jadwal')->toArray();
        $locks = DB::table('kursi_locks')
            ->whereIn('id_jadwal', $jadwalIds)
            ->where('locked_until', '>', now())
            ->get()
            ->groupBy('id_jadwal');

        $totalKursi = Kursi::count() > 0 ? Kursi::count() : 15;
        $semuaKursi = Kursi::all(); // berisi id_kursi dan no_kursi

        $jadwals = $jadwals->map(function ($j) use ($kursiTerisiPerJadwal, $locks, $totalKursi, $semuaKursi) {
            $idJadwal = (int)$j->id_jadwal;

            // Ambil booking/pemesanan pada jadwal ini
            $terisi = $kursiTerisiPerJadwal->has($idJadwal)
                ? $kursiTerisiPerJadwal[$idJadwal]->toArray()
                : [];

            // Ambil locks aktif untuk jadwal ini (jika ada)
            $locksThis = $locks->has($idJadwal) ? $locks[$idJadwal]->toArray() : [];

            // Hitung bangku tersedia (mengabaikan seats yang status booking 'menunggu','ditempat','berhasil' atau locks aktif)
            $bookedCount = count(array_filter($terisi, function ($t) {
                return in_array($t->status_konfirmasi, ['menunggu', 'ditempat', 'berhasil']);
            }));
            $lockCount = count($locksThis);
            $bangkuTersedia = $totalKursi - ($bookedCount + $lockCount);

            // Susun status setiap kursi berdasarkan booking dan locks
            $kursiStatus = $semuaKursi->map(function ($k) use ($terisi, $locksThis) {
                $status = 'kosong'; // default
                $locked_until = null;

                // cek pemesanan yang memblok kursi (berdasarkan id_kursi)
                foreach ($terisi as $booking) {
                    if ($booking->id_kursi == $k->id_kursi) {
                        if (in_array($booking->status_konfirmasi, ['menunggu','ditempat','berhasil'])) {
                            $status = 'disable'; // sudah dipesan / menunggu
                        } elseif ($booking->status_konfirmasi == 'ditolak') {
                            $status = 'kosong';
                        }
                        // jika ketemu, stop loop
                        break;
                    }
                }

                // cek locks aktif (kursi yang dikunci sementara)
                foreach ($locksThis as $l) {
                    if ($l->id_kursi == $k->id_kursi) {
                        // overwrite menjadi disable kalau belum dipesan
                        $status = 'disable';
                        $locked_until = $l->locked_until;
                        break;
                    }
                }

                return [
                    'id_kursi' => $k->id_kursi,
                    'no_kursi' => $k->no_kursi,
                    'status' => $status,
                    'locked_until' => $locked_until,
                ];
            });

            return [
                'id_jadwal' => $j->id_jadwal,
                'jamKeberangkatan' => date('H:i', strtotime($j->jam_keberangkatan)),
                'supir' => $j->supir->nama_supir ?? null,
                'platBus' => $j->kendaraan->plat_nomor ?? null,
                'bangkuTersedia' => $bangkuTersedia,
                'kursi' => $kursiStatus
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Data jadwal berhasil diambil',
            'data' => $jadwals
        ]);
    }

    /**
     * Lock kursi sementara.
     * Menerima array 'kursi' yang berisi nomor kursi (no_kursi), sesuai yang dikirim frontend.
     */
    public function lockKursiSementara(Request $request)
    {
        $request->validate([
            'id_jadwal' => 'required|integer|exists:jadwal,id_jadwal',
            'kursi' => 'required|array|min:1',
            'kursi.*' => 'integer', // ini adalah no_kursi dari frontend
        ]);

        try {
            Log::info('Lock request masuk', $request->all());
            $now = Carbon::now();
            $lockedUntil = $now->copy()->addMinutes(15);

            // Ambil id_kursi berdasarkan no_kursi yang dikirim
            $noKursiArray = $request->kursi;
            $kursiRecords = Kursi::whereIn('no_kursi', $noKursiArray)->get()->keyBy('no_kursi');

            DB::beginTransaction();

            foreach ($noKursiArray as $noK) {
                if (!isset($kursiRecords[$noK])) {
                    // jika nomor kursi tidak ada di tabel kursi, skip atau throw
                    Log::warning("Nomor kursi tidak ditemukan: $noK");
                    continue;
                }
                $idKursi = $kursiRecords[$noK]->id_kursi;

                // Jika sudah ada lock aktif di kursi_locks untuk jadwal & kursi ini, update locked_until
                DB::table('kursi_locks')->updateOrInsert(
                    [
                        'id_jadwal' => $request->id_jadwal,
                        'id_kursi' => $idKursi,
                    ],
                    [
                        'locked_until' => $lockedUntil->toDateTimeString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Kursi dikunci sementara selama 15 menit.',
                'locked_until' => $lockedUntil->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lock kursi error: ' . $e->getMessage());
            return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function unlockKursiOtomatis(Request $request)
    {
        $request->validate([
            'id_jadwal' => 'required|integer|exists:jadwal,id_jadwal',
            'kursi' => 'nullable|array',
        ]);


        $query = DB::table('kursi_locks')->where('id_jadwal', $request->id_jadwal);

        if ($request->has('kursi')) {
            // kursi bisa berupa array no_kursi; maka konversi ke id_kursi
            $noKursi = $request->kursi;
            $ids = Kursi::whereIn('no_kursi', $noKursi)->pluck('id_kursi')->toArray();
            if (!empty($ids)) {
                $query->whereIn('id_kursi', $ids);
            } else {
                // tidak ada id_kursi yang cocok -> nothing to delete
                return response()->json([
                    'status' => true,
                    'message' => 'Tidak ada kursi yang dilepas.',
                ]);
            }
        }

        $deleted = $query->delete();

        return response()->json([
            'status' => true,
            'message' => $deleted > 0
                ? 'Kursi berhasil dilepas.'
                : 'Tidak ada kursi yang dilepas.',
        ]);
    }

    private function autoUnlockExpiredSeats()
    {
        DB::table('kursi_locks')->where('locked_until', '<', now())->delete();
    }

    public function store(Request $request)
    {
        // Validasi request
        $validated = $request->validate([
            'id_penumpang' => 'required|exists:penumpang,id',
            'id_jadwal' => 'required|exists:jadwal,id_jadwal',
            'tanggal' => 'required|date',
            'nama' => 'required|array|min:1',
            'nama.*' => 'string|max:255',
            'kursi' => 'required|array|min:1',
            'kursi.*' => 'integer', // ini diharapkan no_kursi atau id_kursi? anggap no_kursi
            'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $jadwal = Jadwal::with('rute')->findOrFail($validated['id_jadwal']);
            $harga = $jadwal->rute->harga ?? 0;

            // Buat pemesanan
            $pemesanan = Pemesanan::create([
                'id_penumpang' => $validated['id_penumpang'],
                'id_jadwal' => $jadwal->id_jadwal,
                'tanggal_pemesanan' => now()->format('Y-m-d'),
                'tanggal_keberangkatan' => $validated['tanggal'],
            ]);

            $totalBayar = 0;

            // Jika kursi yang dikirim adalah no_kursi, kita perlu konversi ke id_kursi
            $kursiNoList = $validated['kursi'];
            $kursiMap = Kursi::whereIn('no_kursi', $kursiNoList)->get()->keyBy('no_kursi');

            // Detail pemesanan (nama per penumpang)
            foreach ($validated['nama'] as $index => $nama) {
                $noKursi = $validated['kursi'][$index] ?? null;
                if (!$noKursi) continue;

                $kursiRecord = $kursiMap[$noKursi] ?? null;
                if (!$kursiRecord) {
                    // kursi tidak ditemukan: skip atau throw; di sini skip
                    continue;
                }
                $idKursi = $kursiRecord->id_kursi;

                // Buat data penumpang baru
                $penumpang = Penumpang::create([
                    'nama_penumpang' => $nama,
                ]);

                // Simpan detail pemesanan
                DetailPemesanan::create([
                    'id_pemesanan' => $pemesanan->id_pemesanan,
                    'id_penumpang' => $penumpang->id, // gunakan id penumpang baru
                    'id_kursi' => $idKursi,
                    'nama_penumpang' => $nama,
                ]);

                $totalBayar += $harga;

                // Hapus lock pada kursi yang sudah dibayar / ditetapkan ke pemesanan
                DB::table('kursi_locks')->where('id_jadwal', $validated['id_jadwal'])
                    ->where('id_kursi', $idKursi)
                    ->delete();
            }

            // Buat pembayaran
            $pembayaran = Pembayaran::create([
                'id_pemesanan' => $pemesanan->id_pemesanan,
                'jumlah_pembayaran' => $totalBayar,
                'batas_waktu_pembayaran' => now()->addHours(2),
                'status_konfirmasi' => 'menunggu',
            ]);

            // Upload bukti pembayaran
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = 'bukti_' . $pemesanan->id_pemesanan . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('public/bukti', $filename);

                $pembayaran->update([
                    'upload_bukti' => str_replace('public/', 'storage/', $path),
                    'status_konfirmasi' => 'menunggu',
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Pemesanan berhasil',
                'data' => [
                    'pemesanan' => $pemesanan,
                    'pembayaran' => $pembayaran,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Pemesanan error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Gagal pemesanan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTiketPenumpang($id_penumpang)
    {
        $pemesanan = Pemesanan::with(['detail_pemesanan.kursi', 'detail_pemesanan.penumpang', 'pembayaran', 'jadwal.rute'])
            ->where('id_penumpang', $id_penumpang)
            ->get();

        $tiket = $pemesanan->map(function ($p) {
            $penumpangList = $p->detail_pemesanan->map(function ($d) {
                return [
                    'nama' => $d->penumpang->nama_penumpang ?? '-',
                    'kursi' => $d->kursi->no_kursi ?? '-'
                ];
            });

            return [
                'nomor_tiket' => 'FT-' . str_pad($p->id_pemesanan, 6, '0', STR_PAD_LEFT),
                'asal' => $p->jadwal->rute->asal ?? '-',
                'tujuan' => $p->jadwal->rute->tujuan ?? '-',
                'tanggal_keberangkatan' => $p->tanggal_keberangkatan,
                'jam' => date('H:i', strtotime($p->jadwal->jam_keberangkatan)),
                'status' => $p->pembayaran->status_konfirmasi ?? 'Menunggu',
                'total_bayar' => $p->pembayaran->jumlah_pembayaran ?? 0,
                'tanggal_pemesanan' => $p->tanggal_pemesanan,
                'penumpang' => $penumpangList,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Data tiket berhasil diambil',
            'data' => $tiket,
        ]);
    }
}
