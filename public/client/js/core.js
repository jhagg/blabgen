/**
 * Visitor system core.
 */

// global blabgen namespace root
var visys = {};
// change to 'console' when debugging
var myconsole = {
	log: function() {}
};

/**
 * Visitor system main state machine.
 */
(function( root, log, $, undefined ) {
	/** -- BEGIN STATE MACHINE -- */

	/** Defines initial state. */
	var initial_state = 'unknown';

	/** Holds current state. */
	var current_state = initial_state;

	/** Event queue. */
	var event_queue = [];

	/** Is currently processing event? */
	var processing_event = false;

	/**
	 * Holds all registered events. Valid event chars are [a-z0-9_-].
	 *
	 *	[event][state] : [ callback, to_state ]
	 *
	 * State may be special state '*', which means event is valid for any state.
	 *
	 * Also holds meta events:
	 *
	 *	<state>:{enter,leave}
	 *	<event>:{before,after}
	 */
	var events = {};

	/** Binds event to transition change. from_state may be array of states. */
	root.bind = function ( event, from_state, to_state, callback ) {
		// meta event. from_state is actually callback.
		if ( arguments.length == 2 ) {
			events[event] = from_state;

		// normal event
		} else {
			// from_state may be several states
			from_state = (from_state instanceof Array) ? from_state : [from_state];

			for ( var i = 0; i < from_state.length; i++ ) {
				events[event] = events[event] || {};
				events[event][from_state[i]] = [ callback, to_state ];
			}
		}
	};

	/** Processes event queue. */
	var process = function () {
		// only one event at a time!
		if ( processing_event || !event_queue.length ) {
			return false;
		}

		processing_event = true;
		log( 'Started processing event ...' );
		var args = event_queue.shift();
		try {
			run.apply( this, args );
		} catch ( err ) {
			log(err);
		} finally {
			log( 'Done processing event ...' );
			processing_event = false;
		}

		// more events queued up?
		if ( event_queue.length ) {
			process();
		}
	};

	/** Runs event. */
	var run = function ( event, eventData ) {
		var args = Array.prototype.slice.call(arguments);
		var r = null;
		log( "Triggering event '" + event + "' ..." );

		// exists specific?
		if ( events[event] && events[event][current_state] ) {
			log( 'Found specific transition ...' );
			r = events[event][current_state];

		// exists generic?
		} else if ( events[event] && events[event]['*'] ) {
			log( 'Found generic transition ...' );
			r = events[event]['*'];
		}

		// r[0] is callback, r[1] is to_state
		if ( r ) {
			// meta event: <current state>:leave
			if ( events[current_state + ':leave'] ) {
				log( "Calling meta event '" + current_state + ":leave' ..." );
				events[current_state + ':leave'].call( this );
			}

			if ( r[0] ) {
				args.shift();
				r[0].apply( this, args );
			}

			if ( current_state != r[1] ) {
				log( 'setting state \'' + r[1] + "' ..." );
				current_state = r[1];

				// meta event: <current state>:enter
				if ( events[current_state + ':enter'] ) {
					log( "Calling meta event '" + current_state + ":enter' ..." );
					events[current_state + ':enter'].call( this );
				}
			}

		// invalid transition!
		} else {
			log( 'error! undefined transition: (' + event + ', ' + current_state + ')' );
			throw 'undefined transition! (' + event + ', ' + current_state + ')';
		}
	};

	/** Triggers event. */
	root.trigger = function ( event, event_data ) {
		var args = Array.prototype.slice.call( arguments );
		log( "Queueing event '" + event + "' ..." );
		event_queue.push( args );
		process();
	};

	/** -- END STATE MACHINE -- */

	/** CONFIGURATION **/

	/** Holds configuration. */
	root.conf = {};
	var conf = root.conf;

	/** Holds translation, if any. */
	conf.translation_map = {};

	/** Default backend API URL. */
	conf.api_base_url =
		document.location.protocol + '//' + document.location.host +
		(document.location.port ? ':' + document.location.port : '') +
		'/api';
	conf.api_base_url = '../api'; // <REMOVE_THIS>

	/** Month names for UI. */
	conf.month_names = [
			'Januari', 'Februari', 'Mars', 'April', 'Maj', 'Juni',
			'Juli', 'Augusti', 'September', 'Oktober', 'November',
			'December'
		];

	/** Default locale. */
	conf.default_locale = 'sv_SE';

	/** Empty user data. Cloned to user_data when resetting state. */
	var empty_user_data = {
		name: null,
		company: null,
		parking: null,
		receiver: null,
		end_date: (new Date()),
		picture_url: null
	}

	/** Holds data entered by the user. Defaults to empty_user_data. */
	var user_data = $.extend( {}, empty_user_data );

	/** Locale. */
	var locale = null;

	/** Gettext instance. */
	var gt = null;

	/** -- CORE FUNCTIONALITY -- **/

	/** Resets state. */
	root.reset = function () {
		log( 'Resetting state and emptying data structs ...' );
		$('#data-name').val('');
		$('#data-company').val('');
		$('#data-parking').val('');
		$('#data-receiver').val('');

		$('.nav .delete').hide();

		visited_screens = {};
		current_screen = null;
		front_screen = null;
		//root.set_locale( conf.default_locale );
		user_data = $.extend( {}, empty_user_data );


	};

	/** Blocks UI. */
	root.blockUI = function ( msg ) {
		if ( !msg ) {
			msg = gt.gettext('Loading') + ' ...';
		}

		$.blockUI({
			message: '<span>' + msg + '</span>',
			fadeIn: 400,
			fadeOut: 400
		});
	};

	/** Unblocks UI. */
	root.unblockUI = function () {
		$.unblockUI();
	};

	/** Shows error page. */
	root.err = function ( msg ) {
	};


	/** -- TRANSITION DEFINITIONS -- */

	// INITIALIZATION

	/** Aborts registration process. */
	root.bind( 'abort', '*', 'start', function() {
		$('#flash').hide();
		// start:enter takes care of resetting
		root.goto('start', root.current());
	});

	/** Initializes. */
	root.bind( 'init', 'unknown', 'start', function() {
		root.set_locale( conf.default_locale, { initial: true } );
		$('#flash').hide();
		root.goto('start');
	});

	/** Error. */
	root.bind( 'error', '*', 'error', function () {
		// always unblock if an error occured. if wasn't blocked, no
		// harm done.
		root.unblockUI();
		root.goto('error');
	});

	// START SCREEN

	/** enter 'start'. */
	root.bind( 'start:enter', function () {
		root.reset();

		// enter = begin
		$(document).unbind('keypress').keypress( function ( e ) {
			var kc = e.keyCode ? e.keyCode : e.which;
			log( 'keypress: ' + kc + ' ...' );
			if ( kc == 13 ) {
				root.trigger('start');
			}
		});
	});

	// NAME SCREEN

	/** 'start' -> 'name'. */
	root.bind( 'start', 'start', 'name', function() {
		root.goto('name');
	});

	/** Entering state 'name'. */
	root.bind( 'name:enter', function () {
		root.pb().hide();

		// enable or disable next?
		if ( $('#data-name').val() ) {
			root.enable(root.nb());
		} else {
			root.disable(root.nb());
		}

		// when something has been entered, next button is enabled
		$('#data-name').keyup( function () {
			if ( $(this).val() ) {
				root.enable(root.nb(), function () {
					root.nb().unbind('click');
					root.nb().click( function () {
						root.trigger('next');
					});
				});
			} else {
				root.disable(root.nb());
			}
		});

		// need to to this after transition animation.
		// this is really ugly. a better solution would
		// be to add a callback option to the goto method.
		setTimeout( function () {
			$('#data-name').focus();
		}, 600 );

		// enter = to company screen
		$(document).unbind('keypress').keypress( function ( e ) {
			var kc = e.keyCode ? e.keyCode : e.which;
			log( 'keypress: ' + kc + ' ...' );
			if ( kc == 13 ) {
				if ( $('#data-name').val() ) {
					root.trigger('next');
				}
				return false;
			}
		});
	});

	/** Leaving state 'name'. */
	root.bind( 'name:leave', function () {
		$('#data-name').unbind('keyup');
	});

	// COMPANY SCREEN

	/** 'name' -> 'company'. */
	root.bind( 'next', 'name', 'company', function() {
		user_data.name = $('#data-name').val();
		root.goto('company', 'name');
	});

	/** 'company' -> 'name'. */
	root.bind( 'prev', 'company', 'name', function() {
		root.goto('name', 'company');
	});

	/** Entering state 'company'. */
	root.bind( 'company:enter', function () {
		// show and bind previous button
		root.pb().show();
		root.pb().unbind('click');
		root.pb().click( function () {
			root.trigger('prev');
		});

		// next is always enabled, because company is not required
		root.nb().unbind('click');
		root.nb().click( function () {
			root.trigger('next');
		});
		root.enable(root.nb());

		// this is just as ugly as the name focusing, see
		// further comments there
		setTimeout( function () {
			$('#data-company').focus();
		}, 600 );

		// enter = to date screen
		$(document).unbind('keypress').keypress( function ( e ) {
			var kc = e.keyCode ? e.keyCode : e.which;
			log( 'keypress: ' + kc + ' ...' );
			if ( kc == 13 ) {
				root.trigger('next');
				return false;
			}
		});
	});

	/** Leaving state 'company'. */
	root.bind( 'company:leave', function () {
	});

	// DATE SCREEN

	/** 'company' -> 'date' */
	root.bind( 'next', 'company', 'date', function () {
		user_data.company = $('#data-company').val();
		user_data.parking = $('#data-parking').val();
		root.goto('date', 'company');
	});

	/** 'date' => 'company' */
	root.bind( 'prev', 'date', 'company', function () {
		root.goto('company', 'date');
	});

	/** Entering 'date' */
	root.bind( 'date:enter', function () {
		// next button is always available, because a date is always
		// selected
		root.enable(root.nb());
		root.nb().unbind('click');
		root.nb().click( function () {
			root.trigger('next');
		});

		// determine dates to show. monday is 1, friday 5.
		today = new Date();
		today.setHours(23);
		today.setMinutes(59);
		today.setSeconds(59);
		today.setMilliseconds(0);
		dates = [];
		dates[0] = new Date(today);
		// (today.getDate()+5)%6 gives todays day # w/ monday = 0 and
		// sunday = 6.  last monday's date = today's date - today's day
		// #
		dates[0].setDate( today.getDate() - (today.getDay()+5) % 6 );
		for ( var i = 1; i <= 4; i++ ) {
			dates[i] = new Date(dates[0]);
			dates[i].setDate( dates[0].getDate() + i );
		}

		log( dates );

		// updates dates list
		var update_list = function () {
			log('Updating date list..');
			$('.dates li a').each( function( index ) {
				root.enable($(this));
				$(this).removeClass('current');
				$(this).removeClass('selected');
				$(this).removeClass('last-selected');

				// dim passed dates
				if ( dates[index] < today ) {
					root.disable( $(this) );
				}

				// select selected dates, compare indices
				// instead of actual dates for robustness
				if (
					user_data.end_date &&
					user_data.end_date_index >= index &&
					!root.disabled( $(this) )
				) {
					$(this).addClass('selected');
				}
				// today is always selected,
				if (
					dates[index].getTime() === today.getTime()
				) {
					$(this).addClass('selected');
				}
				// end date is marked up for special display
				// if no end date is set, mark today's date as
				// end date
				if (
					( user_data.end_date &&
					user_data.end_date_index == index ) ||
					( !user_data.end_date_index &&
					dates[index].getTime() === today.getTime() )
				) {
					$(this).addClass('last-selected');
					user_data.end_date = dates[index];
					user_data.end_date_index = index;
				}
			});
		};

		// fill in dates in date display, mon - fri
		$('.dates li').each( function ( index ) {
			// store date att data attrib for convenience
			$(this).find('a').data('date', dates[index]);
			$(this).find('a').data('index', index);

			// set text
			$(this).find('.d').text(dates[index].getDate());
			$(this).find('.m').text(conf.month_names[dates[index].getMonth()].toLowerCase());
		});

		update_list();

		// when date is selected, select all dates from today -
		// selected so if today is tuesday, and user selects thursday,
		// tuesday, wednesday and thursday will all be selected.
		$('.dates a:not(.disabled)').click( function() {
			// store selected date
			log( 'Selected date ...' );
			user_data.end_date = $(this).data('date');//.getTime();
			user_data.end_date_index = $(this).data('index');
			user_data.start_date = (new Date()).getTime();
			update_list.call(undefined);
		});

		// enter = to receiver screen
		$(document).unbind('keypress').keypress( function ( e ) {
			var kc = e.keyCode ? e.keyCode : e.which;
			log( 'keypress: ' + kc + ' ...' );
			if ( kc == 13 ) {
				root.trigger('next');
				return false;
			}
		});
	});

	/** Leavning 'date'. */
	root.bind( 'date:leave', function () {
		log( user_data.end_date );
	});

	// RECEIVER SCREEN

	/** 'date' => 'receiver'. */
	root.bind( 'next', 'date', 'receiver', function () {
		var employees, s;

		if ( !root.visited('receiver') ) {
			s = $('#data-receiver');

			// list of employees is dynamic
			get_employees( function ( employees ) {
				s.select2('data', null);
				s.empty();
				$.each( employees, function ( key, value ) {
					if ( !value.last_name ) {
						value.last_name = '';
					}
					var o = $('<option>'+value.first_name+
						' '+value.last_name+
						'</option>');
					o.attr('value', value.username);
					s.append(o);
				});
			});

			// when selected, user is allowed to continue
			s.change( function () {
				var v = $(this).val();
				if ( v ) {
					// employee id
					user_data.receiver = $(this).val();
					root.enable(root.nb());
					root.nb().unbind('click');
					root.nb().click( function () {
						log('next button clicked..');
						if ( user_data.picture_url ) {
							root.trigger('next-view');
						} else {
							root.trigger('next');
						}
					});
				} else {
					user_data.receiver = null;
					root.disable(root.nb());
				}
			});

			// disable normal form functionality
			$('#receiver-f').submit( function () {
				return false;
			});
		}

		root.goto('receiver', 'date');
	});

	/** 'receiver' => 'date'. */
	root.bind( 'prev', 'receiver', 'date', function () {
		root.goto('date', 'receiver');
	});

	/** Entering 'receiver'. */
	root.bind( 'receiver:enter', function () {
		// entered already?
		log( user_data.receiver );
		if ( !user_data.receiver ) {
			root.disable(root.nb());
		} else {
			root.enable(root.nb());
			root.nb().unbind('click');
			root.nb().click( function () {
				if ( user_data.picture_url ) {
					root.trigger('next-view');
				} else {
					root.trigger('next');
				}
			});
		}

		// this is just as ugly as the name and company focusing, see
		// further comments there
		setTimeout( function () {
			$('.receiver-w input').focus();
		}, 600 );

		// enter = to photo screen
		$(document).unbind('keypress').keypress( function ( e ) {
			var kc = e.keyCode ? e.keyCode : e.which;
			log( 'keypress: ' + kc + ' ...' );
			if ( kc == 13 ) {
				if ( $('#data-receiver').val() ) {
					root.trigger('next');
				}
				return false;
			}
		});
	});

	/** Leaving 'receiver'. */
	root.bind( 'receiver:leave', function () {
	});

	// PHOTO SCREEN

	/** 'receiver' => 'photo-take'. */
	root.bind( 'next', 'receiver', 'photo-take', function () {
		root.goto('photo', 'receiver');
	});

	/** 'photo-take' => 'receiver'. */
	root.bind( 'prev', 'photo-take', 'receiver', function () {
		root.goto('receiver', 'photo');
	});

	/** 'receiver' => 'photo-view'. */
	root.bind( 'next-view', 'receiver', 'photo-view', function () {
		root.goto('photo', 'receiver');
	});

	/** 'photo-view' => 'receiver'. */
	root.bind( 'prev', 'photo-view', 'receiver', function () {
		root.goto('receiver', 'photo');
	});

	/** Entering 'photo-take'. */
	root.bind( 'photo-take:enter', function () {
		root.nb().unbind('click');
		root.disable( root.nb() );

		$('.delete').hide('slide', { direction: 'down' }, 500);
		if ( !$('.screen-photo p.snap').is(':visible') ) {
			$('.screen-photo p.snap').show('slide', { direction: 'down' }, 500);
		}
		$('#picture-img').attr('src', $('#picture-img').data('video-src') );

		root.enable( $('#cam-up') );
		root.enable( $('#cam-down') );

		user_data.picture_url = null;
		root.disable( root.nb() );
		root.nb().unbind('click');

		// enter = take photo
		$(document).unbind('keypress').keypress( function ( e ) {
			var kc = e.keyCode ? e.keyCode : e.which;
			log( 'keypress: ' + kc + ' ...' );
			if ( kc == 13 ) {
				root.trigger('snap');
				return false;
			}
		});
	});

	/** Take picture. */
	root.bind( 'snap', 'photo-take', 'photo-view', function () {
		// show countdown timer
		$('.screen-photo p.snap').hide('slide', { direction: 'down' }, 500);
		$('.countdown span').hide();
		$('.countdown').show();
		$('.abort').hide('slide', { direction: 'down' }, 500);

		$('.countdown span:nth-child(1)').show();
		setTimeout( function() { $('.countdown span:nth-child(2)').show(); }, 1000 );
		setTimeout( function() { $('.countdown span:nth-child(3)').show(); }, 2000 );

		// flash
		setTimeout( function() {
			$('#flash').fadeIn(100).fadeOut(1500);
		}, 2800);

		root.disable(root.pb());

		// hide countdown and show delete button
		setTimeout( function() {
			log( 'Taking picture ...' );

			// picture is taken by backend via POST request
			$.ajax({
				type: 'POST',
				url: conf.api_base_url + '/pictures.php',
				success: function ( data, txt, xhr ) {
					var url = xhr.getResponseHeader('Location');
					log( 'Picture taken (' + url + ')!' );

					// show picture
					$('#picture-img').data('video-src',
						$('#picture-img').attr('src') );
					$('#picture-img').attr('src', url );

					user_data.picture_url = url;

					$('.countdown').hide();
					$('.nav .delete').show();
					$('.abort').show();

					root.enable(root.nb());
					root.enable(root.pb());
					root.pb().click( function () {
						root.trigger('prev');
					});
				}
			});
		}, 2900);
	});

	root.bind( 'photo-view:enter', function () {
		root.disable( $('#cam-up') );
		root.disable( $('#cam-down') );

		root.nb().unbind('click');
		root.nb().click( function() {
			root.trigger('next');
		});

		// enter = OK photo
		$(document).unbind('keypress').keypress( function ( e ) {
			var kc = e.keyCode ? e.keyCode : e.which;
			log( 'keypress: ' + kc + ' ...' );
			if ( kc == 13 ) {
				root.trigger('next');
				return false;
			}
		});
	});

	/** Delete taken picture. */
	root.bind( 'delete-photo', 'photo-view', 'photo-take', function () {
		$('#picture-img').attr('src', $('#picture-img').data('video-src') );

		user_data.picture_url = null;
		root.disable( root.nb() );
		root.nb().unbind('click');

		// cam control up
		root.enable( $('#cam-up') );
		root.enable( $('#cam-down') );
	});

	// DONE SCREEN

	/** 'photo-view' -> 'submit-wait'. */
	root.bind( 'next', 'photo-view', 'submit-wait', function () {
		root.blockUI( gt.gettext( 'Registering ...' ) );

		var start_date = new Date();
		// sanity check, might happen if 00:00 is passed while the user
		// is registering, and the user chose what is now yesterday as
		// the end date.  in that case, start_date will be set to equal
		// end_date. this is very unlikely to happen.
		if ( start_date.getTime() > user_data.end_date.getTime() ) {
			start_date = user_data.end_date;
		}

		var data = {
			name: user_data.name,
			company: user_data.company,
			parking: user_data.parking,
			start_date: start_date.getTime(),
			end_date: user_data.end_date.getTime(),
			receivers: user_data.receiver,
			picture: user_data.picture_url
		}

		log(data);

		$.ajax({
			url: conf.api_base_url + '/visits.php',
			type: 'POST',
			data: data,
			success: function ( data ) {
				log('OK!');
				root.trigger('ok');
			},
			error: function ( data ) {
				log('NOK!');
				root.trigger('error');
			}
		});
	});

	/** 'submit-wait' -> 'done'. */
	root.bind( 'ok', 'submit-wait', 'done', function () {
		root.unblockUI();
		root.goto('done', 'photo-view');
	});

	/** entering 'done'. */
	root.bind( 'done:enter', function () {
		// any keypress -> start screen
		$(document).unbind('keypress').keypress( function ( e ) {
			var kc = e.keyCode ? e.keyCode : e.which;
			log( 'keypress: ' + kc + ' ...' );
			root.trigger('done');

		});
	});

	/** 'done' -> 'start'. */
	root.bind( 'done', 'done', 'start', function () {
		root.reset();
		root.goto('start', 'done');
	});

	/** -- UTILS -- **/

	var get_employees = function ( success ) {
		var url = conf.api_base_url + '/employees.php';
		log( 'Getting list of employees from backend (' + url + ') ...' );
		$.ajax({
			url: url,
			success: function ( data ) {
				log( 'Got list of employees from backend!' );
				success.call( this, data );
			},
			error: function ( xhr, txtstatus, err ) {
				log( 'Error getting list of employees from backend!' );
				log( err );
				// TODO trigger error
			}
		});
	};

	/** -- UI FUNCTIONALITY -- **/

	/** Holds names and implicit ordering of screens. */
	var screens =
		['start', 'name', 'company', 'date', 'receiver', 'photo', 'submit-wait',
		'done', 'error'];

	/** Front screen name. This is the most 'forward' visited screen. */
	var front_screen = 'start';

	/** Visited screens names. */
	var visited_screens = {};

	/** Returns a reference to the next button. */
	root.nb = function () {
		return $('.next .btn');
	};

	/** Returns a reference to the previous butotn. */
	root.pb = function () {
		return $('.prev .btn');
	};

	/** Returns name of previous screen given screen name. */
	root.prev = function( screen_name ) {
		var i = screens.indexOf(screen_name);
		if ( i > 0 )
			return screens[i - 1];
		else
			return null;
	};

	/** Returns name of next screen given screen name. */
	root.next = function( screen_name ) {
		var i = screens.indexOf(screen_name);
		if ( i >= 0 && i + 1 < screens.length )
			return screens[i + 1];
		else
			return null;
	};

	/** Translates screen name to screen number. */
	root.name_to_id = function( screen_name ) {
		return screens.indexOf(screen_name);
	}

	/** Sets/gets current screen name. */
	root.current = function( screen_name ) {
		if ( screen_name ) {
			log('Setting current screen: ' + screen_name);
			current_screen = screen_name;
			if ( root.name_to_id(screen_name) > root.name_to_id(front_screen) ) {
				log('Setting current front screen: ' + screen_name);
				front_screen = screen_name;
			}
		}
		return current_screen;
	}

	/** Sets/gets visited status for given screen name. */
	root.visited = function( screen_name, visited ) {
		if ( visited !== undefined ) {
			log('Adding visited: ' + screen_name);
			visited_screens[screen_name] = true;
		}
		return visited_screens[screen_name] === true;
	};

	/** Disables given element. */
	root.disable = function( elem, cb ) {
		if ( !elem.data('disabled') ) {
			log( 'Disabling element: ' + elem + ' ...');
			elem.removeClass('enabled').addClass('disabled');
			elem.unbind('click');
			elem.data('disabled', true);
			if ( cb ) {
				cb.call( undefined );
			}
		}
	};

	/** Enables given element. */
	root.enable = function( elem, cb ) {
		if ( elem.data('disabled') ) {
			log( 'Enabling element: ' + elem + ' ...');
			elem.removeClass('disabled').addClass('enabled');
			elem.data('disabled', false);
			if ( cb ) {
				cb.call( undefined );
			}
		}
	};

	/** Disabled? */
	root.disabled = function ( elem ) {
		return !!elem.data('disabled');
	};

	/** Enabled? */
	root.enabled = function ( elem ) {
		return !root.disabled( elem );
	};

	/** Goes to previous screen. */
	root.goto_prev = function() {
		log('Going to previous screen ...');
		var prev_screen = root.prev(root.current())
		root.goto( prev_screen, root.current() );
	};

	/** Goes to next screen. */
	root.goto_next = function() {
		log('Going to next screen ...');
		root.goto( root.next(root.current()), root.current() );
	};

	/** Goes from given screen name to given screen name. */
	root.goto = function( to_name, from_name ) {
		from_name = from_name || 'start';
		log( "Going from screen '" + from_name + "' to '" + to_name + "' ...");

		var to_id = root.name_to_id( to_name );
		var from_id = root.name_to_id( from_name );

		if ( to_id >= from_id ) {
			root.fwd( to_name, from_name );
		} else {
			root.rev( to_name, from_name );
		}

		root.visited(to_name, true);
		root.current(to_name);
		root.update_crumbs();
	};

	/** Goes from given screen forwards to given screen. */
	root.fwd = function( to_name, from_name ) {
		log('Going forward ...');
		switch ( to_name ) {
			// first screen (welcome screen)
			case 'start':
				$('.main:not(.screen-start)').hide();
				$('.steps').hide();//'slide', { direction: 'down' }, 500);
				$('.nav').hide();
				$('.screen-start').show();

				break;

			// last screen
			case 'done':
				$('.steps').hide('slide', { direction: 'down' }, 500);
				$('.nav').hide('slide', { direction: 'down' }, 500 );
				$('.screen-photo').fadeOut( 500 );
				$('.screen-done').fadeIn( 500 );

				break;

			// error is set as last screen, so it is always moved fwd to
			case 'error':
				$('.main:not(.screen-error').hide();
				$('.steps').fadeOut();
				$('.nav').fadeOut();
				$('.screen-error').show();

				break;

			// default: slide out old screen to the left and slide in new screen
			// from the right
			default:
				var cname_to = '.screen-' + to_name;
				var cname_from = '.screen-' + from_name;

				$(cname_from).hide('slide', { direction: 'left' }, 500);
				$(cname_to).show('slide', { direction: 'right' }, 500);

				$('.steps:hidden').show('slide', { direction: 'down' }, 500);
				$('.nav:hidden').show('slide', { direction: 'down' }, 500);

				break;
		}
	};

	/** Goes from given screen backwards to given screen. */
	root.rev = function( to_name, from_name ) {
		switch ( to_name ) {
			case 'start':
				log('foo');
				$('.main:not(.screen-start)').hide();
				$('.nav').hide();
				$('.steps').hide('slide', { direction: 'down' }, 500);
				$('.screen-start').show();

				break;

			default:
				var cname_to = '.screen-' + to_name;
				var cname_from = '.screen-' + from_name;

				$(cname_from).hide('slide', { direction: 'right' }, 500);
				$(cname_to).show('slide', { direction: 'left' }, 500);

				$('.steps:hidden').show('slide', { direction: 'down' }, 500);
				$('.nav:hidden').show('slide', { direction: 'down' }, 500);

				break;
		}
	};

	/** Updates crumbs. */
	root.update_crumbs = function() {
		for ( var i = 0; i < screens.length; i++ ) {
			if ( root.visited(screens[i]) ) {
				$('.steps li:nth-child(' + i + ')').
						removeClass('current').
						removeClass('next').
						removeClass('front').
						addClass('prev');
			} else {
				$('.steps li:nth-child(' + i + ')').
					removeClass('current').
					removeClass('prev').
					removeClass('front').
					addClass('next');
			}
		}

		$('.steps li:nth-child(' + root.name_to_id(current_screen) + ')').
			addClass('current');

		$('.steps li:nth-child(' + root.name_to_id(front_screen) + ')').
			addClass('front');
	};

	/** Sets locale. */
	root.set_locale = function ( l, opts ) {
		if ( l == locale ) {
			log( 'Locale already set to \'' + l + '\', ignoring ...' );
			return;
		}

		if ( !opts || !opts.initial ) {
			root.blockUI();
		}

		root.load_translation_map( l, {
			success: function () {
				locale = l;
				root.update_ui_messages();
				$('a.lang').each( function () {
					var l = $(this).attr('data-locale');
					$(this).removeClass('selected');
					if ( l == locale ) {
						$(this).addClass('selected');
					}
				});
				root.unblockUI();
			},
			error: function () {
				log('error');
				root.unblockUI();
				// TODO show error screen
			}
		});

		log( 'locale set ...' );
	};

	/** Loads a new translation mapping. */
	root.load_translation_map = function ( locale, opts ) {
		opts.success = opts.success || $.noop;
		opts.error = opts.error || $.noop;

		log( 'Loading translation map ...' );
		var url = 'locales/' + locale + '/LC_MESSAGES/messages.json';

		$.ajax({
			url: url,
			method: 'get',
			dataType: 'json',
			success: function ( data ) {
				log( 'New translation map received ...' );
				// Gettext caches on domain name, so before loading a new file
				// we need to reset the locale cache. NOTE this depends on internal
				// implementation of Gettext so it ain't pretty
				Gettext._locale_data = {};
				gt = new Gettext({
					domain: 'messages',
					locale_data: { 'messages': data }
				});
				opts.success.call( undefined );
			},
			error: function ( xhr, txt, x ) {
				opts.error.call( undefined );
			}
		});
	};

	/** Updates UI text strings. */
	root.update_ui_messages = function () {
		log( 'updating ui ...' );
		$('.translatable').each( function () {
			var txt, d;
			// get current text, either from attribute ...
			if ( $(this).data('translatable') ) {
				d = $(this).data('translatable').split(':');
				if ( d[0] == 'attr' ) {
					txt = $(this).attr(d[1]);
				}
			// ... or from html content
			} else {
				txt = $(this).html().trim();
			}

			// store/retrieve original text string (as defined in html source)
			if ( !$(this).data('original') ) {
				$(this).data('original', txt);
			} else {
				txt = $(this).data('original');
			}

			// translate
			var trans = gt.gettext( txt );
			log( 'Translating \'' + txt + '\' -> \'' + trans + '\' ...' );

			// either update attribute ...
			if ( $(this).data('translatable') ) {
				if ( d[0] == 'attr' ) {
					$(this).attr( d[1], trans );
				}
			// ... or html content
			} else {
				$(this).html(trans);
			}
		});
		log( 'done updating ui ...' );
	};
})( visys, myconsole.log, jQuery );

