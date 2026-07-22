<?php

declare(strict_types=1);

namespace Calendorama\Rss;

use Calendorama\Calendar\CalendarArchive;
use Calendorama\View\TemplateRenderer;
use RuntimeException;

/**
 * @phpstan-import-type ArchivePost from CalendarArchive
 * @phpstan-type RssItem array{title: string, link: string, imageUrl: string, pubDate: string, guid: string}
 * @phpstan-type RssView array{siteUrl: string, selfUrl: string, pubDate: string, items: list<RssItem>}
 */
final readonly class RssGenerator
{
    private const SITE_URL = 'https://calendorama.dgroza.ru';
    private const LEGACY_GUID_ORIGIN = 'http://calendorama.dgroza.ru';

    public function __construct(
        private CalendarArchive $archive,
        private TemplateRenderer $templates,
    ) {
    }

    public function render(): string
    {
        $posts = $this->archive->posts();
        if ($posts === []) {
            throw new RuntimeException('Unable to generate RSS from an empty archive.');
        }

        usort($posts, self::newestFirst(...));

        $items = [];
        foreach ($posts as $post) {
            $path = sprintf('blog/%d_%d', $post['month'], $post['day']);
            $route = sprintf(
                '#d%d_%d_%d',
                (int) $post['publishedAt']->format('Y'),
                $post['month'],
                $post['day'],
            );
            $items[] = [
                'title' => $post['title'],
                'link' => self::SITE_URL . '/' . $route,
                'imageUrl' => self::SITE_URL . '/' . $path . '_full.jpg',
                'pubDate' => self::pubDate($post),
                'guid' => self::LEGACY_GUID_ORIGIN . '/' . $path . '_thmb.jpg',
            ];
        }

        /** @var RssView $view */
        $view = [
            'siteUrl' => self::SITE_URL,
            'selfUrl' => self::SITE_URL . '/rss.xml',
            'pubDate' => self::pubDate($posts[0]),
            'items' => $items,
        ];

        return $this->templates->render('rss.xml.twig', ['feed' => $view]);
    }

    /**
     * @param ArchivePost $left
     * @param ArchivePost $right
     */
    private static function newestFirst(array $left, array $right): int
    {
        $byDate = $right['publishedAt']->getTimestamp() <=> $left['publishedAt']->getTimestamp();
        if ($byDate !== 0) {
            return $byDate;
        }

        return [$right['month'], $right['day']] <=> [$left['month'], $left['day']];
    }

    /** @param ArchivePost $post */
    private static function pubDate(array $post): string
    {
        return $post['publishedAt']->format('D, j M Y H:i:s \G\M\T');
    }
}
