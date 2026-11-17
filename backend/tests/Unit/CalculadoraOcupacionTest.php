<?php

namespace Tests\Unit;

use App\Reportes\CalculadoraOcupacion;
use App\Reportes\RangoFechas;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** RF36: ocupación de mesas (cálculo puro, con casos exactos). */
class CalculadoraOcupacionTest extends TestCase
{
    private const ZONA = 'America/Bogota';

    /** Semana del lunes 2 al domingo 8 de noviembre de 2026 (7 días). */
    private function semana(): RangoFechas
    {
        return RangoFechas::desdeTexto('2026-11-02', '2026-11-08', self::ZONA);
    }

    /** Fecha y hora locales (Bogotá) → instante. */
    private function local(string $fechaHora): CarbonImmutable
    {
        return CarbonImmutable::parse($fechaHora, self::ZONA);
    }

    private function mesas(): array
    {
        return [
            ['id_mesa' => 1, 'numero' => 1, 'capacidad' => 4, 'ubicacion' => 'Salón'],
            ['id_mesa' => 2, 'numero' => 2, 'capacidad' => 2, 'ubicacion' => 'Terraza'],
        ];
    }

    /** 10 horas de operación al día, un pedido cuenta máximo 6 horas. */
    private function calculadora(): CalculadoraOcupacion
    {
        return new CalculadoraOcupacion(self::ZONA, 10, 6);
    }

    private function visita(int $mesa, string $inicio, string $fin): array
    {
        return ['id_mesa' => $mesa, 'inicio' => $this->local($inicio), 'fin' => $this->local($fin)];
    }

    private function ahora(): CarbonImmutable
    {
        return $this->local('2026-12-01 12:00:00');
    }

    public function test_calcula_horas_y_porcentaje_de_una_mesa(): void
    {
        $r = $this->calculadora()->calcular(
            [$this->visita(1, '2026-11-02 12:00:00', '2026-11-02 14:00:00')],
            $this->mesas(),
            $this->semana(),
            $this->ahora(),
        );

        // 2 h de 70 h disponibles (7 días × 10 h) = 2,9 %.
        $this->assertSame(2.0, $r['mesas'][0]['horas_ocupada']);
        $this->assertSame(1, $r['mesas'][0]['numero']);
        $this->assertSame(2.9, $r['mesas'][0]['ocupacion']);
        $this->assertSame(120, $r['mesas'][0]['permanencia_promedio_min']);

        // La mesa sin visitas queda en cero y al final.
        $this->assertSame(2, $r['mesas'][1]['numero']);
        $this->assertSame(0.0, $r['mesas'][1]['ocupacion']);

        // Promedio general: 2 h de 140 h (2 mesas × 70 h).
        $this->assertSame(1.4, $r['resumen']['ocupacion_promedio']);
        $this->assertSame(2, $r['resumen']['mesas']);
        $this->assertSame(1, $r['resumen']['sesiones']);
    }

    public function test_une_las_visitas_que_se_cruzan_en_la_misma_mesa(): void
    {
        $r = $this->calculadora()->calcular(
            [
                $this->visita(1, '2026-11-02 12:00:00', '2026-11-02 14:00:00'),
                $this->visita(1, '2026-11-02 13:00:00', '2026-11-02 15:00:00'),
            ],
            $this->mesas(),
            $this->semana(),
            $this->ahora(),
        );

        // 12:00 a 15:00 = 3 h (no 4): el tiempo cruzado no se cuenta dos veces.
        $this->assertSame(3.0, $r['mesas'][0]['horas_ocupada']);
        $this->assertSame(2, $r['mesas'][0]['pedidos']);
        $this->assertSame(1, $r['mesas'][0]['sesiones']);
        $this->assertSame(180, $r['mesas'][0]['permanencia_promedio_min']);
    }

    public function test_visitas_separadas_son_sesiones_distintas(): void
    {
        $r = $this->calculadora()->calcular(
            [
                $this->visita(1, '2026-11-02 12:00:00', '2026-11-02 13:00:00'),
                $this->visita(1, '2026-11-02 19:00:00', '2026-11-02 21:00:00'),
            ],
            $this->mesas(),
            $this->semana(),
            $this->ahora(),
        );

        $this->assertSame(3.0, $r['mesas'][0]['horas_ocupada']);
        $this->assertSame(2, $r['mesas'][0]['sesiones']);
        $this->assertSame(90, $r['mesas'][0]['permanencia_promedio_min']);
        // 2 sesiones / (2 mesas × 7 días).
        $this->assertSame(0.14, $r['resumen']['rotacion_diaria']);
    }

    public function test_un_pedido_largo_se_recorta_al_maximo_de_horas(): void
    {
        $r = $this->calculadora()->calcular(
            [$this->visita(1, '2026-11-02 10:00:00', '2026-11-02 20:00:00')],
            $this->mesas(),
            $this->semana(),
            $this->ahora(),
        );

        // 10 h reales, pero el tope es 6 h.
        $this->assertSame(6.0, $r['mesas'][0]['horas_ocupada']);
    }

