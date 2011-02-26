/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */

/** egwActionHandler Interface **/

/**
 * Constructor for the egwActionHandler interface which (at least) should have the
 * execute function implemented.
 */
function egwActionHandler(_executeEvent)
{
	//Copy the executeEvent parameter
	this.execute = _executeEvent;
}


/** egwAction Object **/

/**
 * Associative array where action classes may register themselves
 */
if (typeof window._egwActionClasses == "undefined")
	window._egwActionClasses = {}

_egwActionClasses["default"] = {
	"actionConstructor": egwAction,
	"implementation": null
};

function egwAction(_id, _handler, _caption, _iconUrl, _onExecute, _allowOnMultiple)
{
	//Default and check the values
	if (typeof _id != "string" || !_id)
		throw "egwAction _id must be a non-empty string!";
	if (typeof _handler == "undefined")
		this.handler = null;
	if (typeof _label == "undefined")
		_label = "";
	if (typeof _iconUrl == "undefined")
		_iconUrl = "";
	if (typeof _onExecute == "undefined")
		_onExecute = null;
	if (typeof _allowOnMultiple == "undefined")
		_allowOnMultiple = true;

	this.id = _id;
	this.caption = _caption;
	this.iconUrl = _iconUrl;
	this.allowOnMultiple = _allowOnMultiple;
	this.type = "default"; //All derived classes have to override this!

	this.execJSFnct = null;
	this.execHandler = false;
	this.set_onExecute(_onExecute);
}

/**
 * Executes this action by using the method specified in the onExecute setter.
 *
 * @param array_senders array with references to the objects which caused the action
 * //TODO: With DragDrop we don't only have senders but also one(!) target.
 */
egwAction.prototype.execute = function(_senders)
{
	if (this.execJSFnct && typeof this.execJSFnct == "function")
	{
		this.execJSFnct(this, _senders);
	}
	else if (this.execHandler)
	{
		this.handler.execute(this, _senders);
	}
}

/**
 * The set_onExecute function is the setter function for the onExecute event of
 * the egwAction object. There are three possible types the passed "_value" may
 * take:
 *	1. _value may be a string with the word "javaScript:" prefixed. The function
 *	   which is specified behind the colon and which has to be in the global scope
 *	   will be executed.
 *	2. _value may be a boolean, which specifies whether the external onExecute handler
 *	   (passed as "_handler" in the constructor) will be used.
 *	3. _value may be a JS functino which will then be called.
 * In all possible situation, the called function will get the following parameters:
 * 	1. A reference to this action
 * 	2. The senders, an array of all objects (JS)/object ids (PHP) which evoked the event
 */ 
egwAction.prototype.set_onExecute = function(_value)
{
	//Reset the onExecute handlers
	this.execJSFnct = null;
	this.execHandler = false;

	if (typeof _value == "string")
	{
		// Check whether the given string contains a javaScript function which
		// should be called upon executing the action
		if (_value.substr(0, 11) == "javaScript:")
		{
			//Check whether the given function exists
			var fnct = _value.substr(11);
			if (typeof window[fnct] == "function")
			{
				this.execJSFnct = window[fnct];
			}
		}
	}
	else if (typeof _value == "boolean")
	{
		// There is no direct reference to the PHP code which should be executed,
		// as the PHP code has knowledge about this.
		this.execHandler = _value;
	}
	else if (typeof _value == "function")
	{
		//The JS function has been passed directly
		this.execJSFnct = _value;
	}
}

egwAction.prototype.set_caption = function(_value)
{
	this.caption = _value;
}

egwAction.prototype.set_iconUrl = function(_value)
{
	this.iconUrl = _value;
}

egwAction.prototype.set_allowOnMultiple = function(_value)
{
	this.allowOnMultiple = _value;
}

egwAction.prototype.updateAction = function(_data)
{
	egwActionStoreJSON(_data, this, true);
}



