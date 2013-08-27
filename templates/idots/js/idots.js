/**
 * EGroupware idots template javascript
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package phpgwapi
 * @subpackage idots
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */


/**
 * idots javascript
 */
(function(){
	// add quick add select box
	egw.link_quick_add('quick_add');

	// instanciate slideout menus via "data-slide-out" of egw.js script tag
	var egw_script = document.getElementById('egw_script_id');
	if (egw_script)
	{
		var data_slide_out = egw_script.getAttribute('data-slide-out');
		if (data_slide_out)
		{
			data_slide_out = JSON.parse(data_slide_out);
			for(var i=0; i < data_slide_out.length; ++i)
			{
				var args=data_slide_out[i];
				
				new ypSlideOutMenu(args.id, args.dir, args.left, args.top, args.width, args.height, args.pos);
				for(var selector in args.bind)
				{
					var data = args.bind[selector];
					jQuery(selector).on(data.event, {menu: args.id, method: data.method}, function(event){ 
						window.ypSlideOutMenu[event.data.method].call(window, event.data.menu);
						event.preventDefault();
					});
				}
			}
		}
	}
})();