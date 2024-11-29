/* globals _, wc_appointments_date_picker_args, wc_appointment_form_params, wca_get_querystring, wca_is_valid_date, moment, rrule */

/*
 * Offset for dates to avoid comparing them at midnight. Browsers are inconsistent with how they
 * handle midnight time right before a DST time change.
 */
var HOUR_OFFSET = 12;

// globally accessible for tests
var wc_appointments_date_picker = {};

jQuery( document ).ready( function( $ ) {
	'use strict';

	var startDate;
	var endDate;
	var currentDateRange                    = {};
	var wc_appointments_locale              = window.navigator.userLanguage || window.navigator.language;
	var wc_appointments_date_picker_object  = {
		init: function() {
			$( 'body' ).on( 'change', '#wc_appointments_field_staff', this.staff_calendar );
			$( 'body' ).on( 'click', '.wc-appointments-date-picker legend small.wc-appointments-date-picker-choose-date', this.toggle_calendar );
			$( 'body' ).on( 'click', '.appointment_date_year, .appointment_date_month, .appointment_date_day', this.open_calendar );
			$( 'body' ).on( 'input', '.appointment_date_year, .appointment_date_month, .appointment_date_day', this.input_date_trigger );
			$( 'body' ).on( 'change', '.appointment_to_date_year, .appointment_to_date_month, .appointment_to_date_day', this.input_date_trigger );
			$( '.wc-appointments-date-picker legend small.wc-appointments-date-picker-choose-date' ).show();
			$( '.wc-appointments-date-picker' ).each( function() {
				var form          = $( this ).closest( 'form' );
				var picker        = form.find( '.picker:eq(0)' );
				var fieldset      = $( this ).closest( 'fieldset' );
				var duration_unit = picker.attr( 'data-duration_unit' );

				// Only when NOT minute or hour duration type.
				if ( -1 === $.inArray( duration_unit, ['minute', 'hour'] ) ) {
					form.on( 'addon-duration-changed', function( event, duration ) {
						// Update 'data-combined_duration'.
						var appointment_duration = parseInt( picker.attr( 'data-appointment_duration' ), 10 );
						var addon_duration = parseInt( duration, 10 );
						var combined_duration = parseInt( appointment_duration + addon_duration, 10 );

						picker.data( 'combined_duration', combined_duration );
						picker.data( 'addon_duration', duration );

						// Highlight next selected days.
						wc_appointments_date_picker.highlight_days( form );
					} );
				}

				wc_appointments_date_picker.date_picker_init( picker );

				$( '.wc-appointments-date-picker-date-fields', fieldset ).hide();
				$( '.wc-appointments-date-picker-choose-date', fieldset ).hide();
			} );
		},

		highlight_days: function( form ) {
			var picker            = form.find( '.picker' );
			var selected_day      = picker.find( 'td.ui-datepicker-current-day' ).not( '.ui-state-disabled' );
			var duration          = picker.attr( 'data-appointment_duration' );
			var combined_duration = picker.data( 'combined_duration' ) ? picker.data( 'combined_duration' ) : duration;
			var days_needed       = ( 1 > combined_duration ) ? 1 : combined_duration;
			var days_highlighted  = days_needed - 1;

			// Empty previous selected months.
			picker.find( 'td' ).removeClass( 'ui-datepicker-selected-day' );

			// Highlight next selected slots,
			// when duration is above 0.
			if ( 0 < days_highlighted ) {
				// Add .selected-month class to selected months.
				selected_day.nextAll( 'td' ).add( selected_day.closest( 'tr' ).nextAll().find( 'td' ) ).slice( 0, days_highlighted ).addClass( 'ui-datepicker-selected-day' );
			}
		},

		staff_calendar: function() {
			var $picker = $( this ).closest( 'form' ).find( '.picker:eq(0)' );
			wc_appointments_date_picker.date_picker_init( $picker );
		},

		toggle_calendar: function() {
			var $picker = $( this ).closest( 'fieldset' ).find( '.picker:eq(0)' );
			wc_appointments_date_picker.date_picker_init( $picker );
			$picker.slideToggle();
		},

		open_calendar: function() {
			var $picker = $( this ).closest( 'fieldset' ).find( '.picker:eq(0)' );
			wc_appointments_date_picker.date_picker_init( $picker );
			$picker.slideDown();
		},

		input_date_trigger: function() {
			var $fieldset = $( this ).closest( 'fieldset' );
			var $picker   = $fieldset.find( '.picker:eq(0)' );
			var year      = parseInt( $fieldset.find( 'input.appointment_date_year' ).val(), 10 );
			var month     = parseInt( $fieldset.find( 'input.appointment_date_month' ).val(), 10 );
			var day       = parseInt( $fieldset.find( 'input.appointment_date_day' ).val(), 10 );

			if ( year && month && day ) {
				var date = new Date( year, month - 1, day );

				// Set selected date for datepicker.
				$picker.datepicker( 'setDate', date );

				// Fire up 'date-selected' trigger.
				// $fieldset.triggerHandler( 'date-selected', date );
			}
		},

		select_date_trigger: function( date ) {
			var fieldset             = $( this ).closest( 'fieldset' );
			var form                 = fieldset.closest( 'form' );
			var picker               = form.find( '.picker:eq(0)' );
			var parsed_date          = date.split( '-' );
			var year                 = parseInt( parsed_date[0], 10 );
			var month                = parseInt( parsed_date[1], 10 );
			var day                  = parseInt( parsed_date[2], 10 );
			var duration_unit        = picker.attr( 'data-duration_unit' );
			var appointment_duration = picker.attr( 'data-appointment_duration' );
			var combined_duration    = picker.data( 'combined_duration' ) ? picker.data( 'combined_duration' ) : appointment_duration;

			// Only when NOT minute or hour duration type.
			if ( -1 === $.inArray( duration_unit, ['minute', 'hour'] ) ) {
				// Full appointment duration length.
				var days_needed      = ( 1 > combined_duration ) ? 1 : combined_duration;
				var days_highlighted = days_needed;

				startDate = new Date( year, month - 1, day );
				endDate = new Date( year, month - 1, day + ( parseInt( days_highlighted, 10 ) - 1 ) );
			}

			// Set fields
			fieldset.find( 'input.appointment_to_date_year' ).val( '' );
			fieldset.find( 'input.appointment_to_date_month' ).val( '' );
			fieldset.find( 'input.appointment_to_date_day' ).val( '' );

			fieldset.find( 'input.appointment_date_year' ).val( parsed_date[0] );
			fieldset.find( 'input.appointment_date_month' ).val( parsed_date[1] );
			fieldset.find( 'input.appointment_date_day' ).val( parsed_date[2] ).change();

			// Disable submit button.
			form.find( '.wc-appointments-appointment-form-button' ).prop( 'disabled', true );

			// Fire up 'date-selected' trigger.
			form.triggerHandler( 'date-selected', date );
		},

		date_picker_init: function( element ) {
			var WC_DatePicker = new WC_Appointments_DatePicker( element );

			// if date is in querystring and it is valid,
			// then set it as default date for datepicker
			if ( null !== wca_get_querystring( 'date' ) ) {
				if ( wca_is_valid_date( wca_get_querystring( 'date' ) ) ) {
					WC_DatePicker.set_default_params( {
						defaultDate: wca_get_querystring( 'date' )
					} );
				}
			}

			WC_DatePicker.set_default_params( {
				onSelect: wc_appointments_date_picker.select_date_trigger,
				minDate: WC_DatePicker.get_data_attr( 'min_date' ),
				maxDate: WC_DatePicker.get_data_attr( 'max_date' ),
				defaultDate: wca_get_querystring( 'date' ),
				changeMonth: WC_DatePicker.get_custom_data( 'changeMonth' ),
				changeYear: WC_DatePicker.get_custom_data( 'changeYear' ),

				showWeek: WC_DatePicker.get_custom_data( 'showWeek' ),
				showOn: WC_DatePicker.get_custom_data( 'showOn' ),
				numberOfMonths: parseInt( WC_DatePicker.get_custom_data( 'numberOfMonths' ) ),
				showButtonPanel: WC_DatePicker.get_custom_data( 'showButtonPanel' ),
				showOtherMonths: WC_DatePicker.get_custom_data( 'showOtherMonths' ),
				selectOtherMonths: WC_DatePicker.get_custom_data( 'selectOtherMonths' ),
				gotoCurrent: WC_DatePicker.get_custom_data( 'gotoCurrent' ),

				closeText: WC_DatePicker.get_custom_data( 'closeText' ),
				currentText: WC_DatePicker.get_custom_data( 'currentText' ),
				prevText: WC_DatePicker.get_custom_data( 'prevText' ),
				nextText: WC_DatePicker.get_custom_data( 'nextText' ),
				monthNames: WC_DatePicker.get_custom_data( 'monthNames' ),
				monthNamesShort: WC_DatePicker.get_custom_data( 'monthNamesShort' ),
				dayNames: WC_DatePicker.get_custom_data( 'dayNames' ),
				/*dayNamesShort: WC_DatePicker.get_custom_data( 'dayNamesShort' ),*/
				/*dayNamesMin: WC_DatePicker.get_custom_data( 'dayNamesMin' ),*/
				dayNamesMin: WC_DatePicker.get_custom_data( 'dayNamesShort' ),

				firstDay: WC_DatePicker.get_custom_data( 'firstDay' ),
				isRTL: WC_DatePicker.get_custom_data( 'isRTL' ),
				beforeShowDay: WC_DatePicker.maybe_load_from_cache.bind( WC_DatePicker ),
				onChangeMonthYear: function( year, month ) {
					this.get_data( year, month )
						.done( this.applyStylesToDates );
				}.bind( WC_DatePicker )
			} );

			WC_DatePicker.create();

			wc_appointments_date_picker.get_day_attributes = WC_DatePicker.maybe_load_from_cache.bind( WC_DatePicker );
		},

		refresh_datepicker: function() {
			var $picker = $( '.wc-appointments-date-picker' ).find( '.picker:eq(0)' );
			$picker.datepicker( 'refresh' );
		},

		get_number_of_days: function( defaultNumberOfDays, form, picker ) {
			var number_of_days       = defaultNumberOfDays;
			var duration_unit        = picker.attr( 'data-duration_unit' );
			var appointment_duration = picker.attr( 'data-appointment_duration' );
			var combined_duration    = picker.data( 'combined_duration' ) ? picker.data( 'combined_duration' ) : appointment_duration;
			var availability_span    = picker.attr( 'data-availability_span' );

			// Only when NOT minute or hour duration type.
			if ( -1 === $.inArray( duration_unit, ['minute', 'hour'] ) ) {
				number_of_days = ( 1 > combined_duration ) ? 1 : combined_duration;
			}

			if ( 1 > number_of_days || 'start' === availability_span ) {
				number_of_days = 1;
			}

			return number_of_days;
		},

		is_slot_appointable: function( args ) {
			var appointable = args.default_availability;

			// Loop all the days we need to check for this slot.
			for ( var i = 0; i < args.number_of_days; i++ ) {
				var the_date     = new Date( args.start_date );
				the_date.setDate( the_date.getDate() + i );

				var year        = the_date.getFullYear();
				var month       = the_date.getMonth() + 1;
				var day         = the_date.getDate();
				var day_of_week = the_date.getDay();
				var ymdIndex    = year + '-' + month + '-' + day;

				// var week        = $.datepicker.iso8601Week( the_date );

				// Sunday is 0, Monday is 1, and so on.
				if ( 0 === day_of_week ) {
					day_of_week = 7;
				}

				// Is staff available in current date?
				// Note: staff_id = 0 is product's availability rules.
				// Each staff rules also contains product's rules.
				var staff_args = {
					date: the_date,
					default_availability: args.default_availability
				};
				var staff_rules = args.availability[ args.staff_id ];
				appointable = wc_appointments_date_picker.is_staff_available_on_date( staff_args, staff_rules );

				// In case all staff is assigned together.
				if ( 'all' === args.staff_assignment && args.has_staff ) {
					var all_staff_args = $.extend(
						{
							availability: args.availability,
							fully_scheduled_days: args.fully_scheduled_days
						},
						staff_args
					);

					appointable = wc_appointments_date_picker.has_all_available_staff( all_staff_args );

				// In case no preference is selected
				// and more than one staff is assigned.
				} else if ( 'customer' === args.staff_assignment && 0 === args.staff_id && args.has_staff && 1 < args.has_staff ) {
					var customer_staff_args = $.extend(
						{
							availability: args.availability,
							fully_scheduled_days: args.fully_scheduled_days,
							staff_count: args.has_staff
						},
						staff_args
					);

					appointable = wc_appointments_date_picker.has_any_available_staff( customer_staff_args );
				}

				// Fully scheduled one entire days?
				if ( args.fully_scheduled_days[ ymdIndex ] ) {
					if ( args.fully_scheduled_days[ ymdIndex ][0] || args.fully_scheduled_days[ ymdIndex ][ args.staff_id ] ) {
						appointable = false;
					}
				}

				if ( !appointable ) {
					break;
				}
			}

			return appointable;
		},

		rrule_cache: {},

		/**
		 * Goes through all the rules and applies then to them to see if appointment is available
		 * for the given date.
		 *
		 * Rules are recursively applied. Rules later array will override rules earlier in the array if
		 * applicable to the slot being checked.
		 *
		 * @param args
		 * @param rules array of rules in order from lowest override power to highest.
		 *
		 * @returns boolean
		 */
		is_staff_available_on_date: function( args, rules ) {
			if ( 'object' !== typeof args || 'object' !== typeof rules ) {
				return false;
			}

			var defaultAvailability = args.default_availability;
			var year         = args.date.getFullYear();
			var month        = args.date.getMonth() + 1; // months start at 0
			var day          = args.date.getDate();
			var day_of_week  = args.date.getDay();
			var ymdIndex     = year + '-' + month + '-' + day;

			var	firstOfJanuary = new Date( year, 0, 1 );
			var week =  Math.ceil( ( ( ( args.date - firstOfJanuary ) / 86400000 ) + firstOfJanuary.getDay() + 1 ) / 7 );

			// Sunday is 0, Monday is 1, and so on.
			if ( 0 === day_of_week ) {
				day_of_week = 7;
			}

			// `args.fully_scheduled_days` and `args.staff_id` only available
			// when checking 'automatic' staff assignment.
			if ( args.fully_scheduled_days && args.fully_scheduled_days[ ymdIndex ] && args.fully_scheduled_days[ ymdIndex ][ args.staff_id ] ) {
				return false;
			}

			var minutesAvailableForDay = [];
			var minutesForADay         = _.range( 1, 1440, 1 );
			// Ensure that the minutes are set when the all slots are available by default.
			if ( defaultAvailability ) {
				minutesAvailableForDay = minutesForADay;
			}

			$.each( rules, function( index, rule ) {
				var type  = rule.type; // rule['type']
				var range = rule.range; // rule['range']
				var minutesAvailableForTime;

				// must be Object and not array.
				if ( $.isArray( range ) ) {
					return true;
				}

				try {
					switch ( type ) {
						case 'months':
							if ( 'undefined' !== typeof range[ month ] ) {
								if ( range[ month ] ) {
									minutesAvailableForDay = minutesForADay;
								} else {
									minutesAvailableForDay = [];
								}
								return true; // go to the next rule
							}
							break;
						case 'weeks':
							if ( 'undefined' !== typeof range[ week ] ) {
								if ( range[ week ] ) {
									minutesAvailableForDay = minutesForADay;
								} else {
									minutesAvailableForDay = [];
								}
								return true; // go to the next rule
							}
							break;
						case 'days':
							if ( 'undefined' !== typeof range[ day_of_week ] ) {
								if ( range[ day_of_week ] ) {
									minutesAvailableForDay = minutesForADay;
								} else {
									minutesAvailableForDay = [];
								}
								return true; // go to the next rule
							}
							break;
						case 'custom':
							if ( 'undefined' !== typeof range[ year ][ month ][ day ] ) {
								if ( range[ year ][ month ][ day ] ) {
									minutesAvailableForDay = minutesForADay;
								} else {
									minutesAvailableForDay = [];
								}
								return true; // go to the next rule
							}
							break;
						case 'rrule':
							var is_all_day = -1 === range.from.indexOf( ':' );
							var current_date = moment.utc( args.date );
							var current_date_sod = current_date.clone().startOf( 'day' );
							var from_date = moment.utc( range.from );
							var to_date = is_all_day ? moment.utc( range.to ).add( 1, 'days' ) : moment.utc( range.to );
							var duration = moment.duration( to_date.diff( from_date ) );
							var rrule_string = rrule.rrulestr( range.rrule, { dtstart: from_date.toDate() } );

							/*
							console.log( ymdIndex );
							console.log( currentDateRange.startDate );
							console.log( currentDateRange.endDate );
							console.log( current_date_sod );
							console.log( rrule_string );
							*/

							var cache_key = index + currentDateRange.startDate + currentDateRange.endDate;

							if ( 'undefined' === typeof wc_appointments_date_picker.rrule_cache[ cache_key ] ) {
								wc_appointments_date_picker.rrule_cache[ cache_key ] = rrule_string.between(
									moment.utc( currentDateRange.startDate ).subtract( duration ).subtract( 1, 'days' ).toDate(),
									moment.utc( currentDateRange.endDate ).subtract( duration ).add( 1, 'days' ).toDate(),
									true
								).map( function( occurrence ) {
									return new moment( occurrence );
								} );
							}

							wc_appointments_date_picker.rrule_cache[cache_key].forEach( function( occurrence ) {
								var occurrence_sod = occurrence.clone().startOf( 'day' );
								var end_occurrence = occurrence.clone().add( duration );
								var end_occurrence_sod = end_occurrence.clone().startOf( 'day' );

								if ( current_date_sod.isSameOrAfter( occurrence_sod ) && current_date_sod.isBefore( end_occurrence_sod ) ) {
									if ( is_all_day ) {
										minutesAvailableForDay = range.rule ? minutesForADay : [];
									} else if ( current_date_sod.isSame( occurrence_sod ) ) {
										var minutesFromStartOfDay = moment.duration( occurrence.diff( occurrence_sod ) ).asMinutes();
										minutesAvailableForTime = _.range( minutesFromStartOfDay, minutesFromStartOfDay + duration.asMinutes(), 1 );

										if ( range.rule ) {
											minutesAvailableForDay = _.union( minutesAvailableForDay, minutesAvailableForTime );
										} else {
											minutesAvailableForDay = _.difference( minutesAvailableForDay, minutesAvailableForTime );
										}
									} else if ( current_date_sod.isAfter( occurrence_sod ) && current_date_sod.isBefore( end_occurrence_sod ) ) {
										// Event is a multi-day event with start and end time but current day is fully inside the start day and end days
										minutesAvailableForDay = range.rule ? minutesForADay : [];
									} else if ( current_date_sod.isSame( end_occurrence_sod ) ) {
										// Event is multi-day and current day is the last day of event. Find how many minutes there are before end time.
										minutesAvailableForTime = _.range( 1, moment.duration( end_occurrence.diff( end_occurrence_sod ) ).asMinutes(), 1 );

										if ( range.rule ) {
											minutesAvailableForDay = _.union( minutesAvailableForDay, minutesAvailableForTime );
										} else {
											minutesAvailableForDay = _.difference( minutesAvailableForDay, minutesAvailableForTime );
										}
									}
								}
							} );

							break;
						case 'time':
						case 'time:1':
						case 'time:2':
						case 'time:3':
						case 'time:4':
						case 'time:5':
						case 'time:6':
						case 'time:7':
							var fromHour = parseInt( range.from.split( ':' )[0] );
							var fromMinute = parseInt( range.from.split( ':' )[1] );
							var fromMinuteNumber = fromMinute + ( fromHour * 60 );
							var toHour = parseInt( range.to.split( ':' )[0] );
							var toMinute = parseInt( range.to.split( ':' )[1] );
							var toMinuteNumber = toMinute + ( toHour * 60 );
							var toMidnight = ( 0 === toHour && 0 === toMinute );
							var slotNextDay = false;

							// Enable next day on calendar, when toHour is less than fromHour and not midnight.
							// When overnight is sunday, make sure it goes to monday next day.
							var prev_day = 0 === ( day_of_week - 1 ) ? 7 : ( day_of_week - 1 );
							if ( ( !toMidnight ) && ( toMinuteNumber <= fromMinuteNumber ) && ( range.day === prev_day ) ) {
								slotNextDay = range.day;
							}

							if ( day_of_week === range.day || 0 === range.day || slotNextDay === range.day ) {
								// Make sure next day toHour adds 24 hours.
								if ( toMinuteNumber <= fromMinuteNumber ) {
									toHour += 24;
									toMinuteNumber = toMinute + ( toHour * 60 );
								}

								// each minute in the day gets a number from 1 to 1440
								minutesAvailableForTime = _.range( fromMinuteNumber, toMinuteNumber, 1 );

								if ( range.rule ) {
									minutesAvailableForDay = _.union( minutesAvailableForDay, minutesAvailableForTime );
								} else {
									minutesAvailableForDay = _.difference( minutesAvailableForDay, minutesAvailableForTime );
								}

								return true;
							}
							break;
						case 'time:range':
						case 'custom:daterange':
							range = range[ year ][ month ][ day ];
							var fromHour2 = parseInt( range.from.split( ':' )[0] );
							var fromMinute2 = parseInt( range.from.split( ':' )[1] );
							var toHour2 = parseInt( range.to.split( ':' )[0] );
							var toMinute2 = parseInt( range.to.split( ':' )[1] );

							// Make sure next day toHour adds 24 hours.
							if ( ( toHour2 <= fromHour2 ) && ( toMinute2 <= fromMinute2 ) ) {
								toHour2 += 24;
							}

							// each minute in the day gets a number from 1 to 1440
							var fromMinuteNumber2 = fromMinute2 + ( fromHour2 * 60 );
							var toMinuteNumber2 = toMinute2 + ( toHour2 * 60 );
							minutesAvailableForTime = _.range( fromMinuteNumber2, toMinuteNumber2, 1 );

							if ( range.rule ) {
								minutesAvailableForDay = _.union( minutesAvailableForDay, minutesAvailableForTime );
							} else {
								minutesAvailableForDay = _.difference( minutesAvailableForDay, minutesAvailableForTime );
							}

							//return true;
							break;
					}
				} catch ( err ) {
					return true; // go to the next rule
				}
			} );

			return !_.isEmpty( minutesAvailableForDay );
		},

		get_week_number: function( date ) {
			var January1 = new Date( date.getFullYear(), 0, 1 );
			var week     = Math.ceil( ( ( ( date - January1 ) / 86400000 ) + January1.getDay() + 1 ) / 7 );

			return week;
		},

		has_all_available_staff: function( args ) {
			var all_staff_assignment = [];
			for ( var staff_id in args.availability ) {
				if ( args.availability.hasOwnProperty( staff_id ) ) {
					staff_id = parseInt( staff_id, 10 );

					// Skip staff_id '0' that has been performed before.
					if ( 0 === staff_id ) {
						continue;
					}

					var staff_rules = args.availability[ staff_id ];
					args.staff_id = staff_id;

					// Return false when all staff assigned at once
					// and any of the staff is unavailable.
					if ( !wc_appointments_date_picker.is_staff_available_on_date( args, staff_rules ) ) {
						all_staff_assignment.push( false );
					}
				}
			}

			// All assigned staff available on date.
			if ( !all_staff_assignment.includes( false ) ) {
				//console.log( args.date );
				return true;
			}

			return false;
		},

		has_any_available_staff: function( args ) {
			var any_staff_assignment = [];
			for ( var staff_id in args.availability ) {
				if ( args.availability.hasOwnProperty( staff_id ) ) {
					staff_id = parseInt( staff_id, 10 );

					// Skip staff_id '0' that has been performed before.
					if ( 0 === staff_id ) {
						continue;
					}

					var staff_rules = args.availability[ staff_id ];
					args.staff_id = staff_id;

					// Return false when all staff assigned at once
					// and any of the staff is unavailable.
					if ( wc_appointments_date_picker.is_staff_available_on_date( args, staff_rules ) ) {
						any_staff_assignment.push( true );
					}
				}
			}

			//console.log( any_staff_assignment );

			// All assigned staff available on date.
			if ( any_staff_assignment.includes( true ) ) {
				//console.log( args.date );
				return true;
			}

			return false;
		},

		get_format_date: function( date ) {
			// 1970, 1971, ... 2015, 2016, ...
			var yyyy = date.getFullYear();
			// 01, 02, 03, ... 10, 11, 12
			var MM = ( 10 > ( date.getMonth() + 1 ) ? '0' : '' ) + ( date.getMonth() + 1 );
			// 01, 02, 03, ... 29, 30, 31
			var dd = ( 10 > date.getDate() ? '0' : '' ) + date.getDate();

			// create the format you want
			return ( yyyy + '-' + MM + '-' + dd );
		},

		get_relative_date: function( relDateAttr ) {
			var minDate = new Date();
			var pattern = /([+-]?[0-9]+)\s*(d|D|w|W|m|M|y|Y)?/g;
			var matches = pattern.exec( relDateAttr );
			while ( matches ) {
				switch ( matches[2] || 'd' ) {
					case 'd' : case 'D' :
						minDate.setDate( minDate.getDate() + parseInt( matches[1], 10 ) );
						break;
					case 'w' : case 'W' :
						minDate.setDate( ( minDate.getDate() + parseInt( matches[1], 10 ) ) * 7 );
						break;
					case 'm' : case 'M' :
						minDate.setMonth( minDate.getMonth() + parseInt( matches[1], 10 ) );
						break;
					case 'y': case 'Y' :
						minDate.setYear( minDate.getFullYear() + parseInt( matches[1], 10 ) );
						break;
				}
				matches = pattern.exec( relDateAttr );
			}
			return minDate;
		},

		find_available_date_within_month: function() {
			var nextConsectiveDates = [];

			$.each( $( '.appointable:not(.ui-state-disabled)' ).find( '.ui-state-default' ), function( i, value ) {
				var numericDate = +$( value ).text();
				if ( numericDate ) {
					nextConsectiveDates.push( numericDate );
				}
			} );

			var nextAvailDate = nextConsectiveDates[0];

			return nextAvailDate;
		},

		filter_selectable_day: function( a, b ) {
			var next_selectable_el = a.filter( function() {
				return Number( $( this ).text() ) === b;
			} );
			return next_selectable_el;
		},

		filter_day_parent: function( a, b ) {
			var day_parent_el = a.filter( function() {
				return Number( $( this ).text() ) === b;
			} ).parent();
			return day_parent_el;
		},

		add_selector_attributes: function( selector, attributes ) {
			$.each( attributes, function( key, val ) {
				selector.attr( key, ( $.isArray( val ) ) ? val.join( ' ' ) : val );
			} );
		}
	};

	/**
	 * Represents a jQuery UI DatePicker.
	 *
	 * @constructor
	 * @version 3.7.4
	 * @since   3.7.4
	 * @param   {object} element - jQuery object for the picker that was initialized.
	 * @param   {object} opts - Optional arguments.
	 */
	var WC_Appointments_DatePicker = function WC_Appointments_DatePicker( element ) {
		this.$picker    = $( element );
		this.$form      = this.$picker.closest( 'form, .cart' );
		this.customData = {};
		this.opts       = {
			cache: false
		};
		this.cache      = {
			data: {},
			attributes: {}
		};

		$.each( wc_appointment_form_params, function( key, val ) {
			this.customData[ key ] = val;
		}.bind( this ) );

		if ( this.customData.cache_ajax_requests && ( 'true' === this.customData.cache_ajax_requests.toLowerCase() || 'false' === this.customData.cache_ajax_requests.toLowerCase() ) ) {
			this.opts.cache = 'true' === this.customData.cache_ajax_requests.toLowerCase();
		}

		/*
		if ( !this.$picker.length ) {
			return;
		}
		*/
	};

	/**
	 * Creates the DatePicker referenced by initializing the first data call.
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 */
	WC_Appointments_DatePicker.prototype.create = function create() {
		var year        = parseInt( this.$form.find( 'input.appointment_date_year' ).val(), 10 );
		var month       = parseInt( this.$form.find( 'input.appointment_date_month' ).val(), 10 );
		var day         = parseInt( this.$form.find( 'input.appointment_date_day' ).val(), 10 );
		var currentDate = this.get_default_date();

		this.$picker
			.empty()
			.removeClass( 'hasDatepicker' )
			.datepicker( this.get_default_params() );

		if ( year && month && day ) {
			this.$picker.datepicker( 'setDate', new Date( year, month - 1, day ) );
		}

		var picker_year = this.$picker.datepicker( 'getDate' ).getFullYear();
		var picker_month = this.$picker.datepicker( 'getDate' ).getMonth() + 1;

		this.get_data( picker_year, picker_month )
			.done( this.applyStylesToDates )
			.done( function() {
				var curr_day;
				var has_no_selectable_dates;
				var next_selectable_day;
				var next_selectable_el;

				// Auto-select first available day.
				// If date is in querystring, select it instead of the first day
				// it overrides autoselect setting.
				// Note: don't autoselect on change events, like staff select box change
				var is_autoselect = this.$picker.attr( 'data-is_autoselect' );

				if (
					// Auto-select, when NO date querystring is present.
					( null === wca_get_querystring( 'date' ) && is_autoselect ) ||
					// Auto-select, when date querystring is present.
					( null !== wca_get_querystring( 'date' ) && wca_is_valid_date( wca_get_querystring( 'date' ) ) )
				) {
					this.$picker.datepicker( 'refresh' );

					// Set to start month to make sure
					// it can check all month, when
					// current month is in future and
					// current month has no selectable days.
					has_no_selectable_dates = this.$picker.find( '.ui-datepicker-current-day' );
					if ( has_no_selectable_dates.hasClass( 'ui-datepicker-unselectable' ) ) {
						this.$picker.datepicker( 'setDate', new Date( currentDate.getFullYear(), currentDate.getMonth() - 1, 1 ) );
					}

					curr_day = this.$picker.find( '.ui-datepicker-current-day' );
					if ( curr_day.hasClass( 'ui-datepicker-unselectable' ) ) {
						// Repeat for next 12 months max.
						for ( var i = 1; 12 > i; i++ ) {
							next_selectable_day = wc_appointments_date_picker.find_available_date_within_month();
							next_selectable_el = wc_appointments_date_picker.filter_selectable_day(
								$( '.ui-state-default' ),
								next_selectable_day
							);
							/*
							next_selectable_el = $( '.ui-state-default' ).filter( function() {
								return ( Number( $( this ).text() ) === next_selectable_day );
							} );
							*/

							// Found available day, break the loop.
							if ( 0 < next_selectable_el.length ) {
								next_selectable_el.click();
								break;
							} else {
								this.$picker.find( '.ui-datepicker-next' ).click();
							}
						}
					} else {
						curr_day.click();
					}
				} else {
					$( '.ui-datepicker-current-day' ).removeClass( 'ui-datepicker-current-day' );
				}
			} );
	};

	/**
	 * Applys attributes to the dates on the datepicker. This is necessary since we cant
	 * defer beforeShowDay until after our data is loaded.
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 * @param   {object} dateRange - Date range for attributes to be applied to.
	 */
	WC_Appointments_DatePicker.prototype.applyStylesToDates = function applyStylesToDates( dateRange ) {
		currentDateRange = dateRange; // Provide public access so rrules can cache all days displayed.

		dateRange.startDate.setHours( HOUR_OFFSET );
		dateRange.endDate.setHours( HOUR_OFFSET );
		var checkDate = new Date( dateRange.startDate.valueOf() ); // new object so we don't modify the original.

		while ( checkDate < dateRange.endDate ) {
			var cacheKey = checkDate.getTime();

			if ( !this.cache.attributes[ cacheKey ] ) {
				var attributes	= this.getDateElementAttributes( checkDate );
				var selector    = wc_appointments_date_picker.filter_day_parent(
					$( 'td[data-month="' + checkDate.getMonth() + '"] a', this.$picker ),
					checkDate.getDate()
				);

				if ( !attributes.selectable ) {
					attributes.class.push( 'ui-datepicker-unselectable' );
					attributes.class.push( 'ui-state-disabled' );
				}

				if ( checkDate.setHours( HOUR_OFFSET, 0, 0, 0 ) === new Date().setHours( HOUR_OFFSET, 0, 0, 0 ) ) {
					attributes.class.push( 'ui-datepicker-today' );
				}

				if ( selector.hasClass( 'ui-datepicker-current-day' ) ) {
					attributes.class.push( 'ui-datepicker-current-day' );
				}

				// Add selector attributes.
				wc_appointments_date_picker.add_selector_attributes(
					selector,
					attributes
				);

				if ( this.opts.cache ) {
					this.cache.attributes[ cacheKey ] = attributes;
				}
			}

			checkDate.setDate( checkDate.getDate() + 1 );
		}
	};

	/**
	 * If caching is being requested beforeShowDay will use this method to load styles from cache if available.
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 * @param   {object} date - Date to apply attributes to.
	 */
	WC_Appointments_DatePicker.prototype.maybe_load_from_cache = function maybe_load_from_cache( date ) {
		var cacheKey         = date.getTime();
		var defaultClass	 = '1' === this.customData.default_availability ? 'appointable' : 'not-appointable';
		var attributes		 = [ true, defaultClass, '' ];
		var cachedAttributes = this.cache.attributes[ cacheKey ];

		if ( cachedAttributes ) {
			cachedAttributes = [ cachedAttributes.selectable, cachedAttributes.class.join( ' ' ), cachedAttributes.title ];
		} else if ( this.appointmentsData ) {
			var checkDate   = new Date( date ); // new object so we don't modify the original.
			checkDate.setHours( HOUR_OFFSET );

			var attrs = this.getDateElementAttributes( checkDate );
			attributes = [ attrs.selectable, attrs.class.join( ' ' ), attrs.title ];
		}

		return cachedAttributes || attributes;
	};

	/**
	 * Returns the default parameters.
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 */
	WC_Appointments_DatePicker.prototype.get_default_params = function get_default_params() {
		return this.defaultParams || {};
	};

	/**
	 * Set and override the default parameters.
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 * @param   {object} params - Parameters to be set or overridden.
	 */
	WC_Appointments_DatePicker.prototype.set_default_params = function set_default_params( params ) {
		var _defaultParams = {
			showWeek: false,
			showOn: false,
			numberOfMonths: 1,
			showButtonPanel: false,
			showOtherMonths: true,
			selectOtherMonths: true,
			gotoCurrent: true,
			dateFormat: $.datepicker.ISO_8601
		};

		if ( 'object' !== typeof params ) {
			throw new Error( 'Cannot set params with typeof ' + typeof params );
		}

		this.defaultParams = $.extend( _defaultParams, params ) || {};
	};

	/**
	 * Get the data from the server for a slot of time.
	 *
	 * @since   3.7.4
	 * @param   {string} year - Year being requested.
	 * @param   {string} month - Month being requested.
	 * @returns {object} Deferred object to be resolved after the http request
	 */
	WC_Appointments_DatePicker.prototype.get_data = function get_data( year, month ) {
		/**
		 * Overlay styles when jQuery.block is called to block the DOM.
		 */
		var blockUIOverlayCSS = {
			background: '#fff',
			opacity: 0.6
		};

		/**
		 * Get a date range based on the start date.
		 *
		 * @since   3.7.4
		 * @param   {string} startDate - Optional start date to get the date range from.
		 * @returns {object} Object referencing the start date and end date for the range calculated.
		 */
		var get_date_range = function get_date_range( startDate ) {
			if ( !startDate ) {
				startDate = new Date( [ year, month, '01' ].join( '/' ) );
			}

			var range = this.get_number_of_days_in_month( month );

			return this.get_padded_date_range( startDate, range );
		}.bind( this );

		var deferred	= $.Deferred();
		var dateRange   = get_date_range();
		var cacheKey	= dateRange.startDate.getTime() + '-' + dateRange.endDate.getTime();

		if ( this.opts.cache && this.cache.data[ cacheKey ] ) {
			deferred.resolveWith( this, [ dateRange, this.cache.data[ cacheKey ] ] );
		} else {
			var params = {
				'wc-ajax': 'wc_appointments_find_scheduled_day_slots',
				'product_id': this.$picker.attr( 'data-product_id' ),
				'security': this.get_custom_data( 'nonce_find_day_slots' )
			};

			this.$picker.block( {
				message: null,
				overlayCSS: blockUIOverlayCSS
			} );

			params.min_date = moment( dateRange.startDate ).format( 'YYYY-MM-DD' );
			params.max_date = moment( dateRange.endDate ).format( 'YYYY-MM-DD' );

			// Get scheduled slots.
			$.ajax( {
				context: this,
				url: wc_appointments_date_picker_args.ajax_url,
				method: 'POST',
				data: params
			} )
				.done( function( data ) {
					this.appointmentsData = this.appointmentsDate || {};

					//console.log(data);

					$.each( data, function( key, val ) {
						if ( $.isArray( val ) || 'object' === typeof val ) {
							var emptyType = ( $.isArray( val ) ) ? [] : {};

							this.appointmentsData[ key ] = this.appointmentsData[ key ] || emptyType;

							$.extend( this.appointmentsData[ key ], val );
						} else {
							this.appointmentsData[ key ] = val;
						}
					}.bind( this ) );

					wc_appointments_date_picker_object.appointmentsData = this.appointmentsData;

					this.cache.data[ cacheKey ] = data;

					if ( !year && !month && this.appointmentsData.min_date ) {
						dateRange = get_date_range( this.get_default_date( this.appointmentsData.min_date ) );
					}

					deferred.resolveWith( this, [ dateRange, data ] );

					this.$picker.unblock();

					this.$form.triggerHandler( 'calendar-data-loaded', [this.appointmentsData, dateRange] );
				}.bind( this ) );
		}

		return deferred;
	};

	/**
	 * Gets the default date
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 * @returns {Date}  Default date
	 */
	WC_Appointments_DatePicker.prototype.get_default_date = function get_default_date( minAppointableDate ) {
		var defaultDate;
		var defaultDateFromData = this.$picker.data( 'default_date' ).split( '-' );
		// We change the day to be 31, as default_date defaults to the current day,
		// but we want to go as far as to the end of the current month.
		defaultDateFromData[2] = '31';
		var modifier           = 1;

		// If for some reason the default_date didn't get or set incorrectly we should
		// try to fix it even though it may be indicative somewith else has gone wrong
		// on the backend.
		defaultDate = ( 3 !== defaultDateFromData.length ) ? new Date() : new Date( defaultDateFromData );

		// The server will sometimes return a min_appointable_date with the data request
		// If that happens we need to modify the default date to start from this
		// modified date.
		if ( minAppointableDate ) {
			switch ( minAppointableDate.unit ) {
				case 'month' :
					modifier = 30;
					break;
				case 'week' :
					modifier = 7;
					break;
			}

			modifier = modifier * minAppointableDate.value;

			defaultDate.setDate( defaultDate.getDate() + modifier );
		}

		return defaultDate;
	};

	/**
	 * Get number of days in a month
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 * @param   {number} [ month = currentMonth ] - The month in a 1 based index to get the number of days for.
	 * @returns {number} Number of days in the month.
	 */
	WC_Appointments_DatePicker.prototype.get_number_of_days_in_month = function get_number_of_days_in_month( month ) {
		var currentDate = this.get_default_date();

		month = month || currentDate.getMonth() + 1;

		return new Date( currentDate.getFullYear(), month, 0 ).getDate();
	};

	/**
	 * Get custom data that was set by the server prior to rendering the client.
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 * @param   {string} key - Custom data attribute to get.
	 */
	WC_Appointments_DatePicker.prototype.get_custom_data = function get_custom_data( key ) {
		if ( !key ) {
			return;
		}

		return this.customData[ key ] || null;
	};

	/**
	 * Get data attribute set on the $picker element.
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 * @param   {string} attr - Data attribute to get.
	 */
	WC_Appointments_DatePicker.prototype.get_data_attr = function get_data_attr( attr ) {
		if ( !attr ) {
			return;
		}

		return this.$picker.data( attr );
	};

	/**
	 * Gets a date range with a padding in days on either side of the range.
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 * @param   {Date}   date - Date to start from.
	 * @param   {number} rangeInDays - Number of days to build for the range.
	 * @param   {number} padInDays - Number of days to pad on either side of the range.
	 */
	WC_Appointments_DatePicker.prototype.get_padded_date_range = function get_padded_date_range( date, rangeInDays, padInDays ) {
		date					= date || this.get_default_date();
		rangeInDays				= rangeInDays || 30;
		padInDays				= padInDays || 7;

		var currentDate 		= new Date();
		var isCurrentDayToday 	= ( date < currentDate );
		var startDate			= new Date( date.setDate( ( isCurrentDayToday ) ? currentDate.getDate() : '01' ) ); // We dont go back further than today
		var endDate				= new Date( startDate.getTime() );

		startDate.setDate( startDate.getDate() - ( ( isCurrentDayToday ) ? 0 : padInDays ) ); // No reason to pad the left if the date is today
		endDate.setDate( endDate.getDate() + ( rangeInDays + padInDays ) );

		if ( startDate < currentDate ) {
			startDate = currentDate;
		}

		return {
			startDate: startDate,
			endDate: endDate
		};
	};

	/**
	 * Gets the date element attributes. This was formerly called is_appointable but changed names to more accurately reflect its new purpose.
	 *
	 * @version 3.7.4
	 * @since   3.7.4
	 * @param   {Date}   key - Date to get the element attributes for.
	 * @returns {object} Attributes computed for the date.
	 */
	WC_Appointments_DatePicker.prototype.getDateElementAttributes = function getDateElementAttributes( date ) {
		var attributes = {
			class: [],
			title: '',
			selectable: true
		};

		var staff_id    = ( 0 < this.$form.find( 'select#wc_appointments_field_staff' ).val() ) ? this.$form.find( 'select#wc_appointments_field_staff' ).val() : 0;
		var year        = date.getFullYear();
		var month       = date.getMonth() + 1;
		var day         = date.getDate();
		var day_of_week = date.getDay();
		var the_date    = new Date( date );
		var today  	    = new Date();
		var curr_year  	= today.getFullYear();
		var curr_month 	= today.getMonth() + 1;
		var curr_day    = today.getDate();
		var ymdIndex    = year + '-' + month + '-' + day;
		var minDate     = this.$picker.datepicker( 'option', 'minDate' );
		var dateMin    	= wc_appointments_date_picker.get_relative_date( minDate );

		// Add day of week class.
		attributes.class.push( 'weekday-' + day_of_week );

		// Offset for dates to avoid comparing them at midnight.
		// Browsers are inconsistent with how they
	    // handle midnight time right before a DST time change.
		if ( 'undefined' !== typeof startDate && 'undefined' !== typeof endDate ) {
			startDate.setHours( HOUR_OFFSET );
			endDate.setHours( HOUR_OFFSET );
		}

		// Select all days, when duration is longer than 1 day.
		if ( date >= startDate && date <= endDate ) {
			attributes.class.push( 'ui-datepicker-selected-day' );
		}

		// Make sure minDate is accounted for.
		// Convert compared dates to format with leading zeroes.
		if ( wc_appointments_date_picker.get_format_date( the_date ) < wc_appointments_date_picker.get_format_date( dateMin ) && 0 !== parseInt( minDate ) ) {
			attributes.title 		= wc_appointment_form_params.i18n_date_unavailable;
			attributes.selectable 	= false;
			attributes.class.push( 'not_appointable' );
		}

		// Unavailable days?
		if ( this.appointmentsData.unavailable_days && this.appointmentsData.unavailable_days[ ymdIndex ] && this.appointmentsData.unavailable_days[ ymdIndex ][ staff_id ] ) {
			attributes.title 		= wc_appointment_form_params.i18n_date_unavailable;
			attributes.selectable 	= false;
			attributes.class.push( 'not_appointable' );
		}

		// Padding days?
		if ( this.appointmentsData.padding_days && this.appointmentsData.padding_days[ ymdIndex ] ) {
			if ( this.appointmentsData.padding_days[ ymdIndex ][0] || this.appointmentsData.padding_days[ ymdIndex ][ staff_id ] ) {
				attributes.title 		= wc_appointment_form_params.i18n_date_unavailable;
				attributes.selectable 	= false;
				attributes.class.push( 'not_appointable' );
			}
		}

		// Restricted days?
		if ( this.appointmentsData.restricted_days && undefined === this.appointmentsData.restricted_days[ day_of_week ] ) {
			attributes.title 		= wc_appointment_form_params.i18n_date_unavailable;
			attributes.selectable 	= false;
			attributes.class.push( 'not_appointable' );
		}

		if ( '' + year + month + day < wc_appointment_form_params.current_time ) {
			attributes.title 		= wc_appointment_form_params.i18n_date_unavailable;
			attributes.selectable 	= false;
			attributes.class.push( 'not_appointable' );
		}

		//console.log( date );
		//console.log( this.appointmentsData.fully_scheduled_days );

		// Fully scheduled?
		if ( this.appointmentsData.fully_scheduled_days[ ymdIndex ] ) {
			if ( this.appointmentsData.fully_scheduled_days[ ymdIndex ][0] || this.appointmentsData.fully_scheduled_days[ ymdIndex ][ staff_id ] ) {
				attributes.title 		= wc_appointment_form_params.i18n_date_fully_scheduled;
				attributes.selectable 	= false;
				attributes.class.push( 'fully_scheduled' );

				return attributes;
			} else if ( 'automatic' === this.appointmentsData.staff_assignment ) {
				attributes.class.push( 'partial_scheduled' );
			}
		}

		// Apply partially scheduled CSS class.
		if ( this.appointmentsData.partially_scheduled_days && this.appointmentsData.partially_scheduled_days[ ymdIndex ] ) {
			if ( 'automatic' === this.appointmentsData.staff_assignment ||
			     ( this.appointmentsData.has_staff && 0 === staff_id ) ||
				 this.appointmentsData.partially_scheduled_days[ ymdIndex ][0] ||
				 this.appointmentsData.partially_scheduled_days[ ymdIndex ][ staff_id ]
			) {
				attributes.class.push( 'partial_scheduled' );
			}

			// Percentage remaining for scheduling
			if ( this.appointmentsData.remaining_scheduled_days[ ymdIndex ] &&
				 this.appointmentsData.remaining_scheduled_days[ ymdIndex ][0] ) {
				attributes.class.push( 'remaining_scheduled_' + this.appointmentsData.remaining_scheduled_days[ ymdIndex ][0] );
			} else if (
				 this.appointmentsData.remaining_scheduled_days[ ymdIndex ] &&
				 this.appointmentsData.remaining_scheduled_days[ ymdIndex ][ staff_id ]
			) {
				attributes.class.push( 'remaining_scheduled_' + this.appointmentsData.remaining_scheduled_days[ ymdIndex ][ staff_id ] );
			}
		}

		// Select all days, when duration is longer than 1 day
		if ( new Date( year, month, day ) < new Date( curr_year, curr_month, curr_day ) ) {
			attributes.class.push( 'past_day' );
		}

		var number_of_days = wc_appointments_date_picker.get_number_of_days( this.appointmentsData.appointment_duration, this.$form, this.$picker );
		var slot_args = {
			start_date: date,
			number_of_days: number_of_days,
			fully_scheduled_days: this.appointmentsData.fully_scheduled_days,
			availability: this.appointmentsData.availability_rules,
			default_availability: this.appointmentsData.default_availability,
			has_staff: this.appointmentsData.has_staff,
			staff_id: staff_id,
			staff_assignment: this.appointmentsData.staff_assignment
		};

		var appointable = wc_appointments_date_picker.is_slot_appointable( slot_args );

		if ( !appointable ) {
			attributes.title 		= wc_appointment_form_params.i18n_date_unavailable;
			attributes.selectable 	= appointable;
			if ( 0 === staff_id ) {
				attributes.class    = [ this.appointmentsData.fully_scheduled_days[ ymdIndex ] ? 'fully_scheduled' : 'not_appointable' ];
			} else if ( this.appointmentsData.fully_scheduled_days[ ymdIndex ] && this.appointmentsData.fully_scheduled_days[ ymdIndex ][ staff_id ] ) {
				attributes.class    = [ this.appointmentsData.fully_scheduled_days[ ymdIndex ][ staff_id ] ? 'fully_scheduled' : 'not_appointable' ];
			}
		} else {
			if ( -1 < attributes.class.indexOf( 'partial_scheduled' ) ) {
				attributes.title = wc_appointment_form_params.i18n_date_partially_scheduled;
			} else if ( -1 < attributes.class.indexOf( 'past_day' ) ) {
				attributes.title = wc_appointment_form_params.i18n_date_unavailable;
			} else {
				attributes.title = wc_appointment_form_params.i18n_date_available;
			}

			attributes.class.push( 'appointable' );
		}

		return attributes;
	};

	moment.locale( wc_appointments_locale );

	// export globally
	wc_appointments_date_picker = wc_appointments_date_picker_object;
	wc_appointments_date_picker.init();
} );
