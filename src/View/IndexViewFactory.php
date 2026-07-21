<?php

declare(strict_types=1);

namespace Calendorama\View;

use Calendorama\Calendar\CalendarArchive;
use Calendorama\View\Model\ArchiveMonthView;
use Calendorama\View\Model\IndexView;
use Psr\Clock\ClockInterface;

final readonly class IndexViewFactory
{
    private const MONTH_NAMES = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    public function __construct(
        private CalendarArchive $archive,
        private ClockInterface $clock,
        private AssetUrlGenerator $assets,
    ) {
    }

    public function create(): IndexView
    {
        $now = $this->clock->now();
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');
        $summary = $this->archive->summary();
        $months = [];

        foreach ($summary->months as $archiveMonth) {
            $route = '#d' . $year . '_' . $archiveMonth->month;
            $months[] = new ArchiveMonthView(
                self::MONTH_NAMES[$archiveMonth->month],
                $archiveMonth->postCount,
                '/' . $route,
                $route,
            );
        }

        return new IndexView(
            $year,
            $month,
            '#d' . $year . '_' . $month,
            365 + (int) $now->format('L'),
            $summary->postCount,
            $months,
            $this->assets->versioned('/style.css'),
            $this->assets->versioned('/script.js'),
        );
    }
}