    public function test_un_pedido_abierto_cuenta_hasta_ahora_y_los_dias_futuros_no_son_disponibles(): void
    {
        // "Ahora" es el miércoles 4 a las 12:00: el periodo (hasta el domingo) solo tiene 3 días transcurridos.
        $ahora = $this->local('2026-11-04 12:00:00');

        $r = $this->calculadora()->calcular(
            [$this->visita(1, '2026-11-04 10:00:00', '2026-11-04 23:00:00')],
            $this->mesas(),
            $this->semana(),
            $ahora,
        );

        // Cuenta de 10:00 a 12:00 = 2 h, sobre 30 h disponibles (3 días × 10 h) = 6,7 %.
        $this->assertSame(2.0, $r['mesas'][0]['horas_ocupada']);
        $this->assertSame(6.7, $r['mesas'][0]['ocupacion']);
    }

    public function test_ignora_visitas_sin_duracion(): void
    {
        $r = $this->calculadora()->calcular(
            [$this->visita(1, '2026-11-02 12:00:00', '2026-11-02 12:00:00')],
            $this->mesas(),
            $this->semana(),
            $this->ahora(),
        );

        $this->assertSame(0, $r['resumen']['sesiones']);
        $this->assertSame(0.0, $r['resumen']['ocupacion_promedio']);
        $this->assertSame(0, $r['resumen']['permanencia_promedio_min']);
    }

    public function test_el_mapa_de_calor_usa_la_hora_local_y_el_promedio_de_mesas(): void
    {
        $r = $this->calculadora()->calcular(
            [$this->visita(1, '2026-11-02 12:00:00', '2026-11-02 14:00:00')],
            $this->mesas(),
            $this->semana(),
            $this->ahora(),
        );

        $this->assertCount(7, $r['mapa_calor']);
        $this->assertCount(24, $r['mapa_calor'][0]);

        // Lunes 12:00 y 13:00: 1 de 2 mesas ocupada todo el tiempo = 50 % (hay un solo lunes).
        $this->assertSame(50.0, $r['mapa_calor'][0][12]);
        $this->assertSame(50.0, $r['mapa_calor'][0][13]);
        // Fuera de ese rango, 0 (en la hora UTC equivalente, 17 y 18, no hay nada).
        $this->assertSame(0.0, $r['mapa_calor'][0][11]);
        $this->assertSame(0.0, $r['mapa_calor'][0][14]);
        $this->assertSame(0.0, $r['mapa_calor'][0][17]);
        // Los demás días, vacíos.
        $this->assertSame(0.0, $r['mapa_calor'][1][12]);
    }

    public function test_una_visita_que_cruza_la_medianoche_se_reparte_entre_dos_dias(): void
    {
        $r = $this->calculadora()->calcular(
            [$this->visita(1, '2026-11-02 23:00:00', '2026-11-03 01:00:00')],
            $this->mesas(),
            $this->semana(),
            $this->ahora(),
        );

        $this->assertSame(50.0, $r['mapa_calor'][0][23]); // lunes 23:00
        $this->assertSame(50.0, $r['mapa_calor'][1][0]);  // martes 00:00
        $this->assertSame(2.0, $r['mesas'][0]['horas_ocupada']);
    }

    public function test_el_promedio_del_mapa_considera_cuantos_lunes_hay_en_el_periodo(): void
    {
        // Dos lunes (2 y 9 de noviembre); solo en uno hay una mesa ocupada de 12:00 a 13:00.
        $rango = RangoFechas::desdeTexto('2026-11-02', '2026-11-09', self::ZONA);

        $r = $this->calculadora()->calcular(
            [$this->visita(1, '2026-11-02 12:00:00', '2026-11-02 13:00:00')],
            $this->mesas(),
            $rango,
            $this->ahora(),
        );

        // 60 min de (2 lunes × 2 mesas × 60 min) = 25 %.
        $this->assertSame(25.0, $r['mapa_calor'][0][12]);
    }

    public function test_el_porcentaje_nunca_pasa_de_cien(): void
    {
        // Un solo día, 1 hora de operación: 6 h de ocupación se limita a 100 %.
        $rango = RangoFechas::desdeTexto('2026-11-02', '2026-11-02', self::ZONA);
        $calculadora = new CalculadoraOcupacion(self::ZONA, 1, 6);

        $r = $calculadora->calcular(
            [$this->visita(1, '2026-11-02 10:00:00', '2026-11-02 16:00:00')],
            $this->mesas(),
            $rango,
            $this->ahora(),
        );

        $this->assertSame(100.0, $r['mesas'][0]['ocupacion']);
    }

    public function test_sin_mesas_no_falla(): void
    {
        $r = $this->calculadora()->calcular([], [], $this->semana(), $this->ahora());

        $this->assertSame(0, $r['resumen']['mesas']);
        $this->assertSame(0.0, $r['resumen']['ocupacion_promedio']);
        $this->assertSame([], $r['mesas']);
    }
}
