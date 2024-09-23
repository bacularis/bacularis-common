const Components = {
	comp: {
		'dir': {full_name: 'Director'},
		'sd': {full_name: 'Storage Daemon'},
		'fd': {full_name: 'File Daemon'},
		'bcons': {full_name: 'Console'}
	},
	get_full_name: function(comp) {
		let name = '';
		if (this.comp.hasOwnProperty(comp)) {
			name = this.comp[comp].full_name;
		}
		return name;
	}
};

var Cookies = {
	default_exipration_time: 31536000000, // 1 year in miliseconds
	set_cookie: function(name, value, expiration) {
		var date = new Date();
		date.setTime(date.getTime() + this.default_exipration_time);
		var expires = 'expires=' + date.toUTCString();
		document.cookie = name + '=' + value + '; ' + expires;
	},
	get_cookie: function(name) {
		name += '=';
		var values = document.cookie.split(';');
		var cookie_val = null;
		var value;
		for (var i = 0; i < values.length; i++) {
			value = values[i];
			while (value.charAt(0) == ' ') {
				value = value.substr(1);
			}
			if (value.indexOf(name) == 0) {
				cookie_val = value.substring(name.length, value.length);
				break;
			}
		}
		return cookie_val;
	}
}

var W3TabsCommon = {
	open: function(btn_id, item_id, item_container_id) {
		var root = document.getElementById(item_container_id) || document;
		var tab_items = root.getElementsByClassName(this.css.tab_item);
		for (var i = 0; i < tab_items.length; i++) {
			if (tab_items[i].id === item_id) {
				tab_items[i].style.display = 'block';
			} else {
				tab_items[i].style.display = 'none';
			}
		}
		var tab_btns = root.getElementsByClassName(this.css.tab_btn);
		for (var i = 0; i < tab_btns.length; i++) {
			if (tab_btns[i].id === btn_id && !tab_btns[i].classList.contains(this.css.tab_item_hover)) {
				tab_btns[i].classList.add(this.css.tab_item_hover);
			} else if (tab_btns[i].id !== btn_id && tab_btns[i].classList.contains(this.css.tab_item_hover)) {
				tab_btns[i].classList.remove(this.css.tab_item_hover);
			}
		}
	},
	is_open: function(item_id) {
		var display = document.getElementById(item_id).style.display;
		return (display === 'block' || display === '');
	}
};

var W3Tabs = {
	css: {
		tab_btn: 'tab_btn',
		tab_item: 'tab_item',
		tab_item_hover: 'w3-grey'
	},
	open: function(btn_id, item_id) {
		set_url_fragment(item_id);
		W3TabsCommon.open.call(this, btn_id, item_id);
	},
	is_open: function(item_id) {
		return W3TabsCommon.is_open(item_id);
	}
};

var W3SubTabs = {
	css: {
		tab_btn: 'subtab_btn',
		tab_item: 'subtab_item',
		tab_item_hover: 'w3-border-red'
	},
	open: function(btn_id, item_id, item_container_id) {
		W3TabsCommon.open.call(this, btn_id, item_id, item_container_id);
	},
	is_open: function(item_id) {
		return W3TabsCommon.is_open(item_id);
	}
};

