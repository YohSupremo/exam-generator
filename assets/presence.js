(function () {
  "use strict";

  // Per-tab unique presence client ID
  let clientId = sessionStorage.getItem("synth_presence_cid");
  if (!clientId) {
    clientId = "usr_" + Math.random().toString(36).slice(2, 9) + "_" + Date.now().toString(36);
    sessionStorage.setItem("synth_presence_cid", clientId);
  }

  // Detect context
  const examId = document.body.dataset.examId || (new URLSearchParams(window.location.search).get("id")) || "";
  const pathname = window.location.pathname;
  let page = "home";
  if (pathname.indexOf("exam.php") !== -1 || examId) page = "exam";
  else if (pathname.indexOf("thesis") !== -1) page = "thesis";

  // Resolve API path relative to current page
  const isSubdir = pathname.indexOf("/thesis/") !== -1;
  const apiEndpoint = isSubdir ? "../api/presence.php" : "api/presence.php";

  function updateBadgeUI(online, examOnline) {
    const countEls = document.querySelectorAll(".live-users-count, #live-users-count");
    countEls.forEach(function (el) {
      const currentVal = el.textContent.trim();
      const nextVal = String(online);
      if (currentVal !== nextVal) {
        el.textContent = nextVal;
        el.classList.add("bump");
        setTimeout(function () { el.classList.remove("bump"); }, 300);
      }
    });

    const badgeEls = document.querySelectorAll(".live-users-badge, #live-users-badge");
    badgeEls.forEach(function (badge) {
      if (examId && examOnline > 0) {
        badge.title = online + " user" + (online === 1 ? "" : "s") + " online (" + examOnline + " viewing this exam)";
      } else {
        badge.title = online + " user" + (online === 1 ? "" : "s") + " actively on the site";
      }
    });
  }

  function ping() {
    const url = apiEndpoint + "?clientId=" + encodeURIComponent(clientId) +
      "&page=" + encodeURIComponent(page) +
      "&examId=" + encodeURIComponent(examId) +
      "&_=" + Date.now();

    fetch(url)
      .then(function (r) {
        if (!r.ok) throw new Error("HTTP " + r.status);
        return r.json();
      })
      .then(function (data) {
        if (data && data.ok) {
          updateBadgeUI(data.online, data.examOnline);
        }
      })
      .catch(function () {
        // Fallback silently without throwing
      });
  }

  // Initial ping on load
  ping();

  // Heartbeat every 12 seconds
  setInterval(ping, 12000);

  // Instant refresh when returning to tab
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) {
      ping();
    }
  });

  // Deregister on tab close
  window.addEventListener("beforeunload", function () {
    if (navigator.sendBeacon) {
      const leaveUrl = apiEndpoint + "?action=leave&clientId=" + encodeURIComponent(clientId);
      navigator.sendBeacon(leaveUrl);
    }
  });
})();
