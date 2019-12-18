<?php
function create_tag($tag, $name=false, $attr_array=false){
	$return = '<'.$tag;
	if ($name){
		if ($attr_array){
			$attributes = ' ';
			foreach ($attr_array as $key=>$value){
				$attributes.=$key.'="'.$value.'"';
			}
			$return.=$attributes;
		}
		$return.='>'.$name.'</'.$tag.'>';
		return $return;
	}
	return $return.' />';
}
function printJS($text){
	print '<script type="text/javascript">'.$text.'</script>';
}
function slog($text){
	printJS("console.log('$text')");
}
function reqDay(){
	$getday['isset'] = 'month';
	$getday['month'] = date('n');
	$getday['year'] = date('Y');
	if (is_numeric($day = $_GET['day']) && strlen($day)==8){
		$getday['year'] = intval(substr($day,0,4));
		$getday['month'] = intval(substr($day,4,2));
		$getday['day'] = intval(substr($day,6,2));
		$getday['isset'] = 'day';
	}
	if (is_numeric($day = $_GET['month']) && strlen($_GET['month'])==6){
		$getday['year'] = intval(substr($day,0,4));
		$getday['month'] = intval(substr($day,4,2));
	}
	return $getday;
}



/**
 * month object
 *
 */
class Month {
	/**
	 * first day as day of week (7 for sunday)
	 *
	 * @var number
	 */
	var $first;
	
	/**
	 * total day in this month
	 *
	 * @var number
	 */
	var $total;
	
	/**
	 * number of the month
	 *
	 * @var number
	 */
	var $number;
	var $name;
	
	/**
	 * year
	 *
	 * @var number
	 */
	var $year;
	
	/**
	 * how many lines in the month calendar
	 *
	 * @var number (4-6)
	 */
	var $lines;
	
	/**
	 * today number
	 *
	 * @var number
	 */
	var $today;
	
	var $prevmonth;
	var $prevmonthname;
	var $prevmonthyear;
	var $nextmonth;
	var $nextmonthname;
	var $nextmonthyear;
	
	var $current;
	var $calendar;
	var $titles;
	
	function Month($month, $year){
		$jd = gregoriantojd($month,1,$year);
		$this->first = jddayofweek($jd);
		if ($this->first === 0){
			$this->first = 7;
		}
		$this->total = cal_days_in_month(CAL_GREGORIAN, $month, $year);
		$this->number = $month;
		$this->year = $year;
		$this->lines = 6;
//		$this->lines = ceil(($this->total + $this->first - 1)/7); // наименьшее кол-во линий в календаре, чтобы влезли все дни
		$this->today = date('j');
		if ($month != date('n') || $year != date('Y') ) {
			$this->today = 33; // incredible!
		}
		$this->name = jdmonthname($jd,1);
		
		$this->current =1;
		$this->calendar = '';
		$this->getTitles();
		$this->setPrevAndNextMonth();
	}
	/**
	 * устанавливает свойства предыдущего месяца и следующего (год, месяц, имя месяца)
	 *
	 */
	function setPrevAndNextMonth(){
		if ($this->number>1){
			$this->prevmonth = $this->number-1;
			$this->prevmonthyear = $this->year;
		} else {
			$this->prevmonth = 12;
			$this->prevmonthyear = $this->year - 1;
		}
		$this->prev = 100*$this->prevmonthyear + $this->prevmonth;
		$this->prevmonthname = jdmonthname(gregoriantojd($this->prevmonth,1,$this->prevmonthyear),1);
		if ($this->number<12){
			$this->nextmonth = $this->number+1;
			$this->nextmonthyear = $this->year;
		} else {
			$this->nextmonth = 1;
			$this->nextmonthyear = $this->year + 1;
		}
		$this->next = 100*$this->nextmonthyear + $this->nextmonth;
		$this->nextmonthname = jdmonthname(gregoriantojd($this->nextmonth,1,$this->nextmonthyear),1);
	}
	/**
	 * получает из файла blog/год_месяц.txt заголовки для записей и записывает их в свойство $this->titles (Array)
	 *
	 */
	function getTitles(){
		$file = 'blog/'.$this->number.'.txt';
		if (file_exists($file)){
			$titles = file($file);
			foreach ($titles as $dayline){
				$pos = strpos($dayline,' ');
				$title = substr($dayline,$pos+1);
				$day = substr($dayline,0,$pos);
				$titlesbyday[$day] = $title;
			}
		} else {
			$titlesbyday = array();
		};
		$this->titles = $titlesbyday;
	}
	/**
	 * возвращает заголовок по числу. если заголовка нет, возвращает no subject
	 *
	 * @param number $day
	 * @return string
	 */
	function title($day){
		return trim($this->titles[$day]?$this->titles[$day]:"no subject");
	}
	function postExist($day_n){
		return file_exists('blog/'.$this->number.'_'.$day_n.'_thmb.jpg');
	}
	function printCalendar(){
		for ($i=1;$i<=$this->lines;$i++){
			$this->printWeek();
		}
		print $this->calendar = '<table id="calendar" cellspacing="1" class="'.$this->number.'_'.$this->year.'">'.$this->calendar.'</table>'."\n";
	}
	function printWeek(){
		for ($j=1;$j<=7;$j++){
			$week .= $this->printDay();
		}
		$this->calendar .= "\t".'<tr>'.$week.'</tr>'."\n\t";
	}
	function printDay(){
		$day = ' ';
		$class = 'a-m-d';
		if ($this->current >= $this->first && $this->current < $this->first + $this->total){
			$day_n = $this->current - $this->first + 1;
			if ($this->postExist($day_n)){
				$s_class = '';
				$id = $this->number.'_'.$day_n;
				$img = 'blog/'.$id;
				$imgfull = $img.'_full.jpg';
				$imgthmb = $img.'_thmb.jpg';
				$day = '<a id="f'.$this->year.'_'.$id.'" title="'.$this->title($day_n).'" class="view_full tooltip goto" href="'.$imgfull.'"><img alt="'.$this->title($day_n).'" class="thumb" width="90px" src="'.$imgthmb.'" /></a>';
			} else {
				$day='<span>'.$day_n.'</span>';
				$s_class = 'w-p';
			}
			if ($day_n==$this->today){
				$s_class.=' today';
			};
			$class = $s_class;
		}
		$this->current++;
		return create_tag('td',$day,array('class'=>$class));
	}
	function printMonthNav(){
		print "\t".'<h2 id="month">'.$this->name.'&nbsp;&nbsp;<span id="month-nav" class="nav"><a title="'.$this->prevmonthname.' '.$this->prevmonthyear.'" href="/#d'.$this->prevmonthyear.'_'.$this->prevmonth.'" class="goto previous tooltip" id="m'.$this->prevmonthyear.'_'.$this->prevmonth.'">&lt;</a><a title="'.$this->nextmonthname.' '.$this->nextmonthyear.'" href="/#d'.$this->nextmonthyear.'_'.$this->nextmonth.'" class="goto next tooltip" id="m'.$this->nextmonthyear.'_'.$this->nextmonth.'">&gt;</a></span></h2>'."\n";
	}
}