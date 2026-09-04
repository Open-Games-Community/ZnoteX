(function () {
	'use strict';

	var DEFAULT_TOOLBAR = [
		'bold,italic,underline,strike',
		'size,color,removeformat',
		'left,center,right',
		'bulletlist,orderedlist',
		'quote,code',
		'link,unlink,image,youtube',
		'undo,redo,source'
	].join('|');

	function boot() {
		if (typeof sceditor === 'undefined') {
			return;
		}

		var boxes = document.querySelectorAll('textarea.znote-editor');
		if (!boxes.length) {
			return;
		}

		Array.prototype.forEach.call(boxes, function (box) {
			var base = box.getAttribute('data-asset-base') || 'assets/sceditor/';

			sceditor.create(box, {
				format: 'bbcode',
				icons: 'material',
				style: base + (box.getAttribute('data-content-css') || 'znote-content.css'),
				plugins: 'undo,autoyoutube',
				toolbar: box.getAttribute('data-toolbar') || DEFAULT_TOOLBAR,
				emoticonsEnabled: false,
				resizeEnabled: true,
				resizeWidth: false,
				autoUpdate: true,
				height: box.getAttribute('data-height') || 260
			});

			mirrorTheme(box);

			var limit = parseInt(box.getAttribute('data-maxlength'), 10);
			if (!isNaN(limit) && limit > 0) {
				startCounter(box, limit);
			}
		});

		bindForms();
	}

	/* The editing area is an iframe, so it cannot see the panel's data-acp-theme
	   or its stylesheet. Copy the attribute across and let the content CSS key
	   off it, so the editor is dark when the admin panel is dark. */
	function mirrorTheme(box) {
		var theme = document.documentElement.getAttribute('data-acp-theme');
		if (!theme) {
			return;
		}

		var instance = sceditor.instance(box);
		var apply = function () {
			var body = instance.getBody();
			if (body && body.ownerDocument) {
				body.ownerDocument.documentElement.setAttribute('data-acp-theme', theme);
			}
		};

		apply();
		instance.bind('ready', apply);
	}

	function instanced(form) {
		return Array.prototype.filter.call(
			form.querySelectorAll('textarea.znote-editor'),
			function (box) { return !!sceditor.instance(box); }
		);
	}

	/* The changelog column is varchar(255), and BBCode tags count toward it -
	   so the limit is shown live rather than discovered on save. */
	function startCounter(box, limit) {
		var instance = sceditor.instance(box);
		var note = document.createElement('p');
		note.className = 'znote-editor-count';
		box.parentNode.appendChild(note);

		var tick = function () {
			var used = instance.val().length;
			note.textContent = used + ' / ' + limit + ' characters';
			note.classList.toggle('is-over', used > limit);
		};

		instance.bind('valuechanged', tick);
		tick();
	}

	function bindForms() {
		var seen = [];

		Array.prototype.forEach.call(document.querySelectorAll('textarea.znote-editor'), function (box) {
			var form = box.form;
			if (!form || seen.indexOf(form) !== -1) {
				return;
			}
			seen.push(form);

			form.addEventListener('submit', function (event) {
				var blocked = false;

				instanced(form).forEach(function (box) {
					var value = sceditor.instance(box).val();

					// SCEditor syncs on submit already; doing it here too keeps
					// this correct if a theme submits the form from script.
					box.value = value;

					var problem = check(box, value);
					warn(box, problem);
					if (problem) {
						blocked = true;
					}
				});

				if (blocked) {
					event.preventDefault();
				}
			});
		});
	}

	function check(box, value) {
		var max = parseInt(box.getAttribute('data-max-images'), 10);
		if (!isNaN(max) && max >= 0) {
			var used = (value.match(/\[img(=[^\]]*)?\]/gi) || []).length;
			if (used > max) {
				return max === 0
					? 'Images are not allowed here.'
					: 'Only ' + max + ' image' + (max === 1 ? '' : 's') + ' allowed — you have ' +
					  used + '. Remove ' + (used - max) + '.';
			}
		}

		var limit = parseInt(box.getAttribute('data-maxlength'), 10);
		if (!isNaN(limit) && limit > 0 && value.length > limit) {
			return 'Too long: ' + value.length + ' of ' + limit +
			       ' characters. BBCode tags count too — remove ' + (value.length - limit) + '.';
		}

		return '';
	}

	function warn(box, message) {
		var container = box.parentNode;
		var note = container.querySelector('.znote-editor-warning');

		if (!message) {
			if (note) note.remove();
			return;
		}

		if (!note) {
			note = document.createElement('p');
			note.className = 'znote-editor-warning';
			container.appendChild(note);
		}

		note.textContent = message;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
