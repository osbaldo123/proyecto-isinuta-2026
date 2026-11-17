import AccesoSistemaController from './AccesoSistemaController'
import Auth from './Auth'
import DashboardController from './DashboardController'
import AfiliadosController from './AfiliadosController'
import PagosController from './PagosController'
import EstadoCuentaController from './EstadoCuentaController'
import MultasController from './MultasController'
import ReportesController from './ReportesController'
import UsersController from './UsersController'
import OtbsController from './OtbsController'
import CategoriasController from './CategoriasController'
import TiposAfiliacionController from './TiposAfiliacionController'
import ServiciosController from './ServiciosController'
import TrabajosController from './TrabajosController'
import ConfiguracionController from './ConfiguracionController'
import RolesController from './RolesController'
import Settings from './Settings'
const Controllers = {
    AccesoSistemaController: Object.assign(AccesoSistemaController, AccesoSistemaController),
Auth: Object.assign(Auth, Auth),
DashboardController: Object.assign(DashboardController, DashboardController),
AfiliadosController: Object.assign(AfiliadosController, AfiliadosController),
PagosController: Object.assign(PagosController, PagosController),
EstadoCuentaController: Object.assign(EstadoCuentaController, EstadoCuentaController),
MultasController: Object.assign(MultasController, MultasController),
ReportesController: Object.assign(ReportesController, ReportesController),
UsersController: Object.assign(UsersController, UsersController),
OtbsController: Object.assign(OtbsController, OtbsController),
CategoriasController: Object.assign(CategoriasController, CategoriasController),
TiposAfiliacionController: Object.assign(TiposAfiliacionController, TiposAfiliacionController),
ServiciosController: Object.assign(ServiciosController, ServiciosController),
TrabajosController: Object.assign(TrabajosController, TrabajosController),
ConfiguracionController: Object.assign(ConfiguracionController, ConfiguracionController),
RolesController: Object.assign(RolesController, RolesController),
Settings: Object.assign(Settings, Settings),
}

export default Controllers