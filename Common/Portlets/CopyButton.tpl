<a href="javascript:void(0)" onclick="oCopyButton<%=$this->ClientID%>.copy(this);" class="raw"><i class="fa-solid fa-copy"></i> <%[ Copy ]%></a>
<script>
const oCopyButton<%=$this->ClientID%> = {
	copy: function(btn) {
		const text = document.getElementById('<%=$this->TextId%>');
		copy_to_clipboard(text.textContent);
		const icon = btn.firstChild;
		icon.classList.replace('fa-copy', 'fa-check');
		setTimeout(() => {
			icon.classList.replace('fa-check', 'fa-copy');
		}, 1500);
	}
};
</script>
