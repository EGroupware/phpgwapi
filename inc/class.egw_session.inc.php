<?php
/**
 * EGroupware API: session handling
 *
 * This class is based on the old phpgwapi/inc/class.sessions(_php4).inc.php:
 * (c) 1998-2000 NetUSE AG Boris Erdmann, Kristian Koehntopp
 * (c) 2003 FreeSoftware Foundation
 * Not sure how much the current code still has to do with it.
 *
 * Former authers were:
 * - NetUSE AG Boris Erdmann, Kristian Koehntopp
 * - Dan Kuykendall <seek3r@phpgroupware.org>
 * - Joseph Engo <jengo@phpgroupware.org>
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage session
 * @author Ralf Becker <ralfbecker@outdoor-training.de> since 2003 on
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Create, verifies or destroys an EGroupware session
 *
 * @deprecated use Api\Session
 */
class egw_session extends Api\Session
{
	/**
	 * Stores or retrieve applications data in/form the eGW session
	 *
	 * @param string $location free lable to store the data
	 * @param string $appname ='' default current application (egw_info[flags][currentapp])
	 * @param mixed $data ='##NOTHING##' if given, data to store, if not specified
	 * @deprecated use egw_cache::setSession($appname, $location, $data) or egw_cache::getSession($appname, $location)
	 * @return mixed session data or false if no data stored for $appname/$location
	 */
	public static function &appsession($location = 'default', $appname = '', $data = '##NOTHING##')
	{
		if (!$appname)
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		}

		// allow to store eg. '' as the value.
		if (func_num_args() === 2 || $data === '##NOTHING##')
		{
			return Api\Cache::getSession($appname, $location);
		}
		return Api\Cache::setSession($appname, $location, $data);
	}
}
