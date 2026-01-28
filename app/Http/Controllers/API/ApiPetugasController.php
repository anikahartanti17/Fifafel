<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\{DetailPemesanan, Jadwal, Kursi, Pembayaran, Pemesanan, Penumpang, Rute};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiPetugasController extends Controller
{
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

    // Ambil jadwal berdasarkan rute
    public function getJam($id_rute)
    {
        $rute = Rute::find($id_rute);
        if (!$rute) {
            return response()->json([
                'status' => false,
                'message' => 'Rute tidak ditemukan',
                'data' => [],
            ], 404);
        }

        $jadwals = Jadwal::with(['supir', 'kendaraan'])
            ->where('id_rute', $id_rute)
            ->get(['id_jadwal', 'jam_keberangkatan', 'id_supir', 'id_kendaraan']);

        return response()->json([
            'status' => true,
            'message' => 'Jadwal berhasil diambil',
            'data' => $jadwals,
        ]);
    }

    // Ambil kursi tersedia per rute, tanggal, dan jadwal
    public function getKursiTersedia(Request $request)
    {
        $id_jadwal = $request->query('jam');
        $tanggal   = $request->query('tanggal');

        if (!$id_jadwal || !$tanggal) {
            return response()->json([
                'tersedia' => [],
                'terisi' => [],
                'locked' => [],
            ]);
        }

        // 🔴 kursi terisi permanen
        $terisi = DB::table('detail_pemesanan')
            ->join('pemesanan', 'detail_pemesanan.id_pemesanan', '=', 'pemesanan.id_pemesanan')
            ->join('pembayaran', 'pemesanan.id_pemesanan', '=', 'pembayaran.id_pemesanan')
            ->join('kursi', 'detail_pemesanan.id_kursi', '=', 'kursi.id_kursi')
            ->where('pemesanan.id_jadwal', $id_jadwal)
            ->where('pemesanan.tanggal_keberangkatan', $tanggal)
            ->whereIn('pembayaran.status_konfirmasi', [
                'menunggu',
                'berhasil',
                'ditempat'
            ])
            ->pluck('kursi.no_kursi')
            ->toArray();

        // 🟡 kursi di-lock sementara
        $locked = DB::table('kursi_locks')
            ->join('kursi', 'kursi.id_kursi', '=', 'kursi_locks.id_kursi')
            ->where('id_jadwal', $id_jadwal)
            ->where('locked_until', '>', now())
            ->pluck('kursi.no_kursi')
            ->toArray();

        // 🟢 semua kursi
        $allSeats = Kursi::pluck('no_kursi')->toArray();

        // ✅ tersedia = semua - terisi - locked
        $tersedia = array_values(array_diff($allSeats, $terisi, $locked));

        return response()->json([
            'tersedia' => $tersedia,
            'terisi'   => $terisi,
            'locked'   => $locked,
        ]);
    }




    // Simpan pemesanan oleh petugas
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_jadwal' => 'required|exists:jadwal,id_jadwal',
            'tanggal' => 'required|date',
            'penumpang' => 'required|array|min:1',
            'penumpang.*.nama' => 'required|string|max:255',
            'penumpang.*.kursi' => 'required|exists:kursi,id_kursi',
        ]);

        DB::beginTransaction();
        try {
            $jadwal = Jadwal::with('rute')->findOrFail($validated['id_jadwal']);
            $harga = $jadwal->rute->harga ?? 0;

            $totalBayar = 0;
            $firstPenumpang = null;
            $penumpangData = []; // 🔹 array baru untuk simpan penumpang + kursi

            foreach ($validated['penumpang'] as $index => $p) {
                $penumpang = Penumpang::create([
                    'nama_penumpang' => $p['nama'],
                ]);

                if ($index === 0) {
                    $firstPenumpang = $penumpang;
                }

                $penumpangData[] = [
                    'id_penumpang' => $penumpang->id,
                    'nama' => $p['nama'],
                    'kursi' => $p['kursi'],
                ];

                $totalBayar += $harga;
            }

            // Buat pemesanan pakai penumpang pertama
            $pemesanan = Pemesanan::create([
                'id_penumpang' => $firstPenumpang->id,
                'id_jadwal' => $jadwal->id_jadwal,
                'tanggal_pemesanan' => now()->format('Y-m-d'),
                'tanggal_keberangkatan' => $validated['tanggal'],
            ]);

            // Simpan detail pemesanan
            foreach ($penumpangData as $p) {
                DetailPemesanan::create([
                    'id_pemesanan' => $pemesanan->id_pemesanan,
                    'id_penumpang' => $p['id_penumpang'],
                    'id_kursi' => $p['kursi'],
                    'nama_penumpang' => $p['nama'],
                ]);
            }

            Pembayaran::create([
                'id_pemesanan' => $pemesanan->id_pemesanan,
                'jumlah_pembayaran' => $totalBayar,
                'batas_waktu_pembayaran' => now(),
                'status_konfirmasi' => 'ditempat',
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Pemesanan berhasil disimpan',
                'data' => [
                    'pemesanan' => $pemesanan,
                    'total_bayar' => $totalBayar,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Pemesanan petugas error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Gagal melakukan pemesanan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
