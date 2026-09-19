(function () {
  "use strict";

  const statusLabels = {
    new: "Received",
    preparing: "Preparing",
    ready: "Ready",
    served: "Served",
    cancelled: "Cancelled"
  };

  const toast = document.querySelector("[data-toast]");
  let toastTimer;
  function announce(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add("is-visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("is-visible"), 3200);
  }

  function formatTime(date) {
    return new Intl.DateTimeFormat("en-AU", { hour: "numeric", minute: "2-digit", second: "2-digit" }).format(date);
  }

  const clock = document.querySelector("[data-live-clock]");
  if (clock) {
    const tick = () => { clock.textContent = formatTime(new Date()); };
    tick();
    setInterval(tick, 1000);
  }

  document.querySelectorAll(".nav-item").forEach((link) => {
    link.addEventListener("click", () => {
      const navState = document.getElementById("nav-open");
      if (navState) navState.checked = false;
    });
  });

  document.querySelectorAll("[data-filter-group]").forEach((group) => {
    group.addEventListener("click", (event) => {
      const button = event.target.closest("[data-filter]");
      if (!button) return;
      group.querySelectorAll("[data-filter]").forEach((item) => {
        item.classList.toggle("is-on", item === button);
        item.setAttribute("aria-pressed", item === button ? "true" : "false");
      });
      const target = document.querySelector(group.dataset.filterTarget);
      if (!target) return;
      const filter = button.dataset.filter;
      target.querySelectorAll("[data-status], [data-state]").forEach((item) => {
        const value = item.dataset.status || item.dataset.state;
        item.classList.toggle("is-hidden", filter !== "all" && value !== filter);
      });
    });
  });

  document.querySelectorAll("[data-item-check]").forEach((button) => {
    button.addEventListener("click", () => {
      button.classList.toggle("is-done");
      const done = button.classList.contains("is-done");
      button.setAttribute("aria-pressed", done ? "true" : "false");
      button.setAttribute("aria-label", done ? "Mark item not prepared" : "Mark item prepared");
      const name = button.closest(".ticket-item")?.querySelector(".dish strong")?.textContent || "Item";
      announce(done ? `${name} marked prepared.` : `${name} returned to preparation.`);
    });
  });

  document.querySelectorAll("[data-next-status]").forEach((button) => {
    button.addEventListener("click", () => {
      const card = button.closest("[data-status]");
      const next = button.dataset.nextStatus;
      if (!card) {
        const row = button.closest(".queue-row");
        const order = row?.querySelector(".queue-copy strong")?.textContent || "Order";
        row?.classList.add("is-cleared");
        button.textContent = statusLabels[next] || next;
        button.disabled = true;
        announce(`${order} updated to ${statusLabels[next] || next}. Served time recorded.`);
        return;
      }
      card.dataset.status = next;
      const badge = card.querySelector("[data-status-badge]");
      if (badge) {
        badge.textContent = statusLabels[next] || next;
        badge.className = `badge b-${next === "new" ? "pending" : next}`;
        badge.dataset.statusBadge = "";
      }
      const order = card.querySelector(".ticket-order strong, .payment-main strong")?.textContent || "Order";
      if (next === "preparing") {
        button.textContent = "Mark order ready";
        button.dataset.nextStatus = "ready";
      } else if (next === "ready") {
        button.textContent = "Ready — notify floor";
        button.disabled = true;
      } else if (next === "served") {
        button.textContent = "Served";
        button.disabled = true;
      }
      announce(`${order} updated to ${statusLabels[next] || next}. Status history recorded.`);
    });
  });

  document.querySelectorAll("[data-clear-queue]").forEach((button) => {
    button.addEventListener("click", () => {
      const row = button.closest(".queue-row");
      row?.classList.add("is-cleared");
      button.textContent = "Collected";
      button.disabled = true;
      announce("Pickup confirmed. The floor team can now serve the order.");
    });
  });

  document.querySelectorAll("[data-availability-toggle]").forEach((input) => {
    input.addEventListener("change", () => {
      const item = input.closest(".availability-item");
      item?.classList.toggle("is-off", !input.checked);
      const name = item?.querySelector("strong")?.textContent || "Menu item";
      announce(`${name}: ${input.checked ? "availability restored" : "unavailable report sent to manager"}.`);
    });
  });

  document.querySelectorAll("[data-table-action]").forEach((button) => {
    button.addEventListener("click", () => {
      const card = button.closest(".table-card");
      const table = card?.querySelector(".table-number strong")?.textContent || "Table";
      announce(`${button.textContent.trim()} opened for ${table}.`);
    });
  });

  document.querySelectorAll("[data-mark-paid]").forEach((button) => {
    button.addEventListener("click", () => {
      const row = button.closest(".payment-row");
      const badge = row?.querySelector(".badge");
      if (badge) {
        badge.textContent = "Paid";
        badge.className = "badge b-ready";
      }
      button.textContent = "Receipt";
      button.removeAttribute("data-mark-paid");
      announce("Payment recorded and order marked paid. Receipt is ready to export.");
    }, { once: true });
  });

  document.querySelectorAll("[data-open-dialog]").forEach((button) => {
    button.addEventListener("click", () => {
      const dialog = document.getElementById(button.dataset.openDialog);
      if (dialog?.showModal) dialog.showModal();
    });
  });
  document.querySelectorAll("[data-close-dialog]").forEach((button) => {
    button.addEventListener("click", () => button.closest("dialog")?.close());
  });
  document.querySelectorAll("dialog form").forEach((form) => {
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const dialog = form.closest("dialog");
      announce(dialog?.dataset.success || "Saved successfully.");
      dialog?.close();
      form.reset();
    });
  });

  const search = document.querySelector("[data-global-search]");
  if (search) {
    search.addEventListener("input", () => {
      const query = search.value.trim().toLowerCase();
      document.querySelectorAll("[data-searchable]").forEach((item) => {
        item.hidden = Boolean(query) && !item.textContent.toLowerCase().includes(query);
      });
    });
  }

  document.querySelectorAll("[data-demo-action]").forEach((button) => {
    button.addEventListener("click", () => announce(button.dataset.demoAction));
  });
})();
