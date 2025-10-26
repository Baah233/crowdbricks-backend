
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ChatController;

Route::prefix('v1')->group(function () {
    // Public
    Route::get('/ping', fn() => response()->json(['ok'=>true]));
    Route::post('/register', [AuthController::class,'register']);
    Route::post('/login', [AuthController::class,'login'])->name('login');
    Route::get('/projects', [ProjectController::class,'index']);
    Route::get('/projects/{id}', [ProjectController::class,'show']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::post('/chat', [ChatController::class, 'chat'])->middleware('throttle:30,1');

    // Webhook - public endpoint (gateway verifies signature)
    Route::post('/payments/webhook/{gateway}', [TransactionController::class,'webhook']);

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class,'logout']);
        Route::get('/user', [AuthController::class,'me']);

        Route::post('/projects/{project}/fund', [TransactionController::class,'fund']);
         // 30 req/min
        // add more protected routes: create project, comments, profile updates...
    });
});
