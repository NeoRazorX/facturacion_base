// JavaScript Document
		$(function() {		
						$('.calendario').datepicker({
						format: "dd-mm-yyyy",
						autoclose: true
							}).on('changeDate', function (ev) {
								$(this).datepicker('hide')
							});
					});