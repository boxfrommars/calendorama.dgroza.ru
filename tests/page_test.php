<?php

declare(strict_types=1);

$runner->test('index preserves the standalone client and hidden templates', static function (): void {
    ob_start();
    require PROJECT_ROOT . '/public/index.php';
    $html = ob_get_clean();

    Assert::contains('<!doctype html>', strtolower($html), 'HTML5 doctype missing');
    Assert::contains('<script type="module" src="/script.js?i=2"></script>', $html, 'Client module missing');
    Assert::contains('<template id="montheslist-template">', $html, 'Month list template missing');
    Assert::contains('<template id="authors-template">', $html, 'Authors template missing');
    Assert::contains('<template id="about-template">', $html, 'About template missing');
    Assert::true(!str_contains($html, 'jquery'), 'jQuery is still referenced');
    Assert::true(!str_contains($html, 'history.js'), 'Legacy history script is still referenced');
    Assert::true(!str_contains($html, 'options.js'), 'Legacy options script is still referenced');
});
