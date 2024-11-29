/* global wc_appointments_availability_filter_params */
jQuery( document ).ready( function( $ ) {
	'use strict';

	/*
	if ( !window.console ) {
		window.console = {
			log : function(str) {
				alert(str);
			}
		};
	}
	*/

	var wc_appointments_availability_filter = {
		init: function() {
			var date_pickers = $( '.widget_availability_filter .date-picker' );

			if ( !date_pickers.length ) {
				return;
			}

			date_pickers.each( function() {
				var picker = $( this );

				picker.datepicker( {
					dateFormat: 'yy-mm-dd',
					numberOfMonths: 1,
					showOtherMonths: true,
					changeMonth: true,
					showButtonPanel: false,
					minDate: 0,
					firstDay: wc_appointments_availability_filter_params.firstday,
					closeText: wc_appointments_availability_filter_params.closeText,
					currentText: wc_appointments_availability_filter_params.currentText,
					prevText: wc_appointments_availability_filter_params.prevText,
					nextText: wc_appointments_availability_filter_params.nextText,
					monthNames: wc_appointments_availability_filter_params.monthNames,
					monthNamesShort: wc_appointments_availability_filter_params.monthNamesShort,
					dayNames: wc_appointments_availability_filter_params.dayNames,
					dayNamesShort: wc_appointments_availability_filter_params.dayNamesShort,
					dayNamesMin: wc_appointments_availability_filter_params.dayNamesMin,
					/*dayNamesMin: wc_appointments_availability_filter_params.dayNamesShort,*/
					isRTL: wc_appointments_availability_filter_params.isRTL
				} );
			} );
		}
	};

	wc_appointments_availability_filter.init();
} );
