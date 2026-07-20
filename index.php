<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/function.php';

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>calendorama!</title>
	<link rel="stylesheet" href="/style.css?i=1" media="screen">
	<link rel="shortcut icon" href="/favicon.ico">
	<link rel="alternate" type="application/rss+xml" title="calendorama feed" href="/rss.xml">
	<script type="module" src="/script.js?i=2"></script>
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
<p id="footer">dmitry groza<br>nadja gimmer<br>in incredible <b><a href="#calendoramaproject" id="about" class="pseudo permtooltip" title="о проекте">calendorama project!</a></b></p>
<!-- надя гиммер, вы прекрасны :–) -->
</div>

<template id="montheslist-template">
	<div id="montheslistblock" class="cl cleanable faded pseudoblock"><ul>
		<li><a href="#january" class="goto pseudotooltip" title="January" id="m2010_1">January</a> (5)</li>
		<li><a href="#february" class="goto pseudotooltip" title="February" id="m2010_2">February</a> (2)</li>
		<li><a href="#march" class="goto pseudotooltip" title="March" id="m2010_3">March</a> (27)</li>
		<li><a href="#april" class="goto pseudotooltip" title="April" id="m2010_4">April</a> (5)</li>
		<li><a href="#may" class="goto pseudotooltip" title="May" id="m2010_5">May</a> (2)</li>
		<li><a href="#june" class="goto pseudotooltip" title="June" id="m2010_6">June</a> (2)</li>
		<li><a href="#july" class="goto pseudotooltip" title="July" id="m2010_7">July</a> (1)</li>
		<li><a href="#august" class="goto pseudotooltip" title="August" id="m2010_8">August</a> (2)</li>
		<li><a href="#september" class="goto pseudotooltip" title="September" id="m2010_9">September</a> (1)</li>
		<li><a href="#october" class="goto pseudotooltip" title="October" id="m2010_10">October</a> (0)</li>
		<li><a href="#november" class="goto pseudotooltip" title="November" id="m2010_11">November</a> (2)</li>
		<li><a href="#december" class="goto pseudotooltip" title="December" id="m2010_12">December</a> (4)</li>
		<li>total (53/365)</li>
	</ul></div>
</template>

<template id="about-template">
	<div id="aboutblock" class="cl cleanable faded pseudoblock">
		<h3>проект <i>календорама!</i></h3>
		<p>это простой блог-календарь с&nbsp;картинками-записями.</p>
		<p><b>идея и&nbsp;дизайн (или его отсутствие):</b> надя гиммер</p>
		<p><b>реализация и&nbsp;наполнение:</b> ваш покорный слуга</p>
		<p><b>дата запуска:</b> 17&nbsp;марта 2009</p>
		<p>boxfrommars@gmail.com</p>
	</div>
</template>

<template id="authors-template">
	<div id="authorsblock" class="cl cleanable faded pseudoblock">
		<h3>авторы картинок</h3>
		<dl>
			<dt>юра (юрек)</dt><dd><a href="http://vkontakte.ru/id12733421" class="pseudotooltip" title="Юрий Лебедев">vkontakte</a></dd><br>
			<dt>кардамон</dt><dd><a href="http://fansales.livejournal.com" class="pseudotooltip" title="Life Aquatic">livejournal</a></dd><br>
			<dt>гельветик (жукевич)</dt><dd>так жукевич же сдох!</dd><br>
			<dt>неизвестный</dt><dd>предпочитает оставаться инкогнито</dd><br>
			<dt>даша</dt><dd>информация скрыта</dd><br>
			<dt>карнавал</dt><dd><a href="http://manutd.com" class="pseudotooltip" title="Manchester United official page">MU</a></dd><br>
			<dt>настя</dt><dd><a href="http://vkontakte.ru/id29120786" class="pseudotooltip" title="Monika Gustaffson">vkontakte</a></dd><br>
			<dt>елисей</dt><dd><a href="http://vkontakte.ru/id834086" class="pseudotooltip" title="Елисей Радионов">vkontakte</a></dd><br>
			<dt>хейлик (ройксопп)</dt><dd>о хейлике нам пока ничего не известно</dd><br>
			<dt>лида пила</dt><dd><a href="http://vkontakte.ru/id13266630" class="pseudotooltip" title="Лида Пила">vkontakte</a></dd><br>
			<dt>юля</dt><dd><a href="http://vkontakte.ru/id10731099" class="pseudotooltip" title="Юлия Мизина">vkontakte</a></dd><br>
			<dt>калинина-малинина</dt><dd><a href="http://vkontakte.ru/id4871535" class="pseudotooltip" title="Natasha Kalinina">vkontakte</a><br>&nbsp;</dd><br>
			<dt>сохина</dt><dd>ключевая фигура</dd><br>
			<dt>б-ка</dt><dd>сложная персона</dd><br>
			<dt>дг</dt><dd><a href="http://dgroza.ru" class="pseudotooltip" title="Дмитрий Гроза-Гудмундсдоттир">dgroza</a></dd><br>
			<dt>мамке</dt><dd><a href="http://vkontakte.ru/id3579364" class="pseudotooltip" title="Ольга Мурзина">vkontakte</a></dd><br>
		</dl>
		<dl class="center">
			<dt>дима (сосед №1)</dt><dd>сосед</dd><br>
			<dt>ольга николаевна</dt><dd><a href="http://vkontakte.ru/id4506412" class="pseudotooltip" title="Ольга Николаевна">vkontakte</a><br>&nbsp;</dd><br>
			<dt>галя</dt><dd>информация отсутствует</dd><br>
			<dt>поивт (2 курс)</dt><dd><ul>
				<li>кирилл астахов</li><li>павел башевский</li><li>артём гузенко</li><li>степан иванов</li><li>майн канаш</li><li>малик канаш</li><li>роман кривошей</li><li>влад недбайло</li><li>евгений полушин</li><li>георгий пуляев</li><li>родион слободенюк</li>
			</ul></dd><br>
			<dt>андрейка</dt><dd><a href="http://vkontakte.ru/id213490" class="pseudotooltip" title="Андрей Гроза">vkontakte</a></dd><br>
			<dt>голимбовски</dt><dd><a href="http://vkontakte.ru/id27131962" class="pseudotooltip" title="Игорь Голимбовски">vkontakte</a></dd><br>
			<dt>ярослав</dt><dd>данные подтверждаются</dd><br>
			<dt>аматери</dt><dd><a href="http://vkontakte.ru/id451707" class="pseudotooltip" title="Виталий Amatery Апрелков">vkontakte</a></dd><br>
			<dt>настя апрелкова</dt><dd><a href="http://vkontakte.ru/id1083793" class="pseudotooltip" title="Анастасия Апрелкова">vkontakte</a></dd><br>
			<dt>маша</dt><dd>информация отсутствует</dd><br>
			<dt>сергей</dt><dd>данные подтверждаются</dd><br>
		</dl>
		<dl class="bordered">
			<dt>люба</dt><dd><a href="http://vkontakte.ru/id7237543" class="pseudotooltip" title="Любовь Архипова">vkontakte</a></dd><br>
			<dt>виталий dreamcatcher</dt><dd><a href="http://vkontakte.ru/id3772217" class="pseudotooltip" title="Виталий Dreamcatcher Федирко">vkontakte</a></dd><br>
			<dt>вадим-директор</dt><dd>коммерческая (ага-ага) тайна</dd><br>
			<dt>максим</dt><dd>пребывает инкогнито</dd><br>
		</dl>
	</div>
</template>
</body>
</html>