/**
 * Initializes visys state machine.
 */
(function ( visys, log, $, undefined ) {
	$(document).ready( function() {
		$.blockUI.defaults.css = {};
		$.blockUI.defaults.overlayCSS = {};

		/** Abort goes to start screen. */
		$('.nav .abort').click( function() {
			visys.trigger('abort');
			return false;
		});

		/** Start button goes to name screen. */
		$('.screen-start a.btn-start').click( function() {
			visys.trigger('start');
			return false;
		});

		/** Done button goes to start screen. */
		$('.screen-done a.btn-done').click( function() {
			visys.trigger('done');
			return false;
		});

		/** Take photo button displays countdown and takes picture. */
		$('.screen-photo a.btn-snap').click( function() {
			visys.trigger('snap');
			return false;
		});

		/** Delete photo button removes current photo. */
		$('.nav a.btn-delete').click( function() {
			visys.trigger('delete-photo');
			return false;
		});

		/** Language selection buttons. */
		$('.lang').click( function () {
			var locale = $(this).attr('data-locale');
			log( "Setting locale '" + locale + "' ..." );
			visys.set_locale( locale );
			return false;
		});

		/** Move camera up/down */
		var up_timer, down_timer;
		var move_up = function () {
			if ( $(this).data('disabled') ) return;

			log( 'Moving camera up ...' );
			$.ajax({
				url: visys.conf.api_base_url + '/cam_movement.php',
				type: 'POST',
				data: { value: '5' },
				success: function () {
					log( 'Camera moved ...' );
				},
				error: function () {
					log( 'Error moving camera ...' );
				}
			});
			up_timer = setTimeout( move_up, 200 );
		};
		var move_down = function () {
			if ( $(this).data('disabled') ) return;

			log( 'Moving camera down ...' );
			$.ajax({
				url: visys.conf.api_base_url + '/cam_movement.php',
				type: 'POST',
				data: { value: '-5' },
				success: function () {
					log( 'Camera moved ...' );
				},
				error: function () {
					log( 'Error moving camera ...' );
				}
			});
			down_timer = setTimeout( move_down, 200 );
		};

		$('#cam-up').mousedown( move_up );
		$('#cam-up').mouseup( function () {
			clearTimeout( up_timer );
		});
		$('#cam-down').mousedown( move_down );
		$('#cam-down').mouseup( function () {
			clearTimeout( down_timer );
		});

		visys.trigger('init');
	});
})( visys, myconsole.log, jQuery );
