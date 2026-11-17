<?php

namespace App\Enums;

enum Permission: string
{
    case VerPanel = 'panel.ver';
    case VerAfiliados = 'afiliados.ver';
    case GestionarAfiliados = 'afiliados.gestionar';
    case VerPagos = 'pagos.ver';
    case GestionarPagos = 'pagos.gestionar';
    case VerReportes = 'reportes.ver';
    case VerReporteDeudas = 'reportes.deudas';
    case VerReporteIngresos = 'reportes.ingresos';
    case GestionarUsuarios = 'usuarios.gestionar';
    case GestionarUsuariosConsulta = 'usuarios.gestionar_consulta';
    case VerMultas = 'multas.ver';
    case GestionarMultas = 'multas.gestionar';
    case VerOtb = 'otb.ver';
    case VerCategorias = 'categorias.ver';
    case GestionarCategorias = 'categorias.gestionar';
    case VerAfiliaciones = 'afiliaciones.ver';
    case GestionarAfiliaciones = 'afiliaciones.gestionar';
    case VerServicios = 'servicios.ver';
    case GestionarServicios = 'servicios.gestionar';
    case VerTrabajos = 'trabajos.ver';
    case GestionarTrabajos = 'trabajos.gestionar';
    case VerConfiguracion = 'configuracion.ver';
    case GestionarConfiguracion = 'configuracion.gestionar';
    case VerRoles = 'roles.ver';
    case GestionarRoles = 'roles.gestionar';

    public function label(): string
    {
        return match ($this) {
            self::VerPanel => 'Ver panel principal',
            self::VerAfiliados => 'Ver afiliados',
            self::GestionarAfiliados => 'Gestionar afiliados',
            self::VerPagos => 'Ver pagos',
            self::GestionarPagos => 'Gestionar pagos',
            self::VerReportes => 'Ver reportes',
            self::VerReporteDeudas => 'Ver reporte de deudas',
            self::VerReporteIngresos => 'Ver reporte de ingresos',
            self::GestionarUsuarios => 'Gestionar usuarios',
            self::GestionarUsuariosConsulta => 'Gestionar usuarios (Administrador)',
            self::VerMultas => 'Ver multas',
            self::GestionarMultas => 'Gestionar multas',
            self::VerOtb => 'Ver OTB',
            self::VerCategorias => 'Ver categorías',
            self::GestionarCategorias => 'Gestionar categorías',
            self::VerAfiliaciones => 'Ver tipos de afiliación',
            self::GestionarAfiliaciones => 'Gestionar tipos de afiliación',
            self::VerServicios => 'Ver servicios',
            self::GestionarServicios => 'Gestionar servicios',
            self::VerTrabajos => 'Ver trabajos de afiliados',
            self::GestionarTrabajos => 'Gestionar trabajos de afiliados',
            self::VerConfiguracion => 'Ver configuración',
            self::GestionarConfiguracion => 'Gestionar configuración',
            self::VerRoles => 'Ver roles',
            self::GestionarRoles => 'Gestionar roles',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::VerPanel => 'panel',
            self::VerAfiliados, self::GestionarAfiliados => 'afiliados',
            self::VerPagos, self::GestionarPagos => 'pagos',
            self::VerReportes, self::VerReporteDeudas, self::VerReporteIngresos => 'reportes',
            self::GestionarUsuarios, self::GestionarUsuariosConsulta => 'usuarios',
            self::VerMultas, self::GestionarMultas => 'multas',
            self::VerOtb => 'otb',
            self::VerCategorias, self::GestionarCategorias => 'categorias',
            self::VerAfiliaciones, self::GestionarAfiliaciones => 'afiliaciones',
            self::VerServicios, self::GestionarServicios => 'servicios',
            self::VerTrabajos, self::GestionarTrabajos => 'trabajos',
            self::VerConfiguracion, self::GestionarConfiguracion => 'configuracion',
            self::VerRoles, self::GestionarRoles => 'roles',
        };
    }

    public static function forSecretaria(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $permission): bool => ! in_array($permission, [
                self::GestionarUsuarios,
                self::GestionarUsuariosConsulta,
                self::VerRoles,
                self::GestionarRoles,
            ], true),
        ));
    }

    public static function forPresidente(): array
    {
        return [
            self::VerPanel,
            self::VerAfiliados,
            self::VerReportes,
            self::VerReporteDeudas,
            self::VerReporteIngresos,
            self::VerPagos,
            self::VerMultas,
            self::VerOtb,
            self::VerServicios,
            self::VerCategorias,
            self::VerAfiliaciones,
            self::VerTrabajos,
            self::GestionarUsuariosConsulta,
            self::VerRoles,
            self::GestionarRoles,
        ];
    }

    public static function forConsultaPagosMultas(): array
    {
        return [
            self::VerPanel,
            self::VerPagos,
            self::VerMultas,
        ];
    }

    public static function forAdministrador(): array
    {
        return self::forSecretaria();
    }

    public static function forCajera(): array
    {
        return self::forConsultaPagosMultas();
    }

    public static function forOperador(): array
    {
        return self::forConsultaPagosMultas();
    }

    public static function valuesForSecretaria(): array
    {
        return array_map(fn (self $case) => $case->value, self::forSecretaria());
    }

    public static function valuesForPresidente(): array
    {
        return array_map(fn (self $case) => $case->value, self::forPresidente());
    }

    public static function valuesForConsultaPagosMultas(): array
    {
        return array_map(fn (self $case) => $case->value, self::forConsultaPagosMultas());
    }

    public static function valuesForAdministrador(): array
    {
        return self::valuesForSecretaria();
    }

    public static function valuesForCajera(): array
    {
        return self::valuesForConsultaPagosMultas();
    }

    public static function valuesForOperador(): array
    {
        return self::valuesForConsultaPagosMultas();
    }
}
