<?php

declare(strict_types=1);

use Calendorama\Calendar\CalendarArchive;
use Calendorama\Rss\RssGenerator;
use Calendorama\View\TemplateRenderer;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$arguments = array_slice($argv, 1);
if ($arguments !== [] && $arguments !== ['--check']) {
    fwrite(STDERR, "Usage: php bin/generate-rss.php [--check]\n");
    exit(2);
}

$root = dirname(__DIR__);
$generator = new RssGenerator(
    new CalendarArchive($root . '/content/archive.json'),
    new TemplateRenderer($root . '/templates'),
);
$generated = $generator->render();
$outputFile = $root . '/public/rss.xml';

if ($arguments === ['--check']) {
    $current = @file_get_contents($outputFile);
    if ($current !== $generated) {
        fwrite(STDERR, "public/rss.xml is stale. Run composer rss.\n");
        exit(1);
    }

    fwrite(STDOUT, "public/rss.xml is up to date.\n");
    exit(0);
}

$written = @file_put_contents($outputFile, $generated, LOCK_EX);
if ($written !== strlen($generated)) {
    fwrite(STDERR, "Unable to write public/rss.xml.\n");
    exit(1);
}

fwrite(STDOUT, "Generated public/rss.xml.\n");
