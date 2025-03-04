<div id="plugin_list" class="w3-container w3-padding">
	<div class="w3-row w3-margin-bottom">
		<a href="javascript:void(0)" onclick="W3SubTabs.open('plugin_list_subtab_settings', 'plugin_settings_list', 'plugin_list'); oPluginListSettings.table.responsive.recalc();">
			<div id="plugin_list_subtab_settings" class="subtab_btn w3-third w3-bottombar w3-hover-light-grey w3-padding w3-border-red"><%[ Plugin settings ]%></div>
		</a>
		<a href="javascript:void(0)" onclick="W3SubTabs.open('plugin_list_subtab_plugins', 'plugin_plugins_list', 'plugin_list'); oPluginListPlugins.table.responsive.recalc();">
			<div id="plugin_list_subtab_plugins" class="subtab_btn w3-third w3-bottombar w3-hover-light-grey w3-padding"><%[ Installed plugins ]%></div>
		</a>
	</div>
	<div id="plugin_settings_list" class="subtab_item">
		<div class="w3-row w3-margin-bottom">
			<button id="plugin_list_add_plugin_settings" class="w3-button w3-green" onclick="oPlugins.load_plugin_settings_window(); return false;">
				<i class="fa-solid fa-plus"></i> &nbsp;<%[ Add plugin settings ]%>
			</button>
		</div>
		<div>
			<table id="plugin_list_settings_list" class="display w3-table w3-striped w3-hoverable w3-margin-bottom" style="width: 100%">
				<thead>
					<tr>
						<th></th>
						<th><%[ Name ]%></th>
						<th><%[ Plugin name ]%></th>
						<th><%[ Enabled ]%></th>
						<th><%[ Actions ]%></th>
					</tr>
				</thead>
				<tbody id="plugin_list_settings_list_body"></tbody>
				<tfoot>
					<tr>
						<th></th>
						<th><%[ Name ]%></th>
						<th><%[ Plugin name ]%></th>
						<th><%[ Enabled ]%></th>
						<th><%[ Actions ]%></th>
					</tr>
				</tfoot>
			</table>
			<p class="info w3-hide-medium w3-hide-small"><%[ Tip: Use left-click to select table row. Use CTRL + left-click to multiple row selection. Use SHIFT + left-click to add a range of rows to selection. ]%></p>
		</div>
	</div>
	<div id="plugin_plugins_list" class="subtab_item" style="display: none">
		<div>
			<table id="plugin_list_plugins_list" class="display w3-table w3-striped w3-hoverable w3-margin-bottom" style="width: 100%">
				<thead>
					<tr>
						<th></th>
						<th><%[ Name ]%></th>
						<th><%[ Type ]%></th>
						<th><%[ Version ]%></th>
					</tr>
				</thead>
				<tbody id="plugin_list_plugins_list_body"></tbody>
				<tfoot>
					<tr>
						<th></th>
						<th><%[ Name ]%></th>
						<th><%[ Type ]%></th>
						<th><%[ Version ]%></th>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>
</div>
<com:TCallback ID="LoadPluginSettingsList" OnCallback="loadPluginSettingsList" />
<com:TCallback ID="SavePluginSettingsForm" OnCallback="savePluginSettingsForm" />
<com:TCallback ID="LoadPluginPluginsList" OnCallback="loadPluginPluginsList" />
<com:TCallback ID="RemovePluginSettingsAction" OnCallback="removePluginSettings">
	<prop:ClientSide.OnComplete>
		oPluginListSettings.table_toolbar.style.display = 'none';
	</prop:ClientSide.OnComplete>
