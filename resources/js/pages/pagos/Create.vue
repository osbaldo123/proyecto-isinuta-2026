<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Check, CircleAlert, Minus, Plus, ReceiptText, Search, Trash2 } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    cantidadMeses,
    clasificarLineaLibre,
    fechaLarga,
    formatBs,
    hoyLocal,
    iniciales,
    rangoMeses,
    sumar,
    tituloRecibo,
    type CuentaRecibo,
} from '@/lib/pagos';

type AfiliadoOption = {
    id: number;
    codigo: string;
    numero_lote?: string;
    nombre_completo: string;
    ci: string;
};

type MesCobro = {
    periodo: string;
    etiqueta: string;
    corto: string;
    monto: number;
    cuota_id: number | null;
    estado: string;
};

type ServicioCobro = {
    servicio_id: number;
    nombre: string;
    tipo: string;
    concepto: string;
    descripcion: string;
    tarifa: number;
    vencidos: number;
    meses: MesCobro[];
    meses_pagados?: MesCobro[];
    recibo: CuentaRecibo;
    texto: string;
};

type MultaPendiente = {
    id: number;
    motivo: string;
    gestion: number | null;
    periodo: string | null;
    descripcion: string | null;
    descripcion_cobro: string;
    monto: number;
    concepto: string;
    titulo: string;
    detalle: string;
    recibo: CuentaRecibo;
    texto: string;
};

type AfiliacionCobro = {
    concepto: string;
    descripcion: string;
    modalidad: string | null;
    saldo: number;
    editable: boolean;
    recibo: CuentaRecibo;
    texto: string;
};

type AfiliadoSeleccionado = AfiliadoOption & {
    afiliado_id: number;
    lote_afiliado_id: number | null;
    otb: string | null;
    estado: string;
    categoria: string | null;
    forma_pago_afiliacion: string | null;
    forma_pago_afiliacion_label: string | null;
    total_cuotas_pendientes: number;
    total_multas_pendientes: number;
    saldo_afiliacion: number;
    multas_pendientes: MultaPendiente[];
    servicios_cobro: ServicioCobro[];
    afiliaciones_cobro: AfiliacionCobro[];
};

type LineaLibre = { id: number; concepto: string; descripcion: string; monto: number | null };

type LineaCobro = {
    concepto: string;
    descripcion: string;
    monto: number;
    servicio_id?: number;
    meses?: number;
    multa_id?: number;
    modalidad?: string | null;
};

const props = defineProps<{
    afiliados: AfiliadoOption[];
    afiliadoSeleccionado: AfiliadoSeleccionado | null;
    metodosPago: Array<{ value: string; label: string }>;
    conceptosLibres: Array<{ value: string; label: string }>;
    conceptosServicio?: unknown[];
    conceptosAfiliacion?: unknown[];
    multaId?: number | null;
}>();

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Recibos', href: '/pagos' },
            { title: 'Cobrar', href: '/pagos/registrar/nuevo' },
        ],
    }),
});

const hoy = hoyLocal();

const form = useForm({
    afiliado_id: props.afiliadoSeleccionado?.id ?? (null as number | null),
    fecha_pago: hoy,
    metodo_pago: 'efectivo',
    observaciones: '',
});

const afiliado = computed(() => props.afiliadoSeleccionado);
const servicios = computed(() => afiliado.value?.servicios_cobro ?? []);
const multas = computed(() => afiliado.value?.multas_pendientes ?? []);
const afiliaciones = computed(() => afiliado.value?.afiliaciones_cobro ?? []);
const deBaja = computed(() => afiliado.value?.estado === 'baja');

/* ── Selección ───────────────────────────────────────────── */

const cantidades = ref<Record<number, number>>({});
const anioVista = ref<Record<number, number>>({});
const multasElegidas = ref<Record<number, boolean>>({});
const afiliacionesElegidas = ref<Record<string, { activa: boolean; monto: number | null }>>({});
const libres = ref<LineaLibre[]>([]);
const recibido = ref('');
let siguienteLibre = 1;

const adeudados = (servicio: ServicioCobro) => {
    let n = 0;

    while (n < servicio.meses.length && ['vencido', 'pendiente'].includes(servicio.meses[n].estado)) {
        n++;
    }

    return n;
};

const anioPorDefecto = (servicio: ServicioCobro) => {
    const deuda = servicio.meses.find((m) => m.estado === 'vencido' || m.estado === 'pendiente');
    const actual = servicio.meses.find((m) => m.estado === 'actual');

    return Number((deuda ?? actual ?? servicio.meses[0])?.periodo.slice(0, 4) ?? new Date().getFullYear());
};

const reiniciarSeleccion = () => {
    cantidades.value = Object.fromEntries(servicios.value.map((s) => [s.servicio_id, adeudados(s)]));
    anioVista.value = Object.fromEntries(servicios.value.map((s) => [s.servicio_id, anioPorDefecto(s)]));
    multasElegidas.value = Object.fromEntries(multas.value.map((m) => [m.id, true]));
    afiliacionesElegidas.value = Object.fromEntries(afiliaciones.value.map((a) => [a.concepto, { activa: false, monto: a.saldo }]));
    libres.value = [];
    recibido.value = '';
};

watch(
    () => props.afiliadoSeleccionado,
    (nuevo) => {
        form.afiliado_id = nuevo?.id ?? null;
        reiniciarSeleccion();
    },
    { immediate: true },
);

const cantidad = (servicio: ServicioCobro) => cantidades.value[servicio.servicio_id] ?? 0;

const fijarCantidad = (servicio: ServicioCobro, n: number) => {
    cantidades.value[servicio.servicio_id] = Math.max(0, Math.min(servicio.meses.length, n));
};

const elegirMes = (servicio: ServicioCobro, indice: number) => {
    fijarCantidad(servicio, indice + 1 === cantidad(servicio) ? indice : indice + 1);
};

/** Meses de cobro: feb, abr, jun, ago, oct, dic (ahí se cobra). */
const esMesDeCobro = (periodo: string) => Number(periodo.slice(5, 7)) % 2 === 0;

/** Azul solo en meses de cobro incluidos en la selección. */
const marcadoAzul = (servicio: ServicioCobro, mes: MesCobro, indice: number | null) =>
    indice !== null && indice < cantidad(servicio) && esMesDeCobro(mes.periodo);

const CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

const mesPlaceholder = (periodo: string, estado: string, monto = 0): MesCobro => {
    const [anio, mes] = periodo.split('-').map(Number);

    return {
        periodo,
        etiqueta: `${CORTOS[(mes || 1) - 1] ?? periodo} ${anio}`,
        corto: `${CORTOS[(mes || 1) - 1] ?? periodo} ${anio}`,
        monto,
        cuota_id: null,
        estado,
    };
};

type MesVista = { mes: MesCobro; indice: number | null };

