<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The Filament admin panel is registered by App\Providers\Filament\
| AdminPanelProvider and lives at /admin. Everything else is API — see
| routes/api.php.
|
*/

Route::get('/', fn () => redirect('/admin'));
