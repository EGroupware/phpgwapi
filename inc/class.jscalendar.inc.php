<?php
/**
 * Wrapper for the jsCalendar
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage html
 * @version $Id$
 */

/**
 * Wrapper for the jsCalendar
 *
 * The constructor loads the necessary javascript-files.
 */
class jscalendar
{
	/**
	 * url to the jscalendar files
	 *
	 * @var string
	 */
	var $jscalendar_url;
	/**
	 * dateformat from the user-prefs
	 *
	 * @var string
	 */
	var $dateformat;

	/**
	 * Constructor
	 *
	 * @param boolean $do_header=true if true, necessary javascript and css gets loaded, only needed for input
	 * @param string $path='jscalendar'
	 * @return jscalendar
	 */
	function __construct($do_header=True,$path='jscalendar')
	{
		$this->jscalendar_url = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/'.$path;
		$this->dateformat = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];

		$args = array_intersect_key($GLOBALS['egw_info']['user']['preferences']['common'],array('lang'=>1,'dateformat'=>1));
		if (isset($GLOBALS['egw_info']['flags']['currentapp']))
		{
			$args['app'] = $GLOBALS['egw_info']['flags']['currentapp'];
		}
		else
		{
			$args['app'] = 'api';
		}
		if ($do_header)
		{
			egw_framework::includeCSS('/phpgwapi/js/jscalendar/calendar-blue.css');
			egw_framework::validate_file('jscalendar','calendar');
			$args = array_intersect_key($GLOBALS['egw_info']['user']['preferences']['common'],array('lang'=>1,'dateformat'=>1));
			egw_framework::validate_file('/phpgwapi/inc/jscalendar-setup.php',$args);
		}
	}

	/**
	 * return javascript needed for jscalendar
	 *
	 * Only needed if jscalendar runs outside of egw_framework, eg. in sitemgr
	 *
	 * @return string
	 */
	function get_javascript()
	{
		$args = array_intersect_key($GLOBALS['egw_info']['user']['preferences']['common'],array('lang'=>1,'dateformat'=>1));
		return
'<link rel="stylesheet" type="text/css" media="all" href="'.$this->jscalendar_url.'/calendar-blue.css" title="blue" />
<script type="text/javascript" src="'.$this->jscalendar_url.'/calendar.js"></script>
<script type="text/javascript" src="'.egw::link('/phpgwapi/inc/jscalendar-setup.php',$args,false).'"></script>
';
	}

	/**
	 * Creates an inputfield for the jscalendar (returns the necessary html and js)
	 *
	 * @param string $name name and id of the input-field (it also names the id of the img $name.'-toggle')
	 * @param int/string $date date as string or unix timestamp (in server timezone)
	 * @param int $year=0 if $date is not used
	 * @param int $month=0 if $date is not used
	 * @param int $day=0 if $date is not used
	 * @param string $helpmsg='' a helpmessage for the statusline of the browser
	 * @param string $options='' any other options to the inputfield
	 * @param boolean $jsreturn=false
	 * @param boolean $useicon=true true: use icon to trigger popup, false: click into input triggers popup
	 * 		the input is made readonly via javascript to NOT trigger mobile devices to display a keyboard!
	 * @return string html
	 */
	function input($name,$date,$year=0,$month=0,$day=0,$helpmsg='',$options='',$jsreturn=false,$useicon=true)
	{
		//echo "<p>jscalendar::input(name='$name', date='$date'='".date('Y-m-d',$date)."', year='$year', month='$month', day='$day')</p>\n";

		if ($date && (is_int($date) || is_numeric($date)))
		{
			$year  = (int)date('Y',$date);
			$month = (int)date('n',$date);
			$day   = (int)date('d',$date);
		}
		if ($year && $month && $day)
		{
			$date = date($this->dateformat,$ts = mktime(12,0,0,$month,$day,$year));
			if (strpos($this->dateformat,'M') !== false)
			{
				static $substr;
				if (is_null($substr)) $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
				static $chars_shortcut;
				if (is_null($chars_shortcut)) $chars_shortcut = (int)lang('3 number of chars for month-shortcut');	// < 0 to take the chars from the end

				$short = translation::translate($m = date('M',$ts), null, '*');	// check if we have a translation of the short-cut
				if ($substr($short,-1) == '*')	// if not generate one by truncating the translation of the long name
				{
					$short = $chars_shortcut > 0 ? $substr(lang(date('F',$ts)),0,$chars_shortcut) :
						$substr(lang(date('F',$ts)),$chars_shortcut);
				}
				$date = str_replace(date('M',$ts),$short,$date);
			}
		}
		if ($helpmsg !== '')
		{
			$options .= " onFocus=\"self.status='".addslashes($helpmsg)."'; return true;\"" .
			" onBlur=\"self.status=''; return true;\"";
		}

		$html = '<input type="text" id="'.$name.'" name="'.$name.'" size="10" value="'.htmlspecialchars($date).'"'.$options.'/>
'.($useicon ? '<img id="'.$name.'-trigger" src="'.common::find_image('phpgwapi','datepopup').'" title="'.lang('Select date').'" style="cursor:pointer; cursor:hand;">' : '');

		$js = '<script type="text/javascript">
'.(!$useicon ? '	document.getElementById("'.$name.'").readOnly=true;' : '').
'	egw_LAB.wait(function() {
		Calendar.setup({
			inputField  : "'.$name.'",'.(!$useicon ? '' : '
			button      : "'.$name.'-trigger"').',
			onUpdate    : function(){var input = document.getElementById("'.$name.'"); jQuery(input).change(); }
		})
	});