const aniosDisponibles = (servicio: ServicioCobro): number[] => {
    if (servicio.meses.length === 0) {
        return [];
    }

    const anios = new Set<number>();
    for (const mes of servicio.meses) {
        anios.add(Number(mes.periodo.slice(0, 4)));
    }
    for (const mes of servicio.meses_pagados ?? []) {
        anios.add(Number(mes.periodo.slice(0, 4)));
    }

    // Tiempo real: siempre el año de hoy (si pasó 2027, aparece 2028 cuando el sistema ya lo tenga).
    const hoy = new Date().getFullYear();
    anios.add(hoy);
    anios.add(Number(servicio.meses[0].periodo.slice(0, 4)));
    anios.add(Number(servicio.meses[servicio.meses.length - 1].periodo.slice(0, 4)));

    const ordenados = [...anios].sort((a, b) => a - b);
    const desde = ordenados[0];
    const hasta = Math.max(ordenados[ordenados.length - 1], hoy);
    const rango: number[] = [];

    for (let anio = desde; anio <= hasta; anio++) {
        rango.push(anio);
    }

    return rango;
};

const anioDe = (servicio: ServicioCobro) => anioVista.value[servicio.servicio_id] ?? anioPorDefecto(servicio);

const cambiarAnio = (servicio: ServicioCobro, delta: number) => {
    const anios = aniosDisponibles(servicio);
    const idx = anios.indexOf(anioDe(servicio));
    const siguiente = anios[Math.max(0, Math.min(anios.length - 1, (idx < 0 ? 0 : idx) + delta))];

    if (siguiente !== undefined) {
        anioVista.value[servicio.servicio_id] = siguiente;
    }
};

/**
 * Solo los 12 meses del año elegido (ene→dic). +/- cambia de año por espacio en pantalla.
 */
const mesesVista = (servicio: ServicioCobro): MesVista[] => {
    const cobrables = servicio.meses;
    if (cobrables.length === 0) {
        return [];
    }

    const anio = anioDe(servicio);
    const porPeriodo = new Map(cobrables.map((mes, indice) => [mes.periodo, { mes, indice }]));
    const pagados = new Map((servicio.meses_pagados ?? []).map((mes) => [mes.periodo, mes]));
    const primerCobrable = cobrables[0].periodo;
    const ultimo = cobrables[cobrables.length - 1].periodo;
    const vista: MesVista[] = [];

    for (let mesNum = 1; mesNum <= 12; mesNum++) {
        const periodo = `${anio}-${String(mesNum).padStart(2, '0')}`;
        const cobrable = porPeriodo.get(periodo);

        if (cobrable) {
            vista.push(cobrable);
            continue;
        }

        if (periodo < primerCobrable) {
            vista.push({
                mes: pagados.get(periodo) ?? mesPlaceholder(periodo, 'pagado', servicio.tarifa),
                indice: null,
            });
            continue;
        }

        if (periodo > ultimo) {
            vista.push({
                mes: mesPlaceholder(periodo, 'adelanto', servicio.tarifa),
                indice: null,
            });
            continue;
        }

        // Hueco dentro del rango cobrable (p. ej. ya cubierto): se muestra como pagado.
        vista.push({
            mes: pagados.get(periodo) ?? mesPlaceholder(periodo, 'pagado', servicio.tarifa),
            indice: null,
        });
    }

    return vista;
};

const mesesElegidos = (servicio: ServicioCobro) => servicio.meses.slice(0, cantidad(servicio));
const subtotal = (servicio: ServicioCobro) => sumar(mesesElegidos(servicio).map((m) => m.monto));

const atajos = (servicio: ServicioCobro) => {
    const lista: Array<{ texto: string; valor: number }> = [];
    const debe = adeudados(servicio);
    const actual = servicio.meses.findIndex((m) => m.estado === 'actual');

    if (debe > 0) {
        lista.push({ texto: `Lo que debe (${debe})`, valor: debe });
    }

    if (actual >= 0 && actual + 1 !== debe) {
        lista.push({ texto: `Hasta ${servicio.meses[actual].etiqueta.split(' ')[0]} (${actual + 1})`, valor: actual + 1 });
    }

    const anio = Math.min(12, servicio.meses.length);

    if (anio > 0 && !lista.some((a) => a.valor === anio)) {
        lista.push({ texto: `Un año (${anio})`, valor: anio });
    }

    lista.push({ texto: 'Ninguno', valor: 0 });

    return lista;
};

/** Trimestres fijos: la multa (rojo) solo en abr / ago / dic — nunca en los 3 meses de demora. */
const TRAMOS_MORA = [
    { meses: [1, 2, 3], aplica: 4 },
    { meses: [5, 6, 7], aplica: 8 },
    { meses: [9, 10, 11], aplica: 12 },
] as const;

const MES_APLICA_MORA = new Set([4, 8, 12]);

/**
 * Fase del cobro (días 1–2 del mes de cobro):
 * 1 = día 1 (azul), 2 = día 2 (naranja), 3 = desde el 3 o mes ya pasado (amarillo pendiente).
 */
const faseCobro = (periodo: string): 1 | 2 | 3 => {
    const [anio, mes] = periodo.split('-').map(Number);
    const hoy = new Date();
    const anioHoy = hoy.getFullYear();
    const mesHoy = hoy.getMonth() + 1;
    const diaHoy = hoy.getDate();

    if (anioHoy > anio || (anioHoy === anio && mesHoy > mes)) {
        return 3;
    }

    if (anioHoy === anio && mesHoy === mes) {
        if (diaHoy <= 1) {
            return 1;
        }

        if (diaHoy === 2) {
            return 2;
        }

        return 3;
    }

    return 1;
};

/** Mes que el afiliado todavía debe (vencido, pendiente, actual o adelanto en la lista). */
const mesSinPagar = (servicio: ServicioCobro, periodo: string) =>
    servicio.meses.some((mes) => mes.periodo === periodo && mes.estado !== 'pagado');

/** Periodos abr/ago/dic de multas de mora ya generadas (recibo Bs 50/100/150). */
const periodosMoraEnRecibo = computed(() =>
    multas.value
        .filter((m) => m.motivo === 'mora_trimestral' || m.motivo === 'mora_anual')
        .map((m) => m.periodo)
        .filter((p): p is string => !!p && MES_APLICA_MORA.has(Number(p.slice(5, 7)))),
);

/**
 * Rojo solo en abr / ago / dic:
 * - si ya hay multa de mora de ese mes en el recibo, o
 * - si el afiliado debe los 3 meses del trimestre (sep–oct–nov → dic, etc.).
 */
const esMesDeMultaTrimestral = (servicio: ServicioCobro, periodo: string) => {
    const mes = Number(periodo.slice(5, 7));

    if (!MES_APLICA_MORA.has(mes)) {
        return false;
    }

    if (periodosMoraEnRecibo.value.includes(periodo)) {
        return true;
    }

    const anio = Number(periodo.slice(0, 4));
    const tramo = TRAMOS_MORA.find((t) => t.aplica === mes);

    if (!tramo) {
        return false;
    }

    return tramo.meses.every((m) => mesSinPagar(servicio, `${anio}-${String(m).padStart(2, '0')}`));
};

