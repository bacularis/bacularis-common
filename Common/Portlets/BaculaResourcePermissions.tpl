<div class="w3-row w3-section">
	<div class="w3-col w3-third"><label for="<%=$this->ResourceDirector->ClientID%>"><%[ Director resource permissions ]%>:</label></div>
	<div class="w3-col w3-twothird">
		<com:TActiveCheckBox
			ID="ResourceDirector"
			CssClass="w3-check"
			CausesValidation="false"
			Attributes.onclick="$('#<%=$this->ResourceDirector->ClientID%>_resources').slideToggle('fast');"
		/>
	</div>
</div>
<div class="w3-row w3-section">
	<div id="<%=$this->ResourceDirector->ClientID%>_resources" style="display: none">
		<div class="w3-container w3-right-align">
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceDirector->ClientID%>_resources_list', 'ro');" class="raw"><i class="fa-solid fa-arrow-up fa-fw"></i> &nbsp;<%[ All read-only ]%></a>&nbsp;
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceDirector->ClientID%>_resources_list', 'rw');" class="raw"><i class="fa-solid fa-exchange-alt fa-rotate-90 fa-fw"></i> &nbsp;<%[ All read-write ]%></a>&nbsp;
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceDirector->ClientID%>_resources_list', 'no');" class="raw"><i class="fa-solid fa-ban fa-fw"></i> &nbsp;<%[ All no access ]%></a>
		</div>
		<table id="<%=$this->ResourceDirector->ClientID%>_resources_list" class="w3-table w3-striped w3-margin-bottom" style="width: 100%">
			<thead>
				<tr>
					<th><%[ Resource ]%></th>
					<th><%[ Preview ]%></th>
					<th><%[ Read-Only ]%></th>
					<th><%[ Read-Write ]%></th>
					<th><%[ No access ]%></th>
				</tr>
			</thead>
			<tbody id="<%=$this->ResourceDirector->ClientID%>_resources_list_body"></tbody>
			<tfoot>
				<tr>
					<th><%[ Resource ]%></th>
					<th><%[ Preview ]%></th>
					<th><%[ Read-Only ]%></th>
					<th><%[ Read-Write ]%></th>
					<th><%[ No access ]%></th>
				</tr>
			</tfoot>
		</table>
	</div>
</div>
<div class="w3-row w3-section">
	<div class="w3-col w3-third"><label for="<%=$this->ResourceStorage->ClientID%>"><%[ Storage resource permissions ]%>:</label></div>
	<div class="w3-col w3-twothird">
		<com:TActiveCheckBox
			ID="ResourceStorage"
			CssClass="w3-check"
			CausesValidation="false"
			Attributes.onclick="$('#<%=$this->ResourceStorage->ClientID%>_resources').slideToggle('fast');"
		/>
	</div>
</div>
<div class="w3-row w3-section">
	<div id="<%=$this->ResourceStorage->ClientID%>_resources" style="display: none">
		<div class="w3-container w3-right-align">
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceStorage->ClientID%>_resources_list', 'ro');" class="raw"><i class="fa-solid fa-arrow-up fa-fw"></i> &nbsp;<%[ All read-only ]%></a>&nbsp;
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceStorage->ClientID%>_resources_list', 'rw');" class="raw"><i class="fa-solid fa-exchange-alt fa-rotate-90 fa-fw"></i> &nbsp;<%[ All read-write ]%></a>&nbsp;
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceStorage->ClientID%>_resources_list', 'no');" class="raw"><i class="fa-solid fa-ban fa-fw"></i> &nbsp;<%[ All no access ]%></a>
		</div>
		<table id="<%=$this->ResourceStorage->ClientID%>_resources_list" class="w3-table w3-striped w3-margin-bottom" style="width: 100%">
			<thead>
				<tr>
					<th><%[ Resource ]%></th>
					<th><%[ Preview ]%></th>
					<th><%[ Read-Only ]%></th>
					<th><%[ Read-Write ]%></th>
					<th><%[ No access ]%></th>
				</tr>
			</thead>
			<tbody id="<%=$this->ResourceStorage->ClientID%>_resources_list_body"></tbody>
			<tfoot>
				<tr>
					<th><%[ Resource ]%></th>
					<th><%[ Preview ]%></th>
					<th><%[ Read-Only ]%></th>
					<th><%[ Read-Write ]%></th>
					<th><%[ No access ]%></th>
				</tr>
			</tfoot>
		</table>
	</div>
