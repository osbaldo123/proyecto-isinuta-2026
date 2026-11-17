/**
 * Utilidades del módulo Pagos: montos en bolivianos, meses en palabras y cómo se agrupan
 * los cobros en recibos (la misma regla que aplica el servidor al emitirlos).
 */

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

export type TipoRecibo = 'ingreso' | 'caja';

export type CuentaRecibo = { key: string; label: string; tipo: TipoRecibo | string };

export type LineaRecibo = {
    concepto?: string;
    descripcion?: string | null;
    texto: string;
    texto_base?: string;
    rango?: string | null;
    meses?: number;
    unitario?: number | null;
    monto: number;
    periodo?: string | null;
};

export type ReciboResumen = {
    id: number;
    numero_recibo: string;
    tipo_recibo: string | null;
    proposito_label: string | null;
    monto_total: number;
    estado: string;
};

export type ReciboDatos = {
    id: number;
    numero_recibo: string;
    tipo_recibo: string;
    titulo_recibo: string;
    proposito: string;
    proposito_label: string;
    fecha_pago: string;
    fecha_pago_display?: string;
    monto_total: number;
    monto_en_letras: string;
    metodo_pago: string;
    metodo_pago_label?: string;
    estado: string;
    observaciones: string | null;
    cobrador: string | null;
    lote_afiliado_id?: number | null;
    afiliado: {
        nombre_completo: string;
        identidad: string;
        numero_lote: string | null;
        codigo_propiedad: string;
        ci: string;
        otb: string | null;
        categoria?: string | null;
    };
    detalles: LineaRecibo[];
    emitido_en?: string | null;
    anulacion?: { fecha: string | null; usuario: string | null; motivo: string | null } | null;
    cobro_id?: string | null;
    recibos_del_cobro?: ReciboResumen[];
};

const formatoBs = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** 1234.5 → "Bs 1.234,50"; los montos enteros sin decimales ("Bs 50"). */
export function formatBs(monto: number | string | null | undefined, conDecimales = false): string {
    const valor = Math.round((Number(monto) || 0) * 100) / 100;

    if (!conDecimales && Number.isInteger(valor)) {
        return `Bs ${valor.toLocaleString('es-BO')}`;
    }

    return `Bs ${formatoBs.format(valor)}`;
}

/** Suma montos sin errores de coma flotante (centavos). */
export function sumar(montos: Array<number | string | null | undefined>): number {
    return montos.reduce<number>((total, monto) => total + Math.round((Number(monto) || 0) * 100), 0) / 100;
}

/** "2026-07" → "julio 2026". */
export function nombreMes(periodo: string): string {
    const [anio, mes] = periodo.split('-').map(Number);

    return `${MESES[(mes || 1) - 1] ?? periodo} ${anio}`;
}

/** ["2026-07", "2026-08", "2026-09"] → "julio a septiembre 2026"; con saltos: "julio 2026, octubre 2026". */
export function rangoMeses(periodos: string[]): string {
    const ordenados = [...new Set(periodos.filter((p) => /^\d{4}-\d{2}$/.test(p)))].sort();
    const bloques: Array<[number, number, number, number]> = [];

    for (const periodo of ordenados) {
        const [anio, mes] = periodo.split('-').map(Number);
        const ultimo = bloques[bloques.length - 1];

        if (ultimo && ultimo[2] * 12 + ultimo[3] + 1 === anio * 12 + mes) {
            ultimo[2] = anio;
            ultimo[3] = mes;
        } else {
            bloques.push([anio, mes, anio, mes]);
        }
    }

    return bloques
        .map(([a1, m1, a2, m2]) => {
            if (a1 === a2 && m1 === m2) {
                return `${MESES[m1 - 1]} ${a1}`;
            }

            return a1 === a2 ? `${MESES[m1 - 1]} a ${MESES[m2 - 1]} ${a2}` : `${MESES[m1 - 1]} ${a1} a ${MESES[m2 - 1]} ${a2}`;
        })
        .join(', ');
}

export function cantidadMeses(n: number): string {
    return n === 1 ? '1 mes' : `${n} meses`;
}

/** Fecha de hoy en la hora del equipo (no en UTC), en formato aaaa-mm-dd. */
export function hoyLocal(): string {
    const ahora = new Date();

    return [ahora.getFullYear(), String(ahora.getMonth() + 1).padStart(2, '0'), String(ahora.getDate()).padStart(2, '0')].join('-');
}

/** "2026-10-27" → "27 de octubre de 2026". */
export function fechaLarga(fecha: string): string {
    const [anio, mes, dia] = fecha.split('-').map(Number);

    return `${dia} de ${MESES[(mes || 1) - 1]} de ${anio}`;
}

export function tituloRecibo(tipo: string): string {
    return tipo === 'ingreso' ? 'RECIBO DE INGRESO' : 'RECIBO DE CAJA';
}

export function iniciales(nombre: string): string {
    return nombre
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}

/**
 * Propósito del recibo de una línea libre (aporte u otro concepto), igual que
 * ReciboPropositoService::clasificar en el servidor para líneas sin servicio ni cuota.
 */
export function clasificarLineaLibre(concepto: string, descripcion: string): CuentaRecibo {
    const texto = descripcion.trim().toLowerCase();
    const etiqueta = descripcion.trim();

    if (texto.includes('cambio de nombre') || texto.includes('cambio nombre')) {
        return { key: 'cambio_de_nombre', label: 'cambio de nombre', tipo: 'caja' };
    }

    const reuniones = texto.includes('reunion') || texto.includes('reunión');
    const trabajos = texto.includes('trabajo');
    const alcantarillado = texto.includes('alcantar');

    if (reuniones !== trabajos) {
        const tema = reuniones ? 'reuniones' : 'trabajos';

        return alcantarillado
            ? { key: `falta_${tema}_alcantarillado`, label: `Falta de ${tema} de alcantarillado`, tipo: 'caja' }
            : { key: `falta_${tema}_agua_potable`, label: `Falta de ${tema} de agua potable`, tipo: 'caja' };
    }

    if (texto.includes('afiliaci')) {
        return { key: 'afiliacion', label: 'Afiliación', tipo: 'caja' };
    }

    if (texto.includes('alcantarillado')) {
        return { key: 'servicio_alcantarillado', label: 'Servicio de Alcantarillado', tipo: 'ingreso' };
    }

    if (texto.includes('agua potable') || texto.includes('servicio agua') || texto.includes('cuota agua')) {
        return { key: 'servicio_agua_potable', label: 'Servicio Agua Potable', tipo: 'ingreso' };
    }

    const cuenta = (prefijo: string, porDefecto: string): CuentaRecibo => {
        const label = etiqueta || porDefecto;

        return { key: `${prefijo}:${label.toLowerCase()}`, label, tipo: 'caja' };
    };

    if (concepto === 'aporte' || texto.includes('aporte')) {
        return cuenta('aporte', 'Aporte');
    }

    if (concepto === 'multa' || texto.includes('multa')) {
        return cuenta('multa', 'Multa');
    }

    return cuenta('otro', 'Otro');
}
