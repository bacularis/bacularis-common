<div id="certs_container" class="w3-container w3-padding">
	<com:Bacularis.Common.Portlets.AdminAccess
		ID="CertsAdminAccessCreateCert"
		OnSave="createCert"
		PostExecuteAction="oCerts.set_cert_ready"
	/>
	<com:Bacularis.Common.Portlets.AdminAccess
		ID="CertsAdminAccessRenewCert"
		OnSave="renewCert"
		PostExecuteAction="oCerts.set_cert_ready"
	/>
	<com:Bacularis.Common.Portlets.AdminAccess
		ID="CertsAdminAccessUninstallCert"
		OnSave="uninstallCert"
	/>
	<div id="certificate_action_error" class="w3-panel w3-red w3-padding w3-twothird" style="display: none; float: none;">
		<h3><%[ Error ]%></h3>
		<p id="certificate_action_error_msg"></p>
	</div>
	<div id="install_cert_info" class="w3-panel w3-green w3-padding w3-twothird" style="display: none; float: none;">
		<h3><%[ Success ]%></h3>
		<p><%[ The SSL certificate has been installed successfully. Please load the page using the HTTPS protocol. ]%></p>
		<p class="w3-center">
			<button type="button" class="w3-button w3-white" onclick="oCerts.go_to_new_page('https');">
				<i class="fa-solid fa-sync-alt"></i> &nbsp;<%[ Load page using HTTPS protocol ]%>
			</button>
		</p>
	</div>
	<div id="remove_cert_info" class="w3-panel w3-green w3-padding w3-twothird" style="display: none; float: none;">
		<h3><%[ Success ]%></h3>
		<p><%[ The SSL certificate has been uninstalled successfully. Please load the page using the HTTP protocol. ]%></p>
		<p class="w3-center">
			<button type="button" class="w3-button w3-white" onclick="oCerts.go_to_new_page('http');">
				<i class="fa-solid fa-sync-alt"></i> &nbsp;<%[ Load page using HTTP protocol ]%>
			</button>
		</p>
	</div>
	<com:TActivePanel ID="CertsInstallCert" CssClass="w3-container">
		<h4><%[ Install SSL certificate ]%></h4>
		<div class="w3-container w3-row w3-padding">
			<div class="w3-quarter w3-col"><%[ Certificate type ]%>:</div>
			<div class="w3-quarter w3-col">
				<com:TActiveDropDownList
					ID="CertsAction"
					AutoPostBack="false"
					CssClass="w3-select w3-border"
					Width="400px"
					Attributes.onchange="oCerts.show_cert_options(this.value);"
				>
					<com:TListItem Value="" Text="" />
					<com:TListItem Value="<%=LetsEncryptCert::CERT_TYPE%>" Text="<%[ Let's Encrypt certificate ]%>" />
					<com:TListItem Value="<%=SelfSignedCert::CERT_TYPE%>" Text="<%[ Self-signed certificate ]%>" />
				</com:TActiveDropDownList>
				<com:TRequiredFieldValidator
					ValidationGroup="CertsGroup"
					ControlToValidate="CertsAction"
					ErrorMessage="<%[ Field required. ]%>"
					ControlCssClass="field_invalid"
					Display="Dynamic"
				>
					<prop:ClientSide.OnValidate>
						sender.enabled = (oCerts.is_cert_available() == false);
					</prop:ClientSide.OnValidate>
				</com:TRequiredFieldValidator>
			</div> &nbsp;<i class="fa fa-asterisk w3-text-red w3-margin-left opt_req"></i>
		</div>
		<div id="install_lets_encrypt_cert" rel="cert_opts" style="display: none">
			<div id="install_lets_encrypt_cert_port_info" class="w3-panel w3-blue w3-padding w3-twothird" style="display: none; float: none;">
				<h3 style="margin: 3px auto;"><%[ Information ]%></h3>
				<p style="margin: 5px auto;"><%[ This certificate type can be useful if you are going to share Bacularis outside your local network. ]%></p>
				<p style="margin: 5px auto;"><%[ To prepare this certificate type, there is required to have Bacularis available on publicly open TCP port 80. This is necessary to perform by the certificate provider a validation that proves your control over the domain name. This it called the HTTP-01 challenge. ]%></p>
				<p style="margin: 5px auto;"><%[ After performing validation and issuing the certificate, Bacularis will be switched to HTTPS port 443 automatically. At the end port can be changed to any other port. ]%></p>
			</div>
			<h4><%[ Certificate parameters ]%></h4>
			<div class="w3-container w3-row w3-padding">
				<div class="w3-quarter w3-col"><%[ Email address ]%>:</div>
				<div class="w3-quarter w3-col">
					<com:TActiveTextBox
						ID="CertsLetsEncryptEmail"
						CssClass="w3-input w3-border"
						Width="400px"
					/>
					<com:TRequiredFieldValidator
						ValidationGroup="CertsGroup"
						ControlToValidate="CertsLetsEncryptEmail"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="field_invalid"
						Display="Dynamic"
					>
						<prop:ClientSide.OnValidate>
							const cont = document.getElementById('install_lets_encrypt_cert');
							sender.enabled = (cont.style.display != 'none');
						</prop:ClientSide.OnValidate>
					</com:TRequiredFieldValidator>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red w3-margin-left opt_req"></i>
			</div>
			<div class="w3-container w3-row w3-padding">
				<div class="w3-quarter w3-col"><%[ Common name (address, e.g. myhost.xyz) ]%>:</div>
				<div class="w3-quarter w3-col">
					<com:TActiveTextBox
						ID="CertsLetsEncryptCommonName"
						CssClass="w3-input w3-border"
						Width="400px"
						Text="<%=preg_replace('/(:\d+)?$/', '', $_SERVER['HTTP_HOST'])%>"
						ActiveControl.EnableUpdate="false"
					/>
					<com:TRequiredFieldValidator
						ValidationGroup="CertsGroup"
						ControlToValidate="CertsLetsEncryptCommonName"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="field_invalid"
						Display="Dynamic"
					/>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red w3-margin-left opt_req"></i>
			</div>
			<div class="w3-container w3-row w3-padding">
				<div class="w3-col"><%[ By clicking the create SSL certificate button, you agree with ]%> <a href="https://letsencrypt.org/repository/" target="_blank">Let's Encrypt Subscriber Agreement</a>.</div>
			</div>
		</div>
		<div id="install_self_signed_cert" rel="cert_opts" style="display: none">
			<h4><%[ Certificate parameters ]%></h4>
			<div class="w3-container w3-row w3-padding">
				<div class="w3-quarter w3-col"><%[ Common name (address, e.g. myhost.xyz) ]%>:</div>
				<div class="w3-quarter w3-col">
					<com:TActiveTextBox
						ID="CertsSelfSignedCommonName"
						CssClass="w3-input w3-border"
						Width="400px"
						Text="<%=preg_replace('/(:\d+)?$/', '', $_SERVER['HTTP_HOST'])%>"
						ActiveControl.EnableUpdate="false"
					/>
					<com:TRequiredFieldValidator
						ValidationGroup="CertsGroup"
						ControlToValidate="CertsSelfSignedCommonName"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="field_invalid"
						Display="Dynamic"
					/>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red w3-margin-left opt_req"></i>
			</div>
			<div class="w3-container w3-row w3-padding">
				<div class="w3-quarter w3-col"><%[ Validity time (days) ]%>:</div>
				<div class="w3-quarter w3-col">
					<com:TActiveTextBox
						ID="CertsSelfSignedValidityDays"
						CssClass="w3-input w3-border"
						Width="70px"
						Text="3650"
					/>
					<com:TRequiredFieldValidator
						ValidationGroup="CertsGroup"
						ControlToValidate="CertsSelfSignedValidityDays"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="field_invalid"
						Display="Dynamic"
					/>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red w3-margin-left opt_req"></i>

			</div>
			<a class="raw w3-show" href="javascript:void(0)" onclick="$('#install_self_signed_cert_additional_opts').toggle('fast');"><i class="fas fa-wrench"></i> &nbsp;<%[ Additional parameters ]%></a>
			<div id="install_self_signed_cert_additional_opts" style="display: none">
				<div class="w3-container w3-row w3-padding">
					<div class="w3-quarter w3-col"><%[ Email address ]%>:</div>
					<div class="w3-quarter w3-col">
						<com:TActiveTextBox
							ID="CertsSelfSignedEmail"
							CssClass="w3-input w3-border"
							Width="400px"
						/>
					</div>
				</div>
				<div class="w3-container w3-row w3-padding">
					<div class="w3-quarter w3-col"><%[ Country code (two letters) ]%>:</div>
					<div class="w3-quarter w3-col">
						<com:TActiveTextBox
							ID="CertsSelfSignedCountryCode"
							CssClass="w3-input w3-border"
							MaxLength="2"
							Width="70px"
						/>
					</div>
				</div>
				<div class="w3-container w3-row w3-padding">
					<div class="w3-quarter w3-col"><%[ State or province ]%>:</div>
					<div class="w3-quarter w3-col">
						<com:TActiveTextBox
							ID="CertsSelfSignedState"
							CssClass="w3-input w3-border"
							Width="400px"
						/>
					</div>
				</div>
				<div class="w3-container w3-row w3-padding">
					<div class="w3-quarter w3-col"><%[ Locality (e.g. city) ]%>:</div>
					<div class="w3-quarter w3-col">
						<com:TActiveTextBox
							ID="CertsSelfSignedLocality"
							CssClass="w3-input w3-border"
							Width="400px"
						/>
					</div>
				</div>
				<div class="w3-container w3-row w3-padding">
					<div class="w3-quarter w3-col"><%[ Organization name ]%>:</div>
					<div class="w3-quarter w3-col">
						<com:TActiveTextBox
							ID="CertsSelfSignedOrganization"
							CssClass="w3-input w3-border"
							Width="400px"
						/>
					</div>
				</div>
				<div class="w3-container w3-row w3-padding">
					<div class="w3-quarter w3-col"><%[ Organization unit (e.g. section) ]%>:</div>
					<div class="w3-quarter w3-col">
						<com:TActiveTextBox
							ID="CertsSelfSignedOrganizationUnit"
							CssClass="w3-input w3-border"
							Width="400px"
						/>
					</div>
				</div>
			</div>
		</div>
		<div id="install_existing_cert" rel="cert_opts" style="display: none">
			existing
		</div>
	</com:TActivePanel>
	<com:TActivePanel ID="CertsCertInstalled" Display="None">
		<h4><i class="fa-solid fa-certificate"></i> &nbsp;<%[ Installed SSL certificate ]%></h4>
		<div class="w3-row w3-margin-bottom">
			<a href="javascript:void(0)" onclick="W3SubTabs.open('certs_cert_installed_subtab_info', 'certs_cert_installed_info_container', '<%=$this->CertsCertInstalled->ClientID%>');">
				<div id="certs_cert_installed_subtab_info" class="subtab_btn w3-third w3-bottombar w3-hover-light-grey w3-padding w3-border-red"><%[ Certificate info ]%></div>
			</a>
			<a href="javascript:void(0)" onclick="W3SubTabs.open('certs_cert_installed_subtab_raw_output', 'certs_cert_installed_raw_output_container', '<%=$this->CertsCertInstalled->ClientID%>');">
				<div id="certs_cert_installed_subtab_raw_output" class="subtab_btn w3-third w3-bottombar w3-hover-light-grey w3-padding"><%[ Raw output ]%></div>
			</a>
		</div>
		<div id="certs_cert_installed_info_container" class="subtab_item" style="margin-left: 37px; width: 830px;">
			<h5><i class="fa-solid fa-gavel"></i> &nbsp;<%[ Issuer ]%>:</h5>
			<div style="margin-left: 37px">
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Country ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_issuer_country_code">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ State ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_issuer_state">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Locality ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_issuer_locality">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Organization name ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_issuer_organization">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Organization unit ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_issuer_organization_unit">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Common name ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_issuer_common_name">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ E-mail ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_issuer_email">-</div>
				</div>
			</div>
			<h5><i class="fa-solid fa-clock"></i> &nbsp;<%[ Validity ]%>:</h5>
			<div style="margin-left: 37px">
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Not before ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_validity_not_before">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Not after ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_validity_not_after">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ State ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_validity_state">-</div>
				</div>
			</div>
			<h5><i class="fa-solid fa-building"></i> &nbsp;<%[ Subject ]%>:</h5>
			<div style="margin-left: 37px">
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Country ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_subject_country_code">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ State ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_subject_state">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Locality ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_subject_locality">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Organization name ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_subject_organization">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Organization unit ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_subject_organization_unit">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ Common name ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_subject_common_name">-</div>
				</div>
				<div class="w3-container w3-row">
					<div class="w3-quarter w3-col"><%[ E-mail ]%>:</div>
					<div class="w3-half w3-col bold" id="installed_cert_subject_email">-</div>
				</div>
			</div>
		</div>
		<div id="certs_cert_installed_raw_output_container" class="subtab_item" style="display: none;">
			<div id="certs_cert_installed_content" class="w3-padding" style="height: 600px; overflow-y: auto; overflow-x: none;">
				<div class="w3-code">
					<pre id="certs_cert_installed_output" class="w3-small"><%=implode('<br />', $this->cert_raw_output)%></pre>
				</div>
			</div>
		</div>
	</com:TActivePanel>
	<div class="w3-container w3-padding">
		<h4><%[ OS environment ]%></h4>
		<div>
			<div class="w3-container w3-row w3-padding">
				<div class="w3-quarter w3-col"><%[ Operating system ]%>:</div>
				<div class="w3-quarter w3-col">
					<com:TActiveDropDownList
						ID="CertsOSProfile"
						AutoPostBack="false"
						CssClass="w3-select w3-border"
						Width="400px"
					/>
					<com:TRequiredFieldValidator
						ValidationGroup="CertsGroup"
						ControlToValidate="CertsOSProfile"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="field_invalid"
						Display="Dynamic"
					/>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red w3-margin-left opt_req"></i>
			</div>
			<div class="w3-container w3-row w3-padding">
				<div class="w3-quarter w3-col"><%[ Web server name ]%>:</div>
				<div class="w3-col" style="width: 160px">
					<com:TActiveDropDownList
						ID="CertsWebServer"
						AutoPostBack="false"
						CssClass="w3-select w3-border"
						Width="150px"
						Attributes.onchange="oCerts.set_web_server(this.value);"
					>
						<com:TListItem Value="" Text="" />
						<com:TListItem Value="<%=Miscellaneous::WEB_SERVERS['apache']['id']%>" Text="<%=Miscellaneous::WEB_SERVERS['apache']['name']%>" />
						<com:TListItem Value="<%=Miscellaneous::WEB_SERVERS['nginx']['id']%>" Text="<%=Miscellaneous::WEB_SERVERS['nginx']['name']%>" />
						<com:TListItem Value="<%=Miscellaneous::WEB_SERVERS['lighttpd']['id']%>" Text="<%=Miscellaneous::WEB_SERVERS['lighttpd']['name']%>" />
					</com:TActiveDropDownList>
					<com:TRequiredFieldValidator
						ValidationGroup="CertsGroup"
						ControlToValidate="CertsWebServer"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="field_invalid"
						Display="Dynamic"
					/>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red opt_req"></i><span id="install_cert_ws_autodetected" class="w3-margin-left w3-small">(auto-detected)</span>
			</div>
		</div>
	</div>
	<div class="w3-container w3-row w3-padding w3-center">
		<button type="button" id="install_cert_save_btn" class="w3-button w3-green" onclick="const fm = Prado.Validation.getForm(); return (Prado.Validation.validate(fm, 'CertsGroup') && <%=$this->CertsAdminAccessCreateCert->ClientID%>_AdminAccess.show_window(true));">
			<i class="fa-solid fa-save"></i> &nbsp;<%[ Create SSL certificate ]%>
		</button>
		<button type="button" id="install_cert_uninstall_btn" class="w3-button w3-red" onclick="const fm = Prado.Validation.getForm(); return (Prado.Validation.validate(fm, 'CertsGroup') && <%=$this->CertsAdminAccessUninstallCert->ClientID%>_AdminAccess.show_window(true));" style="display: none;">
			<i class="fa-solid fa-trash-alt"></i> &nbsp;<%[ Uninstall SSL certificate ]%>
		</button>
		<button type="button" id="install_cert_renew_btn" class="w3-button w3-green" onclick="const fm = Prado.Validation.getForm(); return (Prado.Validation.validate(fm, 'CertsGroup') && <%=$this->CertsAdminAccessRenewCert->ClientID%>_AdminAccess.show_window(true));" style="display: none;">
			<i class="fa-solid fa-redo"></i> &nbsp;<%[ Renew SSL certificate ]%>
		</button>
	</div>
