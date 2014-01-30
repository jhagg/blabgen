//


$(document).ready(function() {
//console.info('asdsad');
//console.log('asdsad');
	
	var $rep = $('.reprint');

	$rep.click(function(event) {
		var $obj = $(this);
		var link = $obj.attr('href');
//console.info('link '+link);
		$obj.attr('href', 'NULL');
		$.get(link);
	});

});
