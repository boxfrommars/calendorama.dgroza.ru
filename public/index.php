<?php

declare(strict_types=1);

use Calendorama\View\TemplateRenderer;

header('Content-Type: text/html; charset=utf-8');

require_once dirname(__DIR__) . '/vendor/autoload.php';

$templates = new TemplateRenderer(dirname(__DIR__) . '/templates');

echo $templates->render('index.html.twig');
