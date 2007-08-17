<?php
/**
 * eGroupWare API - memcache session handler
 *
 * Fixes a problem of the buildin session handler of the memcache pecl extension, 
 * which can NOT work with sessions > 1MB. This handler splits the session-data
 * in 1MB junk, so memcache can handle them.
 *
 * To enable it, you need to set session.save_handler to 'memcache' and 
 * session.save_path to 'tcp://host:port[,tcp://host2:port,...]', 
 * as you have to do it with the original handler.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage session
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

function egw_memcache_open($save_path, $session_name)
{
	global $egw_memcache_obj;
	$egw_memcache_obj = new Memcache;
	foreach(explode(',',ini_get('session.save_path')) as $path)
	{
		$parts = parse_url($path);
		$egw_memcache_obj->addServer($parts['host'],$parts['port']);	// todo parse query
	}
	return(true);
}

function egw_memcache_close()
{
	global $egw_memcache_obj;
	
	return is_object($egw_memcache_obj) ? $egw_memcache_obj->close() : false;
}

define('MEMCACHED_MAX_JUNK',1024*1024);

function egw_memcache_read($id)
{
	global $egw_memcache_obj;

	$id = 'sess-'.$_REQUEST['domain'].'-'.$id;
	for($data=false,$n=0; ($read = $egw_memcache_obj->get($id.'-'.$n)); ++$n)
	{
		$data .= $read;
	}
	return $data;
}

function egw_memcache_write($id, $sess_data)
{
	global $egw_memcache_obj;
	
	$lifetime = (int)ini_get('session.gc_maxlifetime');
	// give anon sessions only a lifetime of 10min
	if (is_object($GLOBALS['egw']->session) && $GLOBALS['egw']->session->session_flags == 'A')
	{
		$lifetime = 600;
	}
	$id = 'sess-'.$GLOBALS['egw_info']['user']['domain'].'-'.$id;
	for($n=$i=0,$len=_bytes($sess_data); $i < $len; $i += MEMCACHED_MAX_JUNK,++$n)
	{
		if (!$egw_memcache_obj->set($id.'-'.$n,_cut_bytes($sess_data,$i,MEMCACHED_MAX_JUNK),0,$lifetime)) return false;
	}
	return true;
}

function _test_mbstring_func_overload()
{
	return @extension_loaded('mbstring') && (ini_get('mbstring.func_overload') & 2);
}

function _bytes(&$data)
{
	global $mbstring_func_overload;
	
	if (is_null($mbstring_func_overload)) _test_mbstring_func_overload();
	
	return $mbstring_func_overload ? mb_strlen($data,'ascii') : strlen($data);
}

function _cut_bytes(&$data,$offset,$len=null)
{
	global $mbstring_func_overload;
	
	if (is_null($mbstring_func_overload)) _test_mbstring_func_overload();
	
	return $mbstring_func_overload ? mb_substr($data,$offset,$len,'ascii') : substr($data,$offset,$len,'ascii');
}

function egw_memcache_destroy($id)
{
	global $egw_memcache_obj;
	
	$id = 'sess-'.$_REQUEST['domain'].'-'.$id;
	for($n=0; $egw_memcache_obj->delete($id.'-'.$n); ++$n) ;
	
	return $n > 0;
}

function egw_memcache_gc($maxlifetime)
{
}

session_set_save_handler("egw_memcache_open", "egw_memcache_close", "egw_memcache_read", "egw_memcache_write", "egw_memcache_destroy", "egw_memcache_gc");
