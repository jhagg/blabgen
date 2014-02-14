$(document).ready(function(){
$('#data-receiver').select2({
	minimumInputLength:	2,
	placeholder:		'',
	width:			'300px',
	dropdownCssClass:	'receiver-drop',
	containerCssClass:	'receiver-cont',
	formatSelection: function(item) {
		return '<div class="receiver-choice">'+item.text+'</div>';
	}
});
});
