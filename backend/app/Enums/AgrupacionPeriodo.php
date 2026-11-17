<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Cómo se agrupan las ventas de un reporte (RF33): por día, semana (lunes a
 * domingo) o mes. Cada caso sabe dónde empieza su periodo, cuál sigue y cómo
 * se llama; así el reporte no tiene un "if" por cada agrupación.
 */
enum AgrupacionPeriodo: string
{
    case DIA = 'dia';
    case SEMANA = 'semana';
    case MES = 'mes';

    /** Primer instante del periodo que contiene la fecha (en su misma zona horaria). */
    public function inicio(CarbonInterface $fecha): CarbonImmutable
    {
        $f = CarbonImmutable::instance($fecha);

        return match ($this) {
            self::DIA => $f->startOfDay(),
            self::SEMANA => $f->startOfWeek(CarbonInterface::MONDAY),
            self::MES => $f->startOfMonth(),
        };
    }

    public function siguiente(CarbonImmutable $inicio): CarbonImmutable
    {
        return match ($this) {
            self::DIA => $inicio->addDay(),
            self::SEMANA => $inicio->addWeek(),
            self::MES => $inicio->addMonthNoOverflow(),
        };
    }

    /** Clave estable del periodo: el día en que empieza (Y-m-d). */
    public function clave(CarbonImmutable $inicio): string
    {
        return $inicio->format('Y-m-d');
    }

    public function etiqueta(CarbonImmutable $inicio): string
    {
        $f = $inicio->locale('es');

        return match ($this) {
            self::DIA => $f->isoFormat('D MMM'),
            self::SEMANA => $f->isoFormat('D MMM').' – '.$f->addDays(6)->isoFormat('D MMM'),
            self::MES => $f->isoFormat('MMM YYYY'),
        };
    }
}
