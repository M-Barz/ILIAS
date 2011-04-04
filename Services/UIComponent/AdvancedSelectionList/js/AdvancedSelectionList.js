var ilAdvancedSelectionListFunc = function() {
};
ilAdvancedSelectionListFunc.prototype =
{
	lists: {},
	items: {},
	init: {},
	
	// add new selection list
	add: function (id, cfg)
	{
		this.lists[id] = cfg;
		this.items[id] = {};
		this.showAnchor(cfg.anchor_id);

		ilOverlay.add('ilAdvSelListTable_' + id,
			{yuicfg: {visible: false, context: [cfg.anchor_id, 'tl', 'bl', ["beforeShow", "windowResize"]]},
			trigger: cfg.anchor_id, trigger_event: cfg.trigger_event, anchor_id: cfg.anchor_id,
			toggle_el: cfg.toggle_el, toggle_class_on: cfg.toggle_class_on,
			asynch: cfg.asynch, asynch_url: cfg.asynch_url, auto_hide: cfg.auto_hide});
	},
			
	itemOn: function (obj)
	{
		obj.className = "il_adv_sel_act";
	},
	
	itemOff: function (obj)
	{
		obj.className = "il_adv_sel";
	},
	
	showAnchor: function(id)
	{
		anchor = document.getElementById(id);
		if (anchor !=  null)
		{
			anchor.style.display='';
		}
	},
	
	submitForm: function (id, hid_name, hid_val, form_id, cmd)
	{
		this.setHiddenInput(id, hid_name, hid_val);
		form_el = document.getElementById(form_id);
		hidden_cmd_el = document.getElementById("ilAdvSelListHiddenCmd_" + id);
		hidden_cmd_el.name = 'cmd[' + cmd + ']';
		hidden_cmd_el.value = '1';
		form_el.submit();
	},
	
	selectForm: function (id, hid_name, hid_val, title)
	{
		this.setHiddenInput(id, hid_name, hid_val);
		anchor_text = document.getElementById("ilAdvSelListAnchorText_" + id);
		anchor_text.innerHTML = title;
		ilOverlay.hide(null, 'ilAdvSelListTable_' + id);
		if (this.lists[id]['select_callback'] != null)
		{
			eval(this.lists[id]['select_callback'] + '(this.items[id][hid_val]);');
		}
	},
	
	setHiddenInput: function (id, hid_name, hid_val)
	{
		hidden_el = document.getElementById("ilAdvSelListHidden_" + id);
		hidden_el.name = hid_name;
		hidden_el.value = hid_val;
	},

	getHiddenInput: function (id)
	{
		hidden_el = document.getElementById("ilAdvSelListHidden_" + id);
		return hidden_el.value;
	},

	addItem: function (id, hid_name, hid_val, title)
	{
		this.items[id][hid_val] = {hid_name: hid_name, hid_val: hid_val, title: title};
	},

	selectItem: function (id, value)
	{
		if (this.items[id][value] != null)
		{
			this.selectForm(id, this.items[id][value]["hid_name"], value, this.items[id][value]["title"]);
		}
	}


};
var ilAdvancedSelectionList = new ilAdvancedSelectionListFunc();