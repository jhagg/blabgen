
$(document).ready(function() {
	
	var $rep = $('.reprint');

	$rep.click(function(event) {
		var $obj = $(this);
		var link = $obj.attr('href');
		$obj.attr('href', 'NULL');
		$.get(link);
	});

});
