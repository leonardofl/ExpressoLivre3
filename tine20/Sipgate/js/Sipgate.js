/**
 * Tine 2.0
 * 
 * @package Sipgate
 * @license http://www.gnu.org/licenses/agpl.html AGPL3
 * @author Alexander Stintzing <alex@stintzing.net>
 * @copyright Copyright (c) 2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version $Id: Sipgate.js 24 2011-05-02 02:47:52Z alex $
 * 
 */

Ext.ns('Tine.Sipgate');

var CallUpdateWindowTask;
var CallingState = false;

Tine.Sipgate.getPanel = function() {

	var translation = new Locale.Gettext();
	translation.textdomain('Sipgate');
		
	Tine.log.debug('NOW in getPanel');

	new Tine.Sipgate.AddressbookGridPanelHook({
				app : {
					i18n : translation
				}
			});

	var editSipgateSettingsAction = new Ext.Action({
				text : translation._('Edit phone settings'),
				iconCls : 'SipgateIconCls',
				handler : function() {
					
				},
				scope : this
			});

	var contextMenu = new Ext.menu.Menu({
				items : [editSipgateSettingsAction]
			});
	/** ********* tree panel **************** */

	var treePanel = new Ext.tree.TreePanel({
				title : translation.gettext('Sipgate'),
				id : 'sipgate-tree',
				iconCls : 'SipgateIconCls',
				rootVisible : true,
				border : false,
				collapsible : true
			});

	/** ********* root node **************** */

	var treeRoot = new Ext.tree.TreeNode({
				text : translation._('Devices'),
				cls : 'treemain',
				allowDrag : false,
				allowDrop : false,
				id : 'root',
				icon : false
			});
	treePanel.setRootNode(treeRoot);

	Tine.Sipgate.loadSipgateStore();

	/** ****** tree panel handlers ********** */

	treePanel.on('click', function(node, event) {
		Tine.log.debug('NOW in treepanel ONCLICK');
				node.select();
				Tine.Sipgate.Main.show(node);
			}, this);

	treePanel.on('contextmenu', function(node, event) {
		Tine.log.debug('NOW in treepanel ONCONTEXTMENU');
				this.ctxNode = node;
				if (node.id != 'root') {
					contextMenu.showAt(event.getXY());
				}
			}, this);

	treePanel.on('beforeexpand', function(panel) {
Tine.log.debug('NOW in treepanel ONBEFOREEXPAND');


				if (panel.getSelectionModel().getSelectedNode() === null) {
					var node = panel.getRootNode();
					node.select();
					node.expand();
					Tine.Sipgate.Main.show(node);
				} else {
					panel.getSelectionModel().fireEvent('selectionchange', panel.getSelectionModel());
				}
			}, this);

	treePanel.getSelectionModel().on('selectionchange',
	


	
			function(_selectionModel) {
//					Tine.Sipgate.Main.displayToolbar();
				Tine.log.debug('NOW in treepanel ONSELECTIONCHANGE');
				var node = _selectionModel.getSelectedNode();

				// update toolbar
				var settingsButton = Ext.getCmp('phone-settings-button');
				if (settingsButton) {
					if (node && node.id != 'root') {
						settingsButton.setDisabled(false);
					} else {
						settingsButton.setDisabled(true);
					}
				}

		}, this);

	return treePanel;
};

/** ************************** main *************************************** */
/**
 * sipgate main view
 * 
 * @todo show phone calls
 */
