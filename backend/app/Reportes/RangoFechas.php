<?php

namespace App\Reportes;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Periodo de un reporte: de "desde" (00:00) a "hasta" (23:59:59) en la zona
 * horaria del restaurante. La base guarda las marcas de tiempo en UTC, por eso
 * también expone los límites en UTC para filtrar.
 */
final readonly class RangoFechas
{
    public const DIAS_POR_DEFECTO = 30;

    public function __construct(
        public CarbonImmutable $desde,
        public CarbonImmutable $hasta,
    ) {}

    /**
     * Sin fechas, toma los últimos 30 días (incluido hoy).
     *
     * @throws ValidationException si el periodo es mayor al máximo configurado
     */
    public static function desdeTexto(?string $desde, ?string $hasta, ?string $zona = null): self
    {
        $zona ??= config('siar.zona_horaria');

        $fin = $hasta ? CarbonImmutable::parse($hasta, $zona)->endOfDay() : CarbonImmutable::now($zona)->endOfDay();
        $inicio = $desde
            ? CarbonImmutable::parse($desde, $zona)->startOfDay()
            : $fin->subDays(self::DIAS_POR_DEFECTO - 1)->startOfDay();

        if ($inicio->gt($fin)) {
            throw ValidationException::withMessages(['hasta' => 'La fecha final no puede ser anterior a la inicial.']);
        }

        $rango = new self($inicio, $fin);
        $maximo = (int) config('siar.reportes.maximo_dias', 366);

        if ($rango->dias() > $maximo) {
            throw ValidationException::withMessages(['hasta' => "El periodo no puede superar {$maximo} días."]);
        }

        return $rango;
    }

    /** Cantidad de días del periodo, contando ambos extremos. */
    public function dias(): int
    {
        return (int) round($this->desde->startOfDay()->diffInDays($this->hasta->startOfDay())) + 1;
    }

    public function desdeUtc(): string
    {
        return $this->desde->utc()->format('Y-m-d H:i:s');
    }

    public function hastaUtc(): string
    {
        return $this->hasta->utc()->format('Y-m-d H:i:s');
    }

    /** El periodo anterior, de la misma duración, justo antes de este (para comparar). */
    public function anterior(): self
    {
        $dias = $this->dias();

        return new self(
            $this->desde->subDays($dias)->startOfDay(),
            $this->desde->subDay()->endOfDay(),
        );
    }

    /** @return array{desde: string, hasta: string, dias: int} */
    public function aArray(): array
    {
        return [
            'desde' => $this->desde->format('Y-m-d'),
            'hasta' => $this->hasta->format('Y-m-d'),
            'dias' => $this->dias(),
        ];
    }
}
