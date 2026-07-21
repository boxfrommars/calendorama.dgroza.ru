<?php

declare(strict_types=1);

use Calendorama\ApplicationFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

ApplicationFactory::create(dirname(__DIR__))->index()->send();
