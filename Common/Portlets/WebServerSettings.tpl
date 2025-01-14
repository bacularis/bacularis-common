<div id="web_server_container" class="w3-container w3-padding">
	<com:Bacularis.Common.Portlets.AdminAccess
		ID="WebServerAdminAccessPort"
		OnSave="saveSettings"
	/>
	<div id="web_server_action_error" class="w3-panel w3-red w3-padding w3-twothird" style="display: none; float: none;">
		<h3><%[ Error ]%></h3>
		<p id="web_server_action_error_msg"></p>
	</div>
	<div id="web_server_port_info" class="w3-panel w3-green w3-padding w3-twothird" style="display: none; float: none;">
		<h3><%[ Success ]%></h3>
		<p><%[ The web server settings have been updated successfully. Please load the page using the new settings. ]%></p>
		<p class="w3-center">
			<button type="button" class="w3-button w3-white" onclick="oWebServerSettings.go_to_new_page();">
				<i class="fa-solid fa-sync-alt"></i> &nbsp;<%[ Load page using new settings ]%>
			</button>
		</p>
	</div>
	<div class="w3-container w3-padding">
		<h4><%[ Network options ]%></h4>
		<div class="w3-container w3-row w3-padding">
			<div class="w3-quarter w3-col"><%[ Web server port ]%>:</div>
			<div class="w3-quarter w3-col">
				<com:TActiveTextBox
					ID="WebServerPort"
					CssClass="w3-input w3-border w3-show-inline-block"
					Width="100px"
				/>
				<com:TRequiredFieldValidator
					ValidationGroup="WebServerGroup"
					ControlToValidate="WebServerPort"
					ErrorMessage="<%[ Field required. ]%>"
					ControlCssClass="field_invalid"
					Display="Dynamic"
				/>
				&nbsp;<i class="fa fa-asterisk w3-text-red opt_req"></i>
			</div>
		</div>
	</div>
	<div class="w3-container w3-padding">
		<h4><%[ OS environment ]%></h4>
		<div>
			<div class="w3-container w3-row w3-padding">
				<div class="w3-quarter w3-col"><%[ Operating system ]%>:</div>
				<div class="w3-quarter w3-col">
					<com:TActiveDropDownList
						ID="WebServerOSProfile"
						AutoPostBack="false"
						CssClass="w3-select w3-border"
						Width="400px"
					/>
					<com:TRequiredFieldValidator
						ValidationGroup="WebServerGroup"
						ControlToValidate="WebServerOSProfile"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="field_invalid"
						Display="Dynamic"
					/>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red opt_req" style="margin-left: 22px;"></i>
			</div>
			<div class="w3-container w3-row w3-padding">
				<div class="w3-quarter w3-col"><%[ Web server name ]%>:</div>
				<div class="w3-col" style="width: 160px">
					<com:TActiveDropDownList
						ID="WebServerList"
						AutoPostBack="false"
						CssClass="w3-select w3-border"
						Width="150px"
						Attributes.onchange="oWebServerSettings.set_web_server(this.value);"
					>
						<com:TListItem Value="" Text="" />
						<com:TListItem Value="<%=Miscellaneous::WEB_SERVERS['apache']['id']%>" Text="<%=Miscellaneous::WEB_SERVERS['apache']['name']%>" />
						<com:TListItem Value="<%=Miscellaneous::WEB_SERVERS['nginx']['id']%>" Text="<%=Miscellaneous::WEB_SERVERS['nginx']['name']%>" />
						<com:TListItem Value="<%=Miscellaneous::WEB_SERVERS['lighttpd']['id']%>" Text="<%=Miscellaneous::WEB_SERVERS['lighttpd']['name']%>" />
					</com:TActiveDropDownList>
					<com:TRequiredFieldValidator
						ValidationGroup="WebServerGroup"
						ControlToValidate="WebServerList"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="field_invalid"
						Display="Dynamic"
					/>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red opt_req"></i><span id="install_web_server_ws_autodetected" class="w3-margin-left w3-small">(auto-detected)</span>
			</div>
		</div>
	</div>
	<div class="w3-container w3-row w3-padding w3-center">
		<button class="w3-button w3-green" type="button" onclick="const fm = Prado.Validation.getForm(); return (Prado.Validation.validate(fm, 'WebServerGroup') && oWebServerSettings.show_confirm_modal(true));"><i class="fa fa-save"></i> &nbsp;<%[ Save ]%></button>
	</div>
	<div id="web_server_setttings_confirm" class="w3-modal" style="display: none">
		<div class="w3-modal-content w3-card-4 w3-animate-top" style="max-width: 600px">
			<header class="w3-container w3-orange w3-text-white">
				<span onclick="oWebServerSettings.show_confirm_modal(false);" class="w3-button w3-xlarge w3-hover-gray w3-display-topright">&times;</span>
				<h2><%[ Warning ]%></h2>
			</header>
			<div class="w3-container">
				<p class="justify"><%[ New web server settings will be automatically applied to the current main API host if this API host is local (with 'localhost' address). ]%> <%[ This way the local connection between Web -> API will be adapted to new settings. ]%></p>
				<p class="justify"><%[ All other existing local API hosts definitions (if exist) have to be updated manually on the [Security] -> [API hosts] page. ]%></p>
			</div>
			<div class="w3-center">
				<button class="w3-button w3-red w3-margin-bottom" type="button" onclick="oWebServerSettings.show_confirm_modal(false);"><i class="fa fa-times"></i> &nbsp;<%[ Cancel ]%></button>
				<button class="w3-button w3-green w3-margin-bottom" type="button" onclick="<%=$this->WebServerAdminAccessPort->ClientID%>_AdminAccess.show_window(true); oWebServerSettings.show_confirm_modal(false);"><i class="fa fa-check"></i> &nbsp;<%[ OK ]%></button>
			</div>
		</div>
	</div>
</div>
<com:TCallback ID="LoadOSProfiles" OnCallback="loadOSProfiles" />
<script>
oWebServerSettings = {
	ids: {
		confirm_modal: 'web_server_setttings_confirm',
		ws_autodetected: 'install_web_server_ws_autodetected',
		port: '<%=$this->WebServerPort->ClientID%>'
	},
	web_server: '<%=$this->web_server%>',
	init: function() {
		this.load_os_profiles();
	},
	load_os_profiles: function() {
		const cb = <%=$this->LoadOSProfiles->ActiveControl->Javascript%>;
		cb.dispatch();
	},
	show_confirm_modal: function(show) {
		const modal = document.getElementById(this.ids.confirm_modal);
		modal.style.display = show ? 'block' : 'none';
	},
	set_web_server: function(value) {
		const ws_autodetected = document.getElementById(this.ids.ws_autodetected);
		ws_autodetected.style.display = (value === this.web_server) ? 'inline' : 'none';
	},
	go_to_new_page: function() {
		const prot = window.location.protocol;
		const addr = window.location.hostname;
		const port = document.getElementById(this.ids.port).value;
		const path = window.location.pathname;
		let url = '%prot//%addr:%port%path';
		url = url.replace('%prot', prot);
		url = url.replace('%addr', addr);
		url = url.replace('%port', port);
		url = url.replace('%path', path);
		window.location.href = url;
	}
};
$(function() {
	oWebServerSettings.init();
});
</script>
