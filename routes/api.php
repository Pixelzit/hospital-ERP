<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\OpdController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientWorkspaceController;
use App\Http\Controllers\Api\PharmacyMedicineController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('me', [AuthController::class, 'me']);

Route::middleware('api.auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('opd/visits', [OpdController::class, 'visits']);
    Route::post('opd/visits', [OpdController::class, 'store']);
    Route::patch('opd/visits/{id}/status', [OpdController::class, 'updateStatus']);
    Route::get('patients', [PatientController::class, 'index']);
    Route::post('patients/duplicates', [PatientController::class, 'duplicates']);
    Route::post('patients', [PatientController::class, 'store']);
    Route::get('patients/{id}', [PatientController::class, 'show']);
    Route::put('patients/{id}', [PatientController::class, 'update']);
    Route::post('patients/{id}/documents', [PatientController::class, 'storeDocument']);
    Route::get('patients/{id}/encounters/{encounterId}', [PatientWorkspaceController::class, 'showEncounter']);
    Route::get('patients/{id}/documents/{documentId}/file', [PatientWorkspaceController::class, 'showDocumentFile']);
    Route::get('patients/{id}/invoices/{invoiceId}', [PatientWorkspaceController::class, 'showInvoice']);
    Route::get('patients/{id}/invoices/{invoiceId}/print', [PatientWorkspaceController::class, 'printInvoice']);
    Route::get('patients/{id}/invoices/{invoiceId}/receipt', [PatientWorkspaceController::class, 'printReceipt']);
    Route::post('patients/{id}/appointments', [AppointmentController::class, 'store']);
    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::get('appointments/slots', [AppointmentController::class, 'slots']);
    Route::put('appointments/{id}', [AppointmentController::class, 'update']);
    Route::post('appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    Route::get('departments', [AppointmentController::class, 'departments']);
    Route::get('doctors', [DoctorController::class, 'index']);
    Route::post('doctors', [DoctorController::class, 'store']);
    Route::get('doctors/{id}', [DoctorController::class, 'show']);
    Route::put('doctors/{id}', [DoctorController::class, 'update']);
    Route::delete('doctors/{id}', [DoctorController::class, 'destroy']);
    Route::get('pharmacy/medicines', [PharmacyMedicineController::class, 'index']);
    Route::post('pharmacy/medicines', [PharmacyMedicineController::class, 'store']);
    Route::get('pharmacy/medicines/{id}', [PharmacyMedicineController::class, 'show']);
});