</com:TCallback>
<script>
var oPluginListSettings = {
	ids: {
		table: 'plugin_list_settings_list'
	},
	actions: [
		{
			action: 'remove',
			label: '<%[ Remove ]%>',
			value: 'name',
			callback: <%=$this->RemovePluginSettingsAction->ActiveControl->Javascript%>
		}
	],
	table: null,
	table_toolbar: null,
	init: function(data) {
		if (this.table) {
			this.table.clear();
			this.table.rows.add(data);
			this.table.draw(false);
		} else {
			this.set_table(data);
			this.set_bulk_actions();
			this.set_events();
		}
	},
	set_events: function() {
		document.getElementById(this.ids.table).addEventListener('click', function(e) {
			$(function() {
				const wa = (this.table.rows({selected: true}).data().length > 0) ? 'show' : 'hide';
				$(this.table_toolbar).animate({
					width: wa
				}, 'fast');
			}.bind(this));
		}.bind(this));
	},
	set_table: function(data) {
		this.table = $('#' + this.ids.table).DataTable({
			data: data,
			deferRender: true,
			fixedHeader: {
				header: true,
				headerOffset: $('#main_top_bar').height()
			},
			layout: {
				topStart: [
					{
						pageLength: {}
					},
					{
						buttons: ['copy', 'csv', 'colvis']
					},
					{
						div: {
							className: 'table_toolbar'
						}
					}
				],
				topEnd: [
					'search'
				],
				bottomStart: [
					'info'
				],
				bottomEnd: [
					'paging'
				]
			},
			stateSave: true,
			stateDuration: (typeof(KEEP_TABLE_SETTINGS) == 'undefined' ? 7200 : KEEP_TABLE_SETTINGS),
			columns: [
				{
					orderable: false,
					data: null,
					defaultContent: '<button type="button" class="w3-button w3-blue"><i class="fa fa-angle-down"></i></button>'
				},
				{
					data: 'name'
				},
				{
					data: 'plugin',
					render: function(data, type, row) {
						let plugin = data;
						if (oPlugins.plugins.hasOwnProperty(data)) {
							plugin = oPlugins.plugins[data].name;
						}
						return plugin;
					}
				},
				{
					data: 'enabled',
					render: function(data, type, row) {
						var ret;
						if (type == 'display') {
							ret = '';
							if (data == 1) {
								var check = document.createElement('I');
								check.className = 'fas fa-check';
								ret = check.outerHTML;
							}
						} else {
							ret = data;
						}
						return ret;
					}
				},
				{
					data: 'name',
					render: function (data, type, row) {
						var btn_edit = document.createElement('BUTTON');
						btn_edit.className = 'w3-button w3-green';
						btn_edit.type = 'button';
						var i_edit = document.createElement('I');
						i_edit.className = 'fa fa-edit';
						var label_edit = document.createTextNode(' <%[ Edit ]%>');
						btn_edit.appendChild(i_edit);
						btn_edit.innerHTML += '&nbsp';
						btn_edit.style.marginRight = '8px';
						btn_edit.appendChild(label_edit);
						const props = {name: data, plugin: row.plugin, enabled: row.enabled};
						btn_edit.setAttribute('onclick', 'oPlugins.load_plugin_settings_window(' + JSON.stringify(props) + ')');
						return btn_edit.outerHTML;
					}
				}
			],
			responsive: {
				details: {
					type: 'column',
					display: DataTable.Responsive.display.childRow
				}
			},
			columnDefs: [{
				className: 'dtr-control',
				orderable: false,
				targets: 0
			},
			{
				className: "dt-center",
				targets: [ 3, 4 ]
			}],
			select: {
				style:    'os',
				selector: 'td:not(:last-child):not(:first-child)',
				blurable: false
			},
			order: [1, 'asc'],
			drawCallback: function () {
				this.api().columns([2, 3]).every(function () {
					var column = this;
					var select = $('<select class="dt-select"><option value=""></option></select>')
					.appendTo($(column.footer()).empty())
					.on('change', function () {
						var val = dtEscapeRegex(
							$(this).val()
						);
						column
						.search(val ? '^' + val + '$' : '', true, false)
						.draw();
					});
					if ([2, 3].indexOf(column[0][0]) > -1) { // Enabled column
						let items = [];
						column.data().unique().sort().each(function (d, j) {
							if (Array.isArray(d)) {
								d = d.toString();
							}
							if (d === '' || items.indexOf(d) > -1) {
								return;
							}
							items.push(d);
						});
						for (let item of items) {
							let ds = '';
							if (column[0][0] == 3) { // enabled flag
								if (item === '1') {
									ds = '<%[ Enabled ]%>';
								} else if (item === '0') {
									ds = '<%[ Disabled ]%>';
								}
							} else if (column[0][0] == 2) { // plugin name
								ds = oPlugins.plugins.hasOwnProperty(item) ? oPlugins.plugins[item].name : '-';
								item = ds;
							} else {
								ds = item;
							}
							if (column.search() == '^' + dtEscapeRegex(item) + '$') {
								select.append('<option value="' + item + '" title="' + ds + '" selected>' + ds + '</option>');
							} else {
								select.append('<option value="' + item + '" title="' + ds + '">' + ds + '</option>');
							}
						}
					} else {
						column.data().sort().unique().each(function(d, j) {
							if (column.search() == '^' + dtEscapeRegex(d) + '$') {
								select.append('<option value="' + d + '" selected>' + d + '</option>');
							} else {
								select.append('<option value="' + d + '">' + d + '</option>');
							}
						});
					}
				});
			}
		});
	},
	set_bulk_actions: function() {
		this.table_toolbar = get_table_toolbar(this.table, this.actions, {
			actions: '<%[ Select action ]%>',
			ok: '<%[ OK ]%>'
		});
	}
};

