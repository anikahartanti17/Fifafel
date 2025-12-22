<?php

use App\Http\Controllers\API\AuthPenumpangController;
use App\Http\Controllers\API\ApiAuthController;
use App\Http\Controllers\API\ApiPenumpangController;
use App\Http\Controllers\API\ApiPetugasController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::options('/{any}', function (Request $request) {
    return response()->noContent(204);
})->where('any', '.*');


// Login penumpang
Route::post('/penumpang/register', [AuthPenumpangController::class, 'register']);
Route::post('/penumpang/login', [AuthPenumpangController::class, 'login']);
Route::post('/penumpang/logout', [AuthPenumpangController::class, 'logout']);
Route::put('/penumpang/{id}', [AuthPenumpangController::class, 'updateProfile']);

//
Route::post('login', [ApiAuthController::class, 'login'])->middleware('guest');

Route::post('/penumpang/register', [AuthPenumpangController::class, 'register']);
// Route::get('/penumpang', [ApiPenumpangController::class, 'index']);
Route::get('/penumpang/{id}', [ApiPenumpangController::class, 'show']);

// Ambil semua rute
Route::get('/rute', [ApiPenumpangController::class, 'getRute']);

// Ambil jam/jadwal berdasarkan rute tertentu
Route::get('/jadwal', [ApiPenumpangController::class, 'getJam']);

// Buat pemesanan baru
Route::post('/pemesanan', [ApiPenumpangController::class, 'store']);
Route::get('tiket/{id_penumpang}', [ApiPenumpangController::class, 'getTiketPenumpang']);

Route::post('/lock-kursi', [ApiPenumpangController::class, 'lockKursiSementara']);
Route::post('/unlock-kursi', [ApiPenumpangController::class, 'unlockKursiOtomatis']);


Route::prefix('petugas')->group(function () {
    Route::get('/rute', [ApiPetugasController::class, 'getRute']);
    Route::get('/rute/{id}/jadwal', [ApiPetugasController::class, 'getJam']);
    Route::get('/kursi/tersedia', [ApiPetugasController::class, 'getKursiTersedia']);
    Route::post('/pesan', [ApiPetugasController::class, 'store']);
});


// Route::post('/login', [ApiAuthController::class, 'login']);


// Login Petugas
// Route::post('/petugas/login', [AuthPetugasController::class, 'login']);
// Route::post('/petugas/logout', [AuthPetugasController::class, 'logout']);



// Upload bukti pembayaran
// Route::post('/pembayaran/upload', [ApiPenumpangController::class, 'uploadBukti']);


// Ambil kursi yang sudah terisi/tersedia berdasarkan rute, tanggal, jadwal
// Route::get('/kursi/tersedia', [ApiPenumpangController::class, 'showKursi']);


//
// Route::get('/pemesanan/terakhir', [ApiPenumpangController::class, 'pesananterkhir']);
// Route::get('/detailpemesanan/terakhir', [ApiPenumpangController::class, 'detailpesananterkhir']);
// Route::get('/jadwal', [ApiPenumpangController::class, 'getJadwal']);
// Route::post('/upload-bukti', [ApiPenumpangController::class, 'uploadBukti']);
// Route::post('/pesan', [ApiPenumpangController::class, 'store']);


// Route::get('/rute', [ApiPetugasController::class, 'rute']);

// Route::get('/kursi/tersedia', [ApiPetugasController::class, 'showKursi']);
// Route::get('/pemesanan/terakhir', [ApiPetugasController::class, 'pesananterkhir']);
// Route::get('/detailpemesanan/terakhir', [ApiPetugasController::class, 'detailpesananterkhir']);
// Route::get('/jadwal', [ApiPetugasController::class, 'getJadwal']);