const pieMes = (servicio: ServicioCobro, mes: MesCobro) => {
    if (mes.estado === 'pagado') {
        return 'pagado';
    }

    if (esMesDeMultaTrimestral(servicio, mes.periodo)) {
        return 'mora';
    }

    if (mes.estado === 'adelanto') {
        return 'adelanto';
    }

    if (mes.estado === 'actual' && !esMesDeCobro(mes.periodo)) {
        return 'este mes';
    }

    // Meses de cobro: «1 - 2» = días de cobro (el color indica la fase).
    if (esMesDeCobro(mes.periodo) && ['vencido', 'pendiente', 'actual'].includes(mes.estado)) {
        return '1 - 2';
    }

    if (mes.estado === 'vencido') {
        return 'pendiente';
    }

    if (mes.estado === 'pendiente') {
        return 'por vencer';
    }

    return mes.estado;
};

/**
 * Colores suaves:
 * verde = pagado · naranja = día 2 · amarillo = pendiente (día 3+) ·
 * rojo = multa trimestral (solo abr / ago / dic) · azul = para cobrar.
 */
const claseMes = (servicio: ServicioCobro, mes: MesCobro, elegido: boolean) => {
    if (mes.estado === 'pagado') {
        return 'cursor-default border-emerald-200 bg-emerald-50 text-emerald-800 shadow-sm dark:border-emerald-400/40 dark:bg-emerald-400/10 dark:text-emerald-200';
    }

    // Rojo: solo abr / ago / dic cuando corresponde la mora (también si aún figura como adelanto).
    if (esMesDeMultaTrimestral(servicio, mes.periodo)) {
        return 'border-rose-200 bg-rose-50 text-rose-800 shadow-sm dark:border-rose-400/40 dark:bg-rose-400/10 dark:text-rose-200';
    }

    if (mes.estado === 'adelanto') {
        return 'border-dashed border-slate-300 bg-transparent text-slate-600 hover:border-sky-300 dark:border-slate-600 dark:text-slate-400 dark:hover:border-sky-500';
    }

    // Azul: mes de cobro seleccionado o día 1 (para cobrar).
    if (elegido && esMesDeCobro(mes.periodo)) {
        return 'border-sky-300 bg-sky-100 text-sky-900 shadow-sm dark:border-sky-400/50 dark:bg-sky-400/15 dark:text-sky-200';
    }

    if (esMesDeCobro(mes.periodo) && ['vencido', 'pendiente', 'actual'].includes(mes.estado)) {
        const fase = faseCobro(mes.periodo);

        if (fase === 1) {
            return 'border-sky-200 bg-sky-50 text-sky-800 shadow-sm dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-200';
        }

        if (fase === 2) {
            return 'border-orange-200 bg-orange-50 text-orange-800 shadow-sm dark:border-orange-400/40 dark:bg-orange-400/10 dark:text-orange-200';
        }

        // Día 3+ o mes de cobro ya pasado → pendiente (amarillo).
        return 'border-amber-200 bg-amber-50 text-amber-900 shadow-sm dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-200';
    }

    // Ene, mar, sep… vencidos: pendiente (amarillo), no rojo de multa.
    if (mes.estado === 'vencido') {
        return 'border-amber-200 bg-amber-50 text-amber-900 shadow-sm dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-200';
    }

    return 'border-slate-300 bg-white text-slate-800 hover:border-sky-300 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:hover:border-sky-500';
};

const resumenServicio = (servicio: ServicioCobro) => {
    const n = cantidad(servicio);

    return n > 0 ? `${rangoMeses(mesesElegidos(servicio).map((m) => m.periodo))} · ${cantidadMeses(n)}` : 'No se cobra este servicio';
};

const agregarLibre = () => {
    libres.value.push({ id: siguienteLibre++, concepto: props.conceptosLibres[0]?.value ?? 'otro', descripcion: '', monto: null });
};

const quitarLibre = (id: number) => {
    libres.value = libres.value.filter((linea) => linea.id !== id);
};

/* ── Validación en pantalla ──────────────────────────────── */

const erroresLibre = computed(() => {
    const vistos = new Set<string>();
    const errores: Record<number, string> = {};

    for (const linea of libres.value) {
        const clave = `${linea.concepto}|${linea.descripcion.trim().toLowerCase()}`;

        if (linea.descripcion.trim() === '') {
            errores[linea.id] = 'Escriba qué se cobra.';
        } else if (!(Number(linea.monto) > 0)) {
            errores[linea.id] = 'Indique un monto mayor a cero.';
        } else if (vistos.has(clave)) {
            errores[linea.id] = 'Esta línea está repetida: cambie la descripción.';
        }

        vistos.add(clave);
    }

    return errores;
});

const errorAfiliacion = (opcion: AfiliacionCobro): string | null => {
    const eleccion = afiliacionesElegidas.value[opcion.concepto];

    if (!eleccion?.activa || !opcion.editable) {
        return null;
    }

    const monto = Number(eleccion.monto);

    if (!(monto > 0)) {
        return 'Indique cuánto abona.';
    }

    return monto > opcion.saldo + 0.001 ? `No puede pasar de lo que falta (${formatBs(opcion.saldo, true)}).` : null;
};

const hayErroresEnPantalla = computed(
    () => Object.keys(erroresLibre.value).length > 0 || afiliaciones.value.some((a) => errorAfiliacion(a) !== null),
);

/* ── Líneas que se envían y vista previa de recibos ──────── */

type LineaConCuenta = { linea: LineaCobro; cuenta: CuentaRecibo; texto: string };

const lineas = computed<LineaConCuenta[]>(() => {
    const resultado: LineaConCuenta[] = [];

    for (const servicio of servicios.value) {
        const n = cantidad(servicio);

        if (n > 0) {
            const periodos = mesesElegidos(servicio).map((m) => m.periodo);

            resultado.push({
                linea: {
                    concepto: servicio.concepto,
                    descripcion: servicio.descripcion,
                    servicio_id: servicio.servicio_id,
                    meses: n,
                    monto: subtotal(servicio),
                },
                cuenta: servicio.recibo,
                texto: `${servicio.texto} — ${rangoMeses(periodos)}${n > 1 ? ` (${cantidadMeses(n)})` : ''}`,
            });
        }
    }

    for (const multa of multas.value) {
        if (multasElegidas.value[multa.id]) {
            resultado.push({
                linea: { concepto: multa.concepto, descripcion: multa.descripcion_cobro, monto: multa.monto, multa_id: multa.id },
                cuenta: multa.recibo,
                texto: multa.texto,
            });
        }
    }

    for (const opcion of afiliaciones.value) {
        const eleccion = afiliacionesElegidas.value[opcion.concepto];

        if (eleccion?.activa) {
            resultado.push({
                linea: {
                    concepto: opcion.concepto,
                    descripcion: opcion.descripcion,
                    monto: opcion.editable ? Number(eleccion.monto) || 0 : opcion.saldo,
                    modalidad: opcion.modalidad,
                },
                cuenta: opcion.recibo,
                texto: opcion.texto,
            });
        }
    }

    for (const libre of libres.value) {
        resultado.push({
            linea: { concepto: libre.concepto, descripcion: libre.descripcion.trim(), monto: Number(libre.monto) || 0 },
            cuenta: clasificarLineaLibre(libre.concepto, libre.descripcion),
            texto: libre.descripcion.trim() || 'Sin descripción',
        });
    }

    return resultado;
});

