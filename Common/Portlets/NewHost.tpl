<div id="new_host_status">
	<com:TActivePanel ID="NewHostAddOk" Display="None" CssClass="w3-panel w3-green w3-padding-16" EnableViewState="false"><i class="fa fa-check"></i> &nbsp;<strong><%[ Host added successfully. ]%></strong></com:TActivePanel>
	<com:TActivePanel ID="NewHostAddError" Display="None" CssClass="w3-panel w3-red w3-padding-16" EnableViewState="false"><i class="fa fa-times"></i> &nbsp;<strong><%[ Problem during save to config file. Please check host config file permission. ]%></strong></com:TActivePanel>
	<com:TActivePanel ID="NewHostAddExists" Display="None" CssClass="w3-panel w3-red w3-padding-16" EnableViewState="false"><i class="fa fa-times"></i> &nbsp;<strong><%[ Given host already exists in config file. ]%></strong></com:TActivePanel>
</div>
<div id="new_host" class="w3-container w3-margin" style="display: none">
	<h2><%[ Add API host ]%></h2>
	<div class="w3-row w3-section">
		<div class="w3-col w3-third"><com:TLabel ForControl="APIProtocol" Text="<%[ Protocol: ]%>" /></div>
		<div class="w3-col w3-third">
			<com:TActiveDropDownList ID="APIProtocol" CssClass="w3-select w3-border" Width="150px" CausesValidation="false">
				<com:TListItem Value="http" Text="HTTP" />
				<com:TListItem Value="https" Text="HTTPS" Selected="true"/>
			</com:TActiveDropDownList>&nbsp;<i class="fa fa-asterisk w3-text-red" style="line-height: 40px"></i>
		</div>
	</div>
	<div class="w3-row w3-section">
		<div class="w3-col w3-third"><com:TLabel ForControl="APIAddress" Text="<%[ IP Address/Hostname: ]%>" /></div>
		<div class="w3-col w3-third">
			<com:TActiveTextBox ID="APIAddress" CssClass="w3-input w3-border" CausesValidation="false" />
			<com:TRequiredFieldValidator ValidationGroup="NewHostGroup" CssClass="validator-block" Display="Dynamic" ControlCssClass="invalidate" ControlToValidate="APIAddress" Text="<%[ Please enter API address. ]%>" />
		</div>
		&nbsp;<i class="fa fa-asterisk w3-text-red" style="line-height: 40px"></i>
	</div>
	<div class="w3-row w3-section">
		<div class="w3-col w3-third"><com:TLabel ForControl="APIPort" Text="<%[ Port: ]%>" /></div>
		<div class="w3-col w3-third">
			<com:TActiveTextBox ID="APIPort" CssClass="w3-input w3-border" CausesValidation="false" Text="9096" Width="70px" Style="display: inline-block" />
			&nbsp;<i class="fa fa-asterisk w3-text-red" style="line-height: 40px"></i>
			<com:TRequiredFieldValidator ValidationGroup="NewHostGroup" CssClass="validator-block" Display="Dynamic" ControlCssClass="invalidate" ControlToValidate="APIPort" Text="<%[ Please enter API port. ]%>" />
		</div>
	</div>
	<div class="auth_setting">
		<div class="w3-row w3-section">
			<com:TRadioButton
				ID="AuthOAuth2"
				GroupName="SelectAuth"
				CssClass="w3-radio"
				Attributes.onclick="$('#configure_basic_auth').hide();$('#configure_oauth2_auth').show();"
			/>
			<com:TLabel
				ForControl="AuthOAuth2"
				CssClass="normal w3-radio"
				Text="<%[ Use OAuth2 for authorization and authentication ]%>"
			/>
		</div>
		<div class="w3-row w3-section">
			<com:TRadioButton
				ID="AuthBasic"
				GroupName="SelectAuth"
				Checked="true"
				CssClass="w3-radio"
				Attributes.onclick="$('#configure_oauth2_auth').hide();$('#configure_basic_auth').show();"
			/>
			<com:TLabel
				ForControl="AuthBasic"
				CssClass="normal w3-radio"
				Text="<%[ Use HTTP Basic authentication ]%>"
			/>
		</div>
	</div>
	<div id="configure_basic_auth" style="display: <%=($this->AuthBasic->Checked === true) ? '' : 'none';%>">
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIBasicLogin" Text="<%[ API Login: ]%>" /></div>
			<div class="w3-col w3-third">
				<com:TActiveTextBox
					ID="APIBasicLogin"
					CssClass="w3-input w3-border"
					CausesValidation="false"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIBasicLogin"
					ValidationGroup="Basic"
					Text="<%[ Please enter API login. ]%>"
				 />
			</div>
			&nbsp;<i class="fa fa-asterisk w3-text-red" style="line-height: 40px"></i>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIBasicPassword" Text="<%[ API Password: ]%>" /></div>
			<div class="w3-col w3-third">
				<com:TActiveTextBox
					ID="APIBasicPassword"
					TextMode="Password"
					CssClass="w3-input w3-border"
					CausesValidation="false"
					PersistPassword="true"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIBasicPassword"
					ValidationGroup="Basic"
					Text="<%[ Please enter API password. ]%>"
				/>
			</div>
			&nbsp;<i class="fa fa-asterisk w3-text-red" style="line-height: 40px"></i>
		</div>
	</div>
	<div id="configure_oauth2_auth" style="display: <%=($this->AuthOAuth2->Checked === true) ? '' : 'none';%>">
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2ClientId" Text="<%[ OAuth2 Client ID: ]%>" /></div>
			<div class="w3-col w3-third">
				<com:TTextBox
					ID="APIOAuth2ClientId"
					CssClass="w3-input w3-border"
					CausesValidation="false"
					MaxLength="32"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2ClientId"
					ValidationGroup="OAuth2"
					Text="<%[ Please enter Client ID. ]%>"
				/>
				<com:TRegularExpressionValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2ClientId"
					RegularExpression="<%=OAuth2::CLIENT_ID_PATTERN%>"
					ValidationGroup="OAuth2"
					Text="<%[ Invalid Client ID value. Client ID may contain a-z A-Z 0-9 - _ characters. ]%>"
					/>
				<a style="display: <%=$this->getClientMode() ? 'none': 'inline'%>" href="javascript:void(0)" onclick="document.getElementById('<%=$this->APIOAuth2ClientId->ClientID%>').value = get_random_string('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_', 32); return false;"><%[ generate ]%></a>
			</div>
			&nbsp;<i class="fa fa-asterisk w3-text-red" style="line-height: 40px"></i>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2ClientSecret" Text="<%[ OAuth2 Client Secret: ]%>" /></div>
			<div class="w3-col w3-third">
				<com:TTextBox
					ID="APIOAuth2ClientSecret"
					CssClass="w3-input w3-border"
					CausesValidation="false"
					MaxLength="50"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2ClientSecret"
					ValidationGroup="OAuth2"
					Text="<%[ Please enter Client Secret. ]%>"
				/>
				<com:TRegularExpressionValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2ClientSecret"
					RegularExpression="<%=OAuth2::CLIENT_SECRET_PATTERN%>"
					ValidationGroup="OAuth2"
					Text="<%[ Invalid Client Secret value. Client Secret may contain any character that is not a whitespace character. ]%>"
				/>
				<a style="display: <%=$this->getClientMode() ? 'none': 'inline'%>" href="javascript:void(0)" onclick="document.getElementById('<%=$this->APIOAuth2ClientSecret->ClientID%>').value = get_random_string('ABCDEFabcdef0123456789', 40); return false;"><%[ generate ]%></a>
			</div>
			&nbsp;<i class="fa fa-asterisk w3-text-red" style="line-height: 40px"></i>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2RedirectURI" Text="<%[ OAuth2 Redirect URI (example: https://baculumgui:9095/web/redirect): ]%>" /></div>
			<div class="w3-col w3-third">
				<com:TTextBox
					ID="APIOAuth2RedirectURI"
					CssClass="w3-input w3-border"
					CausesValidation="false"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2RedirectURI"
					ValidationGroup="OAuth2"
					Text="<%[ Please enter Redirect URI. ]%>"
				/>
			</div>
			&nbsp;<i class="fa fa-asterisk w3-text-red" style="line-height: 40px"></i>
		</div>
		<div class="w3-row w3-section">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2Scope" Text="<%[ OAuth2 scopes (space separated): ]%>" /></div>
			<div class="w3-col w3-third">
				<com:TTextBox
					ID="APIOAuth2Scope"
					CssClass="w3-input w3-border"
					CausesValidation="false"
					TextMode="MultiLine"
				/>
				<com:TRequiredFieldValidator
					CssClass="validator-block"
					Display="Dynamic"
					ControlCssClass="invalidate"
					ControlToValidate="APIOAuth2Scope"
					ValidationGroup="OAuth2"
					Text="<%[ Please enter OAuth2 scopes. ]%>"
				/>
			</div>
			&nbsp;<i class="fa fa-asterisk w3-text-red" style="line-height: 40px"></i>
		</div>
		<div class="w3-row w3-section" style="display: <%=$this->getClientMode() ? 'none': 'block'%>">
			<div class="w3-col w3-third"><com:TLabel ForControl="APIOAuth2BconsoleCfgPath" Text="<%[ Dedicated Bconsole config file path: ]%>" /></div>
			<div class="w3-col w3-third">
				<com:TTextBox
					ID="APIOAuth2BconsoleCfgPath"
					CssClass="w3-input w3-border"
					CausesValidation="false"
				/> <span style="line-height: 38px"><%[ (optional) ]%></span>
			</div>
		</div>
	</div>
	<div class="w3-row w3-section">
		<div class="w3-col w3-third"><com:TLabel ForControl="APIConnectionTest" Text="<%[ API connection test: ]%>" /></div>
		<div class="w3-col w3-third">
			<table border="0" cellpadding="1px" id="new_host_status">
				<tr>
					<td align="center" valign="middle">
						<com:TActiveLinkButton ID="APIConnectionTest" CausesValidation="true" OnCallback="connectionAPITest" CssClass="w3-button w3-green">
							<prop:ClientSide.OnLoading>
								$('#<%=$this->APITestResultOk->ClientID%>').hide();
								$('#<%=$this->APITestResultErr->ClientID%>').hide();
								$('#<%=$this->APICatalogSupportYes->ClientID%>').hide();
								$('#<%=$this->APICatalogSupportNo->ClientID%>').hide();
								$('#<%=$this->APIConsoleSupportYes->ClientID%>').hide();
								$('#<%=$this->APIConsoleSupportNo->ClientID%>').hide();
								$('#<%=$this->APIConfigSupportYes->ClientID%>').hide();
								$('#<%=$this->APIConfigSupportNo->ClientID%>').hide();
								$('#<%=$this->APITestLoader->ClientID%>').show();
							</prop:ClientSide.OnLoading>
							<prop:ClientSide.OnComplete>
								$('#<%=$this->APITestLoader->ClientID%>').hide();
							</prop:ClientSide.OnComplete>
							<i class="fa fa-play"></i> &nbsp;<%[ test ]%>
						</com:TActiveLinkButton>
					</td>
					<td valign="middle">
						<com:TActiveLabel ID="APITestLoader" Display="None"><i class="fa fa-sync w3-spin"></i></com:TActiveLabel>
						<com:TActiveLabel ID="APITestResultOk" Display="None" CssClass="w3-text-green" EnableViewState="false"><i class="fa fa-check"></i> &nbsp;<%[ OK ]%></com:TActiveLabel>
						<com:TActiveLabel ID="APITestResultErr" Display="None" CssClass="w3-text-red" EnableViewState="false"><i class="fa fa-times"></i> &nbsp;<%[ Connection error ]%></com:TActiveLabel>
					</td>
				</tr>
				<tr>
					<td><%[ Catalog support ]%></td>
					<td>
						<com:TActiveLabel ID="APICatalogSupportYes" Display="None" CssClass="w3-text-green" EnableViewState="false"><i class="fa fa-check"></i> &nbsp;<strong><%[ Supported ]%></strong></com:TActiveLabel>
						<com:TActiveLabel ID="APICatalogSupportNo" Display="None" CssClass="w3-text-dark-grey" EnableViewState="false"><i class="fa fa-times"></i> &nbsp;<strong><%[ Not supported ]%></strong></com:TActiveLabel>
					</td>
				</tr>
				<tr>
					<td><%[ Console support ]%></td>
					<td>
						<com:TActiveLabel ID="APIConsoleSupportYes" Display="None" CssClass="w3-text-green" EnableViewState="false"><i class="fa fa-check"></i> &nbsp;<strong><%[ Supported ]%></strong></com:TActiveLabel>
						<com:TActiveLabel ID="APIConsoleSupportNo" Display="None" CssClass="validator-info" EnableViewState="false"><i class="fa fa-times"></i> &nbsp;<strong><%[ Not supported ]%></strong></com:TActiveLabel>
					</td>
				</tr>
				<tr>
					<td><%[ Config support ]%></td>
					<td>
						<com:TActiveLabel ID="APIConfigSupportYes" Display="None" CssClass="w3-text-green" EnableViewState="false"><i class="fa fa-check"></i> &nbsp;<strong><%[ Supported ]%></strong></com:TActiveLabel>
						<com:TActiveLabel ID="APIConfigSupportNo" Display="None" CssClass="validator-info" EnableViewState="false"><i class="fa fa-times"></i> &nbsp;<strong><%[ Not supported ]%></strong></com:TActiveLabel>
					</td>
				</tr>
			</table>
		</div>
	</div>
	<div class="w3-row w3-section" <%=($this->getForceHostName() ? 'style="display: none;"' : '')%>>
		<div class="w3-col w3-third"><com:TLabel ForControl="APIHostName" Text="<%[ Save as: ]%>" /></div>
		<div class="w3-col w3-third">
			<com:TActiveTextBox
				ID="APIHostName"
				CssClass="w3-input w3-border"
				CausesValidation="false"
			/>
		</div> <span style="line-height: 38px"><%[ (optional) ]%></span>
	</div>
	<div class="w3-container w3-center w3-padding-16" style="<%=($this->getShowButtons() ? '' : 'display: none;')%>">
		<com:TLinkButton
			CausesValidation="false"
			CssClass="w3-button w3-red"
			Attributes.onclick="$('#new_host').slideUp(); return false;"
		>
			<i class="fa fa-times"></i> &nbsp;<%[ Cancel ]%>
		</com:TLinkButton>
		<com:TActiveLinkButton
			ID="NewHost"
			ValidationGroup="NewHostGroup"
			OnCommand="TemplateControl.addNewHost"
			CssClass="w3-button w3-green"
			Attributes.onclick="return fields_validation()"
		>
			<prop:ClientSide.OnPreDispatch>
				$('#<%=$this->NewHostAddOk->ClientID%>').hide();
				$('#<%=$this->NewHostAddError->ClientID%>').hide();
				$('#<%=$this->NewHostAddExists->ClientID%>').hide();
			</prop:ClientSide.OnPreDispatch>
			<prop:ClientSide.OnComplete>
				var msg_ok = $('#<%=$this->NewHostAddOk->ClientID%>'); 
				if (msg_ok.is(':visible')) {
					$('#new_host').slideUp();
					setTimeout(function() {
						$('#<%=$this->NewHostAddOk->ClientID%>').slideUp();
					}, 5000);
					$('#new_host_status').find('span').hide();
				}
			</prop:ClientSide.OnComplete>
			<i class="fa fa-save"></i> &nbsp;<%[ Add host ]%>
		</com:TActiveLinkButton>
	</div>
</div>
<script type="text/javascript">
	var fields_validation = function() {
		var basic = document.getElementById('<%=$this->AuthBasic->ClientID%>');
		var oauth2 = document.getElementById('<%=$this->AuthOAuth2->ClientID%>');
		var validation_group;
		if (basic.checked === true) {
			validation_group = 'Basic';
		} else if (oauth2.checked === true) {
			validation_group = 'OAuth2';
		}
		return Prado.Validation.validate(Prado.Validation.getForm(), validation_group);
	}
</script>
