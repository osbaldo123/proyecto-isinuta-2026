<?php

use App\Enums\Permission;
use App\Http\Controllers\AccesoSistemaController;
use App\Http\Controllers\AfiliadosController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationCodeController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\CategoriasController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstadoCuentaController;
use App\Http\Controllers\MultasController;
use App\Http\Controllers\PagosController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\ServiciosController;
use App\Http\Controllers\TiposAfiliacionController;
use App\Http\Controllers\TrabajosController;
use App\Http\Controllers\TransferenciaLoteController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\OtbsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $user = auth()->user();

    if ($user?->canAccessPanel()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('acceso', [AccesoSistemaController::class, 'show'])->name('acceso.sistema');
    Route::post('acceso', [AccesoSistemaController::class, 'store'])
        ->middleware('throttle:8,1')
        ->name('acceso.sistema.store');
    Route::get('acceso/cambiar', [AccesoSistemaController::class, 'cambiar'])->name('acceso.sistema.cambiar');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class);
    Route::post('email/verify-code', [EmailVerificationCodeController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.code');
    Route::post('email/verification-code', [EmailVerificationCodeController::class, 'resend'])
        ->middleware('throttle:3,1')
        ->name('verification.code.send');
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show']);
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Acceso por permisos del rol (incluye roles personalizados del módulo Roles).
    Route::middleware('permission:'.Permission::VerPanel->value)->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('panel', DashboardController::class)->name('panel');
    });

    Route::middleware('permission:'.Permission::VerAfiliados->value)->group(function () {
        Route::get('afiliados', [AfiliadosController::class, 'index'])->name('afiliados.index');
        Route::get('afiliados/buscar', [AfiliadosController::class, 'buscar'])
            ->name('afiliados.buscar');
        Route::get('afiliados/siguiente-lote', [AfiliadosController::class, 'siguienteLote'])
            ->name('afiliados.siguiente-lote');
        Route::redirect('clients', '/afiliados')->name('clients.index');

        Route::middleware('permission:'.Permission::GestionarAfiliados->value)->group(function () {
            Route::get('afiliados/create', [AfiliadosController::class, 'create'])->name('afiliados.create');
            Route::post('afiliados', [AfiliadosController::class, 'store'])->name('afiliados.store');
            Route::get('afiliados/{id}/edit', [AfiliadosController::class, 'edit'])->name('afiliados.edit');
            Route::put('afiliados/{id}', [AfiliadosController::class, 'update'])->name('afiliados.update');
            Route::patch('afiliados/{id}/estado', [AfiliadosController::class, 'changeStatus'])->name('afiliados.change-status');
            Route::delete('afiliados/{id}', [AfiliadosController::class, 'destroy'])
                ->whereNumber('id')
                ->name('afiliados.destroy');

            // Transferencia (cesión) de un lote a otra persona; requiere el lote sin deudas.
            Route::get('afiliados/{id}/transferir', [TransferenciaLoteController::class, 'create'])
                ->whereNumber('id')
                ->name('afiliados.transferir');
            Route::get('afiliados/{id}/transferir/buscar-titular', [TransferenciaLoteController::class, 'buscarTitular'])
                ->whereNumber('id')
                ->name('afiliados.transferir.buscar');
            Route::post('afiliados/{id}/transferir', [TransferenciaLoteController::class, 'store'])
                ->whereNumber('id')
                ->name('afiliados.transferir.store');

            Route::redirect('clients/create', '/afiliados/create')->name('clients.create');
            Route::get('clients/{id}/edit', fn (int $id) => redirect("/afiliados/{$id}/edit"))->name('clients.edit');
        });

        Route::get('afiliados/{id}', [AfiliadosController::class, 'show'])
            ->whereNumber('id')
            ->name('afiliados.show');
    });

    Route::middleware('permission:'.Permission::VerPagos->value)->group(function () {
        Route::get('pagos', [PagosController::class, 'index'])->name('pagos.index');
        Route::get('pagos/{id}', [PagosController::class, 'show'])
            ->whereNumber('id')
            ->name('pagos.show');
        Route::get('pagos/{id}/export/{formato}', [PagosController::class, 'export'])
            ->whereNumber('id')
            ->whereIn('formato', ['xlsx'])
            ->name('pagos.export');
        Route::get('pagos/cobro/{cobro}/imprimir', [PagosController::class, 'imprimirCobro'])
            ->whereUuid('cobro')
            ->name('pagos.cobro.imprimir');
        Route::get('pagos/estado-cuenta/{id}', [EstadoCuentaController::class, 'show'])
            ->whereNumber('id')
            ->name('pagos.estado-cuenta');

        Route::middleware('permission:'.Permission::GestionarPagos->value)->group(function () {
            Route::get('pagos/registrar/nuevo', [PagosController::class, 'create'])->name('pagos.create');
            Route::post('pagos', [PagosController::class, 'store'])->name('pagos.store');
        });
    });

    Route::middleware('permission:'.Permission::VerMultas->value)->group(function () {
        Route::get('multas', [MultasController::class, 'index'])->name('multas.index');
        Route::get('multas/{id}', [MultasController::class, 'show'])
            ->whereNumber('id')
            ->name('multas.show');

        Route::middleware('permission:'.Permission::GestionarMultas->value)->group(function () {
            Route::get('multas/create', [MultasController::class, 'create'])->name('multas.create');
            Route::post('multas', [MultasController::class, 'store'])->name('multas.store');
            Route::post('afiliados/{afiliadoId}/multas/auto', [MultasController::class, 'generarAutomaticas'])
                ->whereNumber('afiliadoId')
                ->name('multas.generar-automaticas');
        });
    });

    Route::middleware('permission:'.Permission::VerReportes->value)->group(function () {
        Route::get('reportes', [ReportesController::class, 'index'])->name('reportes.index');

        Route::middleware('permission:'.Permission::VerReporteDeudas->value)->group(function () {
            Route::get('reportes/deudas', [ReportesController::class, 'deudas'])->name('reportes.deudas');
            Route::get('reportes/deudas/export/{formato}', [ReportesController::class, 'exportDeudas'])
                ->whereIn('formato', ['xlsx'])
                ->name('reportes.deudas.export');
        });

        // Con reportes.ver basta para el reporte principal; reportes.ingresos también lo habilita.
        Route::middleware('permission:'.Permission::VerReporteIngresos->value.','.Permission::VerReportes->value)->group(function () {
            Route::get('reportes/ingresos', [ReportesController::class, 'ingresos'])->name('reportes.ingresos');
            Route::get('reportes/ingresos/export/{formato}', [ReportesController::class, 'exportIngresos'])
                ->whereIn('formato', ['xlsx'])
                ->name('reportes.ingresos.export');
        });
    });

    Route::middleware('permission:'.Permission::GestionarUsuariosConsulta->value)->group(function () {
        Route::get('usuarios', [UsersController::class, 'index'])->name('usuarios.index');
        Route::get('usuarios/create', [UsersController::class, 'create'])->name('usuarios.create');
        Route::post('usuarios', [UsersController::class, 'store'])->name('usuarios.store');
        Route::get('usuarios/{id}', [UsersController::class, 'show'])
            ->whereNumber('id')
            ->name('usuarios.show');
        Route::get('usuarios/{id}/edit', [UsersController::class, 'edit'])
            ->whereNumber('id')
            ->name('usuarios.edit');
        Route::put('usuarios/{id}', [UsersController::class, 'update'])
            ->whereNumber('id')
            ->name('usuarios.update');
        Route::patch('usuarios/{id}/toggle-estado', [UsersController::class, 'toggleEstado'])
            ->whereNumber('id')
            ->name('usuarios.toggle-estado');
        Route::delete('usuarios/{id}', [UsersController::class, 'destroy'])
            ->whereNumber('id')
            ->name('usuarios.destroy');
    });

    Route::middleware('permission:'.Permission::VerOtb->value)->group(function () {
        Route::get('otb', [OtbsController::class, 'index'])->name('otb.index');
        Route::redirect('zonas', '/otb');
    });

    Route::middleware('permission:'.Permission::GestionarAfiliados->value)->group(function () {
        Route::get('otb/create', [OtbsController::class, 'create'])->name('otb.create');
        Route::post('otb', [OtbsController::class, 'store'])->name('otb.store');
        Route::get('otb/{id}/edit', [OtbsController::class, 'edit'])->name('otb.edit');
        Route::put('otb/{id}', [OtbsController::class, 'update'])->name('otb.update');
        Route::patch('otb/{id}/estado', [OtbsController::class, 'toggleEstado'])->name('otb.toggle-estado');
        Route::delete('otb/{id}', [OtbsController::class, 'destroy'])->name('otb.destroy');
        Route::redirect('zonas/create', '/otb/create');
    });

    Route::middleware('permission:'.Permission::VerCategorias->value)->group(function () {
        Route::get('categorias', [CategoriasController::class, 'index'])->name('categorias.index');
    });
    Route::middleware('permission:'.Permission::GestionarCategorias->value)->group(function () {
        Route::get('categorias/create', [CategoriasController::class, 'create'])->name('categorias.create');
        Route::post('categorias', [CategoriasController::class, 'store'])->name('categorias.store');
        Route::get('categorias/{id}/edit', [CategoriasController::class, 'edit'])->name('categorias.edit');
        Route::put('categorias/{id}', [CategoriasController::class, 'update'])->name('categorias.update');
        Route::patch('categorias/{id}/estado', [CategoriasController::class, 'toggleEstado'])->name('categorias.toggle-estado');
    });

    Route::middleware('permission:'.Permission::VerAfiliaciones->value)->group(function () {
        Route::get('afiliaciones', [TiposAfiliacionController::class, 'index'])->name('afiliaciones.index');
    });
    Route::middleware('permission:'.Permission::GestionarAfiliaciones->value)->group(function () {
        Route::get('afiliaciones/create', [TiposAfiliacionController::class, 'create'])->name('afiliaciones.create');
        Route::post('afiliaciones', [TiposAfiliacionController::class, 'store'])->name('afiliaciones.store');
        Route::get('afiliaciones/{id}/edit', [TiposAfiliacionController::class, 'edit'])->name('afiliaciones.edit');
        Route::put('afiliaciones/{id}', [TiposAfiliacionController::class, 'update'])->name('afiliaciones.update');
        Route::delete('afiliaciones/{id}', [TiposAfiliacionController::class, 'destroy'])->name('afiliaciones.destroy');
    });

    Route::middleware('permission:'.Permission::VerServicios->value)->group(function () {
        Route::get('servicios', [ServiciosController::class, 'index'])->name('servicios.index');
        Route::get('servicios/{id}', [ServiciosController::class, 'show'])
            ->whereNumber('id')
            ->name('servicios.show');

        Route::middleware('permission:'.Permission::GestionarServicios->value)->group(function () {
            Route::get('servicios/create', [ServiciosController::class, 'create'])->name('servicios.create');
            Route::post('servicios', [ServiciosController::class, 'store'])->name('servicios.store');
            Route::get('servicios/{id}/edit', [ServiciosController::class, 'edit'])->name('servicios.edit');
            Route::put('servicios/{id}', [ServiciosController::class, 'update'])->name('servicios.update');
            Route::delete('servicios/{id}', [ServiciosController::class, 'destroy'])->name('servicios.destroy');
        });
    });

    Route::middleware('permission:'.Permission::VerTrabajos->value)->group(function () {
        Route::get('trabajos', [TrabajosController::class, 'index'])->name('trabajos.index');
        Route::get('trabajos/{id}', [TrabajosController::class, 'show'])
            ->whereNumber('id')
            ->name('trabajos.show');

        Route::middleware('permission:'.Permission::GestionarTrabajos->value)->group(function () {
            Route::post('trabajos', [TrabajosController::class, 'store'])->name('trabajos.store');
            Route::put('trabajos/{id}/lista', [TrabajosController::class, 'guardarLista'])
                ->whereNumber('id')
                ->name('trabajos.lista');
            Route::put('trabajos/{id}', [TrabajosController::class, 'update'])
                ->whereNumber('id')
                ->name('trabajos.update');
            Route::delete('trabajos/{id}', [TrabajosController::class, 'destroy'])
                ->whereNumber('id')
                ->name('trabajos.destroy');
        });
    });

    Route::middleware('permission:'.Permission::VerConfiguracion->value)->group(function () {
        Route::get('configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
    });

    Route::middleware('permission:'.Permission::GestionarConfiguracion->value)->group(function () {
        Route::put('configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
    });

    Route::middleware('permission:'.Permission::VerRoles->value)->group(function () {
        Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
        Route::get('roles/{id}', [RolesController::class, 'show'])
            ->whereNumber('id')
            ->name('roles.show');
    });

    Route::middleware('permission:'.Permission::GestionarRoles->value)->group(function () {
        Route::get('roles/create', [RolesController::class, 'create'])->name('roles.create');
        Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
        Route::get('roles/{id}/edit', [RolesController::class, 'edit'])
            ->whereNumber('id')
            ->name('roles.edit');
        Route::put('roles/{id}', [RolesController::class, 'update'])
            ->whereNumber('id')
            ->name('roles.update');
        Route::patch('roles/{id}/toggle-estado', [RolesController::class, 'toggleEstado'])
            ->whereNumber('id')
            ->name('roles.toggle-estado');
        Route::delete('roles/{id}', [RolesController::class, 'destroy'])
            ->whereNumber('id')
            ->name('roles.destroy');
    });

});

require __DIR__.'/settings.php';