const recibos = computed(() => {
    // Vista previa: agua/alc se parten por gestión (año), igual que al emitir en el servidor.
    const grupos = new Map<string, { cuenta: CuentaRecibo; lineas: Array<{ texto: string; monto: number }> }>();

    for (const servicio of servicios.value) {
        const n = cantidad(servicio);
        if (n <= 0) {
            continue;
        }

        const porAnio = new Map<string, MesCobro[]>();
        for (const mes of mesesElegidos(servicio)) {
            const anio = mes.periodo.slice(0, 4);
            const lista = porAnio.get(anio) ?? [];
            lista.push(mes);
            porAnio.set(anio, lista);
        }

        for (const [anio, mesesAnio] of porAnio) {
            const periodos = mesesAnio.map((m) => m.periodo);
            const key = servicio.recibo.key.includes(':') ? servicio.recibo.key : `${servicio.recibo.key}:${anio}`;
            const label = servicio.recibo.label.includes('gestión')
                ? servicio.recibo.label
                : `${servicio.recibo.label} · gestión ${anio}`;
            const cuenta = { ...servicio.recibo, key, label };
            const grupo = grupos.get(key) ?? { cuenta, lineas: [] };
            grupo.lineas.push({
                texto: `${servicio.texto} — ${rangoMeses(periodos)}${mesesAnio.length > 1 ? ` (${cantidadMeses(mesesAnio.length)})` : ''}`,
                monto: sumar(mesesAnio.map((m) => m.monto)),
            });
            grupos.set(key, grupo);
        }
    }

    for (const { linea, cuenta, texto } of lineas.value) {
        if (cuenta.key === 'servicio_agua_potable' || cuenta.key === 'servicio_alcantarillado' || cuenta.key.startsWith('servicio_agua_potable:') || cuenta.key.startsWith('servicio_alcantarillado:')) {
            continue; // ya armados arriba por gestión
        }

        const grupo = grupos.get(cuenta.key) ?? { cuenta, lineas: [] };
        grupo.lineas.push({ texto, monto: linea.monto });
        grupos.set(cuenta.key, grupo);
    }

    return [...grupos.values()].map((grupo) => ({ ...grupo, total: sumar(grupo.lineas.map((l) => l.monto)) }));
});

const total = computed(() => sumar(lineas.value.map(({ linea }) => linea.monto)));
const cantidadRecibos = computed(() => (recibos.value.length === 1 ? '1 recibo' : `${recibos.value.length} recibos`));
const esEfectivo = computed(() => form.metodo_pago === 'efectivo');
const montoRecibido = computed(() => Number(recibido.value.replace(',', '.')) || 0);
const diferencia = computed(() => sumar([montoRecibido.value, -total.value]));
const hayMultaMora = computed(() => multas.value.some((m) => m.motivo === 'mora_trimestral' || m.motivo === 'mora_anual'));

const puedeEmitir = computed(() => Boolean(afiliado.value) && !deBaja.value && total.value > 0 && !hayErroresEnPantalla.value && !form.processing);

const erroresServidor = computed(() =>
    Object.entries(form.errors)
        .filter(([campo]) => campo !== 'fecha_pago')
        .map(([, mensaje]) => mensaje),
);

const emitir = () => {
    if (!puedeEmitir.value) {
        return;
    }

    form.transform((datos) => ({ ...datos, detalles: lineas.value.map(({ linea }) => linea) })).post('/pagos', { preserveScroll: true });
};

/* ── Resumen del afiliado ────────────────────────────────── */

const vencido = computed(() => {
    const meses = servicios.value.flatMap((s) => s.meses.filter((m) => m.estado === 'vencido'));

    return { monto: sumar(meses.map((m) => m.monto)), periodos: new Set(meses.map((m) => m.periodo)).size };
});

const deudaTotal = computed(() =>
    sumar([afiliado.value?.total_cuotas_pendientes, afiliado.value?.total_multas_pendientes, afiliado.value?.saldo_afiliacion]),
);

const pendienteNoIncluido = computed(() => Math.max(0, sumar([deudaTotal.value, -total.value])));

const detalleMultas = computed(() => {
    const tipos = new Set(
        multas.value.map((m) => (m.motivo.startsWith('mora') ? 'mora' : m.motivo === 'falta_trabajo' ? 'falta de trabajo' : 'otras')),
    );

    return tipos.size === 0 ? 'Sin multas pendientes' : [...tipos].join(' y ').replace(/^./, (c) => c.toUpperCase());
});

const tieneMoraAnualPendiente = computed(() => {
    const deMora = multas.value.filter((m) => m.motivo === 'mora_trimestral' || m.motivo === 'mora_anual');
    const suma = deMora.reduce((acc, m) => acc + Number(m.monto || 0), 0);
    const hayEtiquetaAnual = deMora.some(
        (m) => m.motivo === 'mora_anual' || String(m.descripcion || m.titulo || '').includes('Mora anual'),
    );

    // Solo «anual» si ya hay mora anual etiquetada o el acumulado del ciclo llega a Bs 150.
    return hayEtiquetaAnual || suma >= 149.99;
});