/** egwActionManager Object **/

/**
 * egwActionManager manages a list of actions, provides functions to add new
 * actions or to update them via JSON.
 */
function egwActionManager(_handler)
{
	//Preset the handler parameter to null
	if (typeof _handler == "undefined")
		_handler = null;

	this.handler = _handler;
	this.actions = [];
}

egwActionManager.prototype.addAction = function(_type, _id, _caption, _iconUrl,
	_onExecute, _allowOnMultiple)
{
	//Get the constructor for the given action type
	if (!_type)
		_type = "default";
	var constructor = _egwActionClasses[_type].actionConstructor;

	if (typeof constructor == "function")
	{
		var action = new constructor(_id, this.handler, _caption, _iconUrl, _onExecute,
			_allowOnMultiple);
		this.actions.push[action];

		return action;
	}
	else
		throw "Given action type not registered.";
}

egwActionManager.prototype.updateActions = function(_actions)
{
	for (var i = 0 ; i < _actions.length; i++)
	{
		//Check whether the given action is already part of this action manager instance
		var elem = _actions[i];
		if (typeof elem == "object" && typeof elem.id == "string" && elem.id)
		{
			//Check whether the action already exists, and if no, add it to the
			//actions list
			var action = this.getAction(elem.id);
			if (!action)
			{
				if (typeof elem.type == "undefined")
					elem.type = "default";

				var constructor = null;

				if (typeof _egwActionClasses[elem.type] != "undefined")
					constructor = _egwActionClasses[elem.type].actionConstructor;

				if (typeof constructor == "function" && constructor)
					action = new constructor(elem.id, this.handler);
				else
					throw "Given action type \"" + elem.type + "\" not registered.";

				this.actions.push(action);
			}

			action.updateAction(elem);
		}
	}
}

egwActionManager.prototype.getAction = function(_id)
{
	for (var i = 0; i < this.actions.length; i++)
	{
		if (this.actions[i].id == _id)
			return this.actions[i];
	}

	return null;
}


/** egwActionImplementation Interface **/

/**
 * Abstract interface for the egwActionImplementation object. The egwActionImplementation
 * object is responsible for inserting the actual action representation (context menu,
 * drag-drop code) into the DOM Tree by using the egwActionObjectInterface object
 * supplied by the object.
 * To write a "class" which derives from this object, simply write a own constructor,
 * which replaces "this" with a "new egwActionImplementation" and implement your
 * code in "doRegisterAction" und "doUnregisterAction".
 * Register your own implementation within the _egwActionClasses object.
 */
function egwActionImplementation()
{
	this.doRegisterAction = function() {throw "Abstract function call: registerAction"};
	this.doUnregisterAction = function() {throw "Abstract function call: unregisterAction"};
	this.doExecuteImplementation = function() {throw "Abstract function call: executeImplementation"};
	this.type = "";
}

/**
 * Injects the implementation code into the DOM tree by using the supplied 
 * actionObjectInterface.
 *
 * @param object _actionObjectInterface is the AOI in which the implementation
 * 	should be registered.
 * @param function _triggerCallback is the callback function which will be triggered
 * 	when the user triggeres this action implementatino (e.g. starts a drag-drop or
 * 	right-clicks on an object.)
 * @param object context in which the triggerCallback should get executed.
 * @returns true if the Action had been successfully registered, false if it
 * 	had not.
 */
egwActionImplementation.prototype.registerAction = function(_actionObjectInterface, _triggerCallback, _context)
{
	if (typeof _context == "undefined")
		_context = null;

	return this.doRegisterAction(_actionObjectInterface, _triggerCallback, _context);
}

/**
 * Unregister action will be called before an actionObjectInterface is destroyed,
 * which gives the egwActionImplementation the opportunity to remove the previously
 * injected code.
 *
 * @returns true if the Action had been successfully unregistered, false if it
 * 	had not.
 */
