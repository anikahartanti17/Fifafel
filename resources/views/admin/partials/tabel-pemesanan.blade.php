<div class="overflow-x-auto rounded-xl shadow mt-2">
    <table class="min-w-full text-sm text-left border border-gray-200 rounded-xl">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-4 py-2">No</th>
                <th class="border px-4 py-2">Nama Penumpang</th>
                <th class="border px-4 py-2">Asal</th>
                <th class="border px-4 py-2">Tujuan</th>
                <th class="border px-4 py-2">No Kursi</th>
                <th class="border px-4 py-2">Tanggal Pemesanan</th>
                <th class="border px-4 py-2">Nama Supir</th>
                <th class="border px-4 py-2">Nomer Kendaraan</th>
                <th class="border px-4 py-2">Jam Keberangkatan</th>
                <th class="border px-4 py-2">Tanggal Keberangkatan</th>
                <th class="border px-4 py-2">Total Pembayaran</th>
                <th class="border px-4 py-2">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $pemesanan)
                <tr class="hover:bg-gray-50">
                    <td class="border px-4 py-2 text-center">{{ $loop->iteration }}</td>
                    <td class="border px-4 py-2">
                        {{ $pemesanan->detail_pemesanan->pluck('penumpang.nama_penumpang')->join(', ') }}
                    </td>
                    <td class="border px-4 py-2">{{ $pemesanan->jadwal->rute->asal }}</td>
                    <td class="border px-4 py-2">{{ $pemesanan->jadwal->rute->tujuan }}</td>
                    <td class="border px-4 py-2">
                        {{ $pemesanan->detail_pemesanan->pluck('kursi.no_kursi')->join(', ') }}
                    </td>
                    <td class="border px-4 py-2">{{ $pemesanan->tanggal_pemesanan }}</td>
                    <td class="border px-4 py-2">{{ $pemesanan->jadwal->supir->nama_supir }}</td>
                    <td class="border px-4 py-2">{{ $pemesanan->jadwal->kendaraan->plat_nomor }}</td>
                    <td class="border px-4 py-2">{{ substr($pemesanan->jadwal->jam_keberangkatan, 0, 5) }}</td>
                    <td class="border px-4 py-2">{{ $pemesanan->tanggal_keberangkatan }}</td>
                    <td class="border px-4 py-2">
                        Rp.{{ number_format($pemesanan->pembayaran->jumlah_pembayaran, 0, ',', '.') }}
                    </td>
                    <td class="border px-4 py-2 text-center">
                        <div class="flex gap-2 justify-center">
                            <a href="{{ route('pemesanan.edit', $pemesanan->id_pemesanan) }}"
                                class="bg-yellow-500 text-white px-3 py-1 rounded">Edit</a>
                            <form method="POST" action="{{ route('pemesanan.destroy', $pemesanan->id_pemesanan) }}">
                                @csrf @method('DELETE')
                                <button class="bg-red-500 text-white px-3 py-1 rounded">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center py-4 text-gray-500">
                        Tidak ada data
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
