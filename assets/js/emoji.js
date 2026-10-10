/*
 * GoalTrick: muestra los emojis como imágenes (Twemoji, CC-BY 4.0, assets/img/emoji/)
 * para que se vean igual en todos los sistemas (Windows no muestra bien muchos emojis).
 * Recorre los textos de la página y los que se añaden después (tooltips, avisos…).
 * No toca <select>/<option>, campos de texto, scripts ni atributos (title, placeholder).
 */
(function () {
    'use strict';
    var me = document.currentScript;
    var BASE = me ? me.src.replace(/js\/emoji\.js.*$/, 'img/emoji/') : 'assets/img/emoji/';
    var RE;
    try {
        RE = new RegExp('[\\u{1F1E6}-\\u{1F1FF}]{2}|\\p{Extended_Pictographic}[\\u{FE0F}\\u{1F3FB}-\\u{1F3FF}]?(?:\\u200D\\p{Extended_Pictographic}[\\u{FE0F}\\u{1F3FB}-\\u{1F3FF}]?)*', 'gu');
    } catch (e) { return; } // navegador muy antiguo: se quedan los emojis de texto
    var QUICK = /[\u00A9\u00AE\u203C-\u3299\uD83C-\uD83E]/;
    var SKIP = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, SELECT: 1, OPTION: 1, INPUT: 1, NOSCRIPT: 1, CODE: 1, PRE: 1, svg: 1, SVG: 1, TITLE: 1 };
    // Símbolos que se usan como texto tipográfico y no tienen imagen
    var KEEP = { '★': 1, '☆': 1 };

    function code(s) {
        if (s.indexOf('‍') < 0) s = s.replace(/️/g, '');
        var out = [];
        for (var i = 0; i < s.length; i++) {
            var cp = s.codePointAt(i);
            out.push(cp.toString(16));
            if (cp > 0xFFFF) i++;
        }
        return out.join('-');
    }

    function img(ch) {
        var el = document.createElement('img');
        el.className = 'emoji';
        el.alt = ch;
        el.draggable = false;
        el.src = BASE + code(ch) + '.svg';
        el.onerror = function () { // sin imagen para ese emoji: se deja el texto
            if (this.parentNode) this.parentNode.replaceChild(document.createTextNode(this.alt), this);
        };
        return el;
    }

    function parseText(node) {
        var text = node.nodeValue;
        RE.lastIndex = 0;
        if (!RE.test(text)) return;
        RE.lastIndex = 0;
        var frag = document.createDocumentFragment(), last = 0, m, changed = false;
        while ((m = RE.exec(text))) {
            if (KEEP[m[0].replace(/️/g, '')]) continue;
            if (m.index > last) frag.appendChild(document.createTextNode(text.slice(last, m.index)));
            frag.appendChild(img(m[0]));
            last = m.index + m[0].length;
            changed = true;
        }
        if (!changed) return;
        if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
        node.parentNode.replaceChild(frag, node);
    }

    function skipEl(el) {
        for (; el && el.nodeType === 1; el = el.parentNode) {
            if (SKIP[el.nodeName] || el.isContentEditable || (el.classList && el.classList.contains('no-emoji'))) return true;
        }
        return false;
    }

    function parse(root) {
        if (!root) return;
        if (root.nodeType === 3) {
            if (root.parentNode && !skipEl(root.parentNode)) parseText(root);
            return;
        }
        if (root.nodeType !== 1 && root.nodeType !== 11) return;
        if (root.nodeType === 1 && skipEl(root)) return;
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (n) { return !QUICK.test(n.nodeValue) || skipEl(n.parentNode) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT; }
        });
        var list = [], n;
        while ((n = walker.nextNode())) list.push(n);
        list.forEach(parseText);
    }

    window.GTEmoji = { parse: parse };
    parse(document.body);
    if (window.MutationObserver) {
        new MutationObserver(function (muts) {
            muts.forEach(function (mu) {
                if (mu.type === 'characterData') parse(mu.target);
                else for (var i = 0; i < mu.addedNodes.length; i++) parse(mu.addedNodes[i]);
            });
        }).observe(document.body, { childList: true, subtree: true, characterData: true });
    }
})();
