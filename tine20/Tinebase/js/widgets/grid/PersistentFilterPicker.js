/*
 * Tine 2.0
 * 
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id$
 *
 */
Ext.namespace('Tine.widgets', 'Tine.widgets.grid');

Tine.widgets.grid.PersistentFilterPicker = Ext.extend(Ext.tree.TreePanel, {
    
    /**
     * @cfg {application}
     */
    app: null,
    /**
     * @cfg {String} filterMountId
     * mount point of persitant filter folder
     */
    filterMountId: '/',
    
    /**
     * @private
     */
    autoScroll: true,
    border: false,
    rootVisible: false,

    /**
     * returns persistent filter tree node
     * 
     * @return {Ext.tree.AsyncTreeNode}
     */
    getPersistentFilterNode: function() {
        return this.filterNode;
    },
    
    /**
     * @private
     */
    initComponent: function() {
        this.loader = new Tine.widgets.grid.PersistentFilterLoader({
            app: this.app
        });
        
        if (! this.root) {
            this.root = new Ext.tree.TreeNode({
                id: '/',
                leaf: false,
                expanded: true
            });
        }
        
        Tine.widgets.grid.PersistentFilterPicker.superclass.initComponent.call(this);
        
        this.on('click', function(node) {
            if (node.attributes.isPersistentFilter) {
                node.select();
                this.onFilterSelect();
            } else if (node.id == '_persistentFilters') {
                node.expand();
                return false;
            }
        }, this);
        
        this.on('contextmenu', this.onContextMenu, this);
    },
    
    /**
     * @private
     */
    afterRender: function() {
        Tine.widgets.grid.PersistentFilterPicker.superclass.afterRender.call(this);
        
        this.filterNode = new Ext.tree.AsyncTreeNode({
            text: _('My saved filters'),
            id: '_persistentFilters',
            leaf: false,
            expanded: false
        });
        
        this.getNodeById(this.filterMountId).appendChild(this.filterNode);
    },
    
    /**
     * load grid from saved filter
     * 
     * NOTE: As all filter plugins add their data on the stores beforeload event
     *       we need a litte hack to only present a filterid.
     *       
     *       When a filter is selected, we register ourselve as latest beforeload,
     *       remove all filter data and paste our filter id. To ensure we are
     *       always the last listener, we directly remove the listener afterwards
     */
    onFilterSelect: function() {
        var store = this.app.getMainScreen().getContentPanel().store;
        store.on('beforeload', this.storeOnBeforeload, this);
        store.load();
        
        if (typeof this.app.getMainScreen().getTreePanel().activate == 'function') {
            this.app.getMainScreen().getTreePanel().activate(0);
        }
    },
    
    storeOnBeforeload: function(store, options) {
        options.params.filter = this.getSelectionModel().getSelectedNode().id;
        store.un('beforeload', this.storeOnBeforeload, this);
    },
    
    onContextMenu: function(node, e) {
        if (! node.attributes.isPersistentFilter) {
            return;
        }
        
        var menu = new Ext.menu.Menu({
            items: [{
                text: _('Delete Filter'),
                iconCls: 'action_delete',
                scope: this,
                handler: function() {
                    Ext.MessageBox.confirm(_('Confirm'), String.format(_('Do you really want to delete the Filter "{0}"?'), node.text), function(_btn){
                        if ( _btn == 'yes') {
                            Ext.MessageBox.wait(_('Please wait'), String.format(_('Deleting Filter "{0}"' ), this.containerName , node.text));
                            
                            Ext.Ajax.request({
                                params: {
                                    method: 'Tinebase_PersistentFilter.delete',
                                    filterId: node.id
                                },
                                scope: this,
                                success: function(){
                                    node.unselect();
                                    node.remove();
                                    Ext.MessageBox.hide();
                                }
                            });
                        }
                    }, this);
                }
            }]
        });
        menu.showAt(e.getXY());
    }
    
});

Tine.widgets.grid.PersistentFilterLoader = Ext.extend(Tine.widgets.data.TreeLoader, {

	method: 'Tinebase_PersistentFilter.search',

    /**
     * @private
     */
    initComponent: function() {
        var gridPanel = this.app.getMainScreen().getContentPanel();
    	var model = gridPanel.store.reader.recordType.getMeta('appName') + '_Model_' + gridPanel.store.reader.recordType.getMeta('modelName') + 'Filter';
        this.filter = [
            {field: 'model', operator: 'equals', value: model}
        ];
        
        Tine.widgets.grid.PersistentFilterLoader.superclass.initComponent.call(this);
    },
	
	/**
     * @private
     * 
     * @todo generalize this?
     */
    createNode: function(attr) {
        var isPersistentFilter = !!attr.model && !!attr.filters,
        node = isPersistentFilter ? {
            isPersistentFilter: isPersistentFilter,
            text: attr.name,
            id: attr.id,
            leaf: attr.leaf === false ? attr.leaf : true,
            filter: attr
        } : attr;
        return Tine.widgets.grid.PersistentFilterLoader.superclass.createNode.call(this, node);
    }
 });
