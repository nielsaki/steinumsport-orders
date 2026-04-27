/**
 * Repeating order lines: add / remove, reindex, show/hide fields by item type.
 * Uses class "is-ssc-hidden" (not the global [hidden] attribute) + event delegation
 * so late-injected / preview shortcodes and Safari stay reliable.
 */
(function () {
	'use strict';
	/* Playground / missing wp_footer: script may be inlined; avoid double-bind if external + inline both run. */
	if (window.__sscOrderLinesInit) {
		return;
	}
	window.__sscOrderLinesInit = true;

	var ITEMS = {
		TRIKOT: 'trikot',
		TSHIRT: 'tshirt',
		RASH: 'rashguard',
		SPEED: 'speedcoach',
		NK: 'nk_stopur',
	};

	function isFarvType(item) {
		return item === ITEMS.SPEED || item === ITEMS.NK;
	}

	function rowsContainer() {
		return document.getElementById('ssc-line-rows');
	}

	/** @param {Element | null} el @param {boolean} hide */
	function setLinePartHidden(el, hide) {
		if (!el) {
			return;
		}
		el.classList.toggle('is-ssc-hidden', hide);
		if (hide) {
			el.setAttribute('aria-hidden', 'true');
		} else {
			el.removeAttribute('aria-hidden');
		}
	}

	function reindex() {
		var c = rowsContainer();
		if (!c) {
			return;
		}
		var rows = c.querySelectorAll('.ssc-line-row');
		var i, row, j, fields, f, name;
		for (i = 0; i < rows.length; i++) {
			row = rows[i];
			fields = row.querySelectorAll('select, input');
			for (j = 0; j < fields.length; j++) {
				f = fields[j].getAttribute('name');
				if (f) {
					name = f.replace(/order_lines\[\d+]/, 'order_lines[' + i + ']');
					fields[j].setAttribute('name', name);
				}
			}
			row.setAttribute('data-line-index', String(i));
		}
	}

	function getItemValue(row) {
		var sel = row.querySelector('.ssc-line-item');
		return sel ? String(sel.value || '') : '';
	}

	function applyLineMode(row) {
		var item = getItemValue(row);
		var g = row.querySelector('[data-ssc-line-part="trikot-gender"]') || row.querySelector('.ssc-sub-trikot-gender');
		var sz = row.querySelector('[data-ssc-line-part="size"]') || row.querySelector('.ssc-sub-size');
		var sp = row.querySelector('[data-ssc-line-part="bumper"]') || row.querySelector('.ssc-sub-speed');
		var nm = row.querySelector('[data-ssc-line-part="name"]') || row.querySelector('.ssc-sub-name');
		var qtyW = row.querySelector('[data-ssc-line-part="qty"]') || row.querySelector('.ssc-sub-qty');
		var qtyIn = row.querySelector('.ssc-line-qty');
		var qtyLab = row.querySelector('.ssc-qty-label');

		var isTrikot = item === ITEMS.TRIKOT;
		var isClothSize = isTrikot || item === ITEMS.TSHIRT || item === ITEMS.RASH;
		var isFarv = isFarvType(item);
		var hasItem = item.length > 0;

		var c = rowsContainer();
		var nRows = c ? c.querySelectorAll('.ssc-line-row').length : 0;
		var action = row.querySelector('[data-ssc-line-part="action"]') || row.querySelector('.ssc-line-close');
		var lineGrid = row.querySelector('.ssc-line-grid');
		if (lineGrid) {
			lineGrid.setAttribute('data-ssc-has-item', hasItem ? '1' : '0');
		}
		setLinePartHidden(action, nRows < 2);

		setLinePartHidden(g, !isTrikot);
		setLinePartHidden(sz, !isClothSize);
		setLinePartHidden(sp, !isFarv);
		setLinePartHidden(nm, !hasItem || isFarv);
		setLinePartHidden(qtyW, !hasItem);

		var itemSel = row.querySelector('.ssc-line-item');
		if (itemSel) {
			itemSel.setAttribute('required', 'required');
		}
		var gEl = row.querySelector('.ssc-line-gender');
		if (gEl) {
			if (isTrikot) {
				gEl.disabled = false;
				gEl.setAttribute('required', 'required');
			} else {
				gEl.disabled = true;
				gEl.removeAttribute('required');
			}
		}
		var szSe = row.querySelector('.ssc-line-size');
		if (szSe) {
			if (isClothSize) {
				szSe.disabled = false;
				szSe.setAttribute('required', 'required');
			} else {
				szSe.disabled = true;
				szSe.removeAttribute('required');
			}
		}
		var nmIn = row.querySelector('.ssc-line-name');
		if (nmIn) {
			nmIn.removeAttribute('required');
		}

		if (qtyIn) {
			if (!hasItem) {
				qtyIn.disabled = true;
				qtyIn.removeAttribute('required');
				qtyIn.value = '';
			} else {
				qtyIn.disabled = false;
				qtyIn.setAttribute('required', 'required');
				if (qtyIn.value === '' || qtyIn.value === '0') {
					qtyIn.value = '1';
				}
			}
		}
		if (qtyLab) {
			var L = window.sscLinesL10n || {};
			qtyLab.textContent = L.qtyDefault || 'Nøgd';
		}
		var bumSel = sp ? sp.querySelector('select.ssc-line-bump') : null;
		if (bumSel && bumSel.tagName === 'SELECT') {
			if (isFarv) {
				bumSel.disabled = false;
				bumSel.setAttribute('required', 'required');
			} else {
				bumSel.disabled = true;
				bumSel.removeAttribute('required');
				bumSel.selectedIndex = 0;
			}
		}
	}

	function syncAllLineRows() {
		var c = rowsContainer();
		if (!c) {
			return;
		}
		var rows = c.querySelectorAll('.ssc-line-row');
		var r;
		for (r = 0; r < rows.length; r++) {
			applyLineMode(rows[r]);
		}
	}

	function onAdd() {
		var c = rowsContainer();
		if (!c || c.children.length < 1) {
			return;
		}
		var proto = c.children[0];
		var next = proto.cloneNode(true);
		var ins = next.querySelectorAll('select, input');
		var t, el;
		for (t = 0; t < ins.length; t++) {
			el = ins[t];
			if (el.name && el.name.indexOf('[item]') > -1) {
				el.selectedIndex = 0;
			} else if (el.name && el.name.indexOf('[qty]') > -1) {
				el.value = '';
				el.disabled = true;
				el.removeAttribute('required');
			} else if (el.name && el.name.indexOf('[size]') > -1) {
				el.selectedIndex = 0;
			} else if (el.name && el.name.indexOf('[gender]') > -1) {
				el.selectedIndex = 0;
			} else if (el.name && el.name.indexOf('[bumper_color]') > -1) {
				el.selectedIndex = 0;
			} else if (el.type === 'text' && el.name && el.name.indexOf('[name]') > -1) {
				el.value = '';
			}
		}
		c.appendChild(next);
		reindex();
		syncAllLineRows();
	}

	function onRemove(e) {
		var t = e.target;
		if (!t || !t.closest) {
			return;
		}
		var btn = t.closest('.ssc-remove-line');
		if (!btn) {
			return;
		}
		var row = btn.closest('.ssc-line-row');
		var c = rowsContainer();
		if (!row || !c) {
			return;
		}
		if (c.querySelectorAll('.ssc-line-row').length < 2) {
			return;
		}
		row.parentNode.removeChild(row);
		reindex();
		syncAllLineRows();
	}

	function onItemTypeChange(e) {
		var t = e.target;
		if (!t || !t.classList || !t.classList.contains('ssc-line-item') || !t.closest) {
			return;
		}
		var row = t.closest('.ssc-line-row');
		if (row) {
			applyLineMode(row);
		}
	}

	function onAddLineClick(e) {
		var t = e.target;
		if (!t || !t.closest) {
			return;
		}
		if (t.closest('#ssc-add-line')) {
			e.preventDefault();
			onAdd();
		}
	}

	/* Delegation works even if #ssc-line-rows is missing at first script parse (previews, async blocks). */
	document.addEventListener('click', onAddLineClick, false);
	document.addEventListener('click', onRemove, false);
	document.addEventListener('change', onItemTypeChange, false);
	document.addEventListener('input', onItemTypeChange, false);

	function bootLineUi() {
		var c = rowsContainer();
		if (!c) {
			return;
		}
		syncAllLineRows();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootLineUi);
	} else {
		bootLineUi();
	}
	window.addEventListener('load', function () {
		setTimeout(bootLineUi, 0);
	});
	setTimeout(bootLineUi, 100);
	setTimeout(bootLineUi, 500);

	/**
	 * Full-page cache often serves a stale _wpnonce; fetch a fresh one right before POST.
	 * Uses native form.submit() after updating the field so this handler does not loop.
	 */
	(function attachFreshNonceOnSubmit() {
		var busy = false;
		document.addEventListener(
			'submit',
			function (e) {
				var form = e.target;
				if (!form || !form.classList || !form.classList.contains('ssc-form')) {
					return;
				}
				var cfg = window.sscFormAjax;
				if (!cfg || !cfg.ajaxUrl || !cfg.action || busy) {
					return;
				}
				var nonceInput = form.querySelector('input[name="_wpnonce"]');
				if (!nonceInput) {
					return;
				}
				e.preventDefault();
				busy = true;
				var sep = cfg.ajaxUrl.indexOf('?') >= 0 ? '&' : '?';
				var url =
					cfg.ajaxUrl +
					sep +
					'action=' +
					encodeURIComponent(cfg.action) +
					'&_=' +
					String(Date.now());
				fetch(url, { credentials: 'same-origin', cache: 'no-store' })
					.then(function (r) {
						return r.json().catch(function () {
							return null;
						});
					})
					.then(function (j) {
						if (j && j.success && j.data && j.data.nonce) {
							nonceInput.value = j.data.nonce;
						}
						busy = false;
						HTMLFormElement.prototype.submit.call(form);
					})
					.catch(function () {
						busy = false;
						HTMLFormElement.prototype.submit.call(form);
					});
			},
			true
		);
	})();

	/** Fallback for rare environments that skip delegated `change` (some mobile / shadow roots). */
	window.sscLineItemChanged = function (selectEl) {
		var r = selectEl && selectEl.closest && selectEl.closest('.ssc-line-row');
		if (r) {
			applyLineMode(r);
		}
	};
})();
