(() => {
  const debounceDelay = 180;
  const minimumQueryLength = 1;
  const maximumCachedQueries = 30;

  function appendHighlightedText(element, text, query, matchTerms = []) {
    const terms = [...new Set(
      [...query.trim().split(/\s+/), ...matchTerms]
        .filter((term) => typeof term === "string" && term.length >= 2)
        .sort((left, right) => right.length - left.length)
    )];
    if (query.trim().length === 1) {
      terms.push(query.trim());
    }

    const matchingTerm = terms.find((term) => text.toLocaleLowerCase().includes(term.toLocaleLowerCase()));
    if (!matchingTerm) {
      element.textContent = text;
      return;
    }

    const start = text.toLocaleLowerCase().indexOf(matchingTerm.toLocaleLowerCase());
    element.append(
      document.createTextNode(text.slice(0, start)),
      Object.assign(document.createElement("mark"), { textContent: text.slice(start, start + matchingTerm.length) }),
      document.createTextNode(text.slice(start + matchingTerm.length))
    );
  }

  function createMessage(text, isError = false) {
    const message = document.createElement("div");
    message.className = `global-search-message${isError ? " is-error" : ""}`;
    message.setAttribute("role", isError ? "alert" : "status");
    message.textContent = text;
    return message;
  }

  function hideResults(input, results) {
    results.replaceChildren();
    results.hidden = true;
    input.setAttribute("aria-expanded", "false");
  }

  function showMessage(input, results, text, isError = false) {
    results.replaceChildren(createMessage(text, isError));
    results.hidden = false;
    input.setAttribute("aria-expanded", "true");
  }

  function renderResults(input, results, items, query) {
    const fragment = document.createDocumentFragment();
    let renderedItems = 0;

    items.forEach((item) => {
      if (
        !item
        || typeof item.name !== "string"
        || typeof item.detail !== "string"
        || typeof item.url !== "string"
        || typeof item.type !== "string"
      ) {
        return;
      }

      let url;
      try {
        url = new URL(item.url, window.location.origin);
      } catch {
        return;
      }
      if (url.origin !== window.location.origin) {
        return;
      }

      const link = document.createElement("a");
      link.className = "global-search-result";
      link.href = url.href;
      link.setAttribute("role", "option");

      const icon = document.createElement("span");
      icon.className = "global-search-result-icon";
      const iconElement = document.createElement("i");
      iconElement.classList.add("bi");
      if (typeof item.icon === "string" && /^bi-[a-z0-9-]+$/.test(item.icon)) {
        iconElement.classList.add(item.icon);
      } else {
        iconElement.classList.add("bi-search");
      }
      iconElement.setAttribute("aria-hidden", "true");
      icon.append(iconElement);

      const copy = document.createElement("span");
      copy.className = "global-search-result-copy";

      const name = document.createElement("span");
      name.className = "global-search-result-name";
      appendHighlightedText(
        name,
        item.name,
        query,
        Array.isArray(item.match_terms) ? item.match_terms : []
      );

      const detail = document.createElement("span");
      detail.className = "global-search-result-detail";
      detail.textContent = item.detail;

      const reason = document.createElement("span");
      reason.className = "global-search-result-reason";
      reason.textContent = typeof item.reason === "string" ? item.reason : "";

      const type = document.createElement("span");
      type.className = "global-search-result-type";
      type.textContent = item.type;

      copy.append(name, detail);
      if (reason.textContent) {
        copy.append(reason);
      }
      link.append(icon, copy, type);
      fragment.append(link);
      renderedItems += 1;
    });

    if (renderedItems === 0) {
      fragment.append(createMessage("No matching results found."));
    }

    results.replaceChildren(fragment);
    results.hidden = false;
    input.setAttribute("aria-expanded", "true");
  }

  document.querySelectorAll("[data-global-search]").forEach((search) => {
    const input = search.querySelector("[data-global-search-input]");
    const results = search.querySelector("[data-global-search-results]");
    const endpoint = search.dataset.endpoint;
    if (!(input instanceof HTMLInputElement) || !(results instanceof HTMLElement) || !endpoint) {
      return;
    }

    let debounceTimer;
    let activeRequest;
    const responseCache = new Map();

    async function performSearch() {
      const query = input.value.trim();
      if (query.length < minimumQueryLength) {
        hideResults(input, results);
        return;
      }

      let requestUrl;
      try {
        requestUrl = new URL(endpoint, window.location.origin);
      } catch {
        showMessage(input, results, "Search is unavailable.", true);
        return;
      }
      if (requestUrl.origin !== window.location.origin) {
        showMessage(input, results, "Search is unavailable.", true);
        return;
      }

      const cachedResponse = responseCache.get(query.toLocaleLowerCase());
      if (cachedResponse) {
        results.classList.remove("is-loading");
        renderResults(input, results, cachedResponse.results, query);
        return;
      }

      activeRequest?.abort();
      const requestController = new AbortController();
      activeRequest = requestController;
      results.classList.add("is-loading");
      showMessage(input, results, "Searching...");
      requestUrl.searchParams.set("q", query);

      try {
        const response = await fetch(requestUrl, {
          headers: { Accept: "application/json" },
          signal: requestController.signal,
        });
        if (!response.ok) {
          throw new Error(`Search request failed with status ${response.status}`);
        }

        const payload = await response.json();
        if (!payload || !Array.isArray(payload.results)) {
          throw new Error("Search response has an invalid format");
        }
        if (input.value.trim() !== query || activeRequest !== requestController) {
          return;
        }

        responseCache.set(query.toLocaleLowerCase(), payload);
        if (responseCache.size > maximumCachedQueries) {
          responseCache.delete(responseCache.keys().next().value);
        }
        renderResults(input, results, payload.results, query);
      } catch (error) {
        if (error.name === "AbortError") {
          return;
        }
        console.error("Global search request failed:", error);
        showMessage(input, results, "Search is temporarily unavailable. Please try again.", true);
      } finally {
        if (activeRequest === requestController) {
          results.classList.remove("is-loading");
          activeRequest = null;
        }
      }
    }

    input.addEventListener("input", () => {
      window.clearTimeout(debounceTimer);
      activeRequest?.abort();

      if (input.value.trim().length < minimumQueryLength) {
        hideResults(input, results);
        return;
      }

      debounceTimer = window.setTimeout(performSearch, debounceDelay);
    });

    input.addEventListener("keydown", (event) => {
      const links = [...results.querySelectorAll(".global-search-result")];
      if (event.key === "Escape") {
        hideResults(input, results);
      } else if (event.key === "ArrowDown" && links.length > 0) {
        event.preventDefault();
        links[0].focus();
      } else if (event.key === "ArrowUp" && links.length > 0) {
        event.preventDefault();
        links[links.length - 1].focus();
      } else if (event.key === "Enter" && links.length > 0) {
        event.preventDefault();
        links[0].click();
      }
    });

    search.addEventListener("keydown", (event) => {
      if (!event.target.matches(".global-search-result")) {
        return;
      }

      const links = [...results.querySelectorAll(".global-search-result")];
      const currentIndex = links.indexOf(event.target);
      if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        event.preventDefault();
        const direction = event.key === "ArrowDown" ? 1 : -1;
        links[(currentIndex + direction + links.length) % links.length]?.focus();
      } else if (event.key === "Escape") {
        hideResults(input, results);
        input.focus();
      }
    });

    document.addEventListener("click", (event) => {
      if (!search.contains(event.target)) {
        hideResults(input, results);
      }
    });

    search.addEventListener("focusout", () => {
      window.setTimeout(() => {
        if (!search.contains(document.activeElement)) {
          hideResults(input, results);
        }
      }, 0);
    });
  });
})();
