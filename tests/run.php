<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$runner = new TestRunner();

require __DIR__ . '/page_test.php';
require __DIR__ . '/request_test.php';
require __DIR__ . '/calendar_test.php';
require __DIR__ . '/rendering_test.php';
require __DIR__ . '/content_test.php';

$failures = $runner->run();
restore_error_handler();

exit($failures === 0 ? 0 : 1);