oPluginForm.init({
	ids: {
		plugin_form: 'plugin_list_plugin_settings_form',
		settings_name: '<%=$this->PluginSettingsName->ClientID%>'
	},
	save_cb: <%=$this->SavePluginSettingsForm->ActiveControl->Javascript%>
});

var oPluginListPlugins = {
	ids: {
		table: 'plugin_list_plugins_list'
	},
	table: null,
	init: function(data) {
		if (this.table) {
			this.table.clear();
			this.table.rows.add(data);
			this.table.draw(false);
		} else {
			this.set_table(data);
		}
	},
	set_table: function(data) {
		this.table = $('#' + this.ids.table).DataTable({
			data: data,
			deferRender: true,
			layout: {
				topStart: [
					{
						pageLength: {}
					},
					{
						buttons: ['copy', 'csv', 'colvis']
					}
				],
				topEnd: [
					'search'
				],
				bottomStart: [
					'info'
				],
				bottomEnd: [
					'paging'
				]
			},
			stateSave: true,
			stateDuration: (typeof(KEEP_TABLE_SETTINGS) == 'undefined' ? 7200 : KEEP_TABLE_SETTINGS),
			columns: [
				{
					orderable: false,
					data: null,
					defaultContent: '<button type="button" class="w3-button w3-blue"><i class="fa fa-angle-down"></i></button>'
				},
				{
					data: 'name'
				},
				{
					data: 'type'
				},
				{
					data: 'version'
				}
			],
			responsive: {
				details: {
					type: 'column',
					display: DataTable.Responsive.display.childRow
				}
			},
			columnDefs: [{
				className: 'dtr-control',
				orderable: false,
				targets: 0
			},
			{
				className: "dt-center",
				targets: [ 2, 3 ]
			}],
			order: [1, 'asc'],
			drawCallback: function () {
				$('#' + oPluginListPlugins.ids.table + ' tbody tr td').css('padding', '10px');
				this.api().columns([2, 3]).every(function () {
					var column = this;
					var select = $('<select class="dt-select"><option value=""></option></select>')
					.appendTo($(column.footer()).empty())
					.on('change', function () {
						var val = dtEscapeRegex(
							$(this).val()
						);
						column
						.search(val ? '^' + val + '$' : '', true, false)
						.draw();
					});
					column.data().sort().unique().each(function(d, j) {
						if (column.search() == '^' + dtEscapeRegex(d) + '$') {
							select.append('<option value="' + d + '" selected>' + d + '</option>');
						} else {
							select.append('<option value="' + d + '">' + d + '</option>');
						}
					});
				});
			}
		});
	}
};

