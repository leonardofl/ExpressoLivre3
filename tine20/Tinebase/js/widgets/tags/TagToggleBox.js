/*
 * Tine 2.0
 * 
 * @license http://www.gnu.org/licenses/agpl.html AGPL Version 3 @author
 * Cornelius Weiss <c.weiss@metaways.de> @copyright Copyright (c) 2007-2009
 * Metaways Infosystems GmbH (http://www.metaways.de)
 * 
 * TODO use new filter syntax in onBeforeQuery when TagFilter is refactored and
 * extends Tinebase_Model_Filter_FilterGroup
 */

Ext.ns('Tine.widgets', 'Tine.widgets.tags');

/**
 * @namespace Tine.widgets.tags
 * @class Tine.widgets.tags.TagToggleBox
 * @extends Ext.tree.TreePanel
 */
Tine.widgets.tags.TagToggleBox = Ext.extend(Ext.form.FormPanel, {

	layout : 'fit',
	border : false,
	cls : 'tw-editdialog',
	store : null,
	buttonAlign : 'right',
    win: null,
    mode: null,
    
	anchor : '100% 100%',
    

	initComponent : function() {
        
		this.addEvents('cancel', 'update', 'close');
		this.initStore();
        this.initTemplate();
        this.initView();

		this.initActions();

		this.initButtons();

		this.items = this.getFormItems();

		Tine.widgets.tags.TagToggleBox.superclass.initComponent.call(this);
	},

	initActions : function() {
		this.action_update = new Ext.Action({
					text : (this.mode == 'detach') ? _('Detach Tags') : _('Attach Tags'),
					minWidth : 70,
					scope : this,
					handler : this.onUpdate,
					iconCls : 'action_saveAndClose'
				});
		this.action_cancel = new Ext.Action({
					text : _('Cancel'),
					minWidth : 70,
					scope : this,
					handler : this.onCancel,
					iconCls : 'action_cancel'
				});

	},

	initStore : function() {

		if (this.selectionModel) {
			var ids = [];

			Ext.each(this.selectionModel.getSelections(), function(el) {
						Ext.each(el.data.tags, function(tag) {
									ids.push(tag.id);
								});
					});

			var baseParams = {
				method : 'Tinebase.searchDistinctTags',
				records : ids
			}
		} else {
			var baseParams = {
				method : 'Tinebase.searchTags',
				paging : {}
			};
		}

		this.store = new Ext.data.JsonStore({
					id : 'id',
					root : 'results',
					totalProperty : 'totalCount',
					fields : Tine.Tinebase.Model.Tag,
					baseParams : baseParams
				});
		this.store.load();

	},

	initButtons : function() {
		this.fbar = ['->', this.action_cancel, this.action_update];
	},
    
	getFormItems : function() {
		return {
			border : false,
			frame : true,
			layout : 'form',
			items : [{
				region : 'center',
				layout : {
					align : 'stretch',
					type : 'vbox'
				}
			}, this.view]
		};
	},
    
    initTemplate: function() {
        this.tpl = new Ext.XTemplate(
            '<tpl for=".">', 
                '<div class="x-combo-list-item">',
                    '<input class="tagcheckel" type="checkbox" style="margin-right:3px;float:left" id="{values.id}" /><div class="tb-grid-tags" style="margin-top:2px;background-color:{values.color};">&#160;</div>',
                    '<div class="x-widget-tag-tagitem-text" style="margin-top:1px" ext:qtip="', 
                        '{[this.encode(values.name)]}', 
                        '<tpl if="type == \'personal\' ">&nbsp;<i>(' + _('personal') + ')</i></tpl>',
                        '</i>&nbsp;[{occurrence}]',
                        '<tpl if="description != null && description.length &gt; 1"><hr>{[this.encode(values.description)]}</tpl>" >',
                        
                        '&nbsp;{[this.encode(values.name)]}',
                        '<tpl if="type == \'personal\' ">&nbsp;<i>(' + _('personal') + ')</i></tpl>',
                    '</div>',
                '</div>', 
            '</tpl>',
            {
                encode: function(value) {
                     if (value) {
                        return Ext.util.Format.htmlEncode(value);
                    } else {
                        return '';
                    }
                }
            }
        );        
    },
    
    initView : function() {
        this.view = new Ext.DataView({
            anchor : '100% 100%',
            autoScroll : true,
            store : this.store,
            tpl : this.tpl,
            emptyText : _('No Tags to remove found in the selected records'),
            loadingText : _('Please Wait...'),
            style : 'background:#fff;'
        });
    },
    
    onCancel: function() {
        this.fireEvent('cancel');
    },
    
    onUpdate: function() {
        Ext.MessageBox.wait(_('Please wait'), _('Detaching selected tags'));
        var els = Ext.select('input.tagcheckel');
        var checked = [];
        els.each(function(el){
            if(el.dom.checked) checked.push(el.id); 
        });
        
        var filter = this.selectionModel.getSelectionFilter();
        var filterModel = this.recordClass.getMeta('appName') + '_Model_' +  this.recordClass.getMeta('modelName') + 'Filter';
        
        Tine.Tinebase.detachTagsFromMultipleRecords(filter, filterModel, checked, this.onUpdated.createDelegate(this));
        Ext.MessageBox.hide();

    },
    
    onUpdated: function() {
        this.fireEvent('updated');
    }
    

});
