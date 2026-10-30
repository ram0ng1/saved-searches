<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Tests\unit;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Ramon\SavedSearches\Frequency;

class FrequencyTest extends TestCase
{
    public function test_imediata_vence_sempre(): void
    {
        $now = Carbon::parse('2026-10-30 12:00:00');

        $this->assertEquals($now, Frequency::dueBefore(Frequency::IMMEDIATELY, $now));
    }

    /** A folga de 2 minutos evita que a diária escorregue a cada execução do cron. */
    public function test_diaria_vence_um_dia_depois_com_folga(): void
    {
        $now = Carbon::parse('2026-10-30 12:00:00');

        $this->assertEquals(Carbon::parse('2026-10-29 12:02:00'), Frequency::dueBefore(Frequency::DAILY, $now));
        $this->assertEquals(Carbon::parse('2026-10-30 11:02:00'), Frequency::dueBefore(Frequency::HOURLY, $now));
        $this->assertEquals(Carbon::parse('2026-10-23 12:02:00'), Frequency::dueBefore(Frequency::WEEKLY, $now));
    }

    public function test_nao_altera_o_instante_recebido(): void
    {
        $now = Carbon::parse('2026-10-30 12:00:00');
        Frequency::dueBefore(Frequency::WEEKLY, $now);

        $this->assertEquals('2026-10-30 12:00:00', $now->toDateTimeString());
    }
}
