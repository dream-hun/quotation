<?php

declare(strict_types=1);

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\ClarificationController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProcurementFileController;
use App\Http\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', fn (): Response => Inertia::render('welcome'))->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', [ProcurementController::class, 'dashboard'])->name('dashboard');
    Route::get('procurement', [ProcurementController::class, 'index'])->name('procurement.index');
    Route::get('procurement/{purchaseRequest}', [ProcurementController::class, 'show'])->name('procurement.show');
    Route::get('files/{attachment}', [ProcurementFileController::class, 'download'])->name('files.download');
    Route::get('administration', [AdministrationController::class, 'index'])->name('administration.index');
    Route::middleware('throttle:60,1')->group(function (): void {
        Route::post('procurement', [ProcurementController::class, 'store'])->name('procurement.store');
        Route::put('procurement/{purchaseRequest}', [ProcurementController::class, 'update'])->name('procurement.update');
        Route::post('procurement/{purchaseRequest}/publish', [ProcurementController::class, 'publish'])->name('procurement.publish');
        Route::post('procurement/{purchaseRequest}/change', [ProcurementController::class, 'change'])->name('procurement.change');
        Route::post('procurement/{purchaseRequest}/quotation', [QuotationController::class, 'save'])->name('quotations.save');
        Route::post('procurement/{purchaseRequest}/submit', [QuotationController::class, 'submit'])->name('quotations.submit');
        Route::post('procurement/{purchaseRequest}/participation', [QuotationController::class, 'participation'])->name('quotations.participation');
        Route::post('procurement/{purchaseRequest}/evaluation', [EvaluationController::class, 'start'])->name('evaluations.start');
        Route::post('procurement/{purchaseRequest}/assess', [EvaluationController::class, 'assess'])->name('evaluations.assess');
        Route::post('procurement/{purchaseRequest}/recommend', [EvaluationController::class, 'freeze'])->name('evaluations.freeze');
        Route::post('procurement/{purchaseRequest}/decide', [EvaluationController::class, 'decide'])->name('evaluations.decide');
        Route::post('procurement/{purchaseRequest}/clarifications', [ClarificationController::class, 'store'])->name('clarifications.store');
        Route::post('procurement/{purchaseRequest}/files', [ProcurementFileController::class, 'store'])->name('files.store');
        Route::post('administration', [AdministrationController::class, 'store'])->name('administration.store');
    });
    Route::post('procurement/{purchaseRequest}/files/chunks', [ProcurementFileController::class, 'chunk'])
        ->middleware('throttle:600,1')->name('files.chunk');
});

require __DIR__.'/settings.php';