var W3SideBar = {
	ids: {
		sidebar: 'sidebar',
		overlay_bg: 'overlay_bg'
	},
	css: {
		page_main: '.page_main_el'
	},
	cookies: {
		side_bar_hide: 'baculum_side_bar_hide'
	},
	init: function() {
		this.sidebar = document.getElementById(this.ids.sidebar);
		this.page_main = $(this.css.page_main);
		if (!this.sidebar) {
			// don't initialize for pages without sidebar
			this.page_main.css({'margin-left': '0', 'width': '100%'});
			return;
		}
		this.overlay_bg = document.getElementById(this.ids.overlay_bg);
		var hide = Cookies.get_cookie(this.cookies.side_bar_hide);
		if (hide == 1) {
			this.close();
		}
		this.set_events();
	},
	set_events: function() {
		if (this.sidebar) {
			this.sidebar.addEventListener('touchstart', handle_touch_start);
			this.sidebar.addEventListener('touchmove', function(e) {
				handle_touch_move(e, {
					'swipe_left': function() {
						this.close();
					}.bind(this)
				});
			}.bind(this));
		}
	},
	open: function() {
		if (this.sidebar.style.display === 'block' || this.sidebar.style.display === '') {
			this.close();
		} else {
			Cookies.set_cookie('baculum_side_bar_hide', 0);
			this.sidebar.style.display = 'block';
			this.overlay_bg.style.display = 'block';
			this.page_main.css({'margin-left': '250px', 'width': 'calc(100% - 250px)'});
		}
	},
	close: function() {
		Cookies.set_cookie('baculum_side_bar_hide', 1);
		this.sidebar.style.display = 'none';
		this.overlay_bg.style.display = 'none';
		this.page_main.css({'margin-left': '0', 'width': '100%'});
	}
};

const ThemeMode = {
	modes: {
		light: 'light',
		dark: 'dark'
	},
	ids: {
		switcher: 'theme_mode_switcher'
	},
	css: {
		dark_theme: 'dark-theme',
		light_dark: 'light-dark',
		deep_dark: 'deep_dark'
	},
	ls_key: 'bacularis-theme-mode',
	default_mode: 'light',
	cbs: [],
	pre_init: function() {
		const mode = this.get_mode();
		this.set_theme(mode);
	},
	init: function() {
		this.set_events();
		this.init_theme();
	},
	add_cb(cb) {
		this.cbs.push(cb);
	},
	init_theme: function() {
		const mode = this.get_mode();
		this.set_theme(mode);
		this.set_switcher(mode);
	},
	set_mode: function(mode) {
		localStorage.setItem(this.ls_key, mode);
	},
	get_mode: function() {
		return localStorage.getItem(this.ls_key) || this.default_mode;
	},
	set_theme: function(mode) {
		if (mode === this.modes.light) {
			document.body.classList.remove(this.css.dark_theme);
		} else if (mode === this.modes.dark) {
			document.body.classList.add(this.css.dark_theme);
		}
	},
	set_switcher: function(mode) {
		const switcher = document.getElementById(this.ids.switcher);
		if (switcher) {
			switcher.checked = (mode === this.modes.dark);
		}
	},
	set_events: function() {
		const switcher = document.getElementById(this.ids.switcher);
		if (!switcher) {
			return;
		}
		const self = this;
		switcher.addEventListener('click', function() {
			if (this.checked) {
				self.switch_mode(self.modes.light);
			} else {
				self.switch_mode(self.modes.dark);
			}
		});
	},
	toggle_mode: function() {
		const mode = this.get_mode();
		if (mode === this.modes.light) {
			this.switch_mode(this.modes.dark);
		} else if (mode === this.modes.dark) {
			this.switch_mode(this.modes.light);
		}
	},
	switch_mode: function(mode) {
		this.set_mode(mode);
		this.set_theme(mode);
		this.trigger_post_switch_actions();
	},
	trigger_post_switch_actions: function() {
		for (let i = 0; i < this.cbs.length; i++) {
			if (typeof(this.cbs[i]) !== 'function') {
				continue;
			}
			this.cbs[i]();
		}
	},
	is_dark: function() {
		const mode = this.get_mode();
		return (mode === this.modes.dark);
	}
};
ThemeMode.pre_init();

var touch_start_x = null;
var touch_start_y = null;

function handle_touch_start(e) {
	// browser API or jQuery
	var first_touch =  e.touches || e.originalEvent.touches
	touch_start_x = first_touch[0].clientX;
	touch_start_y = first_touch[0].clientY;
}

