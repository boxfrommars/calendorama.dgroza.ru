<?php
header("Content-Type: text/html;charset=utf-8");

include_once('function.php');
$month = new Month($_REQUEST['month'],$_REQUEST['year']);

$month->printCalendar();
$month->printMonthNav();
