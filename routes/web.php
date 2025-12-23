<?php

use Burningyolo\LaravelHttpMonitor\Http\Controllers\HttpMonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('http-monitor')->name('http-monitor.')->middleware(['web'])->group(function () {

    // Dashboard
    Route::get('/', [HttpMonitorController::class, 'index'])->name('index');

    // Inbound
    Route::get('inbound', [HttpMonitorController::class, 'inboundIndex'])->name('inbound.index');
    Route::get('inbound/{id}', [HttpMonitorController::class, 'inboundShow'])->name('inbound.show');
    Route::delete('inbound/{id}', [HttpMonitorController::class, 'inboundDestroy'])->name('inbound.destroy');

    // Outbound
    Route::get('outbound', [HttpMonitorController::class, 'outboundIndex'])->name('outbound.index');
    Route::get('outbound/{id}', [HttpMonitorController::class, 'outboundShow'])->name('outbound.show');
    Route::delete('outbound/{id}', [HttpMonitorController::class, 'outboundDestroy'])->name('outbound.destroy');

    // IPs
    Route::get('ips', [HttpMonitorController::class, 'ipsIndex'])->name('ips.index');
    Route::get('ips/{id}', [HttpMonitorController::class, 'ipsShow'])->name('ips.show');
    Route::delete('ips/{id}', [HttpMonitorController::class, 'ipsDestroy'])->name('ips.destroy');
});
