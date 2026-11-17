import { computed, type ComputedRef, type Ref } from 'vue';

export type CategoriaTarifa = {
    id: number;
    nombre: string;
    descripcion?: string;
    tarifa_agua: number;
    tarifa_alcantarillado: number;
    total_mensual?: number;
};

export type ServicioTarifa = {
    id: number;
    codigo: string;
    nombre: string;
    tipo?: string;
    tarifa_mensual: number;
    tarifas_categoria?: Record<number, number>;
};

export function tarifaServicioPorCategoria(
    servicio: ServicioTarifa,
    categoria: CategoriaTarifa | null,
): number {
    if (!categoria) {
        return Number(servicio.tarifa_mensual);
    }

    const tipo = (servicio.tipo ?? '').toLowerCase();

    if (tipo === 'agua') {
        return Number(categoria.tarifa_agua);
    }

    if (tipo === 'alcantarillado') {
        return Number(categoria.tarifa_alcantarillado);
    }

    return Number(servicio.tarifas_categoria?.[categoria.id] ?? servicio.tarifa_mensual);
}

export function useCategoriaTarifas(
    categorias: CategoriaTarifa[],
    categoriaId: Ref<number | string | null | undefined> | ComputedRef<number | string | null | undefined>,
    servicios: ServicioTarifa[],
    serviciosSeleccionados: Ref<number[]> | ComputedRef<number[]>,
) {
    const categoriaSeleccionada = computed(() => {
        const id = Number(categoriaId.value);

        if (!id) {
            return null;
        }

        return categorias.find((c) => Number(c.id) === id) ?? null;
    });

    const tarifasPorServicioId = computed(() => {
        const categoria = categoriaSeleccionada.value;

        return Object.fromEntries(
            servicios.map((servicio) => [
                servicio.id,
                tarifaServicioPorCategoria(servicio, categoria),
            ]),
        ) as Record<number, number>;
    });

    const tarifaServicio = (servicio: ServicioTarifa): number =>
        tarifasPorServicioId.value[servicio.id] ?? Number(servicio.tarifa_mensual);

    const totalMensualServicios = computed(() =>
        serviciosSeleccionados.value.reduce((acc, id) => acc + (tarifasPorServicioId.value[id] ?? 0), 0),
    );

    return {
        categoriaSeleccionada,
        tarifasPorServicioId,
        tarifaServicio,
        totalMensualServicios,
    };
}