const estadoAfiliado = computed(() => {
    const estado = afiliado.value?.estado;

    if (estado === 'baja') {
        return { texto: 'Dado de baja', clase: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' };
    }

    // Corte solo con mora anual Bs 150. Trimestral (Bs 50 / 100) = sola multa, sin corte.
    if (tieneMoraAnualPendiente.value) {
        return {
            texto: 'Cortado anual',
            clase: 'bg-orange-100 text-orange-800 dark:bg-orange-400/15 dark:text-orange-300',
        };
    }

    return { texto: 'Activo', clase: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-300' };
});

const tarifaTexto = (servicio: ServicioCobro) => {
    const categoria = (afiliado.value?.categoria ?? '').trim();

    return [categoria ? categoria.charAt(0).toUpperCase() + categoria.slice(1).toLowerCase() : null, `${formatBs(servicio.tarifa)} por mes`]
        .filter(Boolean)
        .join(' · ');
};

/* ── Buscador de afiliado (combobox accesible) ───────────── */

const busqueda = ref('');
const buscadorAbierto = ref(false);
const opcionActiva = ref(0);
const buscadorRaiz = ref<HTMLElement | null>(null);

const etiquetaAfiliado = (a: Pick<AfiliadoOption, 'codigo' | 'nombre_completo' | 'ci'>) => `${a.codigo} · ${a.nombre_completo} · CI ${a.ci}`;

const resultados = computed(() => {
    const termino = busqueda.value.trim().toLowerCase();

    if (termino === '') {
        return [];
    }

    const digitos = termino.replace(/\D/g, '');

    return props.afiliados
        .map((item) => {
            const lote = String(item.numero_lote ?? item.codigo.replace(/\D/g, ''));
            const coincide =
                item.nombre_completo.toLowerCase().includes(termino) ||
                item.codigo.toLowerCase().includes(termino) ||
                (digitos !== '' && (lote.includes(digitos) || item.ci.replace(/\D/g, '').includes(digitos)));

            return { item, prioridad: digitos !== '' && lote === digitos ? 0 : 1, coincide };
        })
        .filter((fila) => fila.coincide)
        .sort((a, b) => a.prioridad - b.prioridad)
        .slice(0, 12)
        .map((fila) => fila.item);
});

watch(busqueda, () => {
    opcionActiva.value = 0;
});

const elegirAfiliado = (id: number) => {
    buscadorAbierto.value = false;
    busqueda.value = '';
    router.get('/pagos/registrar/nuevo', { afiliado_id: id }, { preserveState: true, preserveScroll: true });
};

const teclaBuscador = (evento: KeyboardEvent) => {
    if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        buscadorAbierto.value = true;
        opcionActiva.value = Math.min(opcionActiva.value + 1, Math.max(resultados.value.length - 1, 0));
    } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        opcionActiva.value = Math.max(opcionActiva.value - 1, 0);
    } else if (evento.key === 'Enter') {
        evento.preventDefault();
        const elegido = resultados.value[opcionActiva.value];

        if (elegido) {
            elegirAfiliado(elegido.id);
        }
    } else if (evento.key === 'Escape') {
        buscadorAbierto.value = false;
    }
};

