(function () {
    const root = document.documentElement;
    const sharedScriptPattern = /(?:bootstrap(?:\.bundle)?(?:\.min)?|jquery(?:-[\d.]+)?(?:\.min)?|sweetalert2(?:\.all)?(?:\.min)?|i18n|global-search|notifications|dashboard-navigation)\.js$/i;
    const loadedLibraryScripts = new Set();
    let activePageController = null;
    let activeRequestController = null;
    let navigationId = 0;
    let originalScrollBehavior = '';
    let hasOriginalScrollBehavior = false;
    let scrollResetFrame = 0;

    function findAppMain(doc) {
        return doc.querySelector('.app > .app-main');
    }

    function findTopbar(main) {
        return Array.from(main.children).find(function (child) {
            return child.classList.contains('topbar');
        }) || null;
    }

    function ensureContentRoot(main) {
        return main;
    }

    function isPosPage(pathname) {
        return /\/sales\/pos(?:\/|$)/i.test(pathname) || /(?:^|\/)pos\.php$/i.test(pathname);
    }

    function isPosLayout() {
        return Boolean(document.querySelector('.app.app-container-lock'));
    }

    function resetDashboardScroll() {
        if (isPosPage(window.location.pathname)) {
            return;
        }

        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
        const documentRoot = document.documentElement;
        if (!hasOriginalScrollBehavior) {
            originalScrollBehavior = documentRoot.style.scrollBehavior;
            hasOriginalScrollBehavior = true;
        }
        documentRoot.style.scrollBehavior = 'auto';
        window.scrollTo(0, 0);
        documentRoot.scrollTop = 0;
        document.body.scrollTop = 0;

        if (scrollResetFrame) {
            cancelAnimationFrame(scrollResetFrame);
        }
        scrollResetFrame = requestAnimationFrame(function () {
            window.scrollTo(0, 0);
            documentRoot.scrollTop = 0;
            document.body.scrollTop = 0;
            documentRoot.style.scrollBehavior = originalScrollBehavior;
            hasOriginalScrollBehavior = false;
            scrollResetFrame = 0;
        });
    }

    function restoreBrowserScrollRestoration() {
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'auto';
        }
    }

    function preventSidebarScrollIntoView() {
        if (document.readyState === 'complete' || typeof Element.prototype.scrollIntoView !== 'function') {
            return;
        }

        const nativeScrollIntoView = Element.prototype.scrollIntoView;
        const scopedScrollIntoView = function (options) {
            if (!this.matches || !this.matches('.nav-link.active')) {
                return nativeScrollIntoView.apply(this, arguments);
            }
            const sideNav = this.closest('.app-sidebar .side-nav');
            if (!sideNav) {
                return;
            }

            const navBounds = sideNav.getBoundingClientRect();
            const linkBounds = this.getBoundingClientRect();
            const targetTop = sideNav.scrollTop + linkBounds.top - navBounds.top
                - (sideNav.clientHeight - this.offsetHeight) / 2;
            const maxScrollTop = Math.max(0, sideNav.scrollHeight - sideNav.clientHeight);
            const behavior = options && typeof options === 'object' && options.behavior === 'smooth' ? 'smooth' : 'auto';
            sideNav.scrollTo({ top: Math.max(0, Math.min(maxScrollTop, targetTop)), behavior: behavior });
        };
        Element.prototype.scrollIntoView = scopedScrollIntoView;
        document.addEventListener('DOMContentLoaded', function () {
            if (Element.prototype.scrollIntoView === scopedScrollIntoView) {
                Element.prototype.scrollIntoView = nativeScrollIntoView;
            }
        }, { once: true });
    }

    function contentElements(main) {
        const topbar = findTopbar(main);
        return Array.from(main.children).filter(function (child) {
            return child !== topbar;
        });
    }

    function createProgressBar() {
        let progress = document.getElementById('dashboard-navigation-progress');
        if (!progress) {
            progress = document.createElement('div');
            progress.id = 'dashboard-navigation-progress';
            progress.setAttribute('aria-hidden', 'true');
            document.body.insertBefore(progress, document.body.firstChild);
        }
        return progress;
    }

    function isSharedScript(url) {
        return sharedScriptPattern.test(new URL(url, window.location.href).pathname.split('/').pop());
    }

    function shouldLoadScriptOnce(url) {
        return isSharedScript(url) || new URL(url, window.location.href).origin !== window.location.origin;
    }

    function isExecutableScript(script) {
        const type = (script.getAttribute('type') || '').trim().toLowerCase();
        return !type || type === 'text/javascript' || type === 'application/javascript';
    }

    function isSharedStylesheet(url) {
        const parsed = new URL(url, window.location.href);
        return parsed.hostname === 'fonts.googleapis.com'
            || /\/(?:assets\/css\/style\.css|bootstrap(?:\.min)?\.css|bootstrap-icons(?:\.min)?\.css|sweetalert2(?:\.min)?\.css)$/i.test(parsed.pathname);
    }

    function navigationScripts(doc) {
        const app = doc.querySelector('.app');
        const main = findAppMain(doc);
        const scripts = Array.from(doc.scripts).filter(function (script) {
            if (script.closest('.app-sidebar, .topbar')) {
                return false;
            }
            const appearsBeforeMain = app
                && script.parentElement === app
                && Boolean(script.compareDocumentPosition(main) & Node.DOCUMENT_POSITION_FOLLOWING);
            return !appearsBeforeMain;
        });
        if (scripts.some(function (script) {
            return (script.getAttribute('type') || '').trim().toLowerCase() === 'module';
        })) {
            throw new Error('Module scripts require a full page load.');
        }
        return scripts.filter(isExecutableScript).map(function (script) {
            return {
                src: script.getAttribute('src') || '',
                code: script.getAttribute('src') ? '' : script.textContent,
            };
        });
    }

    function pageStyleAssets(doc, baseUrl) {
        return Array.from(doc.head.querySelectorAll('style, link[rel="stylesheet"]')).filter(function (asset) {
            if (asset.tagName === 'STYLE') {
                return true;
            }
            const url = new URL(asset.getAttribute('href'), baseUrl);
            return !isSharedStylesheet(url.href);
        });
    }

    function addPageStyleAssets(doc, baseUrl) {
        document.querySelectorAll('[data-dashboard-navigation-style]').forEach(function (asset) {
            asset.remove();
        });

        const pending = [];
        pageStyleAssets(doc, baseUrl).forEach(function (asset) {
            const clone = asset.cloneNode(true);
            if (clone.tagName === 'LINK') {
                clone.href = new URL(asset.getAttribute('href'), baseUrl).href;
            }
            clone.setAttribute('data-dashboard-navigation-style', '');
            if (clone.tagName === 'LINK') {
                pending.push(new Promise(function (resolve) {
                    clone.addEventListener('load', resolve, { once: true });
                    clone.addEventListener('error', resolve, { once: true });
                    window.setTimeout(resolve, 2000);
                }));
            }
            document.head.appendChild(clone);
        });
        return Promise.all(pending);
    }

    function markInitialPageStyles() {
        document.head.querySelectorAll('style').forEach(function (style) {
            if (!/\.app-sidebar|\.app\s+\.topbar|\.topbar(?:-|\s|:|\.)/.test(style.textContent)) {
                style.setAttribute('data-dashboard-navigation-style', '');
            }
        });
        document.head.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) {
            if (!isSharedStylesheet(new URL(link.href, window.location.href).href)) {
                link.setAttribute('data-dashboard-navigation-style', '');
            }
        });
    }

    function listenerOptionsWithSignal(options, signal) {
        if (typeof options === 'boolean') {
            return { capture: options, signal: signal };
        }
        return Object.assign({}, options || {}, { signal: signal });
    }

    function inlineHandlerNames(container) {
        const ignored = new Set(['if', 'for', 'while', 'switch', 'catch', 'function', 'typeof', 'new', 'return', 'throw', 'delete', 'void', 'instanceof', 'in', 'of', 'this']);
        const names = new Set();
        container.querySelectorAll('*').forEach(function (element) {
            Array.from(element.attributes).forEach(function (attribute) {
                if (!/^on/i.test(attribute.name)) {
                    return;
                }
                const calls = attribute.value.matchAll(/\b([A-Za-z_$][\w$]*)\s*\(/g);
                for (const call of calls) {
                    if (!ignored.has(call[1])) {
                        names.add(call[1]);
                    }
                }
            });
        });
        return names;
    }

    function executeInlineScripts(code, context, names) {
        const nativeDocumentAdd = document.addEventListener;
        const nativeWindowAdd = window.addEventListener;
        const readyCallbacks = context.readyCallbacks;
        document.addEventListener = function (type, listener, options) {
            if (type === 'DOMContentLoaded') {
                readyCallbacks.push({ target: document, listener: listener, eventType: type });
                return;
            }
            return nativeDocumentAdd.call(document, type, listener, listenerOptionsWithSignal(options, context.controller.signal));
        };
        window.addEventListener = function (type, listener, options) {
            if (type === 'load' || type === 'DOMContentLoaded') {
                readyCallbacks.push({ target: window, listener: listener, eventType: type });
                return;
            }
            return nativeWindowAdd.call(window, type, listener, listenerOptionsWithSignal(options, context.controller.signal));
        };

        const exports = Array.from(names).map(function (name) {
            return 'if (typeof ' + name + ' === "function") window[' + JSON.stringify(name) + '] = ' + name + ';';
        }).join('\n');
        try {
            (new Function(code + '\n' + exports))();
        } finally {
            document.addEventListener = nativeDocumentAdd;
            window.addEventListener = nativeWindowAdd;
        }
    }

    function loadExternalScript(url) {
        return new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = url;
            script.async = false;
            script.onload = resolve;
            script.onerror = function () {
                reject(new Error('Unable to load dashboard page script: ' + url));
            };
            document.body.appendChild(script);
        });
    }

    async function executePageScripts(scripts, content, controller) {
        const context = {
            controller: controller,
            readyCallbacks: [],
        };
        const handlerNames = inlineHandlerNames(content);

        for (const script of scripts) {
            if (context.controller.signal.aborted) {
                const abortError = new Error('Dashboard page script initialization was cancelled.');
                abortError.name = 'AbortError';
                throw abortError;
            }
            if (script.src) {
                if (shouldLoadScriptOnce(script.src)) {
                    const scriptUrl = new URL(script.src, window.location.href).href;
                    if (loadedLibraryScripts.has(scriptUrl)) {
                        continue;
                    }
                    await loadExternalScript(scriptUrl);
                    loadedLibraryScripts.add(scriptUrl);
                } else {
                    await loadExternalScript(new URL(script.src, window.location.href).href);
                }
                if (context.controller.signal.aborted) {
                    const abortError = new Error('Dashboard page script initialization was cancelled.');
                    abortError.name = 'AbortError';
                    throw abortError;
                }
            } else if (script.code.trim()) {
                executeInlineScripts(script.code, context, handlerNames);
            }
        }

        context.readyCallbacks.forEach(function (callback) {
            const readyEvent = new Event(callback.eventType);
            if (typeof callback.listener === 'function') {
                callback.listener.call(callback.target, readyEvent);
            } else if (callback.listener && typeof callback.listener.handleEvent === 'function') {
                callback.listener.handleEvent(readyEvent);
            }
        });
        return context;
    }

    function updateSidebarActiveLink(url) {
        if (/\/sales\/pos(?:\/|$)/i.test(url.pathname) || /(?:^|\/)pos\.php$/i.test(url.pathname)) {
            return;
        }

        const links = Array.from(document.querySelectorAll('.app-sidebar .side-nav .nav-link[href]'));
        const normalizePath = function (path) {
            return path.replace(/\/+$/, '') || '/';
        };
        const currentPath = normalizePath(url.pathname);
        const pageAliases = {
            'customers.php': '/customers/customers.php',
            'add-customers.php': '/customers/customers.php',
            'customer-details.php': '/customers/customers.php',
            'reviews.php': '/customers/customers.php',
            'products.php': '/products/products.php',
            'add-product.php': '/products/products.php',
            'edit-product.php': '/products/products.php',
            'categories.php': '/categories/categories.php',
            'add-category.php': '/categories/categories.php',
            'edit-category.php': '/categories/categories.php',
            'orders.php': '/sales/orders/orders.php',
            'add-order.php': '/sales/orders/orders.php',
            'order-details.php': '/sales/orders/orders.php',
            'invoice.php': '/sales/orders/orders.php',
            'payments.php': '/sales/payments/payments.php',
            'returns.php': '/sales/returns/returns.php',
            'inventory.php': '/inventory/inventory.php',
            'stock-adjust.php': '/inventory/inventory.php',
            'stock-history.php': '/inventory/inventory.php',
            'suppliers.php': '/inventory/inventory.php',
            'reports.php': '/sales/reports/reports.php',
            'profit-report.php': '/sales/reports/profit-report.php',
            'shift-summary.php': '/sales/reports/reports.php',
            'users.php': '/users/users.php',
            'add-user.php': '/users/users.php',
            'edit-user.php': '/users/users.php',
            'roles.php': '/users/users.php',
            'activity-logs.php': '/users/users.php',
            'trash.php': '/products/trash.php',
            'settings.php': '/settings.php',
        };
        const pageName = currentPath.slice(currentPath.lastIndexOf('/') + 1);
        const aliasPath = pageAliases[pageName];
        let selectedLink = null;

        if (aliasPath) {
            selectedLink = links.find(function (link) {
                const destination = new URL(link.href, window.location.href);
                return destination.origin === url.origin && normalizePath(destination.pathname).endsWith(aliasPath);
            }) || null;
        }
        if (!selectedLink) {
            selectedLink = links.find(function (link) {
                const destination = new URL(link.href, window.location.href);
                return destination.origin === url.origin && normalizePath(destination.pathname) === currentPath;
            }) || null;
        }

        if (!selectedLink) {
            let longestDirectoryLength = 0;
            links.forEach(function (link) {
                const destination = new URL(link.href, window.location.href);
                if (destination.origin !== url.origin) {
                    return;
                }
                const linkPath = normalizePath(destination.pathname);
                const directory = linkPath.slice(0, linkPath.lastIndexOf('/') + 1);
                if (directory !== '/' && currentPath.startsWith(directory) && directory.length > longestDirectoryLength) {
                    selectedLink = link;
                    longestDirectoryLength = directory.length;
                }
            });
        }

        links.forEach(function (link) {
            const active = link === selectedLink;
            link.classList.toggle('active', active);
            if (active) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    function updateDocumentState(doc, url) {
        if (doc.title) {
            document.title = doc.title;
        }
        if (doc.documentElement.lang) {
            document.documentElement.lang = doc.documentElement.lang;
        }
        if (doc.documentElement.dir) {
            document.documentElement.dir = doc.documentElement.dir;
        }
        if (doc.documentElement.dataset.theme) {
            document.documentElement.dataset.theme = doc.documentElement.dataset.theme;
            document.body.dataset.theme = doc.documentElement.dataset.theme;
        }
        if (doc.body.className !== '') {
            document.body.className = doc.body.className;
        }
        updateSidebarActiveLink(url);
        resetDashboardScroll();
    }

    function waitForFade(main) {
        return new Promise(function (resolve) {
            let completed = false;
            const elements = contentElements(main);
            let remaining = elements.length;
            const finish = function () {
                if (completed) {
                    return;
                }
                completed = true;
                elements.forEach(function (element) {
                    element.removeEventListener('transitionend', onTransitionEnd);
                });
                resolve();
            };
            const onTransitionEnd = function (event) {
                if (event.propertyName !== 'opacity') {
                    return;
                }
                remaining -= 1;
                if (remaining <= 0) {
                    finish();
                }
            };
            if (!remaining) {
                finish();
                return;
            }
            elements.forEach(function (element) {
                element.addEventListener('transitionend', onTransitionEnd);
            });
            window.setTimeout(finish, 220);
            requestAnimationFrame(function () {
                elements.forEach(function (element) {
                    element.classList.add('dashboard-content-transitioning');
                });
            });
        });
    }

    function revealContent(main) {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                contentElements(main).forEach(function (element) {
                    element.classList.remove('dashboard-content-transitioning');
                });
                root.classList.remove('dashboard-navigation-loading');
            });
        });
    }

    function replaceMainContent(main, nextMain, nextTopbar) {
        const topbar = findTopbar(main);
        Array.from(main.childNodes).forEach(function (node) {
            if (node !== topbar) {
                node.remove();
            }
        });
        Array.from(nextMain.childNodes).forEach(function (node) {
            if (node === nextTopbar) {
                return;
            }
            const clone = document.importNode(node, true);
            if (clone.nodeType === Node.ELEMENT_NODE && clone.tagName === 'SCRIPT' && isExecutableScript(clone)) {
                return;
            }
            if (clone.nodeType === Node.ELEMENT_NODE) {
                clone.classList.add('dashboard-content-transitioning');
            }
            main.appendChild(clone);
        });
    }

    function replacePageBody(doc) {
        const progress = document.getElementById('dashboard-navigation-progress');
        const fragment = document.createDocumentFragment();
        Array.from(doc.body.childNodes).forEach(function (node) {
            if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'SCRIPT' && isExecutableScript(node)) {
                return;
            }
            fragment.appendChild(document.importNode(node, true));
        });
        Array.from(document.body.childNodes).forEach(function (node) {
            if (node !== progress) {
                node.remove();
            }
        });
        document.body.appendChild(fragment);

        const main = findAppMain(document);
        if (!main) {
            throw new Error('The destination page is missing its main content area.');
        }
        contentElements(main).forEach(function (element) {
            element.classList.add('dashboard-content-transitioning');
        });
        return main;
    }

    function hideMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const offcanvas = sidebar && window.bootstrap && window.bootstrap.Offcanvas
            ? window.bootstrap.Offcanvas.getInstance(sidebar)
            : null;
        if (offcanvas) {
            offcanvas.hide();
        }
    }

    async function navigate(destination, options) {
        const settings = Object.assign({ pushState: true, targetState: null }, options || {});
        const url = new URL(destination, window.location.href);
        if (url.origin !== window.location.origin) {
            restoreBrowserScrollRestoration();
            window.location.assign(url.href);
            return;
        }

        resetDashboardScroll();
        const currentId = ++navigationId;
        if (activePageController) {
            activePageController.abort();
            activePageController = null;
        }
        if (activeRequestController) {
            activeRequestController.abort();
        }
        activeRequestController = new AbortController();
        root.classList.add('dashboard-navigation-loading');
        createProgressBar();

        try {
            const response = await fetch(url.href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: activeRequestController.signal,
            });
            if (!response.ok) {
                throw new Error('Dashboard navigation failed with HTTP ' + response.status + '.');
            }
            const html = await response.text();
            const page = new DOMParser().parseFromString(html, 'text/html');
            const finalUrl = new URL(response.url || url.href, window.location.href);
            const nextIsPosPage = isPosPage(finalUrl.pathname);
            const nextApp = page.querySelector('.app');
            const nextMain = findAppMain(page);
            const nextSidebar = page.querySelector('.app-sidebar');
            const nextTopbar = nextMain && findTopbar(nextMain);
            if (!nextApp || !nextMain || !nextTopbar || (!nextIsPosPage && !nextSidebar)) {
                restoreBrowserScrollRestoration();
                window.location.assign(response.url || url.href);
                return;
            }

            const nextScripts = navigationScripts(page);
            const currentIsPosPage = isPosLayout();
            let main = ensureContentRoot(findAppMain(document));
            await waitForFade(main);
            if (currentId !== navigationId) {
                return;
            }

            if (settings.pushState) {
                history.pushState({}, '', finalUrl.href);
            } else {
                history.replaceState(settings.targetState || history.state, '', finalUrl.href);
            }
            const stylePromise = addPageStyleAssets(page, finalUrl.href);
            if (currentIsPosPage !== nextIsPosPage) {
                main = replacePageBody(page);
            } else {
                replaceMainContent(main, nextMain, nextTopbar);
            }
            updateDocumentState(page, finalUrl);
            await stylePromise;
            if (currentId !== navigationId) {
                return;
            }
            activePageController = new AbortController();
            await executePageScripts(nextScripts, main, activePageController);
            if (currentId !== navigationId) {
                return;
            }
            resetDashboardScroll();
            hideMobileSidebar();
            revealContent(main);
        } catch (error) {
            if (error.name === 'AbortError' || currentId !== navigationId) {
                return;
            }
            console.error('Dashboard content navigation failed; loading the page normally.', error);
            restoreBrowserScrollRestoration();
            window.location.assign(url.href);
        }
    }

    function shouldHandleLink(link, event) {
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
            || link.hasAttribute('download') || link.hasAttribute('data-bs-toggle') || link.getAttribute('aria-disabled') === 'true') {
            return false;
        }
        const target = link.getAttribute('target');
        if (target && target.toLowerCase() !== '_self') {
            return false;
        }
        const destination = new URL(link.href, window.location.href);
        return (destination.protocol === 'http:' || destination.protocol === 'https:')
            && destination.origin === window.location.origin
            && !(destination.pathname === window.location.pathname && destination.search === window.location.search);
    }

    function init() {
        const main = findAppMain(document);
        const posPage = isPosPage(window.location.pathname);
        if (!main || !findTopbar(main) || (!posPage && !document.querySelector('.app-sidebar'))) {
            return;
        }
        preventSidebarScrollIntoView();
        resetDashboardScroll();
        window.addEventListener('load', resetDashboardScroll, { once: true });
        window.addEventListener('pageshow', resetDashboardScroll);
        window.addEventListener('beforeunload', restoreBrowserScrollRestoration, { once: true });
        ensureContentRoot(main);
        createProgressBar();
        markInitialPageStyles();
        updateSidebarActiveLink(new URL(window.location.href));
        document.querySelectorAll('script[src]').forEach(function (script) {
            if (shouldLoadScriptOnce(script.src)) {
                loadedLibraryScripts.add(new URL(script.src, window.location.href).href);
            }
        });

        document.addEventListener('click', function (event) {
            const target = event.target instanceof Element ? event.target : event.target && event.target.parentElement;
            const link = target && target.closest('a[href]');
            if (!shouldHandleLink(link, event)) {
                return;
            }
            event.preventDefault();
            navigate(link.href);
        });

        document.addEventListener('submit', function (event) {
            if (event.defaultPrevented || !(event.target instanceof HTMLFormElement)) {
                return;
            }
            const form = event.target;
            if ((form.method || 'get').toLowerCase() !== 'get' || form.enctype === 'multipart/form-data') {
                return;
            }
            const target = form.getAttribute('target');
            if (target && target.toLowerCase() !== '_self') {
                return;
            }
            const destination = new URL(form.action || window.location.href, window.location.href);
            if (destination.origin !== window.location.origin) {
                return;
            }
            const data = new FormData(form);
            destination.search = '';
            data.forEach(function (value, key) {
                if (typeof value === 'string') {
                    destination.searchParams.append(key, value);
                }
            });
            event.preventDefault();
            navigate(destination.href);
        });

        window.addEventListener('popstate', function (event) {
            navigate(window.location.href, { pushState: false, targetState: event.state });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
