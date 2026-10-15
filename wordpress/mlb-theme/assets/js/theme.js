/**
 * Micu' lu' Bicu theme: mobile menu, header state on scroll, product category tabs, cart bar link.
 * No dependencies; WooCommerce's jQuery events are used only when jQuery is present.
 */
(function () {
	'use strict';

	// Collapse the promo bar once the visitor scrolls.
	var ticking = false;
	function onScroll() {
		if (ticking) {
			return;
		}
		ticking = true;
		window.requestAnimationFrame(function () {
			document.body.classList.toggle('is-scrolled', window.scrollY > 40);
			ticking = false;
		});
	}
	window.addEventListener('scroll', onScroll, { passive: true });
	onScroll();

	document.addEventListener('click', function (event) {
		var target = event.target instanceof Element ? event.target : null;
		if (!target) {
			return;
		}

		// Mobile menu.
		var toggle = target.closest('.mlb-header__toggle');
		if (toggle) {
			var header = toggle.closest('.mlb-header');
			var open = !header.classList.contains('is-open');
			header.classList.toggle('is-open', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			return;
		}
		var menuLink = target.closest('.mlb-header.is-open .mlb-header__nav a');
		if (menuLink) {
			var openHeader = menuLink.closest('.mlb-header');
			openHeader.classList.remove('is-open');
			var button = openHeader.querySelector('.mlb-header__toggle');
			if (button) {
				button.setAttribute('aria-expanded', 'false');
			}
		}

		// Product category tabs.
		var tab = target.closest('.mlb-tab');
		if (tab) {
			var section = tab.closest('[data-mlb-products]');
			var filter = tab.getAttribute('data-filter') || '';
			section.querySelectorAll('.mlb-tab').forEach(function (other) {
				var active = other === tab;
				other.classList.toggle('is-active', active);
				other.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
			section.querySelectorAll('.mlb-product').forEach(function (card) {
				var cats = (card.getAttribute('data-cats') || '').split(' ');
				card.hidden = filter !== '' && cats.indexOf(filter) === -1;
			});
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') {
			return;
		}
		document.querySelectorAll('.mlb-header.is-open').forEach(function (header) {
			header.classList.remove('is-open');
			var button = header.querySelector('.mlb-header__toggle');
			if (button) {
				button.setAttribute('aria-expanded', 'false');
				button.focus();
			}
		});
	});

	// The phone cart bar goes to the menu while the cart is empty, to the cart otherwise.
	function syncCartBar() {
		document.querySelectorAll('.mlb-cartbar').forEach(function (bar) {
			var count = bar.querySelector('.mlb-cart-count');
			var empty = !count || count.getAttribute('data-count') === '0';
			var link = bar.querySelector('.mlb-cartbar__btn');
			if (link) {
				link.setAttribute('href', bar.getAttribute(empty ? 'data-empty-href' : 'data-cart-href'));
			}
		});
	}
	syncCartBar();

	if (window.jQuery) {
		window.jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded', syncCartBar);
	}
})();