egwActionImplementation.prototype.unregisterAction = function(_actionObjectInterface)
{
	return this.doUnregisterAction(_actionObjectInterface);
}

egwActionImplementation.prototype.executeImplementation = function(_context, _selected, _links)
{
	return this.doExecuteImplementation(_context, _selected, _links);
}


/** egwActionLink Object **/

/**
 * The egwActionLink is used to interconnect egwActionObjects and egwActions.
 * This gives each action object the possibility to decide, whether the action
 * should be active in this context or not.
 *
 * @param _manager is a reference to the egwActionManager whic contains the action
 * 	the object wants to link to.
 */
function egwActionLink(_manager)
{
	this.enabled = true;
	this.visible = true;
	this.actionId = "";
	this.actionObj = null;
	this.manager = _manager;
}

egwActionLink.prototype.updateLink = function (_data)
{
	egwActionStoreJSON(_data, this, true);
}

egwActionLink.prototype.set_enabled = function(_value)
{
	this.enabled = _value;
}

egwActionLink.prototype.set_visible = function(_value)
{
	this.visible = _value;
}

egwActionLink.prototype.set_actionId = function(_value)
{
	this.actionId = _value;
	this.actionObj = this.manager.getAction(_value);

	if (!this.actionObj)
		throw "Given action object does not exist!"
}

/** egwActionObject Object **/

//State bitmask (only use powers of two for new states!)
var EGW_AO_STATE_NORMAL = 0x00;
var EGW_AO_STATE_SELECTED = 0x01;
var EGW_AO_STATE_FOCUSED = 0x02;

var EGW_AO_EVENT_DRAG_OVER_ENTER = 0x00;
var EGW_AO_EVENT_DRAG_OVER_LEAVE = 0x01;

// No shift key is pressed
var EGW_AO_SHIFT_STATE_NONE = 0x00;
// A shift key, which allows multiselection is pressed (usually CTRL on a PC keyboard)
var EGW_AO_SHIFT_STATE_MULTI = 0x01;
// A shift key is pressed, which forces blockwise selection (SHIFT on a PC keyboard)
var EGW_AO_SHIFT_STATE_BLOCK = 0x02;

// If this flag is set, this object will not be returned as "focused". If this
// flag is not applied to container objects, it may lead to some strange behaviour.
var EGW_AO_FLAG_IS_CONTAINER = 0x01;

/**
 * The egwActionObject represents an abstract object to which actions may be
 * applied. Communication with the DOM tree is established by using the
 * egwActionObjectInterface (AOI), which is passed in the constructor.
 * egwActionObjects are organized in a tree structure.
 *
 * @param string _id is the identifier of the object which
 * @param object _parent is the parent object in the hirachy. This may be set to NULL
 * @param object _iface is the egwActionObjectInterface which connects the object
 * 	to the outer world.
 * @param object _manager is the action manager this object is connected to
 * 	this object to the DOM tree. If the _manager isn't supplied, the parent manager
 * 	is taken.
 * @param int _flags a set of additional flags being applied to the object,
 * 	defaults to 0
 */
function egwActionObject(_id, _parent, _iface, _manager, _flags)
{
	//Preset some parameters
	if (typeof _manager == "undefined" && typeof _parent == "object" && _parent)
		_manager = _parent.manager;
	if (typeof _flags == "undefined")
		_flags = 0;

	this.id = _id;
	this.parent = _parent;
	this.children = [];
	this.actionLinks = [];
	this.manager = _manager;
	this.flags = _flags;

	this.iface = _iface;
	this.iface.setStateChangeCallback(this._ifaceCallback, this)
}

/**
 * Returns the object from the tree with the given ID
 */
//TODO: Add "ByID"-Suffix to all other of those functions.
//TODO: Add search function to egw_action_commons.js
egwActionObject.prototype.getObjectById = function(_id)
{
	if (this.id == _id)
	{
		return this;
	}

	for (var i = 0; i < this.children.length; i++)
	{
		var obj = this.children[i].getObjectById(_id);
		if (obj)
		{
			return obj;
		}
	}

	return null;
}

