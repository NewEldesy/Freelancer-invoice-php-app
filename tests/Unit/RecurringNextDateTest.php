<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\RecurringRepository;
use PHPUnit\Framework\TestCase;

final class RecurringNextDateTest extends TestCase
{
    public function testMensuelAddOneMonth(): void
    {
        $next = RecurringRepository::nextDate('2026-01-15', 'mensuel');
        $this->assertSame('2026-02-15', $next);
    }

    public function testHebdomadaireAddSevenDays(): void
    {
        $next = RecurringRepository::nextDate('2026-03-01', 'hebdomadaire');
        $this->assertSame('2026-03-08', $next);
    }

    public function testTrimestrielAddThreeMonths(): void
    {
        $next = RecurringRepository::nextDate('2026-01-01', 'trimestriel');
        $this->assertSame('2026-04-01', $next);
    }

    public function testAnnuelAddOneYear(): void
    {
        $next = RecurringRepository::nextDate('2026-06-15', 'annuel');
        $this->assertSame('2027-06-15', $next);
    }

    public function testUnknownIntervalDefaultsToMensuel(): void
    {
        $next = RecurringRepository::nextDate('2026-01-31', 'inconnu');
        // +1 mois depuis le 31 jan → 28 fév 2026 (année non bissextile)
        $this->assertSame('2026-02-28', $next);
    }

    public function testMensuelFromEndOfJanuaryRollsToFebruary(): void
    {
        $next = RecurringRepository::nextDate('2026-01-31', 'mensuel');
        $this->assertSame('2026-02-28', $next);
    }

    public function testMensuelAcrossYearBoundary(): void
    {
        $next = RecurringRepository::nextDate('2026-12-15', 'mensuel');
        $this->assertSame('2027-01-15', $next);
    }
}