Tine.Sipgate.Main = {
	/**
	 * translations object
	 */
	translation : null,

	/**
	 * holds underlaying store
	 */
	store : null,

	/**
	 * @cfg {Object} paging defaults
	 */
	paging : {
		start : 0,
		stop : 10,
		sort : 'Timestamp',
		dir : 'DESC'
	},

	/**
	 * action buttons
	 */
	actions: {
		dialNumber: null,
		//editSipgateSettings: null,
		addNumber: null
	},

	/**
	 * init component function
	 */
	initComponent : function() {
		this.translation = new Locale.Gettext();
		this.translation.textdomain('Sipgate');
		Tine.log.debug('NOW in initComponent');

		this.actions.dialNumber = new Ext.Action({
					text: this.translation._('Dial number'),
					tooltip: this.translation._('Initiates a new outgoing call'),
					handler: this.handlers.dialNumber,
					iconCls: 'action_DialNumber',
					scope: this
				});

		this.actions.addNumber = new Ext.Action({
			text: this.translation._('Add Number to Addressbook'),
			tooltip: this.translation._('Adds this number to the Addressbook'),
			handler: this.handlers.addNumber,
			iconCls: 'action_AddNumber',
			scope: this
		});

		this.initStore();
	},

	handlers : {
		addNumber : function(_button, _event) {
			var number = '';
			var grid = Ext.getCmp('Sipgate_Callhistory_Grid');
			if (grid) {
				record = grid.getSelectionModel().getSelected();
				if (record && record.id != 'root') {
					sipId = record.data.RemoteNumber;
				}
			}
			Tine.Sipgate.addPhoneNumber(sipId);
		},
		dialNumber : function(_button, _event) {
			var number = '';
			var grid = Ext.getCmp('Sipgate_Callhistory_Grid');
			if (grid) {
				record = grid.getSelectionModel().getSelected();
				if (record && record.id != 'root') {
					sipId = record.data.RemoteNumber;
				}
			}
			Tine.Sipgate.dialPhoneNumber(sipId);
		}
	},

	displayToolbar : function() {

		var toolbar = new Ext.Toolbar({
					id : 'Sipgate_Toolbar',
					items : [{
						xtype : 'buttongroup',
						columns : 1,
						items : [Ext.apply(
								new Ext.Button(this.actions.dialNumber), {
									scale : 'medium',
									rowspan : 2,
									iconAlign : 'top'
								})]
					}, '->']
				});
Tine.log.debug('NOW display toolbar');
		Tine.Tinebase.MainScreen.setActiveToolbar(toolbar);
	},

	/**
	 * init the calls json grid store
	 * 
	 * @todo add more filters (phone, line, ...)
	 * @todo use new filter toolbar later
	 */
	initStore : function() {
		Tine.log.debug('NOW in initStore');

		this.store = new Ext.data.JsonStore({
					id : 'EntryID',
					autoLoad : false,
					// root : 'results',
					//totalProperty : 'totalcount',
					fields : ['EntryID', 'Timestamp', 'RemoteUri', 'LocalUri', 'Status', 'RemoteParty', 'RemoteRecord',	'RemoteNumber'],
					remoteSort : false,
					baseParams : { method : 'Sipgate.getCallHistory' },
					sortInfo : {
						field : this.paging.sort,
						direction : this.paging.dir
					}
				});

		// register store
		Ext.StoreMgr.add('SipgateCallHistoryStore', this.store);

		// prepare filter getCallHistory
		this.store.on('beforeload', function(store, options) {
					
					if (!options.params) {
						options.params = {};
					}
					
					var node = Ext.getCmp('sipgate-tree').getSelectionModel().getSelectedNode()	|| null;
					
					this.store.setBaseParam('_sipUri', node.id);
					this.store.setBaseParam('_start', new Date(Ext.getCmp('startdt').getValue()));
					this.store.setBaseParam('_stop', new Date(Ext.getCmp('enddt').getValue()));
					
				}, this);
	},

	
	
	/**
	 * display the callhistory grid
	 * 
	 */
	displayGrid : function() {

		Tine.log.debug('NOW in displayGrid');
		
		var fromdate = new Ext.form.DateField({
					format : 'D, d. M. Y',
					value: new Date(new Date().getTime() - (24 * 60 * 60 * 1000)),
					fieldLabel : this.translation._('Calls from'),
					id : 'startdt',
					name : 'startdt',
					width : 140,
					allowBlank : false,
					endDateField : 'enddt'
				});
		
		var todate = new Ext.form.DateField({
					
					format : 'D, d. M. Y',
					fieldLabel : this.translation._('Calls to'),
					
					value: new Date(),
					maxValue: new Date(),
					id : 'enddt',
					name : 'enddt',
					width : 140,
					allowBlank : false,
					startDateField : 'startdt'
				});

		var pagingToolbar = new Ext.PagingToolbar({
			
					pageSize : 10000,
					store : this.store,
					displayInfo : true,
					displayMsg : this.translation._('Displaying calls {0} - {1} of {2}'),
					emptyMsg : this.translation._("No calls to display")
				});

		var combinedToolbar = new Ext.Toolbar({items: [ fromdate, todate, pagingToolbar ]});


		
		// the columnmodel
		var columnModel = new Ext.grid.ColumnModel({
					defaults : {
						sortable : true,
						resizable : true
					},
					columns : [{
						id : 'Status',
						header : this.translation._('Status'),
						dataIndex : 'Status',
						width : 20
							// renderer : this.renderer.direction
						}, {
						id : 'RemoteParty',
						header : this.translation._('Remote Party'),
						dataIndex : 'RemoteParty',
						hidden : false
					}, {
						id : 'RemoteNumber',
						header : this.translation._('Remote Number'),
						dataIndex : 'RemoteNumber',
						hidden : true
					}, {
						id : 'LocalUri',
						header : this.translation._('Local Uri'),
						dataIndex : 'LocalUri',
						hidden : true
					}, {
						id : 'RemoteUri',
						header : this.translation._('Remote Uri'),
						dataIndex : 'RemoteUri',
						hidden : true
							// renderer :
							// this.renderer.destination'RemoteParty','RemoteRecord','RemoteNumber'
						}, {
						id : 'Timestamp',
						header : this.translation._('Call started'),
						dataIndex : 'Timestamp'

							// renderer : Tine.Tinebase.common.dateTimeRenderer
						}, {
						id : 'EntryID',
						header : this.translation._('Call ID'),
						dataIndex : 'EntryID',
						hidden : true
					}]
				});

		columnModel.defaultSortable = true; // by default columns are
		// sortable

		// the rowselection model
		var rowSelectionModel = new Ext.grid.RowSelectionModel({
					multiSelect : false
				});

		// the gridpanel
		var gridPanel = new Ext.grid.GridPanel({
					id : 'Sipgate_Callhistory_Grid',
					store : this.store,
					cm : columnModel,
					tbar : combinedToolbar,
					autoSizeColumns : true,
					selModel : rowSelectionModel,
					enableColLock : false,
					loadMask : true,
					autoExpandColumn : 'destination',
					border : false,
					view : new Ext.grid.GridView({
								autoFill : true,
								forceFit : true,
								ignoreAdd : true,
								emptyText : this.translation._('No calls to display')
							})

				});

		gridPanel.on('rowcontextmenu',
				function(_grid, _rowIndex, _eventObject) {
					_eventObject.stopEvent();

					if (!_grid.getSelectionModel().isSelected(_rowIndex)) {
						_grid.getSelectionModel().selectRow(_rowIndex);
					}

					var contextMenu = new Ext.menu.Menu({
								id : 'ctxMenuCall',
								items : [this.actions.dialNumber,
										this.actions.addNumber]
							});
					contextMenu.showAt(_eventObject.getXY());
				}, this);

		Tine.Tinebase.MainScreen.setActiveContentPanel(gridPanel);
	},

	show : function(_node) {

		Tine.log.debug('NOW IN show');
		
		var currentToolbar = Tine.Tinebase.MainScreen.getActiveToolbar();
		
		if (currentToolbar.id != 'Sipgate_Toolbar') {
			this.initComponent();
			this.displayToolbar();
			if (_node.id != 'root') {
				this.store.load({});
			}
			this.displayGrid();
		} else {
			if (_node.id != 'root') {
				this.store.load({});
			}
		}
	}

};