/**
 * Adds an object as child to the actionObject and returns it - if the supplied
 * parameter is a object, the object will be added directly, otherwise an object
 * with the given id will be created.
 *
 * @param string/object _id Id of the object which will be created or the object
 * 	that will be added.
 * @param object if _id was an string, _interface defines the interface which
 * 	will be connected to the newly generated object.
 * @param int _flags are the flags will which be supplied to the newly generated
 * 	object. May be omitted.
 * @returns object the generated object
 */
egwActionObject.prototype.addObject = function(_id, _interface, _flags)
{
	return this.insertObject(false, _id, _interface, _flags);
}

/**
 * Inserts an object as child to the actionObject and returns it - if the supplied
 * parameter is a object, the object will be added directly, otherwise an object
 * with the given id will be created.
 *
 * @param int _index Position where the object will be inserted, "false" will add it
 * 	to the end of the list.
 * @param string/object _id Id of the object which will be created or the object
 * 	that will be added.
 * @param object _iface if _id was an string, _iface defines the interface which
 * 	will be connected to the newly generated object.
 * @param int _flags are the flags will which be supplied to the newly generated
 * 	object. May be omitted.
 * @returns object the generated object
 */
egwActionObject.prototype.insertObject = function(_index, _id, _iface, _flags)
{
	if (_index === false)
		_index = this.children.length;

	var obj = null;

	if (typeof _id == "object")
	{
		obj = _id;

		// Set the parent to null and reset the focus of the object
		obj.parent = null;
		obj.setFocused(false);

		// Set the parent to this object
		obj.parent = this;
	}
	else if (typeof _id == "string")
	{
		obj = new egwActionObject(_id, this, _iface, this.manager, _flags)
	}

	if (obj)
	{
		// Add the element to the children
		this.children.splice(_index, 0, obj);
	}
	else
	{
		throw "Error while adding new element to the ActionObjects!"
	}

	return obj;
}

/**
 * Searches for the root object in the action object tree and returns it.
 */
egwActionObject.prototype.getRootObject = function()
{
	if (this.parent === null)
	{
		return this;
	}
	else
	{
		return this.parent.getRootObject();
	}
}

/**
 * Returns a list with all parents of this object.
 */
egwActionObject.prototype.getParentList = function()
{
	if (this.parent === null)
	{
		return [];
	}
	else
	{
		var list = this.parent.getParentList();
		list.unshift(this.parent);
		return list;
	}
}

/**
 * Returns the first parent which has the container flag
 */
egwActionObject.prototype.getContainerRoot = function()
{
	if (egwBitIsSet(this.flags, EGW_AO_FLAG_IS_CONTAINER) || this.parent === null)
	{
		return this;
	}
	else
	{
		return this.parent.getContainerRoot();
	}
}

/**
 * Returns all selected objects which are in the current container.
 */
egwActionObject.prototype.getSelectedObjects = function(_test)
{
	if (typeof _test == "undefined")
		_test = null;

	var result = [];
	var list = this.getContainerRoot().flatList();
	for (var i = 0; i < list.length; i++)
	{
		if (list[i].getSelected() && (!_test || _test(list[i])))
		{
			result.push(list[i]);
		}
	}

	return result;
}

/**
 * Creates a list which contains all items of the element tree.
 *
 * @param object _obj is used internally to pass references to the array inside
 * 	the object.
 */
egwActionObject.prototype.flatList = function(_obj)
{
	if (typeof(_obj) == "undefined")
	{
		_obj = {
			"elements": []
		}
	}

	_obj.elements.push(this);

	for (var i = 0; i < this.children.length; i++)
	{
		this.children[i].flatList(_obj);
	}

	return _obj.elements;
}

