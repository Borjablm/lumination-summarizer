/**
 * Lumination Summarizer — Front-end JavaScript
 *
 * Handles input modes (URL, text, file), tab switching, file upload via
 * drag-and-drop, AJAX submission, markdown rendering, and mindmap
 * visualisation via dynamically loaded markmap.
 *
 * @package LuminationSummarizer
 * @since   1.0.0
 */
(function () {
	'use strict';

	var cfg = window.luminationSummarizerConfig || {};

	document.addEventListener('DOMContentLoaded', function () {
		var widgets = document.querySelectorAll('.lms-summarizer');
		for (var i = 0; i < widgets.length; i++) {
			initWidget(widgets[i]);
		}
	});

	/**
	 * Initialise a single summarizer widget.
	 */
	function initWidget(root) {
		var state = {
			mode: root.dataset.mode || 'auto',
			output: root.dataset.output || 'sections',
			length: root.dataset.length || 'medium',
			activeTab: root.dataset.mode === 'auto' ? 'url' : root.dataset.mode,
			fileData: null,
			fileName: null,
			isProcessing: false
		};

		var els = {
			tabs: root.querySelectorAll('.lms-tab'),
			panels: root.querySelectorAll('.lms-tab-panel'),
			urlInput: root.querySelector('.lms-url-input'),
			textInput: root.querySelector('.lms-text-input'),
			dropZone: root.querySelector('.lms-drop-zone'),
			fileInput: root.querySelector('.lms-file-input'),
			fileName: root.querySelector('.lms-file-name'),
			submitBtn: root.querySelector('.lms-submit-btn'),
			output: root.querySelector('.lms-output'),
			outputTitle: root.querySelector('.lms-output-title'),
			outputContent: root.querySelector('.lms-output-content'),
			mindmapContainer: root.querySelector('.lms-mindmap-container'),
			copyBtn: root.querySelector('.lms-copy-btn'),
			loading: root.querySelector('.lms-loading'),
			loadingText: root.querySelector('.lms-loading-text')
		};

		setupTabs(state, els);
		setupInputListeners(state, els);
		setupFileUpload(state, els);
		setupSubmit(state, els);
		setupCopy(state, els);
	}

	/* ── Tabs ────────────────────────────────────────────────────────────── */

	function setupTabs(state, els) {
		for (var i = 0; i < els.tabs.length; i++) {
			els.tabs[i].addEventListener('click', function () {
				var tab = this.dataset.tab;
				state.activeTab = tab;

				// Update tab styles.
				for (var j = 0; j < els.tabs.length; j++) {
					els.tabs[j].classList.toggle('lms-tab--active', els.tabs[j].dataset.tab === tab);
					els.tabs[j].setAttribute('aria-selected', els.tabs[j].dataset.tab === tab ? 'true' : 'false');
				}

				// Show/hide panels.
				for (var k = 0; k < els.panels.length; k++) {
					els.panels[k].classList.toggle('lms-hidden', els.panels[k].dataset.panel !== tab);
				}

				updateSubmitState(state, els);
			});
		}
	}

	/* ── Input listeners ─────────────────────────────────────────────────── */

	function setupInputListeners(state, els) {
		if (els.urlInput) {
			els.urlInput.addEventListener('input', function () {
				updateSubmitState(state, els);
			});
		}
		if (els.textInput) {
			els.textInput.addEventListener('input', function () {
				updateSubmitState(state, els);
			});
		}
	}

	function updateSubmitState(state, els) {
		if (state.isProcessing) return;

		var hasInput = false;
		var activeTab = state.mode === 'auto' ? state.activeTab : state.mode;

		if (activeTab === 'url' && els.urlInput) {
			hasInput = els.urlInput.value.trim().length > 0;
		} else if (activeTab === 'text' && els.textInput) {
			hasInput = els.textInput.value.trim().length > 0;
		} else if (activeTab === 'file') {
			hasInput = state.fileData !== null;
		}

		els.submitBtn.disabled = !hasInput;
	}

	/* ── File upload ─────────────────────────────────────────────────────── */

	function setupFileUpload(state, els) {
		if (!els.dropZone || !els.fileInput) return;

		els.dropZone.addEventListener('click', function () {
			els.fileInput.click();
		});

		els.dropZone.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				els.fileInput.click();
			}
		});

		els.fileInput.addEventListener('change', function () {
			if (this.files && this.files[0]) {
				handleFile(this.files[0], state, els);
			}
		});

		// Drag-and-drop.
		els.dropZone.addEventListener('dragover', function (e) {
			e.preventDefault();
			this.classList.add('lms-dragover');
		});

		els.dropZone.addEventListener('dragleave', function () {
			this.classList.remove('lms-dragover');
		});

		els.dropZone.addEventListener('drop', function (e) {
			e.preventDefault();
			this.classList.remove('lms-dragover');
			if (e.dataTransfer.files && e.dataTransfer.files[0]) {
				handleFile(e.dataTransfer.files[0], state, els);
			}
		});
	}

	function handleFile(file, state, els) {
		if (file.type !== 'application/pdf') {
			showError(cfg.i18n.invalidType, els);
			return;
		}

		if (file.size > 10 * 1024 * 1024) {
			showError(cfg.i18n.fileTooBig, els);
			return;
		}

		var reader = new FileReader();
		reader.onload = function () {
			// Strip the data URI prefix to get raw base64.
			var base64 = reader.result.split(',')[1] || '';
			state.fileData = base64;
			state.fileName = file.name;

			els.fileName.textContent = file.name;
			els.fileName.classList.remove('lms-hidden');

			updateSubmitState(state, els);
		};
		reader.readAsDataURL(file);
	}

	/* ── Submit ──────────────────────────────────────────────────────────── */

	function setupSubmit(state, els) {
		els.submitBtn.addEventListener('click', function () {
			if (state.isProcessing) return;

			var activeTab = state.mode === 'auto' ? state.activeTab : state.mode;
			var inputValue = '';

			if (activeTab === 'url' && els.urlInput) {
				inputValue = els.urlInput.value.trim();
			} else if (activeTab === 'text' && els.textInput) {
				inputValue = els.textInput.value.trim();
			} else if (activeTab === 'file') {
				inputValue = state.fileData || '';
			}

			if (!inputValue) return;

			state.isProcessing = true;
			els.submitBtn.disabled = true;

			// Show loading, hide output.
			els.loading.classList.remove('lms-hidden');
			els.output.classList.add('lms-hidden');

			var loadingMsg = state.output === 'mindmap' ? cfg.i18n.generatingMap : cfg.i18n.generating;
			els.loadingText.textContent = loadingMsg;

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'lumination_summarizer_run',
					nonce: cfg.nonce,
					input_mode: activeTab,
					input_value: inputValue,
					output_type: state.output,
					summary_length: state.length,
					page_url: window.location.href
				})
			})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				els.loading.classList.add('lms-hidden');
				state.isProcessing = false;
				updateSubmitState(state, els);

				if (data.success) {
					displayResult(data.data, state, els);
				} else {
					showError(data.data && data.data.message ? data.data.message : cfg.i18n.error, els);
				}
			})
			.catch(function () {
				els.loading.classList.add('lms-hidden');
				state.isProcessing = false;
				updateSubmitState(state, els);
				showError(cfg.i18n.error, els);
			});
		});
	}

	/* ── Display result ──────────────────────────────────────────────────── */

	function displayResult(data, state, els) {
		els.output.classList.remove('lms-hidden');

		if (data.title) {
			els.outputTitle.textContent = data.title;
			els.outputTitle.classList.remove('lms-hidden');
		} else {
			els.outputTitle.classList.add('lms-hidden');
		}

		if (data.output_type === 'mindmap') {
			els.outputContent.classList.add('lms-hidden');
			els.mindmapContainer.classList.remove('lms-hidden');
			renderMindmap(data.summary, els.mindmapContainer);
		} else {
			els.outputContent.classList.remove('lms-hidden');
			els.mindmapContainer.classList.add('lms-hidden');
			renderMarkdown(data.summary, els.outputContent);
		}
	}

	/**
	 * Render markdown content using the best available renderer.
	 */
	function renderMarkdown(markdown, container) {
		// Priority 1: Core's LuminationMathRenderer.
		if (typeof window.LuminationMathRenderer !== 'undefined') {
			window.LuminationMathRenderer.render(container, markdown);
			return;
		}

		// Priority 2: marked + DOMPurify.
		if (typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined') {
			if (typeof marked.setOptions === 'function') {
				marked.setOptions({ gfm: true, breaks: true });
			}
			var parsed = typeof marked.parse === 'function' ? marked.parse(markdown) : marked(markdown);
			container.innerHTML = DOMPurify.sanitize(parsed, { USE_PROFILES: { html: true } });
			return;
		}

		// Priority 3: plain text fallback.
		container.textContent = markdown;
	}

	/**
	 * Render a Markmap mindmap from Markdown.
	 *
	 * Dynamically loads markmap libraries from CDN on first use.
	 */
	function renderMindmap(markdown, container) {
		container.innerHTML = '';
		container.style.minHeight = '450px';

		var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
		svg.setAttribute('id', 'lms-mindmap-' + Date.now());
		svg.style.width = '100%';
		svg.style.height = '450px';
		container.appendChild(svg);

		if (window.markmap && window.markmap.Transformer && window.markmap.Markmap) {
			doRenderMindmap(markdown, svg);
			return;
		}

		// Load markmap dependencies from CDN.
		var scripts = [
			'https://cdn.jsdelivr.net/npm/d3@7/dist/d3.min.js',
			'https://cdn.jsdelivr.net/npm/markmap-lib@0.17/dist/browser/index.min.js',
			'https://cdn.jsdelivr.net/npm/markmap-view@0.17/dist/browser/index.min.js'
		];

		loadScriptsSequentially(scripts, 0, function () {
			doRenderMindmap(markdown, svg);
		});
	}

	function doRenderMindmap(markdown, svg) {
		try {
			var transformer = new markmap.Transformer();
			var result = transformer.transform(markdown);
			markmap.Markmap.create(svg, null, result.root);
		} catch (e) {
			// Fallback: render as markdown.
			svg.parentNode.removeChild(svg);
			var fallback = document.createElement('div');
			fallback.className = 'lms-output-content';
			svg.parentNode.appendChild(fallback);
			renderMarkdown(markdown, fallback);
		}
	}

	function loadScriptsSequentially(urls, index, callback) {
		if (index >= urls.length) {
			callback();
			return;
		}
		var script = document.createElement('script');
		script.src = urls[index];
		script.onload = function () {
			loadScriptsSequentially(urls, index + 1, callback);
		};
		script.onerror = function () {
			// Continue even if one fails — fallback will handle it.
			loadScriptsSequentially(urls, index + 1, callback);
		};
		document.head.appendChild(script);
	}

	/* ── Copy to clipboard ───────────────────────────────────────────────── */

	function setupCopy(state, els) {
		if (!els.copyBtn) return;

		els.copyBtn.addEventListener('click', function () {
			var content = els.outputContent.innerText || els.outputContent.textContent || '';
			if (!content.trim()) return;

			navigator.clipboard.writeText(content).then(function () {
				var label = els.copyBtn.querySelector('span');
				if (label) {
					label.textContent = cfg.i18n.copied;
					els.copyBtn.classList.add('lms-copied');
					setTimeout(function () {
						label.textContent = cfg.i18n.copy;
						els.copyBtn.classList.remove('lms-copied');
					}, 2000);
				}
			});
		});
	}

	/* ── Error display ───────────────────────────────────────────────────── */

	function showError(message, els) {
		els.output.classList.remove('lms-hidden');
		els.outputTitle.classList.add('lms-hidden');
		els.mindmapContainer.classList.add('lms-hidden');
		els.outputContent.classList.remove('lms-hidden');
		els.outputContent.innerHTML = '<p style="color:#e74c3c;">' + escapeHtml(message) + '</p>';
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

})();
