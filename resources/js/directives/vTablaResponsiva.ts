import type { Directive } from 'vue';

/**
 * v-tabla-responsiva
 *
 * En pantallas pequeñas (< 768px) la tabla se muestra como una lista de tarjetas:
 * cada fila es una tarjeta y cada celda muestra su encabezado como etiqueta.
 * En tablet y escritorio la tabla se ve igual que siempre.
 *
 * - Toma el texto de cada <th> del <thead> y lo copia en `data-label` de cada <td>.
 * - Respeta colspan y rowspan (filas agrupadas, p. ej. un afiliado con varios lotes).
 * - Se recalcula sola cuando cambian las filas (filtros, paginación, etc.).
 * - Una celda con `data-label=""` explícito (p. ej. acciones) no muestra etiqueta.
 * - Las celdas con colspan que ocupan toda la fila (mensajes "Sin resultados") se centran.
 *
 * El estilo vive en `resources/css/app.css` (sección "Tablas responsivas").
 */

type TablaEl = HTMLTableElement & { __tablaObserver?: MutationObserver; __tablaFrame?: number };

function leerEncabezados(tabla: HTMLTableElement): string[] {
    const filaEncabezado = tabla.tHead?.rows[tabla.tHead.rows.length - 1];
    if (!filaEncabezado) {
        return [];
    }

    const etiquetas: string[] = [];
    for (const th of Array.from(filaEncabezado.cells)) {
        const texto = (th.getAttribute('data-label') ?? th.textContent ?? '').replace(/\s+/g, ' ').trim();
        const span = Math.max(1, th.colSpan || 1);
        for (let i = 0; i < span; i++) {
            etiquetas.push(texto);
        }
    }

    return etiquetas;
}

function etiquetar(tabla: HTMLTableElement): void {
    const etiquetas = leerEncabezados(tabla);
    const totalColumnas = etiquetas.length;

    for (const cuerpo of Array.from(tabla.tBodies)) {
        // Columnas ocupadas por celdas con rowspan de filas anteriores.
        let ocupadas: number[] = [];

        for (const fila of Array.from(cuerpo.rows)) {
            let columna = 0;
            const ocupadasSiguiente: number[] = [];
            let empiezaCubierta = false;

            const saltarOcupadas = () => {
                while (ocupadas[columna] && ocupadas[columna] > 0) {
                    if (columna === 0) {
                        empiezaCubierta = true;
                    }
                    ocupadasSiguiente[columna] = ocupadas[columna] - 1;
                    columna++;
                }
            };

            for (const celda of Array.from(fila.cells)) {
                saltarOcupadas();

                const span = Math.max(1, celda.colSpan || 1);
                const rowspan = Math.max(1, celda.rowSpan || 1);

                if (celda.tagName === 'TD' && !celda.hasAttribute('data-label-fijo')) {
                    const ocupaTodo = totalColumnas > 1 && span >= totalColumnas;
                    if (ocupaTodo) {
                        celda.setAttribute('data-label', '');
                        celda.setAttribute('data-celda-completa', '');
                    } else {
                        celda.removeAttribute('data-celda-completa');
                        const etiqueta = etiquetas[columna] ?? '';
                        celda.setAttribute('data-label', etiqueta);
                        // Columna de botones: en móvil va al pie de la tarjeta, sin etiqueta.
                        celda.toggleAttribute('data-celda-acciones', /^(acciones|opciones)$/i.test(etiqueta));
                    }
                }

                if (rowspan > 1) {
                    for (let i = 0; i < span; i++) {
                        ocupadasSiguiente[columna + i] = rowspan - 1;
                    }
                }

                columna += span;
            }

            // Columnas cubiertas al final de la fila.
            saltarOcupadas();
            for (let i = columna; i < ocupadas.length; i++) {
                if (ocupadas[i] > 0) {
                    ocupadasSiguiente[i] = ocupadas[i] - 1;
                }
            }

            // Fila que continúa un grupo (rowspan desde arriba): se pega a la tarjeta anterior.
            fila.toggleAttribute('data-fila-continua', empiezaCubierta);
            ocupadas = ocupadasSiguiente;
        }
    }
}

function programar(tabla: TablaEl): void {
    if (tabla.__tablaFrame) {
        cancelAnimationFrame(tabla.__tablaFrame);
    }
    tabla.__tablaFrame = requestAnimationFrame(() => {
        tabla.__tablaFrame = undefined;
        etiquetar(tabla);
    });
}

export const vTablaResponsiva: Directive<TablaEl> = {
    mounted(tabla) {
        tabla.classList.add('tabla-responsiva');
        etiquetar(tabla);

        const observer = new MutationObserver(() => programar(tabla));
        observer.observe(tabla, { childList: true, subtree: true, characterData: true });
        tabla.__tablaObserver = observer;
    },
    updated(tabla) {
        programar(tabla);
    },
    beforeUnmount(tabla) {
        tabla.__tablaObserver?.disconnect();
        if (tabla.__tablaFrame) {
            cancelAnimationFrame(tabla.__tablaFrame);
        }
    },
};