/**
 * Returns a traversal list with all objects which are in between the given object
 * and this one. The operation returns an empty list, if a container object is
 * found on the way.
 */
egwActionObject.prototype.traversePath = function(_to)
{
	var contRoot = this.getContainerRoot();

	if (contRoot)
	{
		// Get a flat list of all the hncp elements and search for this object
		// and the object supplied in the _to parameter.
		var flatList = contRoot.flatList();
		var thisId = flatList.indexOf(this);
		var toId = flatList.indexOf(_to);

		// Check whether both elements have been found in this part of the tree,
		// return the slice of that list.
		if (thisId !== -1 && toId !== -1)
		{
			var from = Math.min(thisId, toId);
			var to = Math.max(thisId, toId);

			return flatList.slice(from, to + 1);
		}
	}

	return [];
}

/**
 * Returns the index of this object in the children list of the parent object.
 */
egwActionObject.prototype.getIndex = function()
{
	if (this.parent === null)
	{
		return 0;
	}
	else
	{
		return this.parent.children.indexOf(this);
	}
}

/**
 * Returns the deepest object which is currently focused. Objects with the
 * "container"-flag will not be returned.
 */
egwActionObject.prototype.getFocusedObject = function()
{
	//Search for the focused object in the children
	for (var i = 0; i < this.children.length; i++)
	{
		var obj = this.children[i].getFocusedObject()
		if (obj)
		{
			return obj;
		}
	}

	//One of the child objects hasn't been focused, probably this object is
	if (!egwBitIsSet(this.flags, EGW_AO_FLAG_IS_CONTAINER) && this.getFocused())
	{
		return this;
	}

	return null;
}

/**
 * Internal function which is connected to the ActionObjectInterface associated
 * with this object in the constructor. It gets called, whenever the object
 * gets (de)selected.
 *
 * @param int _newState is the new state of the object
 * @param int _shiftState is the status of extra keys being pressed during the
 * 	selection process.
 */
egwActionObject.prototype._ifaceCallback = function(_newState, _shiftState)
{
	if (typeof _shiftState == "undefined")
		_shiftState = EGW_AO_SHIFT_STATE_NONE;
	// Remove the focus from all children on the same level
	if (this.parent)
	{
		var selected = egwBitIsSet(_newState, EGW_AO_STATE_SELECTED);
		var objs = [];

		if (selected)
		{
			// Search the index of this object
			var id = this.parent.children.indexOf(this);

			// Deselect all other objects inside this container, if the "MULTI" shift-
			// state is not set
			if (!egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_MULTI))
			{
				var lst = this.getContainerRoot().flatList();
				for (var i = 0; i < lst.length; i++)
				{
					if (lst[i] != this)
					{
						lst[i].setSelected(false);
					}
				}
			}

			// If the LIST state is active, get all objects inbetween this one and the focused one
			// and set their select state.
			if (egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_BLOCK))
			{
				var focused = this.getRootObject().getFocusedObject();
				if (focused)
				{
					objs = this.traversePath(focused);
					for (var i = 0; i < objs.length; i++)
					{
						objs[i].setSelected(true);
					}
				}
			}
		}

		// If the focused element didn't belong to this container, or the "list"
		// shift-state isn't active, set the focus to this element.
		if (objs.length == 0 || !egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_BLOCK))
		{
			this.setFocused(true);
		}
	}
}

/**
 * Returns whether the object is currently selected.
 */
egwActionObject.prototype.getSelected = function()
{
	return egwBitIsSet(this.getState(), EGW_AO_STATE_SELECTED);
}

/**
 * Returns whether the object is currently focused.
 */
egwActionObject.prototype.getFocused = function()
{
	return egwBitIsSet(this.getState(), EGW_AO_STATE_FOCUSED);
}

/**
 * Returns the complete state of the object.
 */
egwActionObject.prototype.getState = function()
{
	return this.iface.getState();
}


