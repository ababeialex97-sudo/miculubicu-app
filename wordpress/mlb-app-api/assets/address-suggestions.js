/**
 * Google address suggestions under the street field of the website checkout (block and
 * classic). Picking one fills the street and town, so the delivery price is calculated from
 * an address Google knows. Calls go through the plugin's REST API; the key stays on the server.
 */
(function () {
  'use strict';

  var config = window.mlbAddressSuggestions;
  if (!config || !config.restUrl) {
    return;
  }

  // Block checkout ids first, then the classic ones.
  var STREET_IDS = ['shipping-address_1', 'billing-address_1', 'shipping_address_1', 'billing_address_1'];
  var CITY_FOR = {
    'shipping-address_1': 'shipping-city',
    'billing-address_1': 'billing-city',
    shipping_address_1: 'shipping_city',
    billing_address_1: 'billing_city',
  };
  var MIN_LENGTH = 3;
  var DELAY = 300;

  var timer = null;
  var active = null; // { input, list, items, index }
  var session = newSession();
  var filling = false;
  var lastQuery = '';

  function newSession() {
    if (window.crypto && window.crypto.randomUUID) {
      return window.crypto.randomUUID();
    }
    return Date.now().toString(36) + Math.random().toString(36).slice(2);
  }

  function isStreet(el) {
    return el && el.tagName === 'INPUT' && STREET_IDS.indexOf(el.id) !== -1;
  }

  function get(path) {
    return fetch(config.restUrl + path, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) {
        throw new Error(String(response.status));
      }
      return response.json();
    });
  }

  // React (block checkout) only notices a value set through the native setter plus an event.
  function setValue(input, value) {
    var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    setter.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function close() {
    if (active && active.list.parentNode) {
      active.list.parentNode.removeChild(active.list);
    }
    active = null;
  }

  function anchorOf(input) {
    return input.closest('.wc-block-components-text-input, .form-row') || input.parentNode;
  }

  function show(input, suggestions) {
    close();
    if (!suggestions.length || document.activeElement !== input) {
      return;
    }

    var list = document.createElement('ul');
    list.className = 'mlb-address-suggestions';
    list.setAttribute('role', 'listbox');

    suggestions.forEach(function (suggestion, index) {
      var item = document.createElement('li');
      item.setAttribute('role', 'option');
      item.id = 'mlb-suggestion-' + index;
      var main = document.createElement('strong');
      main.textContent = suggestion.main;
      item.appendChild(main);
      if (suggestion.secondary) {
        var secondary = document.createElement('span');
        secondary.textContent = suggestion.secondary;
        item.appendChild(secondary);
      }
      // mousedown, so the pick happens before the field loses focus.
      item.addEventListener('mousedown', function (event) {
        event.preventDefault();
        pick(input, suggestion);
      });
      list.appendChild(item);
    });

    var credit = document.createElement('li');
    credit.className = 'mlb-address-suggestions__credit';
    credit.textContent = 'Sugestii de la Google';
    list.appendChild(credit);

    var anchor = anchorOf(input);
    anchor.parentNode.insertBefore(list, anchor.nextSibling);
    active = { input: input, list: list, items: suggestions, index: -1 };
  }

  function highlight(index) {
    if (!active) {
      return;
    }
    var options = active.list.querySelectorAll('[role="option"]');
    active.index = (index + options.length) % options.length;
    options.forEach(function (option, i) {
      option.classList.toggle('is-active', i === active.index);
    });
    active.input.setAttribute('aria-activedescendant', options[active.index].id);
  }

  function pick(input, suggestion) {
    close();
    get('delivery/places/' + encodeURIComponent(suggestion.place_id) + '?session=' + encodeURIComponent(session))
      .then(function (place) {
        filling = true;
        setValue(input, place.address_1);
        var city = document.getElementById(CITY_FOR[input.id]);
        if (city && place.city) {
          setValue(city, place.city);
        }
        filling = false;
        lastQuery = place.address_1;
        if (!place.precise) {
          hint(input, 'Adaugă și numărul străzii.');
        }
        if (window.jQuery) {
          window.jQuery(document.body).trigger('update_checkout');
        }
      })
      .catch(function () {
        filling = false;
      })
      .then(function () {
        session = newSession();
      });
  }

  function hint(input, text) {
    var anchor = anchorOf(input);
    var note = document.createElement('p');
    note.className = 'mlb-address-hint';
    note.textContent = text;
    anchor.parentNode.insertBefore(note, anchor.nextSibling);
    input.addEventListener(
      'input',
      function () {
        if (note.parentNode) {
          note.parentNode.removeChild(note);
        }
      },
      { once: true }
    );
  }

  function search(input) {
    var query = input.value.trim();
    if (query.length < MIN_LENGTH || query === lastQuery) {
      if (query.length < MIN_LENGTH) {
        close();
      }
      return;
    }
    lastQuery = query;
    get('delivery/places?q=' + encodeURIComponent(query) + '&session=' + encodeURIComponent(session))
      .then(function (suggestions) {
        if (input.value.trim() === query) {
          show(input, Array.isArray(suggestions) ? suggestions : []);
        }
      })
      .catch(close);
  }

  // Delegated, because the block checkout draws its fields after the page loads.
  document.addEventListener('input', function (event) {
    if (filling || !isStreet(event.target)) {
      return;
    }
    clearTimeout(timer);
    var input = event.target;
    timer = setTimeout(function () {
      search(input);
    }, DELAY);
  });

  document.addEventListener('keydown', function (event) {
    if (!active || event.target !== active.input) {
      return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      highlight(active.index + (event.key === 'ArrowDown' ? 1 : -1));
    } else if (event.key === 'Enter' && active.index >= 0) {
      event.preventDefault();
      pick(active.input, active.items[active.index]);
    } else if (event.key === 'Escape') {
      close();
    }
  });

  document.addEventListener(
    'blur',
    function (event) {
      if (active && event.target === active.input) {
        close();
      }
      if (isStreet(event.target)) {
        lastQuery = '';
      }
    },
    true
  );
})();
