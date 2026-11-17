<?php

namespace App\Reportes;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * RF36: calcula la ocupación de las mesas a partir de las "visitas" (el tiempo
 * que cada mesa tuvo un pedido). No consulta la base de datos: recibe los
 * intervalos ya armados, así se puede probar con casos exactos.
 *
 * Reglas:
 * - Cada visita se recorta a [inicio, fin], sin pasar del fin del periodo, de
 *   "ahora" ni de un máximo de horas (un pedido que nadie cerró no cuenta como
 *   ocupación infinita).
 * - Las visitas que se cruzan en una misma mesa se unen (no se cuenta dos veces
 *   el mismo tiempo).
 * - % de ocupación = horas ocupadas / (días del periodo × horas de operación).
 * - Mapa de calor: % promedio de mesas ocupadas en cada día de la semana y hora.
 */
class CalculadoraOcupacion
{
    public function __construct(
        private readonly string $zona,
        private readonly float $horasOperacion,
        private readonly float $maximoHorasPorVisita,
    ) {}

    /**
     * @param  list<array{id_mesa: int, inicio: CarbonInterface, fin: CarbonInterface}>  $visitas
     * @param  list<array{id_mesa: int, numero: int, capacidad: int, ubicacion: string|null}>  $mesas
     * @return array{
     *     resumen: array<string, float|int>,
     *     mesas: list<array<string, mixed>>,
     *     mapa_calor: list<list<float>>
     * }
     */
    public function calcular(array $visitas, array $mesas, RangoFechas $rango, CarbonInterface $ahora): array
    {
        $limite = min(CarbonImmutable::instance($rango->hasta), CarbonImmutable::instance($ahora));
        // Los días que todavía no llegan (periodo hasta el fin de mes, por ejemplo) no cuentan como disponibles.
        $dias = $limite->lt($rango->desde) ? 0 : (int) round($rango->desde->startOfDay()->diffInDays($limite->startOfDay())) + 1;

        // 1) Recortar y agrupar por mesa (solo las mesas activas del reporte).
        $idsMesas = array_flip(array_column($mesas, 'id_mesa'));
        $porMesa = [];
        $visitasValidas = 0;
        foreach ($visitas as $visita) {
            if (! isset($idsMesas[$visita['id_mesa']])) {
                continue;
            }

            $inicio = CarbonImmutable::instance($visita['inicio']);
            $fin = min(
                CarbonImmutable::instance($visita['fin']),
                $inicio->addMinutes((int) round($this->maximoHorasPorVisita * 60)),
                $limite,
            );

            if ($fin->lte($inicio)) {
                continue;
            }

            $porMesa[$visita['id_mesa']][] = [$inicio, $fin];
            $visitasValidas++;
        }

        // 2) Unir intervalos que se cruzan, por mesa.
        $unidos = [];
        $visitasPorMesa = [];
        foreach ($porMesa as $idMesa => $intervalos) {
            $visitasPorMesa[$idMesa] = count($intervalos);
            usort($intervalos, fn (array $a, array $b) => $a[0] <=> $b[0]);

            $actual = array_shift($intervalos);
            foreach ($intervalos as [$inicio, $fin]) {
                if ($inicio->lte($actual[1])) {
                    $actual[1] = max($actual[1], $fin);
                } else {
                    $unidos[$idMesa][] = $actual;
                    $actual = [$inicio, $fin];
                }
            }
            $unidos[$idMesa][] = $actual;
        }

        // 3) Métricas por mesa y minutos por (día de la semana, hora).
        $minutosHora = array_fill(0, 7, array_fill(0, 24, 0.0));
        $detalleMesas = [];
        $minutosTotales = 0.0;
        $sesiones = 0;

        foreach ($mesas as $mesa) {
            $intervalos = $unidos[$mesa['id_mesa']] ?? [];
            $minutos = 0.0;

            foreach ($intervalos as [$inicio, $fin]) {
                $minutos += $inicio->diffInSeconds($fin) / 60;
                $this->acumularPorHora($minutosHora, $inicio, $fin);
            }

            $minutosTotales += $minutos;
            $sesiones += count($intervalos);

            $detalleMesas[] = [
                'id_mesa' => $mesa['id_mesa'],
                'numero' => $mesa['numero'],
                'capacidad' => $mesa['capacidad'],
                'ubicacion' => $mesa['ubicacion'],
                'pedidos' => $visitasPorMesa[$mesa['id_mesa']] ?? 0,
                'sesiones' => count($intervalos),
                'horas_ocupada' => round($minutos / 60, 1),
                'ocupacion' => $this->porcentaje($minutos / 60, $dias * $this->horasOperacion),
                'permanencia_promedio_min' => count($intervalos) > 0 ? (int) round($minutos / count($intervalos)) : 0,
            ];
        }

        usort($detalleMesas, fn (array $a, array $b) => [$b['ocupacion'], $a['numero']] <=> [$a['ocupacion'], $b['numero']]);

        // 4) Mapa de calor: % promedio de mesas ocupadas.
        $totalMesas = count($mesas);
        $diasPorSemana = $this->diasPorDiaDeSemana($rango, $limite);
        $mapa = [];
        for ($d = 0; $d < 7; $d++) {
            $fila = [];
            for ($h = 0; $h < 24; $h++) {
                $disponibles = $diasPorSemana[$d] * $totalMesas * 60;
                $fila[] = $disponibles > 0 ? min(100.0, round($minutosHora[$d][$h] / $disponibles * 100, 1)) : 0.0;
            }
            $mapa[] = $fila;
        }

        return [
            'resumen' => [
                'mesas' => $totalMesas,
                'pedidos' => $visitasValidas,
                'sesiones' => $sesiones,
                'horas_ocupadas' => round($minutosTotales / 60, 1),
                'ocupacion_promedio' => $this->porcentaje($minutosTotales / 60, $totalMesas * $dias * $this->horasOperacion),
                'permanencia_promedio_min' => $sesiones > 0 ? (int) round($minutosTotales / $sesiones) : 0,
                'rotacion_diaria' => $totalMesas > 0 && $dias > 0 ? round($sesiones / ($totalMesas * $dias), 2) : 0.0,
            ],
            'mesas' => $detalleMesas,
            'mapa_calor' => $mapa,
        ];
    }

