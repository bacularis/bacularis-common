<div class="w3-center">
	<com:TActiveLabel ID="NewAuthClientError" Display="None" CssClass="w3-text-red" EnableViewState="false"><i class="fas fa-exclamation-circle"></i> <strong><%[ Problem during save to config file. Please check users config file permission. ]%></strong></com:TActiveLabel>
	<com:TActiveLabel ID="NewAuthClientExists" Display="None" CssClass="w3-text-red" EnableViewState="false"><i class="fas fa-exclamation-circle"></i> <strong><%[ Given user already exists in config file. ]%></strong></com:TActiveLabel>
</div>
<com:TPanel ID="AuthPanel" DefaultButton="NewAuthClient">
<div class="w3-container">
	<div class="w3-padding" style="display: <%=($this->getAuthType() == 'basic' ? '' : 'none')%>">
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIBasicLogin" Text="<%[ API Login: ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TActiveTextBox
					ID="APIBasicLogin"
					CssClass="w3-input w3-border"
					CausesValidation="false"
					ReadOnly="<%=$this->Mode == 'edit'%>"
					Style="width: 70%"
				/>
				<com:TActiveHiddenField
					ID="APIBasicLoginHidden"
				/>
				<com:TRequiredFieldValidator
					Display="Dynamic"
					ControlToValidate="APIBasicLogin"
					ValidationGroup="<%=$this->ClientID%>Basic"
					Text="<%[ Please enter API login. ]%>"
				/>
				<com:TRegularExpressionValidator
					ValidationGroup="<%=$this->ClientID%>Basic"
					ControlToValidate="APIBasicLogin"
					RegularExpression="<%=BasicUserConfig::USER_PATTERN%>"
					ErrorMessage="<%[ Invalid user. User may contain a-z A-Z 0-9 characters. ]%>"
					Display="Dynamic"
				/>
			</div>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIBasicPassword" Text="<%[ API Password: ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TActiveTextBox
					ID="APIBasicPassword"
					TextMode="Password"
					MaxLength="60"
					CssClass="w3-input w3-border"
					Style="width: 70%"
					CausesValidation="false"
					PersistPassword="true"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIBasicPassword"
					ValidationGroup="<%=$this->ClientID%>Basic"
					Text="<%[ Please enter API password. ]%>"
				>
					<prop:ClientSide.OnValidate>
						sender.enabled = <%=$this->Mode == 'add' ? 'true': 'false'%>;
					</prop:ClientSide.OnValidate>
				</com:TRequiredFieldValidator>
				<com:TRegularExpressionValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIBasicPassword"
					RegularExpression="<%=BasicUserConfig::PASSWORD_PATTERN%>"
					ValidationGroup="<%=$this->ClientID%>Basic"
					Text="<%[ Password must be longer than 4 chars. ]%>"
				/>
			</div>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="RetypeAPIBasicPassword" Text="<%[ Retype password: ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TActiveTextBox
					ID="RetypeAPIBasicPassword"
					CssClass="w3-input w3-border"
					Style="width: 70%"
					TextMode="Password"
					MaxLength="60"
					PersistPassword="true"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="RetypeAPIBasicPassword"
					ValidationGroup="<%=$this->ClientID%>Basic"
					Text="<%[ Please enter retype password. ]%>"
				>
					<prop:ClientSide.OnValidate>
						sender.enabled = <%=$this->Mode == 'add' ? 'true': 'false'%>;
					</prop:ClientSide.OnValidate>
				</com:TRequiredFieldValidator>
				<com:TRegularExpressionValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="RetypeAPIBasicPassword"
					RegularExpression="<%=BasicUserConfig::PASSWORD_PATTERN%>"
					ValidationGroup="<%=$this->ClientID%>Basic"
					Text="<%[ Password must be longer than 4 chars. ]%>"
				/>
				<com:TCompareValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="RetypeAPIBasicPassword"
					ControlToCompare="APIBasicPassword"
					ValidationGroup="<%=$this->ClientID%>Basic"
					Text="<%[ Passwords must be the same. ]%>"
				/>
			</div>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIBasicBconsoleCfgPath" Text="<%[ Dedicated Bconsole config file path: ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TTextBox
					ID="APIBasicBconsoleCfgPath"
					CssClass="w3-input w3-border"
					Style="width: 70%"
					CausesValidation="false"
				/> <%[ (optional) ]%>
			</div>
		</div>
	</div>
	<div style="display: <%=($this->getAuthType() == 'oauth2' ? '' : 'none')%>">
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2ClientId" Text="<%[ OAuth2 Client ID: ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TTextBox
					ID="APIOAuth2ClientId"
					CssClass="w3-input w3-border"
					Style="width: 70%"
					CausesValidation="false"
					ReadOnly="<%=$this->Mode == 'edit'%>"
					MaxLength="32"
				/>
				<com:TActiveHiddenField
					ID="APIOAuth2ClientIdHidden"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2ClientId"
					ValidationGroup="<%=$this->ClientID%>OAuth2"
					Text="<%[ Please enter Client ID. ]%>"
				/>
				<com:TRegularExpressionValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2ClientId"
					RegularExpression="<%=OAuth2::CLIENT_ID_PATTERN%>"
					ValidationGroup="<%=$this->ClientID%>OAuth2"
					Text="<%[ Invalid Client ID value. Client ID may contain a-z A-Z 0-9 - _ characters. ]%>"
					/>
				<a href="javascript:void(0)" onclick="document.getElementById('<%=$this->APIOAuth2ClientId->ClientID%>').value = get_random_string('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_', 32); return false;" style="display: <%=$this->Mode == 'edit' ? 'none': 'inline'%>"><%[ generate ]%></a>
			</div>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2ClientSecret" Text="<%[ OAuth2 Client Secret: ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TTextBox
					ID="APIOAuth2ClientSecret"
					CssClass="w3-input w3-border"
					Style="width: 70%"
					CausesValidation="false"
					MaxLength="50"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2ClientSecret"
					ValidationGroup="<%=$this->ClientID%>OAuth2"
					Text="<%[ Please enter Client Secret. ]%>"
				/>
				<com:TRegularExpressionValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2ClientSecret"
					RegularExpression="<%=OAuth2::CLIENT_SECRET_PATTERN%>"
					ValidationGroup="<%=$this->ClientID%>OAuth2"
					Text="<%[ Invalid Client Secret value. Client Secret may contain any character that is not a whitespace character. ]%>"
				/>
				<a href="javascript:void(0)" onclick="document.getElementById('<%=$this->APIOAuth2ClientSecret->ClientID%>').value = get_random_string('ABCDEFabcdef0123456789', 40); return false;"><%[ generate ]%></a>
			</div>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2RedirectURI" Text="<%[ OAuth2 Redirect URI (example: https://baculumgui:9095/web/redirect): ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TTextBox
					ID="APIOAuth2RedirectURI"
					CssClass="w3-input w3-border"
					Style="width: 70%"
					CausesValidation="false"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2RedirectURI"
					ValidationGroup="<%=$this->ClientID%>OAuth2"
					Text="<%[ Please enter Redirect URI. ]%>"
				/>
			</div>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2Scope" Text="<%[ OAuth2 scopes (space separated): ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TTextBox
					ID="APIOAuth2Scope"
					CssClass="w3-input w3-border"
					Style="width: 70%"
					CausesValidation="false"
					TextMode="MultiLine"
				/>
				<a href="javascript:void(0)" onclick="set_scopes('<%=$this->APIOAuth2Scope->ClientID%>'); return false;" style="vertical-align: top"><%[ set all scopes ]%></a>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2Scope"
					ValidationGroup="<%=$this->ClientID%>OAuth2"
					Text="<%[ Please enter OAuth2 scopes. ]%>"
				/>
			</div>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2BconsoleCfgPath" Text="<%[ Dedicated Bconsole config file path: ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TTextBox
					ID="APIOAuth2BconsoleCfgPath"
					CssClass="w3-input w3-border"
					Style="width: 70%"
					CausesValidation="false"
				/> <%[ (optional) ]%>
			</div>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2Name" Text="<%[ Short name: ]%>" /></div>
			<div class="w3-col w3-twothird">
				<com:TTextBox
					ID="APIOAuth2Name"
					CssClass="w3-input w3-border"
					Style="width: 70%"
					CausesValidation="false"
				/> <%[ (optional) ]%>
			</div>
		</div>
	</div>
	<div class="w3-center w3-section" style="<%=($this->getShowButtons() ? '' : 'display: none;')%>">
		<com:TActiveLinkButton
			CssClass="w3-button w3-red"
			OnCallback="TemplateControl.cancelNewAuthClient"
			>
			<i class="fas fa-times"></i> &nbsp;<%[ Cancel ]%>
		</com:TActiveLinkButton>
		<com:TActiveLinkButton
			ID="NewAuthClient"
			ValidationGroup="<%=$this->ClientID%>NewAuthClientGroup"
			OnCommand="TemplateControl.saveNewAuthClient"
			Attributes.onclick="return <%=$this->ClientID%>oNewAuthClient.fields_validation()"
			CssClass="w3-button w3-green"
		>
			<prop:ClientSide.OnPreDispatch>
				$('#<%=$this->NewAuthClientError->ClientID%>').hide();
				$('#<%=$this->NewAuthClientExists->ClientID%>').hide();
			</prop:ClientSide.OnPreDispatch>
			<prop:ClientSide.OnComplete>
				$('#<%=$this->ClientID%>new_auth_client').hide();
			</prop:ClientSide.OnComplete>
			<i class="fas fa-save"></i> &nbsp;<%[ Save ]%>
		</com:TActiveLinkButton>
	</div>
</div>
</com:TPanel>
<script type="text/javascript">
var <%=$this->ClientID%>oNewAuthClient = {
	mode: '<%=$this->Mode%>',
	ids: {
		basic: {
			username: '<%=$this->APIBasicLogin->ClientID%>',
			username_hidden: '<%=$this->APIBasicLoginHidden->ClientID%>',
			password: '<%=$this->APIBasicPassword->ClientID%>',
			password_retype: '<%=$this->RetypeAPIBasicPassword->ClientID%>',
			bconsole_cfg_path: '<%=$this->APIBasicBconsoleCfgPath->ClientID%>'
		},
		oauth2: {
			client_id: '<%=$this->APIOAuth2ClientId->ClientID%>',
			client_id_hidden: '<%=$this->APIOAuth2ClientIdHidden->ClientID%>',
			client_secret: '<%=$this->APIOAuth2ClientSecret->ClientID%>',
			redirect_uri: '<%=$this->APIOAuth2RedirectURI->ClientID%>',
			scope: '<%=$this->APIOAuth2Scope->ClientID%>',
			bconsole_cfg_path: '<%=$this->APIOAuth2BconsoleCfgPath->ClientID%>',
			name: '<%=$this->APIOAuth2Name->ClientID%>'
		},
		errors: {
			generic: '<%=$this->NewAuthClientError->ClientID%>',
			exists: '<%=$this->NewAuthClientExists->ClientID%>'
		}
	},
	set_basic_props: function(props) {
		if (!props || typeof(props) != 'object') {
			return false;
		}
		if (props.hasOwnProperty('username')) {
			document.getElementById(this.ids.basic.username).value = props.username;
			document.getElementById(this.ids.basic.username_hidden).value = props.username;
			if (props.hasOwnProperty('bconsole_cfg_path')) {
				document.getElementById(this.ids.basic.bconsole_cfg_path).value = props.bconsole_cfg_path;
			}
		}
	},
	set_oauth2_props: function(props) {
		if (!props || typeof(props) != 'object') {
			return false;
		}
		if (props.hasOwnProperty('client_id')) {
			document.getElementById(this.ids.oauth2.client_id).value = props.client_id;
			document.getElementById(this.ids.oauth2.client_id_hidden).value = props.client_id;
		}
		if (props.hasOwnProperty('client_secret')) {
			document.getElementById(this.ids.oauth2.client_secret).value = props.client_secret;
		}
		if (props.hasOwnProperty('redirect_uri')) {
			document.getElementById(this.ids.oauth2.redirect_uri).value = props.redirect_uri;
		}
		if (props.hasOwnProperty('scope')) {
			document.getElementById(this.ids.oauth2.scope).value = props.scope;
		}
		if (props.hasOwnProperty('bconsole_cfg_path')) {
			document.getElementById(this.ids.oauth2.bconsole_cfg_path).value = props.bconsole_cfg_path;
		}
		if (props.hasOwnProperty('name')) {
			document.getElementById(this.ids.oauth2.name).value = props.name;
		}
	},
	clear_basic_fields: function() {
		document.getElementById(this.ids.basic.username).value = '';
		document.getElementById(this.ids.basic.username_hidden).value = '';
		document.getElementById(this.ids.basic.password).value = '';
		document.getElementById(this.ids.basic.password_retype).value = '';
		document.getElementById(this.ids.basic.bconsole_cfg_path).value = '';
	},
	clear_oauth2_fields: function() {
		document.getElementById(this.ids.oauth2.client_id).value = '';
		document.getElementById(this.ids.oauth2.client_id_hidden).value = '';
		document.getElementById(this.ids.oauth2.client_secret).value = '';
		document.getElementById(this.ids.oauth2.redirect_uri).value = '';
		document.getElementById(this.ids.oauth2.scope).value = '';
		document.getElementById(this.ids.oauth2.bconsole_cfg_path).value = '';
		document.getElementById(this.ids.oauth2.name).value = '';
	},
	hide_errors: function() {
		document.getElementById(this.ids.errors.generic).style.display = 'none';
		document.getElementById(this.ids.errors.exists).style.display = 'none';
	},
	fields_validation: function() {
		var basic = <%=($this->getAuthType() === NewAuthClient::AUTH_TYPE_BASIC ? 1 : 0)%>;
		var oauth2 = <%=($this->getAuthType() === NewAuthClient::AUTH_TYPE_OAUTH2 ? 1 : 0)%>;
		var validation_group;
		if (basic) {
			validation_group = '<%=$this->ClientID%>Basic';
		} else if (oauth2) {
			validation_group = '<%=$this->ClientID%>OAuth2';
		}
		return Prado.Validation.validate(Prado.Validation.getForm(), validation_group);
	}
};
</script>