function handle_touch_move(e, callbacks) {
	if (!touch_start_x || !touch_start_y || typeof(callbacks) !== 'object') {
		// no touch type event or no callbacks
		return;
	}

	var touch_end_x = e.touches[0].clientX;
	var touch_end_y = e.touches[0].clientY;

	var touch_diff_x = touch_start_x - touch_end_x;
	var touch_diff_y = touch_start_y - touch_end_y;

	if (Math.abs(touch_diff_x) > Math.abs(touch_diff_y)) {
		if (touch_diff_x > 0 && callbacks.hasOwnProperty('swipe_left')) {
			// left swipe
			callbacks.swipe_left();
		} else if (callbacks.hasOwnProperty('swipe_right')) {
			// right swipe
			callbacks.swipe_right();
		}
	} else {
		if (touch_diff_y > 0 && callbacks.hasOwnProperty('swipe_up')) {
			// up swipe
			callbacks.swipe_up()
		} else if (callbacks.hasOwnProperty('swipe_down')) {
			// down swipe
			callbacks.swipe_down()
		}
	}

	// reset values
	touch_start_x = null;
	touch_start_y = null;
}

function set_global_listeners() {
	document.addEventListener('keydown', function(e) {
		var key_code = e.keyCode || e.which;
		switch (key_code) {
			case 27: { // escape
				$('.w3-modal').filter(':visible').hide(); // hide modals
				break;
			}
		}
	});
	var modals = document.getElementsByClassName('w3-modal');
	for (var i = 0; i < modals.length; i++) {
		modals[i].addEventListener('click', function(e) {
			var el = e.target || e.srcElement;
			if (el.classList.contains('w3-modal')) {
				$(this).hide(); // shadow clicked, hide modal
			}
		});
	};
}


var get_random_string = function(allowed, len) {
	if (!allowed) {
		allowed = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	}
	var random_string = '';
	for(var i = 0; i < len; i++) {
		random_string += allowed.charAt(Math.floor(Math.random() * allowed.length));
	}
	return random_string;
}

var OAuth2Scopes = [
	'console',
	'jobs',
	'directors',
	'clients',
	'storages',
	'devices',
	'volumes',
	'pools',
	'bvfs',
	'joblog',
	'filesets',
	'schedules',
	'config',
	'actions',
	'oauth2',
	'basic',
	'software'
];
var set_scopes = function(field_id) {
	document.getElementById(field_id).value = OAuth2Scopes.join(' ');
}

function get_url_fragment() {
	var url = window.location.href;
	var regex = new RegExp('#(.+)$');
	var results = regex.exec(url);
	var ret;
	if (!results) {
		ret = '';
	} else if (results[1]) {
		ret = results[1].replace(/\+/g, " ");
		ret = decodeURIComponent(ret);
	}
	return ret;
}

function set_url_fragment(fragment) {
	let url = window.location.href;
	let prev_fragment = get_url_fragment();
	if (prev_fragment) {
		// remove previous fragment
		prev_fragment = prev_fragment.replace(/\s/g, '+');
		prev_fragment = encodeURIComponent(prev_fragment);
		const regex = new RegExp('#' + prev_fragment + '$');
		url = url.replace(regex, '');
	}
	url = url + '#' + fragment;
	window.history.pushState({}, '', url);
}

function set_tab_by_url_fragment() {
	const fragment = get_url_fragment();
	// for HTML elements (buttons, anchors...)
	let btn_el = $('#btn_' + fragment);
	if (btn_el.length == 0) {
		// for PRADO controls (TActiveButton, TActiveLinkButton...)
		const el = document.getElementById(fragment);
		if (el) {
			const btn_id = el.getAttribute('data-btn');
			btn_el = $('#' + btn_id);
		}
	}
	if (btn_el.length == 1) {
		try {
			btn_el.click();
		} catch (e) {
			if (e instanceof TypeError) {
				/*
				 * Tabs are opened before content is loaded.
				 * If onlick handler is content dependent, it causes errors.
				 * Mostly it is setting responsivity for tables. Make it silent.
				 */
			}
		}
	}
}


function copy_to_clipboard(text) {
	if (navigator.clipboard) {
		navigator.clipboard.writeText(text);
	} else {
		const textarea = document.createElement("TEXTAREA");
		textarea.style.width = 0;
		textarea.style.height = 0;
		textarea.textContent = text;
		document.body.appendChild(textarea);
		textarea.select();
		textarea.focus();
		document.execCommand('copy');
		textarea.blur();
		document.body.removeChild(textarea);
	}
}

