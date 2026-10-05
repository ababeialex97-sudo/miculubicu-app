/* global mlbAdmin */
/**
 * "Comenzi aplicație" panel: lists app orders and changes their status.
 * Plain DOM, no build step; data comes from /wp-json/mlb/v1/admin/.
 */
(function () {
	'use strict';

	var REFRESH_MS = 20000;
	var config = window.mlbAdmin;
	var root = document.getElementById( 'mlb-orders-app' );
	if ( ! config || ! root ) {
		return;
	}

	var state = {
		scope: 'active',
		location: '',
		orders: [],
		knownIds: null,
		busy: {},
		error: '',
	};

	var ACTION_LABELS = {
		confirmed: 'Acceptă',
		preparing: 'Pune pe jar',
		on_the_way: 'Plecată la client',
		ready_for_pickup: 'Gata de ridicare',
		completed: 'Finalizată',
		cancelled: 'Anulează',
	};

	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( key === 'text' ) {
				node.textContent = attrs[ key ];
			} else if ( key === 'onClick' ) {
				node.addEventListener( 'click', attrs[ key ] );
			} else if ( key === 'onChange' ) {
				node.addEventListener( 'change', attrs[ key ] );
			} else if ( attrs[ key ] !== null && attrs[ key ] !== undefined && attrs[ key ] !== false ) {
				node.setAttribute( key, attrs[ key ] === true ? '' : attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			if ( child ) {
				node.appendChild( typeof child === 'string' ? document.createTextNode( child ) : child );
			}
		} );
		return node;
	}

	function request( path, options ) {
		options = options || {};
		return window
			.fetch( config.root + path, {
				method: options.method || 'GET',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json' },
				body: options.body ? JSON.stringify( options.body ) : undefined,
			} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					if ( ! response.ok ) {
						throw new Error( ( data && data.message ) || 'Eroare ' + response.status );
					}
					return data;
				} );
			} );
	}

	function money( value ) {
		var number = parseFloat( value );
		var text = number.toFixed( 2 ).replace( '.', ',' ).replace( ',00', '' );
		return text + ' lei';
	}

	function minutesAgo( iso ) {
		if ( ! iso ) {
			return '';
		}
		var minutes = Math.round( ( Date.now() - new Date( iso ).getTime() ) / 60000 );
		if ( minutes < 1 ) {
			return 'acum';
		}
		if ( minutes < 60 ) {
			return 'acum ' + minutes + ' min';
		}
		return new Date( iso ).toLocaleTimeString( 'ro-RO', { hour: '2-digit', minute: '2-digit' } );
	}

	// Short beep for a new order, so staff notice it without watching the screen.
	function beep() {
		try {
			var context = new ( window.AudioContext || window.webkitAudioContext )();
			var oscillator = context.createOscillator();
			var gain = context.createGain();
			oscillator.frequency.value = 880;
			gain.gain.value = 0.2;
			oscillator.connect( gain );
			gain.connect( context.destination );
			oscillator.start();
			oscillator.stop( context.currentTime + 0.4 );
		} catch ( e ) {
			// Audio is optional.
		}
	}

	function load() {
		return request( 'orders?scope=' + state.scope )
			.then( function ( orders ) {
				var ids = orders.map( function ( order ) {
					return order.id;
				} );
				var fresh = state.knownIds
					? orders.filter( function ( order ) {
							return state.knownIds.indexOf( order.id ) === -1 && order.status === 'received';
					  } )
					: [];
				if ( fresh.length > 0 ) {
					beep();
				}
				state.knownIds = ( state.knownIds || [] ).concat( ids );
				state.orders = orders;
				state.error = '';
			} )
			.catch( function ( error ) {
				state.error = error.message;
			} )
			.then( render );
	}

	function changeStatus( order, status ) {
		if ( status === 'cancelled' && ! window.confirm( 'Anulezi comanda #' + order.number + '? Clientul primește o notificare.' ) ) {
			return;
		}
		state.busy[ order.id ] = true;
		render();
		request( 'orders/' + order.id + '/status', { method: 'POST', body: { status: status } } )
			.then( function ( updated ) {
				state.orders = state.orders.map( function ( item ) {
					return item.id === updated.id ? updated : item;
				} );
				state.error = '';
			} )
			.catch( function ( error ) {
				state.error = 'Comanda #' + order.number + ': ' + error.message;
			} )
			.then( function () {
				delete state.busy[ order.id ];
				render();
			} );
	}

	function toolbar() {
		var scopeSelect = el(
			'select',
			{
				id: 'mlb-scope',
				onChange: function ( event ) {
					state.scope = event.target.value;
					load();
				},
			},
			[
				el( 'option', { value: 'active', text: 'Comenzi deschise', selected: state.scope === 'active' } ),
				el( 'option', { value: 'today', text: 'Toate comenzile de azi', selected: state.scope === 'today' } ),
			]
		);

		var locationSelect = el(
			'select',
			{
				id: 'mlb-location',
				onChange: function ( event ) {
					state.location = event.target.value;
					render();
				},
			},
			[ el( 'option', { value: '', text: 'Toate punctele de lucru' } ) ].concat(
				config.locations.map( function ( location ) {
					return el( 'option', { value: location.id, text: location.name, selected: state.location === location.id } );
				} )
			)
		);

		return el( 'div', { class: 'mlb-toolbar' }, [
			el( 'label', { for: 'mlb-scope', class: 'screen-reader-text', text: 'Ce comenzi' } ),
			scopeSelect,
			el( 'label', { for: 'mlb-location', class: 'screen-reader-text', text: 'Punct de lucru' } ),
			locationSelect,
			el( 'button', { type: 'button', class: 'button', text: 'Reîncarcă', onClick: load } ),
			config.testMode ? el( 'span', { class: 'mlb-badge mlb-badge-test', text: 'Mod de test' } ) : null,
		] );
	}

	function card( order ) {
		var busy = !! state.busy[ order.id ];
		var actions = order.next_statuses.map( function ( status ) {
			return el( 'button', {
				type: 'button',
				class: status === 'cancelled' ? 'button mlb-cancel' : 'button button-primary',
				text: ACTION_LABELS[ status ] || config.statuses[ status ],
				disabled: busy,
				onClick: function () {
					changeStatus( order, status );
				},
			} );
		} );

		var address =
			order.fulfillment === 'delivery'
				? [ order.address.address_1, order.address.address_2, order.address.city ].filter( Boolean ).join( ', ' )
				: 'Ridicare personală';

		return el( 'article', { class: 'mlb-card mlb-status-' + order.status, 'data-order-id': order.id }, [
			el( 'header', { class: 'mlb-card-head' }, [
				el( 'strong', { text: '#' + order.number } ),
				el( 'span', { class: 'mlb-badge', text: config.statuses[ order.status ] } ),
				el( 'span', { class: 'mlb-muted', text: minutesAgo( order.created_at ) } ),
			] ),
			el( 'p', { class: 'mlb-who' }, [
				el( 'strong', { text: order.customer_name || 'Client' } ),
				' · ',
				el( 'a', { href: 'tel:' + order.phone, text: order.phone } ),
			] ),
			el( 'p', { class: 'mlb-where', text: ( order.location_name ? order.location_name + ' · ' : '' ) + address } ),
			el(
				'ul',
				{ class: 'mlb-items' },
				order.items.map( function ( item ) {
					return el( 'li', {}, [
						el( 'span', { text: item.quantity + ' × ' + item.name } ),
						item.preferences ? el( 'em', { text: item.preferences } ) : null,
					] );
				} )
			),
			order.note ? el( 'p', { class: 'mlb-note', text: order.note } ) : null,
			el( 'p', { class: 'mlb-total' }, [
				el( 'span', { text: 'Total, numerar' } ),
				el( 'strong', { text: money( order.total ) } ),
			] ),
			el( 'p', { class: order.grandchef.sent ? 'mlb-gc mlb-gc-ok' : 'mlb-gc mlb-gc-missing' }, [
				order.grandchef.sent ? 'Trimisă la GrandChef #' + order.grandchef.order_id : 'Netrimisă la GrandChef: verifică în WooCommerce',
				' · ',
				el( 'a', { href: order.edit_url, text: 'Deschide comanda' } ),
			] ),
			actions.length ? el( 'div', { class: 'mlb-actions' }, actions ) : null,
		] );
	}

	function render() {
		var orders = state.orders.filter( function ( order ) {
			return ! state.location || order.location_id === state.location;
		} );
		var waiting = orders.filter( function ( order ) {
			return order.status === 'received';
		} ).length;
		document.title = ( waiting ? '(' + waiting + ') ' : '' ) + 'Comenzi aplicație';

		var children = [ toolbar() ];
		if ( state.error ) {
			children.push( el( 'div', { class: 'notice notice-error inline' }, [ el( 'p', { text: state.error } ) ] ) );
		}
		children.push(
			orders.length
				? el( 'div', { class: 'mlb-grid' }, orders.map( card ) )
				: el( 'p', { class: 'mlb-empty', text: state.scope === 'active' ? 'Nicio comandă deschisă.' : 'Nicio comandă azi.' } )
		);

		root.replaceChildren.apply( root, children );
	}

	load();
	window.setInterval( function () {
		if ( ! document.hidden ) {
			load();
		}
	}, REFRESH_MS );
} )();
