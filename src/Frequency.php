<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches;

use Carbon\Carbon;

/**
 * De quanto em quanto tempo uma busca salva roda. `immediately` acompanha
 * o agendador (a cada 5 minutos).
 */
final class Frequency
{
    public const IMMEDIATELY = 'immediately';
    public const HOURLY = 'hourly';
    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';

    public const ALL = [self::IMMEDIATELY, self::HOURLY, self::DAILY, self::WEEKLY];

    /**
     * A busca está vencida quando rodou pela última vez antes deste instante.
     * A folga cobre o atraso natural do cron: sem ela, uma busca diária
     * escorregaria 5 minutos por dia.
     */
    public static function dueBefore(string $frequency, Carbon $now): Carbon
    {
        $slack = 2;

        return match ($frequency) {
            self::HOURLY => $now->copy()->subHour()->addMinutes($slack),
            self::DAILY => $now->copy()->subDay()->addMinutes($slack),
            self::WEEKLY => $now->copy()->subWeek()->addMinutes($slack),
            default => $now->copy(),
        };
    }
}