/** ************************** store *************************************** */

/**
 * get user phones store
 * 
 * @return Ext.data.JsonStore with phones
 */
Tine.Sipgate.loadSipgateStore = function() {
Tine.log.debug('NOW IN loadSipgateStore');
	var store = Ext.StoreMgr.get('SipgateStore');

	if (!store) {
		// create store (get from initial data)
		store = new Ext.data.JsonStore({
					fields : ['SipUri', 'UriAlias', 'type', 'E164Out',
							'DefaultUri'],
					data : Tine.Sipgate.registry.get('Devices'),
					autoLoad : true,
					id : 'SipUri'
				});
		Ext.StoreMgr.add('SipgateStore', store);
		Tine.Sipgate.updateSipgateTree(store);
	}
	return store;
};

/**
 * load phones
 */
Tine.Sipgate.updateSipgateTree = function(store) {
Tine.log.debug('NOW IN updateSipgateTree');

	var translation = new Locale.Gettext();
	translation.textdomain('Phone');

	// get tree root
	var treeRoot = Ext.getCmp('sipgate-tree').getRootNode();

	// remove all children first
	treeRoot.eachChild(function(child) {
				treeRoot.removeChild(child);
			});

	// add phones to tree menu
	store.each(function(record) {
				// Tine.log.debug(record);

				var node = new Ext.tree.TreeNode({
							id : record.id,
							record : record,
							text : record.data.SipUri,
							qtip : record.data.UriAlias,
							iconCls : 'SipgateTreeNode_' + record.data.type,
							leaf : true
						});
				treeRoot.appendChild(node);
			});
};

/**
 * @param string
 *            number
 */