/**
 * Sets the focus of the element. The formerly focused element in the tree will
 * be de-focused.
 *
 * @param boolean _focused - whether to remove or set the focus. Defaults to true
 * @param object _recPrev is internally used to prevent infinit recursion. Do not touch.
 */
egwActionObject.prototype.setFocused = function(_focused, _recPrev)
{
	if (typeof _focused == "undefined")
		_focused = true;

	//TODO: When deleting and moving objects is implemented, don't forget to update
	//	the selection and the focused element!!

	if (typeof _recPrev == "undefined")
		_recPrev = false;

	//Check whether the focused state has changed
	if (_focused != this.getFocused())
	{
		//Reset the focus of the formerly focused element
		if (!_recPrev)
		{
			var focused = this.getRootObject().getFocusedObject();
			if (focused)
			{
				focused.setFocused(false, this);
			}
		}

		if (!_focused)
		{
			//If the object is not focused, reset the focus state of all children
			for (var i = 0; i < this.children.length; i++)
			{
				if (this.children[i] != _recPrev)
				{
					this.children[i].setFocused(false, _recPrev);
				}
			}
		}
		else
		{
			//Otherwise set the focused state of the parent to true
			if (this.parent)
			{
				this.parent.setFocused(true, _recPrev);
			}
		}

		//No perform the actual change in the interface state.
		this.iface.setState(egwSetBit(this.iface.getState(), EGW_AO_STATE_FOCUSED,
			_focused));
	}
}

/**
 * Sets the selected state of the element.
 * TODO: Callback
 */
egwActionObject.prototype.setSelected = function(_selected)
{
	this.iface.setState(egwSetBit(this.iface.getState(), EGW_AO_STATE_SELECTED,
		_selected));
}

/**
 * Updates the actionLinks of the given ActionObject.
 *
 * @param array _actionLinks contains the information about the actionLinks which
 * 	should be updated as an array of objects. Example
 * 	[
 * 		{
 * 			"actionId": "file_delete",
 * 			"enabled": true
 * 		}
 * 	]
 * 	If an supplied link doesn't exist yet, it will be created (if _doCreate is true)
 * 	and added to the list. Otherwise the information will just be updated.
 * @param boolean _recursive If true, the settings will be applied to all child
 * 	object (default false)
 * @param boolean _doCreate If true, not yet existing links will be created (default true)
 */
egwActionObject.prototype.updateActionLinks = function(_actionLinks, _recursive, _doCreate)
{
	if (typeof _recursive == "undefined")
		_recursive = false;
	if (typeof _doCreate == "undefined")
		_doCreate = true;

	for (var i = 0; i < _actionLinks.length; i++)
	{
		var elem = _actionLinks[i];
		if (typeof elem.actionId != "undefined" && elem.actionId)
		{
			//Get the action link object, if it doesn't exist yet, create it
			var actionLink = this.getActionLink(elem.actionId);
			if (!actionLink && _doCreate)
			{
				actionLink = new egwActionLink(this.manager);
				this.actionLinks.push(actionLink);
			}

			//Set the supplied data
			if (actionLink)
			{
				actionLink.updateLink(elem);
			}
		}
	}

	if (_recursive)
	{
		for (var i = 0; i < this.children.length; i++)
		{
			this.children[i].updateActionLinks(_actionLinks, true, _doCreate);
		}
	}
}

egwActionObject.prototype.registerActions = function()
{
	var groups = this.getActionImplementationGroups();

	for (group in groups)
	{
		// Get the action implementation for each group
		if (typeof _egwActionClasses[group] != "undefined" &&
		    _egwActionClasses[group].implementation &&
		    this.iface)
		{
			var impl = _egwActionClasses[group].implementation();

			// Register a handler for that action with the interface of that object,
			// the callback and this object as context for the callback
			impl.registerAction(this.iface, this.executeActionImplementation, this);
		}
	}
}

