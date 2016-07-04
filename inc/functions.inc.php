<?php
/**
 * EGroupware API loader
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @deprecated use api/src/loader.php
 * @version $Id$
 */

// fix APi version shown with old header.inc.php
include(EGW_SERVER_ROOT.'/api/setup/setup.inc.php');
$GLOBALS['egw_info']['server']['versions']['phpgwapi'] = $GLOBALS['egw_info']['server']['versions']['api'] = $setup_info['api']['version'];

require_once dirname(dirname(__DIR__)).'/api/src/loader.php';
