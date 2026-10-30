/* =========================================================
   MeetSync — Frontend Enhancements
   Progressive enhancement only: every page keeps working
   without this file, and no form data or PHP logic changes.
========================================================= */

(function () {
  "use strict";

  var THEME_KEY = "meetsync-theme";
  var root = document.documentElement;

  // Apply the saved theme before first paint to avoid a flash.
  try {
    var savedTheme = localStorage.getItem(THEME_KEY);
    var prefersDark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
    root.setAttribute("data-theme", savedTheme || (prefersDark ? "dark" : "light"));
  } catch (error) {
    root.setAttribute("data-theme", "lightwhat");
  }

  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var ICONS = {
    moon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>',
    sun: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>',
    up: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>',
    menu: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>'
  };

  function createButton(className, label, html) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = className;
    button.setAttribute("aria-label", label);
    button.innerHTML = html;
    return button;
  }

  /* ---------- Active navigation link ---------- */

  function markActiveLinks() {
    var current = window.location.pathname.replace(/\/+$/, "");
    var links = document.querySelectorAll(".navbar a[href]");

    links.forEach(function (link) {
      var target = new URL(link.getAttribute("href"), window.location.href).pathname.replace(/\/+$/, "");
      if (target === current) {
        link.setAttribute("aria-current", "page");
      }
    });
  }

  /* ---------- Mobile navigation ---------- */

  function setupMobileNav() {
    var linkGroup =
      document.querySelector(".site-header nav.navbar") ||
      document.querySelector(".site-header .navbar > div:nth-child(2)") ||
      document.querySelector("body > nav.navbar .container");

    if (!linkGroup || linkGroup.querySelectorAll("a").length < 2) {
      return;
    }

    linkGroup.classList.add("ms-nav-links", "ms-collapsible");
    if (!linkGroup.id) {
      linkGroup.id = "ms-nav-links";
    }

    var toggle = createButton("ms-nav-toggle", "Toggle navigation menu", ICONS.menu + "<span>Menu</span>");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-controls", linkGroup.id);
    linkGroup.parentNode.insertBefore(toggle, linkGroup);

    var headerScope = linkGroup.closest(".site-header, body > nav.navbar");

    toggle.addEventListener("click", function () {
      var open = headerScope.classList.toggle("nav-open");
      toggle.setAttribute("aria-expanded", String(open));
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && headerScope.classList.contains("nav-open")) {
        headerScope.classList.remove("nav-open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.focus();
      }
    });
  }

  /* ---------- Dismissible messages ---------- */

  function setupMessages() {
    document.querySelectorAll(".message").forEach(function (message) {
      if (!message.textContent.trim()) {
        return;
      }

      var content = document.createElement("div");
      while (message.firstChild) {
        content.appendChild(message.firstChild);
      }
      message.appendChild(content);

      var isError = message.classList.contains("message-error") || message.classList.contains("error");
      message.setAttribute("role", isError ? "alert" : "status");

      var dismiss = createButton("ms-dismiss", "Dismiss message", "&times;");
      dismiss.addEventListener("click", function () {
        message.classList.add("is-leaving");
        window.setTimeout(function () {
          message.remove();
        }, reduceMotion ? 0 : 250);
      });
      message.appendChild(dismiss);
    });
  }

  /* ---------- Password reveal ---------- */

  function setupPasswordToggles() {
    // change_password.php already ships its own "show passwords" checkbox.
    if (document.getElementById("showPasswords")) {
      return;
    }

    document.querySelectorAll(
  'input[type="password"]:not(#password)'
).forEach(function (input) {
      var wrap = document.createElement("div");
      wrap.className = "ms-password-wrap";
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);

      var toggle = createButton("ms-password-toggle", "Show password", "Show");
      toggle.addEventListener("click", function () {
        var reveal = input.type === "password";
        input.type = reveal ? "text" : "password";
        toggle.textContent = reveal ? "Hide" : "Show";
        toggle.setAttribute("aria-label", reveal ? "Hide password" : "Show password");
        input.focus();
      });
      wrap.appendChild(toggle);
    });
  }

  /* ---------- Submit feedback (no disabling, so button name/value still posts) ---------- */

  function setupFormFeedback() {
    document.querySelectorAll("form").forEach(function (form) {
      form.addEventListener("submit", function (event) {
        if (form.dataset.msSubmitting === "true") {
          event.preventDefault();
          return;
        }

        var submitter = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
        if (!submitter || !submitter.classList) {
          return;
        }

        window.requestAnimationFrame(function () {
          if (event.defaultPrevented) {
            return;
          }
          form.dataset.msSubmitting = "true";
          submitter.classList.add("is-loading");
          submitter.setAttribute("aria-busy", "true");

          // Downloads and same-page responses never unload, so release the lock.
          window.setTimeout(function () {
            form.dataset.msSubmitting = "false";
            submitter.classList.remove("is-loading");
            submitter.removeAttribute("aria-busy");
          }, 5000);
        });
      });
    });

    window.addEventListener("pageshow", function (event) {
      if (event.persisted) {
        document.querySelectorAll(".is-loading").forEach(function (element) {
          element.classList.remove("is-loading");
          element.removeAttribute("aria-busy");
        });
        document.querySelectorAll("form").forEach(function (form) {
          form.dataset.msSubmitting = "false";
        });
      }
    });
  }

  /* ---------- Tables: search + sort ---------- */

  function cellValue(row, index) {
    var cell = row.cells[index];
    return cell ? cell.textContent.replace(/\s+/g, " ").trim() : "";
  }

  function compareValues(a, b) {
    var numberA = parseFloat(a.replace(/[^0-9.\-]/g, ""));
    var numberB = parseFloat(b.replace(/[^0-9.\-]/g, ""));
    var numericPattern = /^[\s#\-]*[0-9.,]+\s*%?$/;

    if (numericPattern.test(a) && numericPattern.test(b) && !isNaN(numberA) && !isNaN(numberB)) {
      return numberA - numberB;
    }

    var dateA = Date.parse(a);
    var dateB = Date.parse(b);
    if (!isNaN(dateA) && !isNaN(dateB)) {
      return dateA - dateB;
    }

    return a.localeCompare(b, undefined, { numeric: true, sensitivity: "base" });
  }

  function setupTables() {
    document.querySelectorAll("table").forEach(function (table, tableIndex) {
      var body = table.tBodies[0];
      var headRow = table.tHead ? table.tHead.rows[0] : null;
      if (!body || !headRow) {
        return;
      }

      var rows = Array.prototype.slice.call(body.rows);
      if (rows.length < 2) {
        return;
      }

      Array.prototype.forEach.call(headRow.cells, function (header, columnIndex) {
        var label = header.textContent.trim().toLowerCase();
        if (!label || label === "action" || label === "actions") {
          return;
        }

        header.classList.add("ms-sortable");
        header.setAttribute("tabindex", "0");
        header.setAttribute("aria-sort", "none");

        function sort() {
          var ascending = header.getAttribute("aria-sort") !== "ascending";
          Array.prototype.forEach.call(headRow.cells, function (cell) {
            if (cell.classList.contains("ms-sortable")) {
              cell.setAttribute("aria-sort", "none");
            }
          });
          header.setAttribute("aria-sort", ascending ? "ascending" : "descending");

          rows
            .slice()
            .sort(function (rowA, rowB) {
              var result = compareValues(cellValue(rowA, columnIndex), cellValue(rowB, columnIndex));
              return ascending ? result : -result;
            })
            .forEach(function (row) {
              body.appendChild(row);
            });
        }

        header.addEventListener("click", sort);
        header.addEventListener("keydown", function (event) {
          if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            sort();
          }
        });
      });

      if (rows.length < 4) {
        return;
      }

      var anchor = table.closest(".table-container") || table;
      var toolbar = document.createElement("div");
      toolbar.className = "ms-table-toolbar";

      var searchId = "ms-table-search-" + tableIndex;
      var searchLabel = document.createElement("label");
      searchLabel.className = "sr-only";
      searchLabel.setAttribute("for", searchId);
      searchLabel.textContent = "Filter table rows";
      searchLabel.style.cssText = "position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);";

      var search = document.createElement("input");
      search.type = "search";
      search.id = searchId;
      search.placeholder = "Filter rows...";
      search.autocomplete = "off";

      var count = document.createElement("span");
      count.className = "ms-table-count";
      count.setAttribute("aria-live", "polite");

      var emptyRow = document.createElement("tr");
      emptyRow.className = "ms-empty-row";
      emptyRow.hidden = true;
      var emptyCell = document.createElement("td");
      emptyCell.colSpan = headRow.cells.length;
      emptyCell.textContent = "No matching records.";
      emptyRow.appendChild(emptyCell);
      body.appendChild(emptyRow);

      function updateCount(visible) {
        count.textContent = visible + " of " + rows.length + " records";
      }

      search.addEventListener("input", function () {
        var query = search.value.trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
          var match = !query || row.textContent.toLowerCase().indexOf(query) !== -1;
          row.hidden = !match;
          if (match) {
            visible += 1;
          }
        });
        emptyRow.hidden = visible !== 0;
        body.appendChild(emptyRow);
        updateCount(visible);
      });

      updateCount(rows.length);
      toolbar.appendChild(searchLabel);
      toolbar.appendChild(search);
      toolbar.appendChild(count);
      anchor.parentNode.insertBefore(toolbar, anchor);
    });
  }

  /* ---------- Animated stat counters ---------- */

  function setupCounters() {
    document.querySelectorAll(".stat-number, .stat-card .number").forEach(function (element) {
      var text = element.textContent.trim();
      if (!/^\d+$/.test(text)) {
        return;
      }

      var target = parseInt(text, 10);
      if (reduceMotion || target === 0) {
        return;
      }

      var duration = Math.min(1200, 400 + target * 20);
      var start = null;
      element.textContent = "0";

      function step(timestamp) {
        if (start === null) {
          start = timestamp;
        }
        var progress = Math.min((timestamp - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        element.textContent = String(Math.round(target * eased));
        if (progress < 1) {
          window.requestAnimationFrame(step);
        }
      }

      window.requestAnimationFrame(step);
    });
  }

  /* ---------- Reveal on scroll ---------- */

  function setupReveal() {
    if (reduceMotion || !("IntersectionObserver" in window)) {
      return;
    }

    var targets = document.querySelectorAll(
      "main > .card, main > section.card, .stat-card, .menu-item, .menu-card, .meeting-list > .card, .profile-card, .password-card, .table-container"
    );

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            var element = entry.target;
            element.classList.add("is-visible");
            observer.unobserve(element);
            // Drop reveal styles afterwards so hover transitions behave normally.
            window.setTimeout(function () {
              element.classList.remove("ms-reveal", "is-visible");
              element.style.transitionDelay = "";
            }, 800);
          }
        });
      },
      { rootMargin: "0px 0px -40px 0px", threshold: 0.05 }
    );

    targets.forEach(function (element, index) {
      element.classList.add("ms-reveal");
      element.style.transitionDelay = Math.min(index % 6, 5) * 40 + "ms";
      observer.observe(element);
    });
  }

  /* ---------- Floating controls: theme + back to top ---------- */

  function setupFloatingControls() {
    var group = document.createElement("div");
    group.className = "ms-fab-group";

    var toTop = createButton("ms-fab ms-to-top", "Back to top", ICONS.up);
    toTop.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
    });

    var themeToggle = createButton("ms-fab", "Toggle dark mode", "");

    function renderThemeIcon() {
      var dark = root.getAttribute("data-theme") === "dark";
      themeToggle.innerHTML = dark ? ICONS.sun : ICONS.moon;
      themeToggle.setAttribute("aria-label", dark ? "Switch to light mode" : "Switch to dark mode");
      themeToggle.setAttribute("aria-pressed", String(dark));
    }

    themeToggle.addEventListener("click", function () {
      var next = root.getAttribute("data-theme") === "dark" ? "light" : "dark";
      root.setAttribute("data-theme", next);
      try {
        localStorage.setItem(THEME_KEY, next);
      } catch (error) {
        /* storage unavailable: theme still applies for this page */
      }
      renderThemeIcon();
    });

    renderThemeIcon();
    group.appendChild(toTop);
    group.appendChild(themeToggle);
    document.body.appendChild(group);

    var ticking = false;
    window.addEventListener(
      "scroll",
      function () {
        if (ticking) {
          return;
        }
        ticking = true;
        window.requestAnimationFrame(function () {
          toTop.classList.toggle("is-visible", window.scrollY > 400);
          ticking = false;
        });
      },
      { passive: true }
    );
  }

  /* ---------- Textarea auto-grow ---------- */

  function setupTextareas() {
    document.querySelectorAll("textarea").forEach(function (textarea) {
      function resize() {
        textarea.style.height = "auto";
        textarea.style.height = Math.max(textarea.scrollHeight + 2, 110) + "px";
      }
      textarea.addEventListener("input", resize);
      resize();
    });
  }

  function init() {
    markActiveLinks();
    setupMobileNav();
    setupMessages();
    setupPasswordToggles();
    setupFormFeedback();
    setupTables();
    setupCounters();
    setupReveal();
    setupTextareas();
    setupFloatingControls();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
