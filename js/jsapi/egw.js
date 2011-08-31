/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

"use strict";

var egw;

/**
 * Central object providing all kinds of api services on clientside:
 * - preferences:   egw.preferences("dateformat")
 * - translation:   egw.lang("%1 entries deleted", 5)
 * - link registry: egw.open(123, "infolog")
 */
if (window.opener && typeof window.opener.egw == 'object')
{
	egw = window.opener.egw;
}
else if (window.top && typeof window.top.egw == 'object')
{
	egw = window.top.egw;
}
else
{
	egw = {
		/**
		 * Object holding the prefences as 2-dim. associative array, use egw.preference(name[,app]) to access it
		 * 
		 * @access: private, use egw.preferences() or egw.set_perferences()
		 */
		prefs: {
			common: { 
				dateformat: "Y-m-d", 
				timeformat: 24,
				lang: "en"
			}
		},
		
		/**
		 * base-URL of the EGroupware installation
		 * 
		 * get set via egw_framework::header()
		 */
		webserverUrl: "/egroupware",
	
		/**
		 * Setting prefs for an app or 'common'
		 * 
		 * @param object _data object with name: value pairs to set
		 * @param string _app application name, 'common' or undefined to prefes of all apps at once
		 */
		set_preferences: function(_data, _app)
		{
			if (typeof _app == 'undefined')
			{
				this.prefs = _data;
			}
			else
			{
				this.prefs[_app] = _data;
			}
		},
	
		/**
		 * Query an EGroupware user preference
		 * 
		 * If a prefernce is not already loaded (only done for "common" by default), it is synchroniosly queryed from the server!
		 * 
		 * @param string _name name of the preference, eg. 'dateformat'
		 * @param string _app='common'
		 * @return string preference value
		 * @todo add a callback to query it asynchron
		 */
		preference: function(_name, _app) 
		{
			if (typeof _app == 'undefined') _app = 'common';
			
			if (typeof this.prefs[_app] == 'undefined')
			{
				xajax_doXMLHTTPsync('home.egw_framework.ajax_get_preference.template', _app);
				
				if (typeof this.prefs[_app] == 'undefined') this.prefs[_app] = {};
			}
			return this.prefs[_app][_name];
		},
		
		/**
		 * Set a preference and sends it to the server
		 * 
		 * Server will silently ignore setting preferences, if user has no right to do so!
		 * 
		 * @param string _app application name or "common"
		 * @param string _name name of the pref
		 * @param string _val value of the pref
		 */
		set_preference: function(_app, _name, _val)
		{
			xajax_doXMLHTTP('home.egw_framework.ajax_set_preference.template', _app, _name, _val);
			
			// update own preference cache, if _app prefs are loaded (dont update otherwise, as it would block loading of other _app prefs!)
			if (typeof this.prefs[_app] != 'undefined') this.prefs[_app][_name] = _val;
		},
		
		/**
		 * Translations
		 * 
		 * @access: private, use egw.lang() or egw.set_lang_arr()
		 */
		lang_arr: {},
		
		/**
		 * Set translation for a given application
		 * 
		 * @param string _app
		 * @param object _message message => translation pairs
		 */
		set_lang_arr: function(_app, _messages)
		{
			this.lang_arr[_app] = _messages;
		},
		
		/**
		 * Translate a given phrase replacing optional placeholders
		 * 
		 * @param string _msg message to translate
		 * @param string _arg1 ... _argN
		 */
		lang: function(_msg, _arg1)
		{
			var translation = _msg;
			_msg = _msg.toLowerCase();
			
			// search apps in given order for a replacement
			var apps = [window.egw_appName, 'etemplate', 'common'];
			for(var i = 0; i < apps.length; ++i)
			{
				if (typeof this.lang_arr[apps[i]] != "undefined" &&
					typeof this.lang_arr[apps[i]][_msg] != 'undefined')
				{
					translation = this.lang_arr[apps[i]][_msg];
					break;
				}
			}
			if (arguments.length == 1) return translation;
			
			if (arguments.length == 2) return translation.replace('%1', arguments[1]);
			
			// to cope with arguments containing '%2' (eg. an urlencoded path like a referer),
			// we first replace all placeholders '%N' with '|%N|' and then we replace all '|%N|' with arguments[N]
			translation = translation.replace(/%([0-9]+)/g, '|%$1|');
			for(var i = 1; i < arguments.length; ++i)
			{
				translation = translation.replace('|%'+i+'|', arguments[i]);
			}
			return translation;
		},
		
		/**
		 * View an EGroupware entry: opens a popup of correct size or redirects window.location to requested url
		 * 
		 * Examples: 
		 * - egw.open(123,'infolog') or egw.open('infolog:123') opens popup to edit or view (if no edit rights) infolog entry 123
		 * - egw.open('infolog:123','timesheet','add') opens popup to add new timesheet linked to infolog entry 123
		 * - egw.open(123,'addressbook','view') opens addressbook view for entry 123 (showing linked infologs)
		 * - egw.open('','addressbook','view_list',{ search: 'Becker' }) opens list of addresses containing 'Becker'
		 * 
		 * @param string|int id either just the id or "app:id" if app==""
		 * @param string app app-name or empty (app is part of id)
		 * @param string type default "edit", possible "view", "view_list", "edit" (falls back to "view") and "add"
		 * @param object|string extra extra url parameters to append as object or string
		 * @param string target target of window to open
		 */
		open: function(id, app, type, extra, target)
		{
			if (typeof this.link_registry != 'object')
			{
				alert('egw.open() link registry is NOT defined!');
				return;
			}
			if (!app)
			{
				var app_id = id.split(':',2);
				app = app_id[0];
				id = app_id[1];
			}
			if (!app || typeof this.link_registry[app] != 'object')
			{
				alert('egw.open() app "'+app+'" NOT defined in link registry!');
				return;	
			}
			var app_registry = this.link_registry[app];
			if (typeof type == 'undefined') type = 'edit';
			if (type == 'edit' && typeof app_registry.edit == 'undefined') type = 'view';
			if (typeof app_registry[type] == 'undefined')
			{
				alert('egw.open() type "'+type+'" is NOT defined in link registry for app "'+app+'"!');
				return;	
			}
			var url = this.webserverUrl+'/index.php';
			var delimiter = '?';
			var params = app_registry[type];
			if (type == 'view' || type == 'edit')	// add id parameter for type view or edit
			{
				params[app_registry[type+'_id']] = id;
			}
			else if (type == 'add' && id)	// add add_app and app_id parameters, if given for add
			{
				var app_id = id.split(':',2);
				params[app_registry.add_app] = app_id[0];
				params[app_registry.add_id] = app_id[1];
			}
			for(var attr in params)
			{
				url += delimiter+attr+'='+encodeURIComponent(params[attr]);
				delimiter = '&';
			}
			if (typeof extra == 'object')
			{
				for(var attr in extra)
				{
					url += delimiter+attr+'='+encodeURIComponent(extra[attr]);			
				}
			}
			else if (typeof extra == 'string')
			{
				url += delimiter + extra;
			}
			if (typeof app_registry[type+'_popup'] == 'undefined')
			{
				if (target)
				{
					window.open(url, target);
				}
				else
				{
					egw_appWindowOpen(app, url);
				}
			}
			else
			{
				var w_h = app_registry[type+'_popup'].split('x');
				if (w_h[1] == 'egw_getWindowOuterHeight()') w_h[1] = egw_getWindowOuterHeight();
				egw_openWindowCentered2(url, target, w_h[0], w_h[1], 'yes', app, false);
			}
		},
		
		/**
		 * Link registry
		 * 
		 * @access: private, use egw.open() or egw.set_link_registry()
		 */
		link_registry: null,
		
		/**
		 * Set link registry
		 * 
		 * @param object _registry whole registry or entries for just one app
		 * @param string _app
		 */
		set_link_registry: function (_registry, _app)
		{
			if (typeof _app == 'undefined')
			{
				this.link_registry = _registry;
			}
			else
			{
				this.link_registry[_app] = _registry;
			}
		},
		
		/**
		 * Clientside config
		 * 
		 * @access: private, use egw.config(_name, _app="phpgwapi")
		 */
		configs: {},
		
		/**
		 * Query clientside config
		 * 
		 * @param string _name name of config variable
		 * @param string _app default "phpgwapi"
		 * @return mixed
		 */
		config: function (_name, _app)
		{
			if (typeof _app == 'undefined') _app = 'phpgwapi';

			if (typeof this.configs[_app] == 'undefined') return null;
			
			return this.configs[_app][_name];
		},
		
		/**
		 * Set clientside configuration for all apps
		 * 
		 * @param array/object
		 */
		set_configs: function(_configs)
		{
			this.configs = _configs;
		},
		
		/**
		 * Map to serverside available images for users template-set
		 * 
		 * @access: private, use egw.image(_name, _app)
		 */
		images: {},
		
		/**
		 * Set imagemap, called from /phpgwapi/images.php
		 * 
		 * @param array/object _images
		 */
		set_images: function (_images)
		{
			this.images = _images;
		},
		
		/**
		 * Get image URL for a given image-name and application
		 * 
		 * @param string _name image-name without extension
		 * @param string _app application name, default current app of window
		 * @return string with URL of image
		 */
		image: function (_name, _app)
		{
			if (typeof _app == 'undefined') _app = this.getAppName();
			
			// own instance specific images in vfs have highest precedence
			if (typeof this.images['vfs'] != 'undefined' && typeof this.images['vfs'][_name] != 'undefined')
			{
				return this.webserverUrl+this.images['vfs'][_name];
			}
			if (typeof this.images[_app] != 'undefined' && typeof this.images[_app][_name] != 'undefined')
			{
				return this.webserverUrl+this.images[_app][_name];
			}
			if (typeof this.images['phpgwapi'] != 'undefined' && typeof this.images['phpgwapi'][_name] != 'undefined')
			{
				return this.webserverUrl+this.images['vfs'][_name];
			}
			// if no match, check if it might contain an extension
			if (_name.match(/\.(png|gif|jpg)$/i))
			{
				return this.image(_name.replace(/.(png|gif|jpg)$/i,''), _app);
			}
			console.log('egw.image("'+_name+'", "'+_app+'") image NOT found!');
			return null;
		},
		
		/**
		 * Returns the name of the currently active application
		 * 
		 * @ToDo: fixme: does not work, as egw object runs in framework for jdots
		 */
		getAppName: function ()
		{
			if (typeof egw_appName == 'undefined')
			{
				return 'egroupware';
			}
			else
			{
				return egw_appName;
			}
		},
		
		/**
		 * Data about current user
		 * 
		 * @access: private, use egw.user(_field)
		 */
		userData: {},
		
		/**
		 * Set data of current user
		 * 
		 * @param object _data
		 */
		set_user: function(_data)
		{
			this.userData = _data;
		},
		
		/**
		 * Get data about current user
		 *
		 * @param string _field
		 * - 'account_id','account_lid','person_id','account_status',
		 * - 'account_firstname','account_lastname','account_email','account_fullname','account_phone'
		 * @return string|null
		 */
		user: function (_field)
		{
			return this.userData[_field];
		}
	};
}