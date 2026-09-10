<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\PatientController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [DashboardController::class, 'index']);
Route::get('patients', [PatientController::class, 'index']);
Route::post('patients/duplicates', [PatientController::class, 'duplicates']);
Route::post('patients', [PatientController::class, 'store']);
Route::get('patients/{id}', [PatientController::class, 'show']);
Route::put('patients/{id}', [PatientController::class, 'update']);
Route::post('patients/{id}/documents', [PatientController::class, 'storeDocument']);
Route::get('appointments', [AppointmentController::class, 'index']);
Route::get('doctors', [DoctorController::class, 'index']);
Route::post('doctors', [DoctorController::class, 'store']);
Route::get('doctors/{id}', [DoctorController::class, 'show']);
Route::put('doctors/{id}', [DoctorController::class, 'update']);
Route::delete('doctors/{id}', [DoctorController::class, 'destroy']);