Tine.Sipgate.dialPhoneNumber = function(number, contact) {

	var translation = new Locale.Gettext();
	translation.textdomain('Sipgate');

	if (!contact) {
		contact = {
			data : {
				n_fn : translation._('unknown Person')
			}
		};

	}

	var info = {
		name : contact.data.n_fn,
		number : number
	};
	
	var popUpWindow = Tine.Sipgate.CallStateWindow.openWindow({
				info : info
			});

	var initRequest = Ext.Ajax.request({
				url : 'index.php',

				params : {
					method : 'Sipgate.dialNumber',
					_number : number
				},

				success : function(_result, _request) {

					// Tine.log.debug(_result);

					sessionId = Ext.decode(_result.responseText).state.SessionID;
					Ext.getCmp('callstate-window').sessionId = sessionId;
					Tine.Sipgate.CallStateWindow.startTask(sessionId, contact);
					Ext.getCmp('call-cancel-button').enable();

				},
				failure : function(result, request) {
				}
			});
};

Tine.Sipgate.addPhoneNumber = function(number) {

	// check if addressbook app is available
	if (!Tine.Addressbook
			|| !Tine.Tinebase.common.hasRight('run', 'Addressbook')) {
		return;
	}

	var popupWindow = Tine.Sipgate.SearchAddressDialog.openWindow({ number:number });

	
};

Tine.Sipgate.closeSession = function(sessionId) {
	Tine.log.debug('Closing Session', sessionId);
	Ext.Ajax.request({
				url : 'index.php',

				params : {
					method : 'Sipgate.closeSession',
					sessionId : sessionId
				},
				success : function(_result, _request) {

				},
				failure : function(result, request) {

				}
			});
};

/**
 * @param string
 *            sessionId
 * @param {Object}
 *            contact
 */
Tine.Sipgate.updateCallStateWindow = function(sessionId, contact) {

	// Tine.log.debug('Update Call State: ' + sessionId);

	Ext.Ajax.request({
		url : 'index.php',

		params : {
			method : 'Sipgate.getSessionStatus',
			sessionId : sessionId
		},
		success : function(_result, _request) {
			result = Ext.decode(_result.responseText);

			try {
				if (uC = Ext.getCmp('csw-update-container')) {
					throw uC;
				} else
					throw false;
			} catch (e) {
				if (e != false) {
					try {
						if (result.StatusCode == 200) {
							throw result;
						} else {
							throw false;
						}
					} catch (e) {
						if (e != false) {

							// Statusmeldungen auswerten

							switch (result.SessionStatus) {
								case 'first dial' :
									Ext.getCmp('csw-call-info').update(Tine.Tinebase.appMgr.get('Sipgate').i18n._('first dial'));
									Ext.get('csw-my-phone').frame("ff0000", 1);
									CallingState = true;
									break;
								case 'second dial' :
									Ext.getCmp('csw-call-info').update(Tine.Tinebase.appMgr.get('Sipgate').i18n._('second dial') + ' ' + contact.data.n_fn);
									Ext.get('csw-my-phone').addClass('established');
									Ext.get('csw-other-phone').frame("ff0000",1);
									CallingState = '1';
									break;
								case 'established' :
									Ext.get('csw-other-phone').addClass('established');
									Ext.getCmp('csw-call-info').update(Tine.Tinebase.appMgr.get('Sipgate').i18n._('established') + ' ' + contact.data.n_fn);
									CallingState = '2';
									break;
								case ('call 1 busy' || 'call 1 failed') :
									Ext.get('csw-my-phone').addClass('error');
									Ext.getCmp('csw-call-info').update(Tine.Tinebase.appMgr.get('Sipgate').i18n._('call 1 busy'));
									CallingState = false;
									Tine.Sipgate.CallStateWindow.stopTask();
									break;
								case ('call 2 busy' || 'call 1 failed') :
									Ext.get('csw-other-phone').addClass('error');
									Ext.get('csw-my-phone').removeClass('established');
									Ext.getCmp('csw-call-info').update(contact.data.n_fn + ' ' + Tine.Tinebase.appMgr.get('Sipgate').i18n._('call 2 busy'));
									CallingState = false;
									Tine.Sipgate.CallStateWindow.stopTask();
									break;
								case 'hungup' :
									switch (CallingState) {
										case '1' :
											Ext.getCmp('csw-call-info').update(Tine.Tinebase.appMgr.get('Sipgate').i18n._('hungup before other called'));
											break;
										default :
											Ext.getCmp('csw-call-info').update(Tine.Tinebase.appMgr.get('Sipgate').i18n._('hungup'));
									}
									Ext.get('csw-my-phone').removeClass('established');
									Ext.get('csw-other-phone').removeClass('established');
									Tine.Sipgate.CallStateWindow.stopTask();
									CallingState = false;
									break;
								default :
									Ext.getCmp('csw-call-info').update(result.SessionStatus);
									Tine.Sipgate.CallStateWindow.stopTask();
									CallingState = false;
							}
						}
					}
				}
			}
		},
		failure : function(result, request) {

		}
	});

};

