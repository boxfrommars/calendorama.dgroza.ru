<?php

declare(strict_types=1);

namespace Calendorama\Calendar;

use Calendorama\View\TemplateRenderer;

final readonly class CalendarRenderer
{
    private const WEEKS = 6;

    public function __construct(
        private CalendarContent $content,
        private TemplateRenderer $templates,
    ) {
    }

    public function render(CalendarMonth $month): string
    {
        return $this->templates->render(
            'calendar-fragment.html.twig',
            [
                'calendar' => [
                    'class' => $month->month . '_' . $month->year,
                    'weeks' => $this->weeks($month),
                ],
                'navigation' => [
                    'monthName' => $month->name,
                    'previous' => self::navigationLink(
                        $month->previousMonthName,
                        $month->previousMonth,
                        $month->previousYear,
                    ),
                    'next' => self::navigationLink(
                        $month->nextMonthName,
                        $month->nextMonth,
                        $month->nextYear,
                    ),
                ],
            ],
        );
    }

    /**
     * @return list<list<array{
     *     class: string,
     *     day: int|null,
     *     post: array{anchorId: string, title: string, fullUrl: string, thumbnailUrl: string}|null
     * }>>
     */
    private function weeks(CalendarMonth $month): array
    {
        $weeks = [];
        $cellNumber = 1;

        for ($weekNumber = 1; $weekNumber <= self::WEEKS; $weekNumber++) {
            $week = [];
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $week[] = $this->cell($month, $cellNumber);
                $cellNumber++;
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * @return array{
     *     class: string,
     *     day: int|null,
     *     post: array{anchorId: string, title: string, fullUrl: string, thumbnailUrl: string}|null
     * }
     */
    private function cell(CalendarMonth $month, int $cellNumber): array
    {
        if ($cellNumber < $month->firstWeekday || $cellNumber >= $month->firstWeekday + $month->daysInMonth) {
            return ['class' => 'a-m-d', 'day' => null, 'post' => null];
        }

        $day = $cellNumber - $month->firstWeekday + 1;
        $hasPost = $this->content->hasPost($month->month, $day);
        $class = $hasPost ? '' : 'w-p';
        if ($day === $month->today) {
            $class = trim($class . ' today');
        }

        $post = null;
        if ($hasPost) {
            $id = $month->month . '_' . $day;
            $post = [
                'anchorId' => 'f' . $month->year . '_' . $id,
                'title' => $this->content->title($month->month, $day),
                'fullUrl' => $this->content->fullImageUrl($month->month, $day),
                'thumbnailUrl' => $this->content->thumbnailUrl($month->month, $day),
            ];
        }

        return ['class' => $class, 'day' => $day, 'post' => $post];
    }

    /** @return array{title: string, href: string, id: string} */
    private static function navigationLink(string $monthName, int $month, int $year): array
    {
        return [
            'title' => $monthName . ' ' . $year,
            'href' => '/#d' . $year . '_' . $month,
            'id' => 'm' . $year . '_' . $month,
        ];
    }
}