</div>
<com:TCallback ID="LoadOSProfiles" OnCallback="loadOSProfiles" />
<script>
const oCerts = {
	ids: {
		self_signed_opts: 'install_self_signed_cert',
		lets_encrypt_opts: 'install_lets_encrypt_cert',
		existing_opts: 'install_existing_cert',
		common_name: '<%=$this->CertsSelfSignedCommonName->ClientID%>',
		save_btn: 'install_cert_save_btn',
		renew_btn: 'install_cert_renew_btn',
		uninstall_btn: 'install_cert_uninstall_btn',
		cert_info: 'install_cert_info',
		le_port_info: 'install_lets_encrypt_cert_port_info',
		validity_not_before: 'installed_cert_validity_not_before',
		validity_not_after: 'installed_cert_validity_not_after',
		validity_state: 'installed_cert_validity_state',
		ws_autodetected: 'install_cert_ws_autodetected'
	},
	rels: {
		cert_opts: 'cert_opts'
	},
	cert_types: {
		lets_encrypt: '<%=LetsEncryptCert::CERT_TYPE%>',
		self_signed: '<%=SelfSignedCert::CERT_TYPE%>'
	},
	cert_prop_types: [
		'issuer',
		'subject'
	],
	cert_prop_names: [
		'country_code',
		'state',
		'locality',
		'organization',
		'organization_unit',
		'common_name',
		'email'
	],
	validity_warning_treshold: 2592000, // 30 days
	cert_props: <%=json_encode($this->cert_props)%>,
	web_server: '<%=$this->web_server%>',
	init: function() {
		this.load_os_profiles();
		this.set_cert_props();
		this.set_validity_props();
		this.set_btns();
	},
	load_os_profiles: function() {
		const cb = <%=$this->LoadOSProfiles->ActiveControl->Javascript%>;
		cb.dispatch();
	},
	set_cert_props: function() {
		let el;
		for (const type of this.cert_prop_types) {
			if (!this.cert_props.hasOwnProperty(type)) {
				continue;
			}
			for (const name of this.cert_prop_names) {
				if (this.cert_props[type].hasOwnProperty(name)) {
					el = document.getElementById('installed_cert_' + type + '_' + name);
					if (el) {
						el.textContent = this.cert_props[type][name];
					}
				}
			}
		}
	},
	set_validity_props: function() {
		if (!this.cert_props.hasOwnProperty('validity')) {
			return;
		}
		const not_before = document.getElementById(this.ids.validity_not_before);
		not_before.textContent = this.cert_props.validity.not_before;
		const not_after = document.getElementById(this.ids.validity_not_after);
		not_after.textContent = this.cert_props.validity.not_after;

		const start = new Date();
		const stop = new Date(this.cert_props.validity.not_after);
		let tdiff = parseInt((stop.getTime() - start.getTime()) / 1000) + (60 * 60 * 24);
		if (tdiff < 0) {
			tdiff = 0;
		}
		const state = document.getElementById(this.ids.validity_state);
		const tperiod = Units.format_time_period(tdiff, null, true);
		const validity_val = parseInt(tperiod.value, 10) + ' ' + tperiod.format + ((tperiod.value > 1) ? 's' : '');

		const img = document.createElement('I');
		let label;
		let msg = ' %state - <%[ valid for ]%> %time';
		if (tdiff < this.validity_warning_treshold && tdiff > 0) {
			// certificate valid for a short time - warning
			state.classList.add('w3-text-orange');
			img.classList.add('fa-solid', 'fa-triangle-exclamation', 'w3-text-orange');
			label = document.createTextNode(' - ' + validity_val);
			msg = msg.replace('%state', '<%[ Close to expire ]%>');
		} else if (tdiff <= 0) {
			// certificate invalid - error
			state.classList.add('w3-text-red');
			img.classList.add('fa-solid', 'fa-circle-exclamation', 'w3-text-red');
			label = document.createTextNode(' - ' + validity_val);
			msg = msg.replace('%state', '<%[ Expired ]%>');
		} else {
			// certificate valid for long time - OK
			state.classList.add('w3-text-green');
			img.classList.add('fa-solid', 'fa-check-square', 'w3-text-green');
			msg = msg.replace('%state', '<%[ OK ]%>');
		}
		msg = msg.replace('%time', validity_val);
		label = document.createTextNode(msg);
		state.textContent = '';
		state.appendChild(img);
		state.appendChild(label);
	},
	set_btns: function() {
		const save_btn = document.getElementById(this.ids.save_btn);
		const renew_btn = document.getElementById(this.ids.renew_btn);
		const uninstall_btn = document.getElementById(this.ids.uninstall_btn);
		if (this.is_cert_available()) {
			save_btn.style.display = 'none';
			renew_btn.style.display = 'inline-block';
			uninstall_btn.style.display = 'inline-block';
		} else {
			save_btn.style.display = 'inline-block';
			uninstall_btn.style.display = 'none';
		}
	},
	is_cert_available: function() {
		return this.cert_props.hasOwnProperty('subject');
	},
	set_web_server: function(value) {
		const ws_autodetected = document.getElementById(this.ids.ws_autodetected);
		ws_autodetected.style.display = (value === this.web_server) ? 'inline' : 'none';
	},
	hide_all_cert_options: function() {
		$('div[rel="' + this.rels.cert_opts + '"]').hide();
	},
	show_cert_options: function(val) {
		this.hide_all_cert_options();
		let cont_id = '';
		if (val == this.cert_types.lets_encrypt) {
			cont_id = this.ids.lets_encrypt_opts;
		} else if (val == this.cert_types.self_signed) {
			cont_id = this.ids.self_signed_opts;
		} else if (val == this.cert_types.existing) {
			cont_id = this.ids.existing_opts;
		}
		const let_port_info = document.getElementById(this.ids.le_port_info);
		let_port_info.style.display = 'block';
		const cont = document.getElementById(cont_id);
		if (cont) {
			cont.style.display = 'block';
		}
	},
	set_cert_ready: function() {
		const cert_info = document.getElementById(oCerts.ids.cert_info);
		if (cert_info.style.display == '') {
			const save_btn = document.getElementById(oCerts.ids.save_btn);
			save_btn.style.display = 'none';
			const renew_btn = document.getElementById(oCerts.ids.renew_btn);
			renew_btn.style.display = 'none';
		}
	},
	go_to_new_page: function(prot) {
		const addr = document.getElementById(this.ids.common_name).value;
		const port = window.location.port;
		const path = window.location.pathname;
		let url = '%prot://%addr:%port%path';
		url = url.replace('%prot', prot)
		url = url.replace('%addr', addr)
		url = url.replace('%port', port);
		url = url.replace('%path', path);
		window.location.href = url;
	}
};
$(function() {
	oCerts.init();
});
</script>
