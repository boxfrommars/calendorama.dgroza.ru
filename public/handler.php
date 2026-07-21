<?php

declare(strict_types=1);

use Calendorama\ApplicationFactory;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$request = Request::createFromGlobals();
ApplicationFactory::create(dirname(__DIR__))->calendar($request)->send();
