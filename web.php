use App\Http\Controllers\ClientController;

Route::post('clients', [ClientController::class, 'create']);
Route::get('clients/{slug}', [ClientController::class, 'show']);
Route::put('clients/{slug}', [ClientController::class, 'update']);
Route::delete('clients/{slug}', [ClientController::class, 'destroy']);
