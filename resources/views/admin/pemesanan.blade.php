@extends('layouts.admin')
@section('title', 'Pemesanan Tiket')
@section('content')
    <div class="py-6 px-6">
        <div class="bg-white shadow-md rounded-2xl p-6">
            <div class="mb-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold mb-4">Daftar Pemesanan</h1>
                <a href="{{ route('pemesanan.create') }}"
                    class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 shadow transition">
                    + Tambah Pemesanan
                </a>
            </div>

            {{-- Form Filter --}}
            <form method="GET" action="{{ route('pemesanan.index') }}" class="flex flex-row items-end flex-wrap gap-4">
                <!-- Filter Rute -->
                <div class="w-48">
                    <label for="rute" class="block mb-1 text-sm font-medium text-gray-700">Rute</label>
                    <select name="rute" id="rute" onchange="this.form.submit()"
                        class="border border-gray-300 rounded w-full p-2">
                        <option value="">-- Semua Rute --</option>
                        @foreach ($rutes as $rute)
                            <option value="{{ $rute->id_rute }}" {{ request('rute') == $rute->id_rute ? 'selected' : '' }}>
                                {{ $rute->asal }} - {{ $rute->tujuan }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Tanggal -->
                <div class="w-40">
                    <label for="tanggal" class="block mb-1 text-sm font-medium text-gray-700">Tanggal</label>
                    <input type="date" name="tanggal" id="tanggal" value="{{ request('tanggal') }}"
                        class="border border-gray-300 rounded w-full p-2">
                </div>

                <!-- Filter Jam -->
                <div class="w-32">
                    <label for="jam" class="block mb-1 text-sm font-medium text-gray-700">Jam</label>
                    <select name="jam" id="jam" class="border border-gray-300 rounded w-full p-2">
                        <option value="">-- Semua Jam --</option>
                        @foreach ($jams as $jam)
                            <option value="{{ $jam }}" {{ request('jam') == $jam ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::parse($jam)->format('H:i') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Status -->
                {{-- <div class="w-40">
                    <label for="status" class="block mb-1 text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status" class="border border-gray-300 rounded w-full p-2">
                        <option value="">-- Semua Status --</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
            {{ ucfirst($status) }}
            </option>
            @endforeach
            </select>
    </div> --}}

                <!-- Tombol Filter -->
                <div class="self-end mb-1">
                    <button type="submit"
                        class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-xl hover:bg-blue-700 shadow transition">
                        Filter
                    </button>
                </div>
            </form>

            {{-- Tabel Pemesanan --}}
            <div class="overflow-x-auto rounded-xl shadow mt-2">
                <h2 class="text-lg font-bold mt-4 text-green-700">Pemesanan Langsung</h2>
                @include('admin.partials.tabel-pemesanan', ['data' => $pemesananLangsung])

                <h2 class="text-lg font-bold mt-8 text-blue-700">Pemesanan Online</h2>
                @include('admin.partials.tabel-pemesanan', ['data' => $pemesananOnline])

            </div>
        </div>
    </div>
@endsection