egwActionObject.prototype.triggerCallback = function()
{
	if (this.onBeforeTrigger)
	{
		return this.onBeforeTrigger();
	}
	return true;
}

egwActionObject.prototype.executeActionImplementation = function(_implContext, _implType)
{
	if (typeof _implType == "string")
		_implType = _egwActionClasses[_implType].implementation();

	if (typeof _implType == "object" && _implType)
	{
		var selectedActions = this.getSelectedLinks(_implType.type, true);
		if (selectedActions.selected.length > 0 && egwObjectLength(selectedActions.links) > 0)
		{
			_implType.executeImplementation(_implContext,
				selectedActions.selected, selectedActions.links);
		}
	}
}

/**
 * Returns all selected objects, which returned true in their triggerCallback and
 * all action links of those objects, which are of the given implementation type,
 * wheras actionLink properties such as "enabled" and "visible" are accumuleted.
 */
egwActionObject.prototype.getSelectedLinks = function(_implType, _includeThis)
{
	// Get all objects in this container which are currently selected
	var selected = this.getSelectedObjects();

	if (_includeThis)
	{
		var thisInList = false;
		for (var i = 0; i < selected.length; i++)
		{
			if (selected[i] == this)
			{
				thisInList = true;
				break;
			}
		}

		if (!thisInList)
		{
			this.setSelected(true);
			this._ifaceCallback(egwSetBit(this.getState(), EGW_AO_STATE_SELECTED, true),
				EGW_AO_SHIFT_STATE_NONE);
			selected = [this];
		}
	}

	var actionLinks = {};
	var testedSelected = [];
	for (var i = 0; i < selected.length; i++)
	{
		var obj = selected[i];
		if (obj.triggerCallback())
		{
			testedSelected.push(obj);

			for (var j = 0; j < obj.actionLinks.length; j++)
			{
				var olink = obj.actionLinks[j]; //object link

				// Test whether the action type is of the given implementation type
				if (olink.actionObj.type == _implType)
				{
					if (typeof actionLinks[olink.actionId] == "undefined")
					{
						actionLinks[olink.actionId] = {
							"actionObj": olink.actionObj,
							"enabled": i == 0 && (olink.actionObj.allowOnMultiple || selected.length == 1),
							"visible": false,
							"cnt": 0
						}
					}

					var llink = actionLinks[olink.actionId];
					llink.enabled = llink.enabled && olink.enabled && olink.visible;
					llink.visible = llink.visible || olink.visible;
					llink.cnt++;
				}
			}
		}
	}

	for (k in actionLinks)
	{
		actionLinks[k].enabled = actionLinks[k].enabled && (actionLinks[k].cnt >= selected.length);
	}


	// Return an object which contains the accumulated actionLinks and all selected
	// objects.
	return {
		"selected": testedSelected,
		"links": actionLinks
	}
}

/**
 * Returns the action link, which contains the association to the action with
 * the given actionId.
 *
 * @param string _actionId name of the action associated to the link
 */
egwActionObject.prototype.getActionLink = function(_actionId)
{
	for (var i = 0; i < this.actionLinks.length; i++)
	{
		if (this.actionLinks[i].actionObj.id == _actionId)
		{
			return this.actionLinks[i];
		}
	}

	return null;
}

/**
 * Returns all actions associated to the object tree, grouped by type.
 *
 * @param function _test gets an egwActionObject and should return, whether the
 * 	actions of this object are added to the result. Defaults to a "always true"
 * 	function.
 * @param object _groups is an internally used parameter, may be omitted.
 */
