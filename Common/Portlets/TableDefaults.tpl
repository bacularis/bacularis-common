<script>
var TABLE_TRANSLATIONS = {
	lengthMenu: '<%[ Show _MENU_ entries ]%>',
	search: '<%[ Search: ]%>',
	info: "<%[ Showing _START_ to _END_ of _TOTAL_ entries ]%>",
	zeroRecords: '<%[ No matching records found ]%>',
	infoFiltered: '(<%[ filtered from _MAX_ total entries ]%>)',
	emptyTable: '<%[ No data available in table ]%>',
	buttons: {
		copy: '<%[ Copy ]%>',
		colvis: '<%[ Column visibility ]%>'
	},
	select: {
		rows: {
			_: '<%[ %d rows selected ]%>',
			1: '<%[ 1 row selected ]%>'
		}
	},
	paginate: {
		first: '<%[ First ]%>',
		previous: '<%[ Previous ]%>',
		next: '<%[ Next ]%>',
		last: '<%[ Last ]%>'
	}
};
$.extend(true, $.fn.dataTable.defaults, {
	language: TABLE_TRANSLATIONS
});
</script>