const clicFuera = (evento: MouseEvent) => {
    if (!buscadorRaiz.value?.contains(evento.target as Node)) {
        buscadorAbierto.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', clicFuera));
onBeforeUnmount(() => document.removeEventListener('mousedown', clicFuera));

const tarjeta = 'rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900';
const campo =
    'h-11 rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-900 transition [color-scheme:light] placeholder:text-slate-400 focus:border-cyan-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-400/25 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-50 dark:[color-scheme:dark]';
</script>

<template>
    <Head title="Cobrar" />

    <div class="min-h-[calc(100vh-4rem)] w-full">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-3 py-3 sm:gap-4 sm:px-6 sm:py-4 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-slate-50">Cobrar</h1>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">Elija qué paga el afiliado y revise los recibos antes de emitirlos.</p>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label for="fecha-pago" class="text-xs font-semibold text-slate-600 dark:text-slate-400">Fecha de pago</label>
                        <input
                            id="fecha-pago"
                            v-model="form.fecha_pago"
                            type="date"
                            :max="hoy"
                            required
                            :class="campo"
                            :aria-invalid="form.errors.fecha_pago ? 'true' : undefined"
                        />
                        <p v-if="form.errors.fecha_pago" class="text-xs font-medium text-red-600 dark:text-red-400">{{ form.errors.fecha_pago }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <span id="metodo-titulo" class="text-xs font-semibold text-slate-600 dark:text-slate-400">Método</span>
                        <div
                            role="group"
                            aria-labelledby="metodo-titulo"
                            class="flex gap-1 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <button
                                v-for="metodo in metodosPago"
                                :key="metodo.value"
                                type="button"
                                :aria-pressed="form.metodo_pago === metodo.value"
                                class="min-h-9 rounded-lg px-4 text-sm font-semibold transition"
                                :class="
                                    form.metodo_pago === metodo.value
                                        ? 'bg-cyan-600 text-white dark:bg-cyan-400 dark:text-cyan-950'
                                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                                "
                                @click="form.metodo_pago = metodo.value"
                            >
                                {{ metodo.label }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Afiliado -->
            <section aria-label="Afiliado" :class="[tarjeta, 'flex flex-col gap-4']">
                <div ref="buscadorRaiz" class="relative">
                    <label for="buscar-afiliado" class="sr-only">Buscar afiliado</label>
                    <Search class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-slate-400" />
                    <input
                        id="buscar-afiliado"
                        v-model="busqueda"
                        type="search"
                        role="combobox"
                        autocomplete="off"
                        aria-autocomplete="list"
                        aria-controls="resultados-afiliado"
                        :aria-expanded="buscadorAbierto && busqueda.trim() !== ''"
                        :aria-activedescendant="buscadorAbierto && resultados[opcionActiva] ? `afiliado-${resultados[opcionActiva].id}` : undefined"
                        :placeholder="afiliado ? `${etiquetaAfiliado(afiliado)} — buscar otro` : 'Buscar por nombre, número de lote o CI'"
                        :class="[campo, 'w-full pl-11']"
                        @focus="buscadorAbierto = true"
                        @input="buscadorAbierto = true"
                        @keydown="teclaBuscador"
                    />
                    <ul
                        v-if="buscadorAbierto && busqueda.trim() !== ''"
                        id="resultados-afiliado"
                        role="listbox"
                        class="absolute z-30 mt-1.5 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl dark:border-slate-700 dark:bg-slate-900"
                    >
                        <li v-if="resultados.length === 0" class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No hay coincidencias.</li>
                        <li
                            v-for="(opcion, indice) in resultados"
                            :id="`afiliado-${opcion.id}`"
                            :key="opcion.id"
                            role="option"
                            :aria-selected="indice === opcionActiva"
                            class="cursor-pointer px-4 py-2.5 text-sm text-slate-900 dark:text-slate-50"
                            :class="indice === opcionActiva ? 'bg-cyan-50 dark:bg-cyan-400/10' : ''"
                            @mouseenter="opcionActiva = indice"
                            @mousedown.prevent="elegirAfiliado(opcion.id)"
                        >
                            {{ etiquetaAfiliado(opcion) }}
                        </li>
                    </ul>
                    <p v-if="form.errors.afiliado_id" class="mt-1 text-xs font-medium text-red-600 dark:text-red-400">
                        {{ form.errors.afiliado_id }}
                    </p>
                </div>

                <template v-if="afiliado">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3.5">
                            <div
                                aria-hidden="true"
                                class="flex size-12 shrink-0 items-center justify-center rounded-full bg-slate-100 font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            >
                                {{ iniciales(afiliado.nombre_completo) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <strong class="text-lg text-slate-900 dark:text-slate-50">{{ afiliado.nombre_completo }}</strong>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="estadoAfiliado.clase"
                                    >
                                        <CircleAlert v-if="tieneMoraAnualPendiente" class="size-3.5" />
                                        {{ estadoAfiliado.texto }}
                                    </span>
                                </div>
                                <p class="mt-1 flex flex-wrap gap-x-3.5 gap-y-0.5 text-[13px] text-slate-600 dark:text-slate-400">
                                    <span>Lote {{ afiliado.numero_lote || '—' }}</span>
                                    <span v-if="afiliado.otb">OTB {{ afiliado.otb }}</span>
                                    <span>CI {{ afiliado.ci }}</span>
                                    <span v-if="afiliado.categoria">Categoría {{ afiliado.categoria.toLowerCase() }}</span>
                                </p>
                            </div>
                        </div>
                        <Link
                            v-if="afiliado.lote_afiliado_id"
                            :href="`/pagos/estado-cuenta/${afiliado.lote_afiliado_id}`"
                            class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 px-3.5 text-sm font-medium text-slate-800 transition hover:border-cyan-300 hover:text-cyan-700 dark:border-slate-700 dark:text-slate-100 dark:hover:border-cyan-600 dark:hover:text-cyan-300"
                        >
                            Estado de cuenta
                        </Link>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                        <div
                            class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 dark:border-slate-800 dark:bg-slate-950/60"
                        >
                            <span class="text-xs text-slate-600 dark:text-slate-400">Mensualidades pendientes</span>
                            <strong
                                class="text-xl tabular-nums"
                                :class="vencido.monto > 0 ? 'text-orange-700 dark:text-orange-300' : 'text-slate-900 dark:text-slate-50'"
                                >{{ formatBs(vencido.monto) }}</strong
                            >
                            <span class="text-xs text-slate-500 dark:text-slate-500">{{
                                vencido.periodos > 0 ? cantidadMeses(vencido.periodos) : 'Al día con las mensualidades'
                            }}</span>
                        </div>
                        <div
                            class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 dark:border-slate-800 dark:bg-slate-950/60"
                        >
                            <span class="text-xs text-slate-600 dark:text-slate-400">Multas pendientes</span>
                            <strong class="text-xl tabular-nums text-slate-900 dark:text-slate-50">{{
                                formatBs(afiliado.total_multas_pendientes)
                            }}</strong>
                            <span class="text-xs text-slate-500 dark:text-slate-500">{{ detalleMultas }}</span>
                        </div>
                        <div
                            class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 dark:border-slate-800 dark:bg-slate-950/60"
                        >
                            <span class="text-xs text-slate-600 dark:text-slate-400">Saldo de afiliación</span>
                            <strong class="text-xl tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(afiliado.saldo_afiliacion) }}</strong>
                            <span class="text-xs text-slate-500 dark:text-slate-500">{{
                                afiliado.saldo_afiliacion > 0 ? afiliado.forma_pago_afiliacion_label || 'Pendiente' : 'Afiliación al día'
                            }}</span>
                        </div>
                        <div
                            class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 dark:border-slate-800 dark:bg-slate-950/60"
                        >
                            <span class="text-xs text-slate-600 dark:text-slate-400">Deuda total</span>
                            <strong class="text-xl tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(deudaTotal) }}</strong>
                            <span class="text-xs text-slate-500 dark:text-slate-500">Al {{ fechaLarga(hoy) }}</span>
                        </div>
                    </div>

                </template>
                <p v-else class="text-sm text-slate-600 dark:text-slate-400">Busque al afiliado para ver lo que debe y cobrarle.</p>
            </section>

            <div
                v-if="deBaja"
                role="alert"
                class="flex items-start gap-3 rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            >
                <AlertTriangle class="mt-0.5 size-5 shrink-0 text-orange-600 dark:text-orange-300" />
                Este lote está dado de baja: no se le pueden registrar cobros.
            </div>

            <div v-if="afiliado && !deBaja" class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="flex min-w-0 flex-col gap-5">
                    <!-- Mensualidades -->
                    <section aria-labelledby="mensualidades-titulo" :class="[tarjeta, 'flex flex-col gap-4']">
                        <h2 id="mensualidades-titulo" class="text-[17px] font-bold text-slate-900 dark:text-slate-50">Mensualidades</h2>

                        <p
                            v-if="servicios.length === 0"
                            class="rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-400"
                        >
                            Este lote no tiene servicio de agua ni de alcantarillado.
                        </p>

                        <div
                            v-for="servicio in servicios"
                            :key="servicio.servicio_id"
                            class="flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-slate-800"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <strong class="text-[15px] text-slate-900 dark:text-slate-50">{{ servicio.nombre }}</strong>
                                    <p class="text-[13px] text-slate-600 dark:text-slate-400">{{ tarifaTexto(servicio) }}</p>
                                </div>
                                <div v-if="servicio.meses.length > 0 && aniosDisponibles(servicio).length > 0" class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex size-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-800 transition hover:border-cyan-400 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                        :aria-label="`Año anterior`"
                                        :disabled="anioDe(servicio) <= (aniosDisponibles(servicio)[0] ?? 0)"
                                        @click="cambiarAnio(servicio, -1)"
                                    >
                                        <Minus class="size-4" />
                                    </button>
                                    <div aria-live="polite" class="flex min-w-[5.5rem] flex-col items-center">
                                        <strong class="text-lg tabular-nums text-slate-900 dark:text-slate-50">{{ anioDe(servicio) }}</strong>
                                        <span class="text-xs text-slate-500">año</span>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex size-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-800 transition hover:border-cyan-400 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                        :aria-label="`Año siguiente`"
                                        :disabled="anioDe(servicio) >= (aniosDisponibles(servicio).at(-1) ?? 0)"
                                        @click="cambiarAnio(servicio, 1)"
                                    >
                                        <Plus class="size-4" />
                                    </button>
                                </div>
                            </div>

                            <p v-if="servicio.meses.length === 0" class="text-sm text-slate-600 dark:text-slate-400">
                                No hay meses por cobrar: está al día.
                            </p>

                            <div v-else role="group" :aria-label="`Meses de ${servicio.nombre.toLowerCase()}`" class="flex flex-wrap gap-2">
                                <button
                                    v-for="item in mesesVista(servicio)"
                                    :key="item.mes.periodo"
                                    type="button"
                                    :disabled="item.indice === null"
                                    :aria-pressed="marcadoAzul(servicio, item.mes, item.indice)"
                                    :aria-label="`${item.mes.etiqueta}, ${pieMes(servicio, item.mes)}, ${formatBs(item.mes.monto)}${item.indice !== null && item.indice < cantidad(servicio) ? ', incluido' : ''}`"
                                    class="flex min-h-[52px] min-w-[4.9rem] flex-col items-start justify-center gap-0.5 rounded-xl border px-2.5 py-1.5 text-left transition disabled:cursor-default"
                                    :class="claseMes(servicio, item.mes, marcadoAzul(servicio, item.mes, item.indice))"
                                    @click="item.indice !== null && elegirMes(servicio, item.indice)"
                                >
                                    <span class="flex items-center gap-1 text-sm font-semibold">
                                        <Check
                                            v-if="item.mes.estado === 'pagado' || marcadoAzul(servicio, item.mes, item.indice)"
                                            class="size-3.5"
                                            :stroke-width="3"
                                        />
                                        {{ item.mes.corto }}
                                    </span>
                                    <span class="text-[11px] font-medium tabular-nums opacity-80">{{ formatBs(item.mes.monto) }}</span>
                                    <span class="text-[10px] font-medium opacity-70">{{ pieMes(servicio, item.mes) }}</span>
                                </button>
                            </div>

                            <div v-if="servicio.meses.length > 0" class="flex flex-wrap items-center justify-between gap-2.5">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="atajo in atajos(servicio)"
                                        :key="atajo.texto"
                                        type="button"
                                        :aria-pressed="cantidad(servicio) === atajo.valor"
                                        class="min-h-9 rounded-full border px-3 text-[13px] transition"
                                        :class="
                                            cantidad(servicio) === atajo.valor
                                                ? 'border-cyan-500 bg-cyan-50 font-semibold text-cyan-800 dark:border-cyan-400 dark:bg-cyan-400/15 dark:text-cyan-300'
                                                : 'border-slate-200 text-slate-600 hover:border-cyan-300 dark:border-slate-700 dark:text-slate-300 dark:hover:border-cyan-600'
                                        "
                                        @click="fijarCantidad(servicio, atajo.valor)"
                                    >
                                        {{ atajo.texto }}
                                    </button>
                                </div>
                                <span class="text-sm text-slate-700 dark:text-slate-200">
                                    {{ resumenServicio(servicio) }}:
                                    <strong class="tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(subtotal(servicio)) }}</strong>
                                </span>
                            </div>
                        </div>

                    </section>

                    <!-- Multas -->
                    <section v-if="multas.length > 0" aria-labelledby="multas-titulo" :class="[tarjeta, 'flex flex-col gap-3']">
                        <div>
                            <h2 id="multas-titulo" class="text-[17px] font-bold text-slate-900 dark:text-slate-50">Multas pendientes</h2>
                            <p class="mt-1 text-[13px] text-slate-600 dark:text-slate-400">
                                <template v-if="hayMultaMora">
                                    Mora trimestral: Bs 50 (solo multa, sin corte). Se acumula Bs 50 → Bs 100 → Bs 150 anual.
                                    El corte del servicio aplica solo con la mora anual Bs 150.
                                </template>
                                <template v-else>
                                    Incluye faltas de trabajo u otras multas. Desmarque las que no cobrará en este recibo.
                                </template>
                            </p>
                        </div>
                        <label
                            v-for="multa in multas"
                            :key="multa.id"
                            :for="`multa-${multa.id}`"
                            class="flex min-h-[52px] cursor-pointer items-center gap-3 rounded-xl border bg-slate-50 px-3 py-2.5 transition dark:bg-slate-950/60"
                            :class="multasElegidas[multa.id] ? 'border-cyan-400 dark:border-cyan-500' : 'border-slate-200 dark:border-slate-800'"
                        >
                            <input
                                :id="`multa-${multa.id}`"
                                v-model="multasElegidas[multa.id]"
                                type="checkbox"
                                class="size-5 shrink-0 cursor-pointer accent-cyan-600 [color-scheme:light] dark:accent-cyan-400 dark:[color-scheme:dark]"
                            />
                            <span class="flex min-w-0 flex-1 flex-col">
                                <strong class="text-sm text-slate-900 dark:text-slate-50">{{ multa.titulo }}</strong>
                                <span class="text-xs text-slate-600 dark:text-slate-400">{{ multa.detalle || multa.descripcion_cobro }}</span>
                            </span>
                            <strong class="text-[15px] tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(multa.monto) }}</strong>
                        </label>
                    </section>

                    <!-- Afiliación y otros conceptos -->
                    <section aria-labelledby="otros-titulo" :class="[tarjeta, 'flex flex-col gap-3']">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 id="otros-titulo" class="text-[17px] font-bold text-slate-900 dark:text-slate-50">
                                    Afiliación y otros conceptos
                                </h2>
                                <p class="mt-1 text-[13px] text-slate-600 dark:text-slate-400">
                                    {{ afiliaciones.length > 0 ? 'Saldo de afiliación pendiente.' : 'Afiliación al día.' }}
                                    Aportes y otros cobros se agregan con su descripción.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-dashed border-slate-300 px-3.5 text-sm font-medium text-slate-800 transition hover:border-cyan-400 hover:text-cyan-700 dark:border-slate-600 dark:text-slate-100 dark:hover:border-cyan-500 dark:hover:text-cyan-300"
                                @click="agregarLibre"
                            >
                                <Plus class="size-4" />
                                Agregar concepto
                            </button>
                        </div>

                        <div
                            v-for="opcion in afiliaciones"
                            :key="opcion.concepto"
                            class="flex flex-wrap items-center gap-3 rounded-xl border bg-slate-50 px-3 py-2.5 dark:bg-slate-950/60"
                            :class="
                                afiliacionesElegidas[opcion.concepto]?.activa
                                    ? 'border-cyan-400 dark:border-cyan-500'
                                    : 'border-slate-200 dark:border-slate-800'
                            "
                        >
                            <input
                                :id="`afiliacion-${opcion.concepto}`"
                                v-model="afiliacionesElegidas[opcion.concepto].activa"
                                type="checkbox"
                                class="size-5 shrink-0 cursor-pointer accent-cyan-600 [color-scheme:light] dark:accent-cyan-400 dark:[color-scheme:dark]"
                            />
                            <label :for="`afiliacion-${opcion.concepto}`" class="flex min-w-0 flex-1 cursor-pointer flex-col">
                                <strong class="text-sm text-slate-900 dark:text-slate-50">{{ opcion.descripcion }}</strong>
                                <span class="text-xs text-slate-600 dark:text-slate-400">
                                    {{
                                        opcion.editable
                                            ? `Falta pagar ${formatBs(opcion.saldo, true)}. Puede abonar una parte.`
                                            : 'Se cobra el monto completo.'
                                    }}
                                </span>
                            </label>
                            <div v-if="opcion.editable" class="flex flex-col items-end gap-1">
                                <label :for="`abono-${opcion.concepto}`" class="sr-only">Monto del abono</label>
                                <input
                                    :id="`abono-${opcion.concepto}`"
                                    v-model.number="afiliacionesElegidas[opcion.concepto].monto"
                                    v-numeric.decimal
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    :max="opcion.saldo"
                                    inputmode="decimal"
                                    :disabled="!afiliacionesElegidas[opcion.concepto]?.activa"
                                    :class="[campo, 'w-32 text-right tabular-nums disabled:opacity-50']"
                                />
                                <span v-if="errorAfiliacion(opcion)" class="text-xs font-medium text-red-600 dark:text-red-400">{{
                                    errorAfiliacion(opcion)
                                }}</span>
                            </div>
                            <strong v-else class="text-[15px] tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(opcion.saldo) }}</strong>
                        </div>

                        <div
                            v-for="(linea, indice) in libres"
                            :key="linea.id"
                            class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/60"
                        >
                            <div class="grid gap-2 sm:grid-cols-[10rem_minmax(0,1fr)_8rem_auto]">
                                <div>
                                    <label :for="`libre-concepto-${linea.id}`" class="sr-only">Concepto {{ indice + 1 }}</label>
                                    <select :id="`libre-concepto-${linea.id}`" v-model="linea.concepto" :class="[campo, 'w-full pr-9']">
                                        <option v-for="opcion in conceptosLibres" :key="opcion.value" :value="opcion.value">
                                            {{ opcion.label }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label :for="`libre-descripcion-${linea.id}`" class="sr-only">Descripción {{ indice + 1 }}</label>
                                    <input
                                        :id="`libre-descripcion-${linea.id}`"
                                        v-model="linea.descripcion"
                                        type="text"
                                        maxlength="255"
                                        placeholder="Descripción (por ejemplo: certificado de no adeudo)"
                                        :class="[campo, 'w-full']"
                                    />
                                </div>
                                <div>
                                    <label :for="`libre-monto-${linea.id}`" class="sr-only">Monto {{ indice + 1 }}</label>
                                    <input
                                        :id="`libre-monto-${linea.id}`"
                                        v-model.number="linea.monto"
                                        v-numeric.decimal
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        inputmode="decimal"
                                        placeholder="Bs"
                                        :class="[campo, 'w-full text-right tabular-nums']"
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex size-11 items-center justify-center justify-self-end rounded-xl text-slate-500 transition hover:bg-red-50 hover:text-red-600 dark:text-slate-400 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                    :aria-label="`Quitar concepto ${indice + 1}`"
                                    @click="quitarLibre(linea.id)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                            <p v-if="erroresLibre[linea.id]" class="text-xs font-medium text-red-600 dark:text-red-400">
                                {{ erroresLibre[linea.id] }}
                            </p>
                        </div>
                    </section>
                </div>

                <!-- Recibos a emitir -->
                <aside aria-labelledby="recibos-titulo" :class="[tarjeta, 'flex min-w-0 flex-col gap-3.5 lg:sticky lg:top-4']">
                    <div class="flex items-baseline justify-between gap-2">
                        <h2 id="recibos-titulo" class="flex items-center gap-2 text-[17px] font-bold text-slate-900 dark:text-slate-50">
                            <ReceiptText class="size-[18px] text-cyan-600 dark:text-cyan-400" />
                            Recibos a emitir
                        </h2>
                        <span class="text-[13px] text-slate-600 dark:text-slate-400">{{ cantidadRecibos }}</span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Agua y alcantarillado: un recibo por gestión (año). Multas, afiliación y demás van en recibo de caja.
                    </p>

                    <p
                        v-if="recibos.length === 0"
                        class="rounded-xl border border-dashed border-slate-300 px-4 py-4 text-center text-[13px] text-slate-600 dark:border-slate-700 dark:text-slate-400"
                    >
                        Todavía no eligió nada para cobrar.
                    </p>

                    <div
                        v-for="recibo in recibos"
                        :key="recibo.cuenta.key"
                        class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/60"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span
                                class="shrink-0 whitespace-nowrap text-xs font-bold tracking-wide"
                                :class="recibo.cuenta.tipo === 'ingreso' ? 'text-sky-700 dark:text-sky-300' : 'text-violet-700 dark:text-violet-300'"
                            >
                                {{ tituloRecibo(String(recibo.cuenta.tipo)) }}
                            </span>
                            <span class="min-w-0 truncate text-xs text-slate-500 dark:text-slate-400" :title="recibo.cuenta.label">{{
                                recibo.cuenta.label
                            }}</span>
                        </div>
                        <div v-for="(linea, indice) in recibo.lineas" :key="indice" class="flex justify-between gap-3 text-[13px]">
                            <span class="text-slate-800 dark:text-slate-100">{{ linea.texto }}</span>
                            <span class="whitespace-nowrap tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(linea.monto) }}</span>
                        </div>
                    </div>

                    <div class="flex items-baseline justify-between border-t border-slate-200 pt-3 dark:border-slate-800">
                        <span class="text-sm text-slate-600 dark:text-slate-400">Total a cobrar</span>
                        <strong class="text-[26px] tabular-nums text-slate-900 dark:text-slate-50">{{ formatBs(total) }}</strong>
                    </div>

                    <p
                        v-if="pendienteNoIncluido > 0.009"
                        class="rounded-xl border border-orange-200 bg-orange-50 px-3 py-2 text-[13px] text-orange-900 dark:border-orange-400/30 dark:bg-orange-400/10 dark:text-orange-100"
                    >
                        Deuda del lote {{ formatBs(deudaTotal) }}. Quedan
                        <strong class="tabular-nums">{{ formatBs(pendienteNoIncluido) }}</strong>
                        sin incluir en este cobro.
                    </p>

                    <div v-if="esEfectivo" class="flex items-end gap-3">
                        <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                            <label for="recibido" class="text-xs font-semibold text-slate-600 dark:text-slate-400">Recibido (Bs)</label>
                            <input
                                id="recibido"
                                v-model="recibido"
                                v-numeric.decimal
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                :class="[campo, 'text-base tabular-nums']"
                            />
                        </div>
                        <div class="flex w-28 shrink-0 flex-col gap-1 pb-2.5" aria-live="polite">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">{{
                                recibido.trim() === '' || diferencia >= 0 ? 'Cambio' : 'Falta'
                            }}</span>
                            <strong
                                class="whitespace-nowrap text-lg tabular-nums"
                                :class="
                                    recibido.trim() === ''
                                        ? 'text-slate-400'
                                        : diferencia >= 0
                                          ? 'text-emerald-700 dark:text-emerald-300'
                                          : 'text-orange-700 dark:text-orange-300'
                                "
                                >{{ recibido.trim() === '' ? '—' : formatBs(Math.abs(diferencia)) }}</strong
                            >
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="notas" class="text-xs font-semibold text-slate-600 dark:text-slate-400">Notas (opcional)</label>
                        <textarea id="notas" v-model="form.observaciones" rows="2" maxlength="2000" :class="[campo, 'h-auto resize-y py-2.5']" />
                    </div>

                    <div
                        v-if="erroresServidor.length > 0"
                        role="alert"
                        class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-[13px] text-red-800 dark:border-red-400/40 dark:bg-red-500/10 dark:text-red-200"
                    >
                        <p class="font-semibold">No se emitió el cobro:</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-4">
                            <li v-for="(mensaje, indice) in erroresServidor" :key="indice">{{ mensaje }}</li>
                        </ul>
                    </div>

                    <button
                        type="button"
                        class="min-h-[52px] rounded-xl bg-cyan-600 px-4 text-base font-bold text-white shadow-sm transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-cyan-400 dark:text-cyan-950 dark:hover:bg-cyan-300"
                        :disabled="!puedeEmitir"
                        @click="emitir"
                    >
                        {{
                            form.processing
                                ? 'Emitiendo…'
                                : recibos.length === 0
                                  ? 'Elija qué cobrar'
                                  : `Emitir ${cantidadRecibos} · ${formatBs(total)}`
                        }}
                    </button>
                    <p class="text-center text-xs text-slate-500 dark:text-slate-400">
                        Revise los meses y los montos antes de emitir: un recibo emitido no se puede modificar ni anular.
                    </p>
                </aside>
            </div>
        </div>
    </div>
</template>