egwActionObject.prototype.getActionImplementationGroups = function(_test, _groups)
{
	// If the _groups parameter hasn't been given preset it to an empty object
	// (associative array).
	if (typeof _groups == "undefined")
		_groups = {};
	if (typeof _test == "undefined")
		_test = function(_obj) {return true};

	for (var i = 0; i < this.actionLinks.length; i++)
	{
		var action = this.actionLinks[i].actionObj;
		if (typeof action != "undefined" && _test(this))
		{
			if (typeof _groups[action.type] == "undefined")
			{
				_groups[action.type] = [];
			}

			_groups[action.type].push(
				{
					"object": this,
					"link": this.actionLinks[i]
				}
			);
		}
	}

	// Recursively add the actions of the children to the result (as _groups is
	// an object, only the reference is passed).
	for (var i = 0; i < this.children.length; i++)
	{
		this.children[i].getActionImplementationGroups(_test, _groups);
	}

	return _groups;
}


/** egwActionObjectInterface Interface **/

/**
 * The egwActionObjectInterface has to be implemented for each actual object in 
 * the browser. E.g. for the object "DataGridRow", there has to be an
 * egwActionObjectInterface which is responsible for returning the outer DOMNode
 * of the object to which JS-Events may be attached by the egwActionImplementation
 * object, and to do object specific stuff like highlighting the object in the
 * correct way and to route state changes (like: "object has been selected")
 * to the egwActionObject object the interface is associated to.
 */
function egwActionObjectInterface()
{
	//Preset the interface functions

	this.doGetDOMNode = function() {return null};

	// _outerCall may be used to determine, whether the state change has been
	// evoked from the outside and the stateChangeCallback has to be called
	// or not.
	this.doSetState = function(_state, _outerCall) {};

	this.doTriggerEvent = function(_event) {};

	this._state = EGW_AO_STATE_NORMAL;
}

/**
 * Sets the callback function which will be called when a user interaction changes
 * state of the object.
 */
egwActionObjectInterface.prototype.setStateChangeCallback = function(_callback, _context)
{
	this.stateChangeCallback = _callback;
	this.stateChangeContext = _context;
}

/**
 * Internal function which should be used whenever the select status of the object
 * has been changed by the user. This will automatically calculate the new state of
 * the object and call the stateChangeCallback (if it has been set)
 *
 * @param boolean _selected Whether the object is selected or not.
 * @param int _shiftState special keys which change how the state change will
 * 	be treated.
 */
egwActionObjectInterface.prototype._selectChange = function(_selected, _shiftState)
{
	//Set the EGW_AO_STATE_SELECTED bit accordingly and call the callback
	this._state = egwSetBit(this._state, EGW_AO_STATE_SELECTED, _selected);
	if (this.stateChangeCallback)
	{
		this.stateChangeCallback.call(this.stateChangeContext,
			this._state, _shiftState);
	}
}

/**
 * Returns the DOM-Node the ActionObject is actually a representation of.
 * Calls the internal "doGetDOMNode" function, which has to be overwritten
 * by implementations of this class.
 */
egwActionObjectInterface.prototype.getDOMNode = function()
{
	return this.doGetDOMNode();
}

/**
 * Sets the state of the object.
 * Calls the internal "doSetState" function, which has to be overwritten
 * by implementations of this class. The state-change callback must not be evoked!
 *
 * @param _state is the state of the object.
 */
egwActionObjectInterface.prototype.setState = function(_state)
{
	//Call the doSetState function with the new state (if it has changed at all)
	if (_state != this._state)
	{
		this._state = _state;
		this.doSetState(_state, true);
	}
}

/**
 * Returns the current state of the object. The state is maintained by the 
 * egwActionObjectInterface and implementations do not have to overwrite this
 * function as long as they call the _selectChange function.
 */
egwActionObjectInterface.prototype.getState = function()
{
	return this._state;
}


/** egwActionObjectManager Object **/

/**
 * The egwActionObjectManager is a dummy class which only contains a dummy
 * AOI. It may be used as root object or as object containers.
 */
function egwActionObjectManager(_id, _manager)
{
	return new egwActionObject(_id, null, new egwActionObjectInterface(),
		_manager, EGW_AO_FLAG_IS_CONTAINER);
}

