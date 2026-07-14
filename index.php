<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/function.php';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>calendorama!</title>
	<link rel="stylesheet" type="text/css" href="/style.css?i=1" media="screen" />
	<link rel="shortcut icon" href="/favicon.ico" />
	<link rel="alternate" type="application/rss+xml" title="calendorama feed" href="/rss.xml"></link>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.4.2/jquery.min.js" integrity="sha256-4joqTi18K0Hrzdj/wGed9xQOt/UuHuur+CeogYJkPFk=" crossorigin="anonymous"></script>
	<script type="text/javascript" src="/history.js"></script>
	<script type="text/javascript" src="/options.js"></script>
	<script type="text/javascript" src="/script.js?i=1"></script>
</head>
<body>
<div id="wrap">

<h1>calendorama!</h1>
<ul id="navigate">
	<li><a href="/rss.xml" class="permtooltip" title="подписаться на rss" id="rss">rss</a></li>
	<li><a href="#authors" class="pseudo permtooltip" title="авторы картинок" id="authors">authors</a></li>
	<li><a href="#montheslist" class="pseudo permtooltip" title="перейти к месяцу" id="montheslist">monthes</a></li>
</ul>
<div id="month-wrap"></div>
<p id="footer">
	dmitry groza<br />
	nadja gimmer<br />
	in incredible <b><a href="#calendoramaproject" id="about" class="pseudo permtooltip" title="о проекте">calendorama project!</a></b>
</p>
<!--	надя гиммер, вы прекрасны :–) -->
</div>
</body>
</html>