function save_file(data, filename, type) {
	const file = new Blob([data], {type: type});
	const a = document.createElement('A');
	const url = URL.createObjectURL(file);
	a.href = url;
	a.download = filename;
	document.body.appendChild(a);
	a.click();
	document.body.removeChild(a);
	window.URL.revokeObjectURL(url);
}

function getClosestScrollEl(el) {
	let ret = null;
	if (el !== null) {
		if (el.clientHeight > 0 && el.scrollHeight > el.clientHeight) {
			ret = el;
		} else {
			ret = getClosestScrollEl(el.parentNode);
		}
	}
	return ret;
}

function base64tohex(btext) {
	let dec;
	try {
		dec = atob(btext);
	} catch(e) {
		// unable to decode base64 string
	}

	let ret;
	if (dec) {
		ret = dec.split('').map(function(ch) {
			return ch.charCodeAt(0).toString(16).replace(/^([\da-f])$/, '0$1');
		}).join('');
	}
	return ret;
}

function get_table_toolbar(table, actions, txt) {
	var table_toolbar = document.querySelector('#' + table.table().node().id + '_wrapper div.table_toolbar');
	table_toolbar.className += ' dt-buttons';
	table_toolbar.style.display = 'none';
	var title = document.createTextNode(txt.actions);
	var select = document.createElement('SELECT');
	var option = document.createElement('OPTION');
	option.value = '';
	select.appendChild(option);
	var acts = {};
	for (var i = 0; i < actions.length; i++) {
		if (actions[i].hasOwnProperty('enabled') && actions[i].enabled === false) {
			continue;
		}
		option = document.createElement('OPTION');
		option.value = actions[i].action,
		label = document.createTextNode(actions[i].label);
		option.appendChild(label);
		select.appendChild(option);
		acts[actions[i].action] = actions[i];
	}
	const acts_len = Object.keys(acts).length;
	if (acts_len == 0) {
		return table_toolbar;
	} else if (acts_len == 1) {
		select.selectedIndex = 1;
	}
	var btn = document.createElement('BUTTON');
	btn.type = 'button';
	btn.className = 'dt-button';
	btn.style.verticalAlign = 'top';
	label = document.createTextNode(txt.ok);
	btn.appendChild(label);
	btn.addEventListener('click', function(e) {
		if (!select.value) {
			// no value, no action
			return
		}
		var selected = [];
		var sel_data = table.rows({selected: true}).data();
		sel_data.each(function(v, k) {
			selected.push(v[acts[select.value].value]);
		});

		// call validation if defined
		if (acts[select.value].hasOwnProperty('validate') && typeof(acts[select.value].validate) == 'function') {
			if (acts[select.value].validate(sel_data) === false) {
				// validation error
				return false;
			}
		}
		// call pre-action before calling bulk action
		if (acts[select.value].hasOwnProperty('before') && typeof(acts[select.value].before) == 'function') {
			acts[select.value].before();
		}
		selected = selected.join('|');
		if (acts[select.value].hasOwnProperty('callback')) {
			acts[select.value].callback.options.RequestTimeOut = 60000; // Timeout set to 1 minute
			acts[select.value].callback.setCallbackParameter(selected);
			acts[select.value].callback.dispatch();
		}
	});
	table_toolbar.appendChild(title);
	table_toolbar.appendChild(select);
	table_toolbar.appendChild(btn);
	return table_toolbar;
}

/**
 * Used to escape values before putting them into regular expression.
 * Dedicated to use in table values.
 */
dtEscapeRegex = function(value) {
	if (typeof(value) != 'string' && typeof(value.toString) == 'function') {
		value = value.toString();
	}
	return $.fn.dataTable.util.escapeRegex(value);
};

if (typeof($) == 'function') {
	$(function() {
		ThemeMode.init();
		W3SideBar.init();
		set_global_listeners();
		set_tab_by_url_fragment();
	});
}