</div>
<div class="w3-row w3-section">
	<div class="w3-col w3-third"><label for="<%=$this->ResourceClient->ClientID%>"><%[ Client resource permissions ]%>:</label></div>
	<div class="w3-col w3-twothird">
		<com:TActiveCheckBox
			ID="ResourceClient"
			CssClass="w3-check"
			CausesValidation="false"
			Attributes.onclick="$('#<%=$this->ResourceClient->ClientID%>_resources').slideToggle('fast');"
		/>
	</div>
</div>
<div class="w3-row w3-section">
	<div id="<%=$this->ResourceClient->ClientID%>_resources" style="display: none">
		<div class="w3-container w3-right-align">
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceClient->ClientID%>_resources_list', 'ro');" class="raw"><i class="fa-solid fa-arrow-up fa-fw"></i> &nbsp;<%[ All read-only ]%></a>&nbsp;
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceClient->ClientID%>_resources_list', 'rw');" class="raw"><i class="fa-solid fa-exchange-alt fa-rotate-90 fa-fw"></i> &nbsp;<%[ All read-write ]%></a>&nbsp;
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceClient->ClientID%>_resources_list', 'no');" class="raw"><i class="fa-solid fa-ban fa-fw"></i> &nbsp;<%[ All no access ]%></a>
		</div>
		<table id="<%=$this->ResourceClient->ClientID%>_resources_list" class="w3-table w3-striped w3-margin-bottom" style="width: 100%">
			<thead>
				<tr>
					<th><%[ Resource ]%></th>
					<th><%[ Preview ]%></th>
					<th><%[ Read-Only ]%></th>
					<th><%[ Read-Write ]%></th>
					<th><%[ No access ]%></th>
				</tr>
			</thead>
			<tbody id="<%=$this->ResourceClient->ClientID%>_resources_list_body"></tbody>
			<tfoot>
				<tr>
					<th><%[ Resource ]%></th>
					<th><%[ Preview ]%></th>
					<th><%[ Read-Only ]%></th>
					<th><%[ Read-Write ]%></th>
					<th><%[ No access ]%></th>
				</tr>
			</tfoot>
		</table>
	</div>
</div>
<div class="w3-row w3-section">
	<div class="w3-col w3-third"><label for="<%=$this->ResourceBconsole->ClientID%>"><%[ Bconsole resource permissions ]%>:</label></div>
	<div class="w3-col w3-twothird">
		<com:TActiveCheckBox
			ID="ResourceBconsole"
			CssClass="w3-check"
			CausesValidation="false"
			Attributes.onclick="$('#<%=$this->ResourceBconsole->ClientID%>_resources').slideToggle('fast');"
		/>
	</div>
</div>
<div class="w3-row w3-section">
	<div id="<%=$this->ResourceBconsole->ClientID%>_resources" style="display: none">
		<div class="w3-container w3-right-align">
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceBconsole->ClientID%>_resources_list', 'ro');" class="raw"><i class="fa-solid fa-arrow-up fa-fw"></i> &nbsp;<%[ All read-only ]%></a>&nbsp;
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceBconsole->ClientID%>_resources_list', 'rw');" class="raw"><i class="fa-solid fa-exchange-alt fa-rotate-90 fa-fw"></i> &nbsp;<%[ All read-write ]%></a>&nbsp;
			<a href="javascript:void(0)" onclick="<%=$this->ClientID%>set_mode_all('<%=$this->ResourceBconsole->ClientID%>_resources_list', 'no');" class="raw"><i class="fa-solid fa-ban fa-fw"></i> &nbsp;<%[ All no access ]%></a>
		</div>
		<table id="<%=$this->ResourceBconsole->ClientID%>_resources_list" class="w3-table w3-striped w3-margin-bottom" style="width: 100%">
			<thead>
				<tr>
					<th><%[ Resource ]%></th>
					<th><%[ Preview ]%></th>
					<th><%[ Read-Only ]%></th>
					<th><%[ Read-Write ]%></th>
					<th><%[ No access ]%></th>
				</tr>
			</thead>
			<tbody id="<%=$this->ResourceBconsole->ClientID%>_resources_list_body"></tbody>
			<tfoot>
				<tr>
					<th><%[ Resource ]%></th>
					<th><%[ Preview ]%></th>
					<th><%[ Read-Only ]%></th>
					<th><%[ Read-Write ]%></th>
					<th><%[ No access ]%></th>
				</tr>
			</tfoot>
		</table>
	</div>
