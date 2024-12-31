<div id="<%=$this->ClientID%>_admin_access_modal" class="w3-modal" style="display: none">
	<div class="w3-modal-content w3-card-4 w3-animate-zoom w3-margin-bottom" style="max-width: 700px">
		<header class="w3-container w3-green">
			<span onclick="<%=$this->ClientID%>_AdminAccess.show_window(false);" class="w3-button w3-display-topright">&times;</span>
			<h4><%[ Administrator access needed ]%></h4>
		</header>
		<div class="w3-container w3-margin-top">
			<div class="w3-row w3-margin-bottom">
				<div class="w3-col w3-third"><label for="<%=$this->AdminAccessName->ClientID%>"><%[ System admin name ]%>:</label></div>
				<div class="w3-half">
					<com:TActiveTextBox
						ID="AdminAccessName"
						CssClass="w3-input w3-border"
						AutoPostBack="false"
						Attributes.placeholder="e.g. root"
					/>
					<com:TRequiredFieldValidator
						ValidationGroup="<%=$this->ClientID%>AdminAccessGroup"
						ControlToValidate="AdminAccessName"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="validation-error"
						Display="Dynamic"
					/>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red opt_req"></i>

			</div>
			<div class="w3-row w3-margin-bottom">
				<div class="w3-col w3-third"><label for="<%=$this->AdminAccessPassword->ClientID%>"><%[ System admin password ]%>:</label></div>
				<div class="w3-half">
					<com:TActiveTextBox
						ID="AdminAccessPassword"
						TextMode="Password"
						CssClass="w3-input w3-border"
						AutoPostBack="false"
					/>
					<com:TRequiredFieldValidator
						ValidationGroup="<%=$this->ClientID%>AdminAccessGroup"
						ControlToValidate="AdminAccessPassword"
						ErrorMessage="<%[ Field required. ]%>"
						ControlCssClass="validation-error"
						Display="Dynamic"
					/>
				</div> &nbsp;<i class="fa fa-asterisk w3-text-red opt_req"></i>
			</div>
			<div class="w3-row">
				<div class="w3-col w3-third"><label for="<%=$this->AdminAccessUseSudo->ClientID%>"><%[ Use sudo ]%>:</label></div>
				<div class="w3-half">
					<com:TActiveCheckBox
						ID="AdminAccessUseSudo"
						CssClass="w3-check"
						AutoPostBack="false"
					/>
				</div>
			</div>
		</div>
		<footer class="w3-container w3-center">
			<div class="w3-section">
				<button class="w3-button w3-red" type="button" onclick="<%=$this->ClientID%>_AdminAccess.show_window(false);"><i class="fa fa-times"></i> &nbsp;<%[ Cancel ]%></button>
				<button class="w3-button w3-green" type="button" onclick="const fm = Prado.Validation.getForm(); return (Prado.Validation.validate(fm, '<%=$this->ClientID%>AdminAccessGroup') && <%=$this->ClientID%>_AdminAccess.execute());"><i class="fa fa-gears"></i> &nbsp;<%[ Run ]%></button>
				<i id="<%=$this->ClientID%>_admin_access_command_loader" class="fa-solid fa-sync w3-spin w3-margin-left" style="visibility: hidden;"></i>
			</div>
		</footer>
	</div>
</div>
<com:TCallback ID="AdminAccessAction" OnCallback="execute">
	<prop:ClientSide.OnComplete>
		<%=$this->ClientID%>_AdminAccess.show_window(false);
		<%=$this->ClientID%>_AdminAccess.show_command_loader(false);
		<%=$this->ClientID%>_AdminAccess.post_execute_action();
	</prop:ClientSide.OnComplete>
</com:TCallback>
<script>
const <%=$this->ClientID%>_AdminAccess = {
	ids: {
		modal: '<%=$this->ClientID%>_admin_access_modal',
		username: '<%=$this->AdminAccessName->ClientID%>',
		password: '<%=$this->AdminAccessPassword->ClientID%>',
		loader: '<%=$this->ClientID%>_admin_access_command_loader'
	},
	show_window: function(show) {
		const password = document.getElementById(this.ids.password);
		password.value = '';

		const win = document.getElementById(this.ids.modal);
		win.style.display = show ? 'block' : 'none';

		const user = document.getElementById(this.ids.username);
		user.focus();
	},
	show_command_loader: function(show) {
		const loader = document.getElementById(this.ids.loader);
		loader.style.visibility = show ? 'visible' : 'hidden';
	},
	execute: function() {
		const cb = <%=$this->AdminAccessAction->ActiveControl->Javascript%>;
		cb.dispatch();
		this.show_command_loader(true);
	},
	post_execute_action: function() {
		const post_execute_action = <%=$this->getPostExecuteAction() ?: 'false'%>;
		if (typeof(post_execute_action) == 'function') {
			post_execute_action();
		}
	}
};
</script>