var oPlugins = {
	ids: {
		plugin_win: 'plugin_list_plugin_settings_window',
		plugin_form: 'plugin_list_plugin_settings_form',
		win_title_add: 'plugin_list_plugin_settings_title_add',
		win_title_edit: 'plugin_list_plugin_settings_title_edit',
		settings_name: '<%=$this->PluginSettingsName->ClientID%>',
		settings_enabled: '<%=$this->PluginSettingsEnabled->ClientID%>',
		settings_plugin_name: '<%=$this->PluginSettingsPluginName->ClientID%>',
		window_mode: '<%=$this->PluginSettingsWindowMode->ClientID%>',
		error_settings_exist: 'plugin_list_plugin_settings_exists',
		error_settings_error: 'plugin_list_plugin_settings_error'
	},
	plugins: [],
	settings: [],
	init: function() {
		this.load_plugin_plugins_list();
	},
	load_plugin_settings_list: function() {
		const cb = <%=$this->LoadPluginSettingsList->ActiveControl->Javascript%>;
		cb.dispatch();
	},
	load_plugin_settings_list_cb: function(data) {
		oPlugins.settings = data;
		const tdata = Object.values(data);
		oPluginListSettings.init(tdata);
	},
	load_plugin_plugins_list: function() {
		const cb = <%=$this->LoadPluginPluginsList->ActiveControl->Javascript%>;
		cb.dispatch();
	},
	load_plugin_plugins_list_cb: function(data) {
		oPlugins.plugins = data;
		const tdata = Object.values(data);
		oPluginListPlugins.init(tdata);
		oPlugins.load_plugin_settings_list();
	},
	show_plugin_settings_window: function(show) {
		const win = document.getElementById(oPlugins.ids.plugin_win);
		win.style.display = show ? 'block' : 'none';
	},
	load_plugin_settings_window: function(props) {
		oPluginForm.clear_plugin_settings_form();
		this.clear_plugin_settings_window();
		const window_mode = document.getElementById(this.ids.window_mode);
		const name = document.getElementById(this.ids.settings_name);
		if (props) {
			window_mode.value = 'edit';
			const title_edit = document.getElementById(this.ids.win_title_edit);
			title_edit.style.display = 'inline-block';
			name.value = props.name;
			name.setAttribute('readonly', 'readonly');
			const enabled = document.getElementById(this.ids.settings_enabled);
			enabled.checked = (props.enabled == 1);
			const plugin_name = document.getElementById(this.ids.settings_plugin_name);
			plugin_name.value = props.plugin;
			this.load_plugin_settings_form(props.plugin);
			oPluginForm.set_form_fields(this.settings[props.name].parameters, this.plugins[props.plugin].parameters);
		} else {
			window_mode.value = 'add';
			name.removeAttribute('readonly');
			const title_add = document.getElementById(this.ids.win_title_add);
			title_add.style.display = 'inline-block';
		}
		this.show_plugin_settings_window(true);
	},
	clear_plugin_settings_window: function() {
		const self = oPlugins;
		[
			self.ids.win_title_add,
			self.ids.win_title_edit,
			self.ids.error_settings_exist,
			self.ids.error_settings_error
		].forEach((id) => {
			document.getElementById(id).style.display = 'none';
		});
		const settings_name = document.getElementById(self.ids.settings_name);
		settings_name.value = '';
		const settings_enabled = document.getElementById(self.ids.settings_enabled);
		settings_enabled.checked = true;
		const settings_plugin_name = document.getElementById(self.ids.settings_plugin_name);
		settings_plugin_name.selectedIndex = 0;
	},
	load_plugin_settings_form: function(name) {
		oPluginForm.clear_plugin_settings_form();
		if (name != 'none') {
			const data = oPlugins.plugins[name];
			oPluginForm.build_form(data);
			oPluginForm.set_form_fields('', data.parameters);
		}
	}
};
$(function() {
	oPlugins.init();
});
</script>
<div id="plugin_list_plugin_settings_window" class="w3-modal">
	<div class="w3-modal-content w3-animate-top w3-card-4">
		<header class="w3-container w3-green">
			<span onclick="oPlugins.show_plugin_settings_window(false);" class="w3-button w3-display-topright">&times;</span>
			<h2 id="plugin_list_plugin_settings_title_add" style="display: none"><%[ Add plugin settings ]%></h2>
			<h2 id="plugin_list_plugin_settings_title_edit" style="display: none"><%[ Edit plugin settings ]%></h2>
		</header>
		<div class="w3-container w3-padding-large" style="padding-bottom: 0 !important;">
			<span id="plugin_list_plugin_settings_exists" class="error" style="display: none"><%[ Plugin settings with the given name already exists. ]%></span>
			<span id="plugin_list_plugin_settings_error" class="error" style="display: none"></span>
			<div class="w3-row directive_field">
				<div class="w3-col w3-third"><label for="<%=$this->PluginSettingsName->ClientID%>"><%[ Name ]%>:</label></div>
				<div class="w3-half w3-show-inline-block">
					<com:TActiveTextBox
						ID="PluginSettingsName"
						CssClass="w3-input w3-border"
						Attributes.pattern="<%=PluginConfigBase::SETTINGS_NAME_PATTERN%>"
						Attributes.required="required"
					/>
				</div>
			</div>
			<div class="w3-row directive_field">
				<div class="w3-col w3-third"><label for="<%=$this->PluginSettingsEnabled->ClientID%>"><%[ Enabled ]%>:</label></div>
				<div class="w3-half w3-show-inline-block">
					<com:TActiveCheckBox
						ID="PluginSettingsEnabled"
						CssClass="w3-check w3-border"
						AutoPostBack="false"
					/>
				</div>
			</div>
			<div class="w3-row directive_field">
				<div class="w3-col w3-third"><label for="<%=$this->PluginSettingsPluginName->ClientID%>"><%[ Plugin ]%>:</label></div>
				<div class="w3-half w3-show-inline-block">
					<com:TActiveDropDownList
						ID="PluginSettingsPluginName"
						CssClass="w3-select w3-border"
						AutoPostBack="false"
						Attributes.onchange="oPlugins.load_plugin_settings_form(this.value);"
					/>
				</div>
			</div>
			<com:TActiveHiddenField ID="PluginSettingsWindowMode" />
			<div id="plugin_list_plugin_settings_form"></div>
		</div>
		<footer class="w3-container w3-padding-large w3-center">
			<button type="button" class="w3-button w3-red" onclick="oPlugins.show_plugin_settings_window(false);"><i class="fas fa-times"></i> &nbsp;<%[ Cancel ]%></button>
			<button type="button" class="w3-button w3-green" onclick="oPluginForm.save_form();"><i class="fa-solid fa-save"></i> &nbsp;<%[ Save ]%></button>
		</footer>
	</div>
</div>