</div>
<com:TActiveHiddenField ID="DirPermSettings" />
<com:TActiveHiddenField ID="SdPermSettings" />
<com:TActiveHiddenField ID="FdPermSettings" />
<com:TActiveHiddenField ID="BconsPermSettings" />
<script>
function <%=$this->ClientID%>set_preview(id, mode) {
	const preview = document.getElementById(id);
	if (!preview) {
		return;
	}
	if (mode == 'ro') {
		preview.className = 'w3-text-yellow bold';
		preview.textContent = '<%[ read only ]%>';
	} else if (mode == 'rw') {
		preview.className = 'w3-text-green bold';
		preview.textContent = '<%[ read write ]%>';
	} else if (mode == 'no') {
		preview.className = 'w3-text-red bold';
		preview.textContent = '<%[ no access ]%>';
	}
};
function <%=$this->ClientID%>set_mode_all(id, mode) {
	const selector = 'input[type="radio"][data-%mode="true"]'.replace('%mode', mode);
	const container = document.getElementById(id);
	const radios = container.querySelectorAll(selector);
	for (let i = 0; i < radios.length; i++) {
		$(radios[i]).click();
	}
}
class <%=$this->ClientID%>ResourcePermissionsBase {
	constructor(data) {
		this.table = null;
		this.data = data;
	}
	init() {
		this.set_table();
	}
	set_table() {
		this.table = $('#' + this.ids.table).DataTable({
			data: this.data,
			dom: 'lrt',
			stateSave: true,
			pageLength: 25,
			lengthChange: false,
			autoWidth: false,
			columns: [
				{data: 'resource'},
				{
					data: 'preview',
					render: function(data, type, row) {
						let ret = '';
						if (type == 'display') {
							const preview = document.createElement('SPAN');
							preview.id = this.ids.table + row.resource;
							const read_only = document.getElementById(this.ids.table + row.resource + 'ro');
							const read_write = document.getElementById(this.ids.table + row.resource + 'rw');
							const no_access = document.getElementById(this.ids.table + row.resource + 'no');
							if (read_only && read_only.checked) {
								<%=$this->ClientID%>set_preview(preview.id, 'ro');
							} else if (read_write && read_write.checked) {
								<%=$this->ClientID%>set_preview(preview.id, 'rw');
							} else if (no_access && no_access.checked) {
								<%=$this->ClientID%>set_preview(preview.id, 'no');
							}
							ret = preview.outerHTML;
						}
						return ret;
					}.bind(this)
				},
				{
					data: 'read_only',
					render: function(data, type, row) {
						let ret = '';
						if (type == 'display') {
							var read_only = document.createElement('INPUT');
							read_only.type = 'radio';
							read_only.id = this.ids.table + row.resource + 'ro';
							read_only.name = this.component + row.resource;
							read_only.classList.add('w3-check');
							read_only.setAttribute('data-ro', 'true');
							if (data === true) {
								read_only.setAttribute('checked', 'checked');
								<%=$this->ClientID%>set_preview(this.ids.table + row.resource, 'ro');
							}
							read_only.setAttribute('onclick', '<%=$this->ClientID%>set_preview(\'' + this.ids.table + row.resource + '\', \'ro\');');
							ret = read_only.outerHTML;
						}
						return ret;
					}.bind(this)
				},
				{
					data: 'read_write',
					render: function(data, type, row) {
						let ret = '';
						if (type == 'display') {
							var read_write = document.createElement('INPUT');
							read_write.type = 'radio';
							read_write.id = this.ids.table + row.resource + 'rw';
							read_write.name = this.component + row.resource;
							read_write.classList.add('w3-check');
							read_write.setAttribute('data-rw', 'true');
							if (data === true) {
								read_write.setAttribute('checked', 'checked');
								<%=$this->ClientID%>set_preview(this.ids.table + row.resource, 'rw');
							}
							read_write.setAttribute('onclick', '<%=$this->ClientID%>set_preview(\'' + this.ids.table + row.resource + '\', \'rw\');');
							ret = read_write.outerHTML;
						}
						return ret;
					}.bind(this)
				},
				{
					data: 'no_access',
					render: function(data, type, row) {
						let ret = '';
						if (type == 'display') {
							var no_access = document.createElement('INPUT');
							no_access.type = 'radio';
							no_access.id = this.ids.table + row.resource + 'no';
							no_access.name = this.component + row.resource;
							no_access.classList.add('w3-check');
							no_access.setAttribute('data-no', 'true');
							if (data === true) {
								no_access.setAttribute('checked', 'checked');
								<%=$this->ClientID%>set_preview(this.ids.table + row.resource, 'no');
							}
							no_access.setAttribute('onclick', '<%=$this->ClientID%>set_preview(\'' + this.ids.table + row.resource + '\', \'no\');');
							ret = no_access.outerHTML;
						}
						return ret;
					}.bind(this)
				}
			],
			columnDefs: [{
				className: "dt-center",
				targets: [ 1, 2, 3, 4 ]
			},{
				sortable: false,
				targets: [ 1, 2, 3, 4 ]
			}],
			order: [0, 'asc']
		});

		// force re-render for proper displaying 'preview' column
		this.table.rows().invalidate().draw();
	}
	get_settings() {
		const settings = []
		let resource, read_only, read_write, no_access, perm;
		const tdata = this.table.data();
		for (let i = 0; i < tdata.length; i++) {
			resource = tdata[i].resource;
			read_only = document.getElementById(this.ids.table + tdata[i].resource + 'ro').checked;
			read_write = document.getElementById(this.ids.table + tdata[i].resource + 'rw').checked;
			no_access = document.getElementById(this.ids.table + tdata[i].resource + 'no').checked;
			perm = '';
			if (read_only) {
				perm = 'ro';
			} else if (read_write) {
				perm = 'rw';
			} else if (no_access) {
				perm = 'no';
			}
			settings.push({
				resource: resource,
				perm: perm
			});
		}
		return settings;
	}
	is_enabled() {
		const tdata = this.table.data();
		let enabled = false;
		for (let i = 0; i < tdata.length; i++) {
			if (tdata[i].perm !== 'rw') {
				enabled = true;
				break;
			}
		}
		return enabled;
	}
	set_enabled() {
		const checkbox = document.getElementById(this.ids.enabled);
		checkbox.checked = this.is_enabled();
		const resources = document.getElementById(this.ids.resources);
		resources.style.display = checkbox.checked ? '' : 'none';
	}
};
class <%=$this->ClientID%>ResourcePermissionsDirector extends <%=$this->ClientID%>ResourcePermissionsBase {
	constructor(data) {
		super(data);
		this.component = 'dir';
		this.ids = {
			enabled: '<%=$this->ResourceDirector->ClientID%>',
			resources: '<%=$this->ResourceDirector->ClientID%>_resources',
			table: '<%=$this->ResourceDirector->ClientID%>_resources_list'
		};
		this.init();
		this.set_enabled();
	}
};
class <%=$this->ClientID%>ResourcePermissionsStorage extends <%=$this->ClientID%>ResourcePermissionsBase  {
	constructor(data) {
		super(data);
		this.component = 'sd';
		this.ids = {
			enabled: '<%=$this->ResourceStorage->ClientID%>',
			resources: '<%=$this->ResourceStorage->ClientID%>_resources',
			table: '<%=$this->ResourceStorage->ClientID%>_resources_list'
		};
		this.init();
		this.set_enabled();
	}
};
class <%=$this->ClientID%>ResourcePermissionsClient extends <%=$this->ClientID%>ResourcePermissionsBase {
	constructor(data) {
		super(data);
		this.component = 'fd';
		this.ids = {
			enabled: '<%=$this->ResourceClient->ClientID%>',
			resources: '<%=$this->ResourceClient->ClientID%>_resources',
			table: '<%=$this->ResourceClient->ClientID%>_resources_list'
		};
		this.init();
		this.set_enabled();
	}

};
class <%=$this->ClientID%>ResourcePermissionsBconsole extends <%=$this->ClientID%>ResourcePermissionsBase {
	constructor(data) {
		super(data);
		this.component = 'bcons';
		this.ids = {
			enabled: '<%=$this->ResourceBconsole->ClientID%>',
			resources: '<%=$this->ResourceBconsole->ClientID%>_resources',
			table: '<%=$this->ResourceBconsole->ClientID%>_resources_list'
		};
		this.init();
		this.set_enabled();
	}

};
var <%=$this->ClientID%>ResourcePermissions = {
	director: null,
	storage: null,
	client: null,
	bconsole: null,
	ids: {
		dir_perm_settings: '<%=$this->DirPermSettings->ClientID%>',
		sd_perm_settings: '<%=$this->SdPermSettings->ClientID%>',
		fd_perm_settings: '<%=$this->FdPermSettings->ClientID%>',
		bcons_perm_settings: '<%=$this->BconsPermSettings->ClientID%>'
	},
	set_user_props: function(props) {
		const self = <%=$this->ClientID%>ResourcePermissions;
		const prep_res_fn = (item) => {
			if (item.perm == 'rw') {
				item.read_write = true;
			} else {
				item.read_write = false;
			}
			if (item.perm == 'ro') {
				item.read_only = true;
			} else {
				item.read_only = false;
			}
			if (item.perm == 'no') {
				item.no_access = true;
			} else {
				item.no_access = false;
			}
			return item;
		}
		self.cleanup();
		const dir_res_perm = props.dir_res_perm.map(prep_res_fn);
		self.director = new <%=$this->ClientID%>ResourcePermissionsDirector(dir_res_perm);
		const sd_res_perm = props.sd_res_perm.map(prep_res_fn);
		self.storage = new <%=$this->ClientID%>ResourcePermissionsStorage(sd_res_perm);
		const fd_res_perm = props.fd_res_perm.map(prep_res_fn);
		self.client = new <%=$this->ClientID%>ResourcePermissionsClient(fd_res_perm);
		const bcons_res_perm = props.bcons_res_perm.map(prep_res_fn);
		self.bconsole = new <%=$this->ClientID%>ResourcePermissionsBconsole(bcons_res_perm);
	},
	save_user_props: function() {
		let perms;

		// Save director settings
		if (this.director) {
			const dir_enabled = document.getElementById(this.director.ids.enabled);
			const dir_input = document.getElementById(this.ids.dir_perm_settings);
			perms = [];
			if (dir_enabled.checked) {
				const dir_settings = this.director.get_settings();
				for (let i = 0; i < dir_settings.length; i++) {
					perms.push(dir_settings[i].resource + ':' + dir_settings[i].perm);
				}
			}
			dir_input.value = perms.join(' ');
		}

		// Save storage settings
		if (this.storage) {
			const sd_enabled = document.getElementById(this.storage.ids.enabled);
			const sd_input = document.getElementById(this.ids.sd_perm_settings);
			perms = [];
			if (sd_enabled.checked) {
				const sd_settings = this.storage.get_settings();
				for (let i = 0; i < sd_settings.length; i++) {
					perms.push(sd_settings[i].resource + ':' + sd_settings[i].perm);
				}
			}
			sd_input.value = perms.join(' ');
		}

		// Save client settings
		if (this.client) {
			const fd_enabled = document.getElementById(this.client.ids.enabled);
			const fd_input = document.getElementById(this.ids.fd_perm_settings);
			perms = [];
			if (fd_enabled.checked) {
				const fd_settings = this.client.get_settings();
				for (let i = 0; i < fd_settings.length; i++) {
					perms.push(fd_settings[i].resource + ':' + fd_settings[i].perm);
				}
			}
			fd_input.value = perms.join(' ');
		}


		if (this.bconsole) {
			const bcons_enabled = document.getElementById(this.bconsole.ids.enabled);
			const bcons_input = document.getElementById(this.ids.bcons_perm_settings);
			perms = [];
			if (bcons_enabled.checked) {
				const bcons_settings = this.bconsole.get_settings();
				for (let i = 0; i < bcons_settings.length; i++) {
					perms.push(bcons_settings[i].resource + ':' + bcons_settings[i].perm);
				}
			}
			bcons_input.value = perms.join(' ');
		}
	},
	cleanup: function() {
		[
			this.director,
			this.storage,
			this.client,
			this.bconsole
		].forEach((item) => {
			if (item) {
				item.table.clear().destroy();
			}
		});
	}
};
</script>
