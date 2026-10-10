/* GoalTrick - pequeños comportamientos de la interfaz */
(function () {
    'use strict';

    // Traducciones: la página define window.GT_I18N con los textos de JS_STRINGS ya traducidos
    var L = function (text, args) {
        var d = window.GT_I18N || {};
        var r = d[text] || text;
        var cut = r.indexOf('##');
        if (cut >= 0) { r = r.substring(0, cut); }
        if (args) {
            Object.keys(args).forEach(function (k) { r = r.split('{' + k + '}').join(String(args[k])); });
        }
        return r;
    };

    // Reloj del servidor
    var clock = document.getElementById('server-clock');
    if (clock) {
        var offset = parseInt(clock.dataset.ts, 10) - Date.now();
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        setInterval(function () {
            var d = new Date(Date.now() + offset);
            clock.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
        }, 1000);
    }

    // Cuentas atrás
    var countdowns = document.querySelectorAll('[data-deadline]');
    if (countdowns.length) {
        var tick = function () {
            countdowns.forEach(function (el) {
                var diff = Math.floor((new Date(el.dataset.deadline).getTime() - Date.now()) / 1000);
                if (diff <= 0) { el.textContent = L('finalizado'); return; }
                var d = Math.floor(diff / 86400), h = Math.floor(diff % 86400 / 3600),
                    m = Math.floor(diff % 3600 / 60), s = diff % 60;
                el.textContent = (d ? d + 'd ' : '') + h + 'h ' + m + 'm ' + s + 's';
            });
        };
        tick();
        setInterval(tick, 1000);
    }

    // Confirmaciones
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm)) { e.preventDefault(); }
        });
    });
    document.querySelectorAll('form[data-confirm-submit]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!confirm(f.dataset.confirmSubmit)) { e.preventDefault(); }
        });
    });

    // Alineación: arrastrar y soltar, clic + clic y tooltip con los datos del jugador
    var form = document.getElementById('lineup-form');
    var dataEl = document.getElementById('lineup-data');
    if (form && dataEl) {
        var DATA = JSON.parse(dataEl.textContent);
        var P = DATA.players, NAMES = DATA.slots;
        var slotEls = {};
        form.querySelectorAll('.lslot').forEach(function (el) { slotEls[el.dataset.slot] = el; });
        var list = document.getElementById('squad-list');
        var tip = document.getElementById('player-tip');
        var counter = document.getElementById('starter-count');
        var formationLabel = document.getElementById('formation-label');
        var filter = 'all';
        var selected = null;   // {pid, from}
        var dragging = null;   // {pid, from}

        // Mejor rendimiento de la plantilla en cada posición (para colorear)
        var best = {};
        Object.keys(P).forEach(function (id) {
            var f = P[id].fit;
            Object.keys(f).forEach(function (s) { if (!best[s] || f[s] > best[s]) { best[s] = f[s]; } });
        });

        var esc = function (t) { return String(t).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); };
        var get = function (slot) { var v = slotEls[slot].querySelector('input').value; return v ? parseInt(v, 10) : 0; };
        var set = function (slot, pid) { slotEls[slot].querySelector('input').value = pid ? pid : ''; };
        var slotOf = function (pid) {
            for (var s in slotEls) { if (get(s) === pid) { return s; } }
            return null;
        };
        var status = function (p) {
            if (p.inj > 0.5) { return '<span class="pc-flag inj" title="' + esc(L('Lesionado {n} sem.', {n: Math.ceil(p.inj)})) + '">✚</span>'; }
            if (p.inj > 0) { return '<span class="pc-flag bruise" title="' + esc(L('Magullado')) + '">🩹</span>'; }
            if (p.susp) { return '<span class="pc-flag susp" title="' + esc(L('Sancionado')) + '">🟥</span>'; }
            if (p.yc >= 2) { return '<span class="pc-flag" title="' + esc(L('{n} amarillas', {n: p.yc})) + '">🟨</span>'; }
            return '';
        };

        var render = function () {
            var starters = 0, lines = {D: 0, M: 0, F: 0};
            Object.keys(slotEls).forEach(function (slot) {
                var el = slotEls[slot], body = el.querySelector('.lslot-body'), pid = get(slot), p = P[pid];
                el.classList.toggle('filled', !!p);
                el.classList.toggle('is-selected', !!(selected && selected.from === slot));
                if (!p) {
                    if (pid) { set(slot, 0); }
                    body.innerHTML = '<div class="lslot-empty">+</div>';
                    return;
                }
                if (!/^SUB_/.test(slot)) {
                    starters++;
                    if (/FW/.test(slot)) { lines.F++; } else if (/CD|B$/.test(slot)) { lines.D++; } else if (slot !== 'GK') { lines.M++; }
                }
                var off = slot.charAt(0) !== 'S' && slot !== 'GK' ? p.fit[slot] / (best[slot] || 1) : 1;
                body.innerHTML = '<div class="pcard' + (p.inj > 0.5 || p.susp ? ' unavailable' : '') + '" draggable="true" data-pid="' + p.id + '" data-from="' + slot + '">'
                    + '<img src="' + esc(p.img) + '" alt="">' + status(p)
                    + '<div class="pc-name">' + esc(p.short) + '</div>'
                    + '<div class="pc-meta"><span class="role-tag">' + p.role + '</span> ' + esc(L('F{f} · C{c}', {f: p.form, c: p.stamina}))
                    + (off < 0.8 ? ' <span class="pc-warn" title="' + esc(L('En tu plantilla hay jugadores que rinden bastante más en esta posición')) + '">⚠</span>' : '') + '</div>'
                    + '<button type="button" class="pc-x" title="' + esc(L('Quitar')) + '">✕</button></div>';
            });
            var html = '';
            Object.keys(P).map(function (k) { return P[k]; }).forEach(function (p) {
                var at = slotOf(p.id);
                if (filter === 'free' && at) { return; }
                if (filter !== 'all' && filter !== 'free' && p.role !== filter) { return; }
                html += '<div class="pchip' + (at ? ' used' : '') + (p.inj > 0.5 || p.susp ? ' unavailable' : '') + (selected && selected.pid === p.id && !selected.from ? ' is-selected' : '')
                    + '" draggable="true" data-pid="' + p.id + '"><img src="' + esc(p.img) + '" alt="">'
                    + '<div class="pchip-main"><div class="pchip-name">' + esc(p.name) + ' ' + status(p) + '</div>'
                    + '<div class="small muted"><span class="role-tag">' + p.role + '</span> ' + esc(L('{age} años · TSI {tsi} · forma {form}', {age: p.age, tsi: p.tsi, form: p.form})) + '</div></div>'
                    + (at ? '<span class="pchip-at" title="' + esc(NAMES[at]) + '">' + esc(at.replace('SUB_', 'S·')) + '</span>' : '') + '</div>';
            });
            list.innerHTML = html || '<div class="box-body small muted">' + esc(L('No hay jugadores en este filtro.')) + '</div>';
            counter.textContent = starters;
            counter.className = starters === 11 ? 'pos' : 'neg';
            formationLabel.textContent = (get('GK') ? '' : L('sin portero') + ' · ') + lines.D + '-' + lines.M + '-' + lines.F;
            // Capitán y lanzador: sólo jugadores alineados
            ['captain-select', 'setpieces-select'].forEach(function (id) {
                var sel = document.getElementById(id);
                if (!sel) { return; }
                Array.prototype.forEach.call(sel.options, function (o) {
                    o.disabled = !!(o.value && !slotOf(parseInt(o.value, 10)));
                });
                if (sel.value && sel.options[sel.selectedIndex].disabled) { sel.value = ''; }
            });
        };

        var place = function (pid, from, to) {
            if (!to || from === to) { return; }
            var prev = slotOf(pid);
            if (from == null) { from = prev; }
            var target = get(to);
            var isStarter = function (s) { return s && !/^SUB_/.test(s); };
            if (isStarter(to) && !target && !isStarter(from)) {
                var n = 0;
                for (var s in slotEls) { if (isStarter(s) && get(s)) { n++; } }
                if (n >= 11) {
                    notice(L('Ya tienes 11 titulares. Suelta al jugador sobre un titular para sustituirlo o quita antes a otro.'));
                    selected = null;
                    render();
                    return;
                }
            }
            if (from) {
                set(from, target);   // intercambio (o deja el hueco vacío)
            }
            set(to, pid);
            selected = null;
            render();
        };
        var msgEl = document.getElementById('lineup-msg'), msgTimer = null;
        var notice = function (t) {
            if (!msgEl) { return; }
            msgEl.textContent = t;
            msgEl.hidden = false;
            clearTimeout(msgTimer);
            msgTimer = setTimeout(function () { msgEl.hidden = true; }, 4000);
        };
        var remove = function (slot) { if (slot) { set(slot, 0); selected = null; render(); } };

        var paintFit = function (pid) {
            var p = P[pid];
            Object.keys(slotEls).forEach(function (slot) {
                var el = slotEls[slot];
                el.classList.remove('fit-good', 'fit-ok', 'fit-bad');
                if (!p || !p.fit[slot]) { return; }
                var r = p.fit[slot] / (best[slot] || 1);
                el.classList.add(r >= 0.9 ? 'fit-good' : (r >= 0.75 ? 'fit-ok' : 'fit-bad'));
            });
        };

        // Arrastrar y soltar
        form.addEventListener('dragstart', function (e) {
            var c = e.target.closest('[data-pid]');
            if (!c) { return; }
            dragging = {pid: parseInt(c.dataset.pid, 10), from: c.dataset.from || null};
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', String(dragging.pid)); } catch (err) {}
            tip.hidden = true;
            paintFit(dragging.pid);
            setTimeout(function () { c.classList.add('dragging'); }, 0);
        });
        form.addEventListener('dragend', function () {
            dragging = null;
            paintFit(null);
            form.querySelectorAll('.over, .dragging').forEach(function (el) { el.classList.remove('over', 'dragging'); });
        });
        form.addEventListener('dragover', function (e) {
            if (!dragging) { return; }
            var d = e.target.closest('.drop, .drop-out');
            if (d) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; d.classList.add('over'); }
        });
        form.addEventListener('dragleave', function (e) {
            var d = e.target.closest('.drop, .drop-out');
            if (d && !d.contains(e.relatedTarget)) { d.classList.remove('over'); }
        });
        form.addEventListener('drop', function (e) {
            if (!dragging) { return; }
            e.preventDefault();
            var d = e.target.closest('.drop, .drop-out');
            var info = dragging;
            if (d && d.classList.contains('drop')) {
                place(info.pid, info.from, d.dataset.slot);
            } else if (d && info.from) {
                remove(info.from);
            }
        });

        // Clic: seleccionar jugador y después posición
        form.addEventListener('click', function (e) {
            var x = e.target.closest('.pc-x');
            if (x) { remove(x.closest('.lslot').dataset.slot); return; }
            var chip = e.target.closest('.pchip');
            if (chip) {
                var pid = parseInt(chip.dataset.pid, 10);
                if (selected && selected.from) { remove(selected.from); return; }
                selected = selected && selected.pid === pid ? null : {pid: pid, from: null};
                paintFit(selected ? pid : null);
                render();
                return;
            }
            var slotEl = e.target.closest('.lslot');
            if (slotEl && !e.target.closest('select')) {
                var slot = slotEl.dataset.slot;
                if (selected) {
                    if (selected.from === slot) { selected = null; render(); paintFit(null); return; }
                    place(selected.pid, selected.from, slot);
                    paintFit(null);
                } else if (get(slot)) {
                    selected = {pid: get(slot), from: slot};
                    paintFit(selected.pid);
                    render();
                }
            }
        });
        form.addEventListener('dblclick', function (e) {
            var c = e.target.closest('.lslot');
            if (c) { remove(c.dataset.slot); paintFit(null); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && selected) { selected = null; paintFit(null); render(); }
        });
        form.querySelectorAll('.squad-filter button').forEach(function (b) {
            b.addEventListener('click', function () {
                form.querySelectorAll('.squad-filter button').forEach(function (o) { o.classList.remove('active'); });
                b.classList.add('active');
                filter = b.dataset.filter;
                render();
            });
        });

        // Tooltip con los datos del jugador
        var showTip = function (el, ev) {
            var p = P[el.dataset.pid];
            if (!p || dragging) { return; }
            var slot = el.dataset.from;
            var rows = p.skills.map(function (s) {
                return '<tr><th>' + esc(s[0]) + '</th><td><span class="skillbar ' + (s[1] >= 13 ? 'sb-top' : s[1] >= 9 ? 'sb-high' : s[1] >= 6 ? 'sb-mid' : 'sb-low')
                    + '"><span style="width:' + (s[1] * 5) + '%"></span></span></td><td>' + esc(s[2]) + ' <span class="muted">(' + s[1] + ')</span></td></tr>';
            }).join('');
            var bestSlot = Object.keys(p.fit).sort(function (a, b) { return p.fit[b] / (best[b] || 1) - p.fit[a] / (best[a] || 1); })[0];
            tip.innerHTML = '<div class="tip-head"><img src="' + esc(p.img) + '" alt=""><div><strong>' + esc(p.name) + '</strong><br>'
                + '<span class="small">' + esc(p.roleName) + ' · ' + esc(L('{y} años y {d} días', {y: p.age, d: p.days})) + '</span></div></div>'
                + '<div class="tip-stats"><span>TSI <b>' + p.tsi + '</b></span><span>' + esc(L('Forma')) + ' <b>' + esc(p.formName) + '</b></span><span>' + esc(L('Condición')) + ' <b>' + p.stamina + '</b></span>'
                + '<span>' + esc(L('Exp.')) + ' <b>' + p.xp + '</b></span><span>' + esc(L('Liderazgo')) + ' <b>' + p.lead + '</b></span><span>' + esc(L('Goles')) + ' <b>' + p.goals + '</b></span></div>'
                + (p.spec ? '<div class="tip-line">' + esc(L('Especialidad:')) + ' <b>' + esc(p.spec) + '</b></div>' : '')
                + (p.inj > 0 ? '<div class="tip-line neg">' + esc(L('Lesionado: {n} semana(s)', {n: p.inj})) + '</div>' : '')
                + (p.susp ? '<div class="tip-line neg">' + esc(L('Sancionado para el próximo partido')) + '</div>' : '')
                + (p.yc ? '<div class="tip-line">' + esc(L('Tarjetas amarillas: {n}', {n: p.yc})) + '</div>' : '')
                + '<table class="tip-skills">' + rows + '</table>'
                + '<div class="tip-line small">' + esc(L('Mejor posición:')) + ' <b>' + esc(NAMES[bestSlot]) + '</b>'
                + (slot && p.fit[slot] ? ' · ' + esc(L('aquí rinde al {pct}% del mejor de tu plantilla', {pct: Math.round(p.fit[slot] / (best[slot] || 1) * 100)})) : '') + '</div>';
            tip.hidden = false;
            moveTip(ev);
        };
        var moveTip = function (ev) {
            if (tip.hidden) { return; }
            var w = tip.offsetWidth, h = tip.offsetHeight, x = ev.clientX + 16, y = ev.clientY + 16;
            if (x + w > window.innerWidth - 8) { x = ev.clientX - w - 16; }
            if (y + h > window.innerHeight - 8) { y = Math.max(8, window.innerHeight - h - 8); }
            tip.style.left = Math.max(8, x) + 'px';
            tip.style.top = y + 'px';
        };
        form.addEventListener('mouseover', function (e) {
            var el = e.target.closest('[data-pid]');
            if (el && !el.contains(e.relatedTarget)) { showTip(el, e); }
        });
        form.addEventListener('mousemove', moveTip);
        form.addEventListener('mouseout', function (e) {
            var el = e.target.closest('[data-pid]');
            if (el && !el.contains(e.relatedTarget)) { tip.hidden = true; }
        });

        // Cargar una alineación guardada en el editor
        var PRESETS = DATA.presets || {};
        var applyPreset = function (slot) {
            var pr = PRESETS[slot];
            if (!pr) { return; }
            Object.keys(slotEls).forEach(function (s) {
                var pid = pr.positions && pr.positions[s] ? parseInt(pr.positions[s], 10) : 0;
                set(s, P[pid] ? pid : 0);
                var ord = slotEls[s].querySelector('select.order');
                if (ord) { ord.value = (pr.orders && pr.orders[s]) || 'normal'; }
            });
            [['tactic-select', pr.tactic], ['attitude-select', pr.attitude], ['captain-select', pr.captain_id || ''], ['setpieces-select', pr.setpieces_id || '']].forEach(function (x) {
                var el = document.getElementById(x[0]);
                if (el && x[1] !== undefined && x[1] !== null) { el.value = String(x[1]); }
            });
            selected = null;
            paintFit(null);
            render();
            notice(L('Alineación cargada. Revísala y pulsa «Enviar» para usarla en el próximo partido.'));
            form.scrollIntoView({behavior: 'smooth', block: 'start'});
        };
        document.querySelectorAll('[data-load-preset]').forEach(function (b) {
            b.addEventListener('click', function () { applyPreset(b.dataset.loadPreset); });
        });
        var slotSel = document.getElementById('preset-slot'), nameIn = document.getElementById('preset-name');
        if (slotSel && nameIn) {
            slotSel.addEventListener('change', function () { nameIn.value = slotSel.options[slotSel.selectedIndex].dataset.name || ''; });
        }

        render();
    }
})();

/* Menús desplegables de la barra superior (idioma y usuario): se cierran al pulsar fuera o con Esc (v1.10) */
(function () {
    var menus = document.querySelectorAll('.topbar details');
    if (!menus.length) { return; }
    document.addEventListener('click', function (ev) {
        menus.forEach(function (d) { if (d.open && !d.contains(ev.target)) { d.open = false; } });
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { menus.forEach(function (d) { d.open = false; }); }
    });
    menus.forEach(function (d) {
        d.addEventListener('toggle', function () {
            if (d.open) { menus.forEach(function (o) { if (o !== d) { o.open = false; } }); }
        });
    });
})();
