@extends('layouts.admin')
@section('title', 'Edit Pemesanan')

@section('content')
    @php
        $seats = $seats ?? [1, 2, null, 'driver', null, 3, 4, 5, 6, null, 7, 8, 9, null, 10, 11, 12, 13, 14, 15];
        $kursi_terisi = $kursi_terisi ?? [];
        $rutes = $rutes ?? [];
        $jadwals = $jadwals ?? [];
        $selected = old('kursi', $pemesanan->detail_pemesanan->pluck('kursi.no_kursi')->toArray() ?? []);
        $id_rute = old('id_rute', $pemesanan->jadwal->rute->id_rute ?? null);
        $tanggal = old('tanggal', $pemesanan->tanggal_keberangkatan);
        $id_jadwal = old('id_jadwal', $pemesanan->id_jadwal);
        $sudahPilih = $id_rute && $tanggal && $id_jadwal;
        $kursi_map = [];

        // kursi terisi permanen (KECUALI kursi milik pemesanan ini)
        foreach ($kursi_terisi as $no_kursi) {
            if (!in_array($no_kursi, $selected)) {
                $kursi_map[(string) $no_kursi] = 'terisi';
            }
        }

        // kursi locked sementara
        foreach ($kursi_locked ?? [] as $no_kursi) {
            if (!isset($kursi_map[(string) $no_kursi]) && !in_array($no_kursi, $selected)) {
                $kursi_map[(string) $no_kursi] = 'locked';
            }
        }

    @endphp

    <div class="py-8 px-6">
        <div class="bg-white rounded-xl shadow-lg p-8 max-w-6xl mx-auto">
            <h2 class="text-2xl font-semibold mb-6">Edit Pemesanan</h2>
            <form method="POST" action="{{ route('pemesanan.update', $pemesanan->id_pemesanan) }}">
                @csrf
                @method('PUT')

                {{-- Nama Penumpang --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Penumpang</label>
                    <input type="text" name="nama" value="{{ old('nama', $pemesanan->penumpang->nama_penumpang) }}"
                        class="w-full border border-gray-300 rounded-md px-4 py-2 text-sm" required />
                </div>

                <!-- Pilih Rute -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilih Rute</label>
                    <select name="id_rute" class="w-full border rounded-md px-4 py-2 text-sm" id="rute-select">
                        <option value="">-- Pilih Rute --</option>
                        @foreach ($rutes as $rute)
                            <option value="{{ $rute->id_rute }}" {{ $id_rute == $rute->id_rute ? 'selected' : '' }}>
                                {{ $rute->asal }}-{{ $rute->tujuan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tanggal Keberangkatan --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Keberangkatan</label>
                    <input type="date" name="tanggal" id="tanggal" value="{{ $tanggal }}"
                        class="w-full border rounded-md px-4 py-2 text-sm" required>
                </div>

                <!-- Jam Keberangkatan -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jam Keberangkatan</label>
                    <select name="id_jadwal" class="w-full border rounded-md px-4 py-2 text-sm" id="jadwal-select">
                        <option value="">-- Pilih Jam --</option>
                        @foreach ($jadwals as $jadwal)
                            <option value="{{ $jadwal->id_jadwal }}"
                                {{ old('id_jadwal', $pemesanan->id_jadwal ?? '') == $jadwal->id_jadwal ? 'selected' : '' }}>
                                {{ $jadwal->jam_keberangkatan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Pilih Kursi --}}
                <div class="flex justify-center mb-6">
                    <div class="grid grid-cols-4 gap-4">
                        @foreach ($seats as $seat)
                            @if ($seat === null)
                                <div></div>
                            @elseif ($seat === 'driver')
                                <div class="flex items-center justify-center text-2xl">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icon-tabler-steering-wheel">
                                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                        <path d="M12 12m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                        <path d="M12 14l0 7" />
                                        <path d="M10 12l-6.75 -2" />
                                        <path d="M14 12l6.75 -2" />
                                    </svg>
                                </div>
                            @else
                                @php
                                    $status = $sudahPilih ? $kursi_map[(string) $seat] ?? 'tersedia' : 'belum';
                                    $isSelected = in_array($seat, $selected);
                                @endphp

                                <label class="cursor-pointer">
                                    <input type="checkbox" name="kursi[]" value="{{ $seat }}" class="sr-only"
                                        {{ $isSelected ? 'checked' : '' }}
                                        {{ in_array($status, ['terisi', 'locked']) && !$isSelected ? 'disabled' : '' }}>

                                    <div
                                        class="seat-box text-sm font-semibold text-center px-3 py-2 rounded-md transition
                                        {{ $status === 'terisi' ? 'bg-gray-300 text-gray-500 cursor-not-allowed' : '' }}
                                        {{ $status === 'locked' ? 'bg-yellow-400 text-black cursor-not-allowed' : '' }}
                                        {{ $isSelected ? 'bg-red-600 text-white' : '' }}
                                        {{ $status === 'tersedia' && !$isSelected ? 'bg-blue-600 text-white hover:bg-blue-700' : '' }}">
                                        {{ $seat }}
                                    </div>
                                </label>
                            @endif
                        @endforeach
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md text-sm transition">
                    Perbarui Pemesanan
                </button>
            </form>
        </div>
    </div>

    <script>
        let protectedSeats = new Set(@json(array_map('strval', $selected)));
        let lastSelectedSeat = null;
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $('#rute-select').on('change', function() {
            var idRute = $(this).val();
            $('#jadwal-select').html('<option value="">Memuat...</option>');

            if (idRute) {
                $.ajax({
                    url: '/get-jadwal-by-rute/' + idRute,
                    type: 'GET',
                    success: function(data) {
                        $('#jadwal-select').empty().append('<option value="">-- Pilih Jam --</option>');
                        data.forEach(function(jadwal) {
                            $('#jadwal-select').append(
                                `<option value="${jadwal.id_jadwal}">${jadwal.jam_keberangkatan}</option>`
                            );
                        });
                    },
                    error: function() {
                        alert('Gagal memuat jadwal!');
                    }
                });
            } else {
                $('#jadwal-select').html('<option value="">-- Pilih Jam --</option>');
            }
        });
    </script>
    <script>
        function refreshKursi() {
            const tanggal = document.getElementById('tanggal')?.value;
            const idJadwal = document.getElementById('jadwal-select')?.value;

            if (!tanggal || !idJadwal) return;

            fetch(
                    `/admin/get-kursi?id_jadwal=${idJadwal}&tanggal=${tanggal}&ignore_pemesanan={{ $pemesanan->id_pemesanan }}`)
                .then(res => res.json())
                .then(res => {
                    const terisi = (res.terisi ?? []).map(String);
                    const locked = (res.locked ?? []).map(String);

                    document.querySelectorAll('input[name="kursi[]"]').forEach(cb => {
                        const seat = cb.value;
                        const box = cb.closest('label').querySelector('.seat-box');

                        if (
                            cb.checked ||
                            seat === lastSelectedSeat ||
                            protectedSeats.has(seat)
                        ) {
                            return;
                        }



                        box.classList.remove(
                            'bg-gray-300',
                            'bg-yellow-400',
                            'bg-blue-600',
                            'text-gray-500',
                            'text-black',
                            'text-white',
                            'cursor-not-allowed',
                            'hover:bg-blue-700'
                        );

                        if (terisi.includes(seat)) {
                            cb.disabled = true;
                            box.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
                        } else if (locked.includes(seat)) {
                            cb.disabled = true;
                            box.classList.add('bg-yellow-400', 'text-black', 'cursor-not-allowed');
                        } else {
                            cb.disabled = false;
                            box.classList.add('bg-blue-600', 'text-white', 'hover:bg-blue-700');
                        }
                    });

                    // ✅ RESET SETELAH REFRESH BERHASIL
                    lastSelectedSeat = null;
                });

        }

        document.addEventListener('DOMContentLoaded', () => {
            refreshKursi();
            setInterval(refreshKursi, 5000);
        });
    </script>

    <script>
        $(document).ready(function() {
            // refresh kursi saat halaman edit dibuka
            refreshKursi();

            // reset kursi jika rute diganti
            $('#rute-select').on('change', function() {
                $('input[name="kursi[]"]').prop('checked', false);
            });
        });
    </script>
    <script>
        $('input[name="kursi[]"]').on('change', function() {
            const selected = $('input[name="kursi[]"]:checked')
                .map(function() {
                    return parseInt(this.value);
                })
                .get();

            if (selected.length === 0) return;

            $.ajax({
                url: '/admin/lock-kursi',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    id_jadwal: $('#jadwal-select').val(),
                    kursi: selected,
                    ignore_pemesanan: '{{ $pemesanan->id_pemesanan }}'
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('input[name="kursi[]"]').forEach(cb => {
                cb.addEventListener('change', function() {
                    if (!this.checked) return;

                    const newSeat = this.value;

                    // 🔴 kursi lama TIDAK lagi dilindungi
                    protectedSeats.clear();
                    protectedSeats.add(newSeat);

                    lastSelectedSeat = newSeat;

                    document.querySelectorAll('input[name="kursi[]"]').forEach(other => {
                        const box = other.closest('label').querySelector('.seat-box');

                        if (other !== this) {
                            other.checked = false;
                            other.disabled = false;

                            box.className =
                                'seat-box text-sm font-semibold text-center px-3 py-2 rounded-md transition ' +
                                'bg-blue-600 text-white hover:bg-blue-700';
                        }
                    });

                    const box = this.closest('label').querySelector('.seat-box');
                    box.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                    box.classList.add('bg-red-600', 'text-white');
                });
            });
        });
    </script>

@endsection
