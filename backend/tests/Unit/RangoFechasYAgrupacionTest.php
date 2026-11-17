<?php

namespace Tests\Unit;

use App\Enums\AgrupacionPeriodo;
use App\Reportes\RangoFechas;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Periodos de los reportes: rango de fechas y agrupación (día, semana, mes). */
class RangoFechasYAgrupacionTest extends TestCase
{
    private const ZONA = 'America/Bogota';

    public function test_el_rango_cubre_desde_las_00_hasta_las_23_59_del_ultimo_dia(): void
    {
        $r = RangoFechas::desdeTexto('2026-11-02', '2026-11-04', self::ZONA);

        $this->assertSame(3, $r->dias());
        $this->assertSame('2026-11-02 00:00:00', $r->desde->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-04 23:59:59', $r->hasta->format('Y-m-d H:i:s'));
        // Bogotá es UTC-5: el día local empieza a las 05:00 UTC.
        $this->assertSame('2026-11-02 05:00:00', $r->desdeUtc());
        $this->assertSame('2026-11-05 04:59:59', $r->hastaUtc());
        $this->assertSame(['desde' => '2026-11-02', 'hasta' => '2026-11-04', 'dias' => 3], $r->aArray());
    }

    public function test_sin_fechas_toma_los_ultimos_30_dias(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-17 15:00:00', self::ZONA));

        $r = RangoFechas::desdeTexto(null, null, self::ZONA);

        $this->assertSame(30, $r->dias());
        $this->assertSame('2026-11-17', $r->hasta->format('Y-m-d'));
        $this->assertSame('2026-10-19', $r->desde->format('Y-m-d'));

        CarbonImmutable::setTestNow();
    }

    public function test_el_periodo_anterior_tiene_la_misma_duracion(): void
    {
        $anterior = RangoFechas::desdeTexto('2026-11-08', '2026-11-14', self::ZONA)->anterior();

        $this->assertSame(7, $anterior->dias());
        $this->assertSame('2026-11-01', $anterior->desde->format('Y-m-d'));
        $this->assertSame('2026-11-07', $anterior->hasta->format('Y-m-d'));
    }

    public function test_rechaza_fechas_invertidas_y_periodos_demasiado_largos(): void
    {
        try {
            RangoFechas::desdeTexto('2026-11-10', '2026-11-01', self::ZONA);
            $this->fail('Debió rechazar fechas invertidas.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('hasta', $e->errors());
        }

        try {
            RangoFechas::desdeTexto('2025-01-01', '2026-11-01', self::ZONA);
            $this->fail('Debió rechazar un periodo de casi dos años.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('366', $e->errors()['hasta'][0]);
        }
    }

    public function test_la_semana_empieza_el_lunes(): void
    {
        // Miércoles 4 de noviembre de 2026.
        $inicio = AgrupacionPeriodo::SEMANA->inicio(CarbonImmutable::parse('2026-11-04 15:30:00', self::ZONA));

        $this->assertSame('2026-11-02', $inicio->format('Y-m-d'));
        $this->assertSame('2026-11-09', AgrupacionPeriodo::SEMANA->siguiente($inicio)->format('Y-m-d'));
        $this->assertSame('2026-11-02', AgrupacionPeriodo::SEMANA->clave($inicio));
    }

    public function test_agrupaciones_por_dia_y_por_mes(): void
    {
        $fecha = CarbonImmutable::parse('2026-11-17 18:45:00', self::ZONA);

        $dia = AgrupacionPeriodo::DIA->inicio($fecha);
        $this->assertSame('2026-11-17 00:00:00', $dia->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-18', AgrupacionPeriodo::DIA->siguiente($dia)->format('Y-m-d'));

        $mes = AgrupacionPeriodo::MES->inicio($fecha);
        $this->assertSame('2026-11-01', $mes->format('Y-m-d'));
        $this->assertSame('2026-12-01', AgrupacionPeriodo::MES->siguiente($mes)->format('Y-m-d'));
        // El mes siguiente de enero 31 no se salta febrero.
        $this->assertSame('2026-02-01', AgrupacionPeriodo::MES->siguiente(CarbonImmutable::parse('2026-01-01', self::ZONA))->format('Y-m-d'));
    }

    public function test_etiquetas_en_espanol(): void
    {
        $inicio = CarbonImmutable::parse('2026-11-02', self::ZONA);

        $this->assertStringContainsString('2', AgrupacionPeriodo::DIA->etiqueta($inicio));
        $this->assertStringContainsStringIgnoringCase('nov', AgrupacionPeriodo::DIA->etiqueta($inicio));
        $this->assertStringContainsString('–', AgrupacionPeriodo::SEMANA->etiqueta($inicio));
        $this->assertStringContainsString('2026', AgrupacionPeriodo::MES->etiqueta($inicio));
    }
}