</script>
';
		if ($jsreturn) return array('html' => $html, 'js' => $js);

		return $html."\n".$js;
	}

	/**
	 * Flat jscalendar with tooltips and url's for days, weeks and month
	 *
	 * @param string $url url to call if user clicks on a date (&date=YYYYmmdd is appended automatically)
	 * @param string/int $date=null format YYYYmmdd or timestamp
	 * @param string $weekUrl=''
	 * @param string $weekTTip=''
	 * @param string $monthUrl=''
	 * @param string $monthTTip=''
	 * @param string $id='calendar-container'
	 * @return string html
	 */
	function flat($url,$date=null,$weekUrl='',$weekTTip='',$monthUrl='',$monthTTip='',$id='calendar-container')
	{
		if ($date)	// string if format YYYYmmdd or timestamp
		{
			$date = is_int($date) ? date('m/d/Y',$date) :
				substr($date,4,2).'/'.substr($date,6,2).'/'.substr($date,0,4);
		}
		return '
<div id="'.$id.'"></div>
<script type="text/javascript">
function dateChanged(calendar) {
'.  // Beware that this function is called even if the end-user only
// changed the month/year.  In order to determine if a date was
// clicked you can use the dateClicked property of the calendar:
// redirect to $url extended with a &date=YYYYMMDD
'    if (calendar.dateClicked) {
	egw_link_handler("'.$url.'&date=" + calendar.date.print("%Y%m%d"),"calendar");
}
};

function todayClicked(calendar) {
	egw_link_handler("'.$url.'&date='.egw_time::to('now', 'Ymd').'","calendar");
}

'.($weekUrl ? '
function weekClicked(calendar,weekstart) {
	egw_link_handler("'.$weekUrl.'&date=" + weekstart.print("%Y%m%d"),"calendar");
}
' : '').($monthUrl ? '
function monthClicked(calendar,monthstart) {
	egw_link_handler("'.$monthUrl.'&date=" + monthstart.print("%Y%m%d"),"calendar");
}
' : '').'

egw_LAB.wait(function() {
	Calendar.setup({
  		flat         : "'.$id.'",
  		flatCallback : dateChanged'.($weekUrl ? ',
  		flatWeekCallback : weekClicked' : '').($weekTTip ? ',
  		flatWeekTTip : "'.addslashes($weekTTip).'"' : '').($monthUrl ? ',
  		flatMonthCallback : monthClicked' : '').($monthTTip ? ',
  		flatMonthTTip : "'.addslashes($monthTTip).'"' : '').($date ? ',
		flatTodayCallback : todayClicked,
 		date : "'.$date.'"
		' : '').'
	});
});
</script>';
	}

	/**
	 * Converts the date-string back to an array with year, month, day and a timestamp
	 *
	 * @param string $datestr content of the inputfield generated by jscalendar::input()
	 * @param boolean/string $raw='raw' key of the timestamp-field in the returned array or False of no timestamp
	 * @param string $day='day' keys for the array, eg. to set mday instead of day
	 * @param string $month='month' keys for the array
	 * @param string $year='year' keys for the array
	 * @return array/boolean array with the specified keys and values or false if $datestr == ''
	 */
	function input2date($datestr,$raw='raw',$day='day',$month='month',$year='year')
	{
		//echo "<p>jscalendar::input2date('$datestr') ".print_r($fields,True)."</p>\n";
		if ($datestr === '')
		{
			return False;
		}
		$fields = preg_split('/[.\\/-]/',$datestr);
		foreach(preg_split('/[.\\/-]/',$this->dateformat) as $n => $field)
		{
			if ($field == 'M')
			{
				if (!is_numeric($fields[$n]))
				{
					$partcial_match = 0;
					for($i = 1; $i <= 12; $i++)
					{
						$long_name  = lang(date('F',mktime(12,0,0,$i,1,2000)));
						$short_name = translation::translate(date('M',mktime(12,0,0,$i,1,2000)), null, '*');	// do we have a translation of the short-cut
						if (substr($short_name,-1) == '*')	// if not generate one by truncating the translation of the long name
						{
							$short_name = substr($long_name,0,(int) lang('3 number of chars for month-shortcut'));
						}
						//echo "<br>checking '".$fields[$n]."' against '$long_name' or '$short_name'";
						if ($fields[$n] == $long_name || $fields[$n] == $short_name)
						{
							//echo " ==> OK<br>";
							$fields[$n] = $i;
							break;
						}
						if (@strstr($long_name,$fields[$n]) == $long_name)	// partcial match => multibyte saver
						{
							$partcial_match = $i;
						}
					}
					if ($i > 12 && $partcial_match)	// nothing found, but a partcial match
					{
						$fields[$n] = $partcial_match;
					}
				}
				$field = 'm';
			}
			$date[$field] = (int)$fields[$n];
		}

		$ret = array(
			$year  => $date['Y'],
			$month => $date['m'],
			$day   => $date['d']
		);
		if ($raw)
		{
			$ret[$raw] = mktime(12,0,0,$date['m'],$date['d'],$date['Y']);
		}
		//echo "<p>jscalendar::input2date('$datestr','$raw',$day','$month','$year') = "; print_r($ret); echo "</p>\n";

		return $ret;
	}
}