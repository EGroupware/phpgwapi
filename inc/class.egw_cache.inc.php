<?php
/**
 * EGroupware API: Caching data
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage cache
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2009-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Class to manage caching in eGroupware.
 *
 * @deprecated use Api\Cache instead
 */
class egw_cache extends Api\Cache {}

class egw_cache_apc extends Api\Cache\Apc {}
class egw_cache_files extends Api\Cache\Files {}
class egw_cache_memcache extends Api\Cache\Memcache {}
class egw_cache_memcached extends Api\Cache\Memcached {}
