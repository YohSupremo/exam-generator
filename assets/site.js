(function () {
  "use strict";

  const dz = document.getElementById("dropzone");
  const fileInput = document.getElementById("pdf-file");
  const dzHint = document.getElementById("dz-hint");
  const submitBtn = document.getElementById("submit-btn");
  const statusArea = document.getElementById("status-area");
  const statusText = document.getElementById("status-text");
  const titleInput = document.getElementById("title-input");
  const form = document.getElementById("upload-form");

  let selectedFile = null;
  let pollTimer = null;

  function setStatus(html, show) {
    if (show === undefined) show = true;
    statusArea.hidden = !show;
    if (show) statusText.innerHTML = html;
  }

  function updateDropzoneUI() {
    submitBtn.disabled = !selectedFile;
    if (selectedFile) {
      dzHint.textContent = selectedFile.name + " (" + (selectedFile.size / 1024 / 1024).toFixed(2) + " MB)";
    } else {
      dzHint.textContent = "";
    }
  }

  if (fileInput) {
    fileInput.addEventListener("change", function () {
      selectedFile = fileInput.files[0] || null;
      updateDropzoneUI();
    });
  }

  if (dz) {
    dz.addEventListener("click", function () { fileInput.click(); });
    ["dragover", "dragenter"].forEach(function (ev) {
      dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add("drag"); });
    });
    ["dragleave", "drop"].forEach(function (ev) {
      dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove("drag"); });
    });
    dz.addEventListener("drop", function (e) {
      const f = e.dataTransfer.files[0];
      if (f && f.type === "application/pdf") {
        selectedFile = f;
        fileInput.files = undefined;
        const dt = new DataTransfer();
        dt.items.add(f);
        fileInput.files = dt.files;
        updateDropzoneUI();
      } else if (f) {
        alert("Please drop a PDF file.");
      }
    });
  }

  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!selectedFile) return;
      const fd = new FormData();
      fd.append("pdf", selectedFile);
      fd.append("title", titleInput ? titleInput.value.trim() : "");

      submitBtn.disabled = true;
      submitBtn.textContent = "Uploading\u2026";
      setStatus("Uploading PDF\u2026");

      fetch("api/generate.php", { method: "POST", body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.ok) { throw new Error(res.error || "Upload failed"); }
          setStatus("Job created. <code>" + res.id + "</code>");
          startPolling(res.id);
        })
        .catch(function (err) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Generate Exam";
          setStatus("<span style='color:var(--red)'>" + String(err.message).replace(/</g, "&lt;") + "</span>");
        });
    });
  }

  function startPolling(id) {
    if (pollTimer) clearInterval(pollTimer);
    // keep the page from navigating away while it fast-tracks
    pollTimer = setInterval(function () { poll(id); }, 2000);
    poll(id);
  }

  function poll(id) {
    fetch("api/status.php?id=" + encodeURIComponent(id))
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.status === "running") {
          setStatus("Generating exam with opencode \u2014 this takes a few minutes\u2026");
          document.querySelectorAll("[data-status='queued'], [data-status='running']").forEach(function (el) {
            el.textContent = "running";
            el.className = "badge badge-running";
          });
        } else if (res.status === "done") {
          if (pollTimer) clearInterval(pollTimer);
          setStatus("Exam ready! Opening it\u2026");
          setTimeout(function () {
            window.location.href = "exam.php?id=" + encodeURIComponent(id);
          }, 800);
        } else if (res.status === "error") {
          if (pollTimer) clearInterval(pollTimer);
          setStatus("<span style='color:var(--red)'>Generation failed: " + String(res.error || "unknown error").replace(/</g, "&lt;") + "</span>");
          submitBtn.disabled = false;
          submitBtn.textContent = "Generate Exam";
        }
      })
      .catch(function () { /* transient network error, keep polling */ });
  }

  // auto-track a job when arriving with ?job=...
  window.AUTO_POLL_JOB = window.AUTO_POLL_JOB || null;
  if (window.AUTO_POLL_JOB) startPolling(window.AUTO_POLL_JOB);

  const backendEl = document.getElementById("backend-status");
  if (backendEl) {
    var pingAttempts = 0;
    var pingTimer = null;
    function renderBackend(online, misconfigured) {
      backendEl.innerHTML = online
        ? '<span class="dot online"></span>Backend online'
        : '<span class="dot offline"></span>' + (misconfigured ? "Backend misconfigured" : "Backend unreachable");
      if (online) { if (pingTimer) clearInterval(pingTimer); }
    }
    function pingBackend() {
      fetch("api/status.php?ping=1")
        .then(function (r) {
          if (!r.ok) throw new Error("http " + r.status);
          return r.json();
        })
        .then(function (res) {
          renderBackend(!!(res.opencode && res.php), true);
        })
        .catch(function () {
          pingAttempts++;
          if (pingAttempts < 5) {
            setTimeout(pingBackend, 1500);
          } else {
            renderBackend(false, false);
          }
        });
    }
    pingBackend();
    pingTimer = setInterval(pingBackend, 15000);
  }
})();

function deleteExam(id) {
  if (!confirm("Delete this exam and its generated data?")) return;
  fetch("api/generate.php?op=delete&id=" + encodeURIComponent(id), { method: "POST" })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (res.ok) location.reload();
      else alert(res.error || "Delete failed");
    })
    .catch(function () { alert("Delete failed"); });
}