    private function porcentaje(float $horas, float $disponibles): float
    {
        return $disponibles > 0 ? min(100.0, round($horas / $disponibles * 100, 1)) : 0.0;
    }

    /** Reparte los minutos de un intervalo entre las horas del reloj local en que ocurre. */
    private function acumularPorHora(array &$minutosHora, CarbonImmutable $inicio, CarbonImmutable $fin): void
    {
        $cursor = $inicio->setTimezone($this->zona);
        $fin = $fin->setTimezone($this->zona);

        while ($cursor->lt($fin)) {
            $finHora = $cursor->startOfHour()->addHour();
            $hasta = min($finHora, $fin);

            // isoWeekday: 1 (lunes) a 7 (domingo) → índice 0 a 6.
            $minutosHora[$cursor->isoWeekday() - 1][$cursor->hour] += $cursor->diffInSeconds($hasta) / 60;
            $cursor = $hasta;
        }
    }

    /** @return array<int, int> cuántos lunes, martes... hay en el periodo (índice 0 = lunes) */
    private function diasPorDiaDeSemana(RangoFechas $rango, CarbonImmutable $limite): array
    {
        $cuenta = array_fill(0, 7, 0);
        $dia = $rango->desde->startOfDay();

        while ($dia->lte($limite)) {
            $cuenta[$dia->isoWeekday() - 1]++;
            $dia = $dia->addDay();
        }

        return $cuenta;
    }
}
