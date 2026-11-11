// Show demo toast buttons.
document.querySelectorAll("[data-toast]").forEach((button) => {
  button.addEventListener("click", () => {
    const toastElement = document.querySelector(button.dataset.toast);
    if (toastElement) {
      bootstrap.Toast.getOrCreateInstance(toastElement).show();
    }
  });
});

function updateNotificationUnreadCount(count) {
  const button = document.querySelector(".notification-action");
  const menuCount = document.querySelector(".notification-menu [data-unread-count]");
  let badge = button?.querySelector(".notification-count");

  if (menuCount) {
    menuCount.textContent = String(count);
  }
  if (count < 1) {
    badge?.remove();
    button?.classList.remove("has-notifications");
    button?.setAttribute("aria-label", "Notifications");
    return;
  }

  if (button && !badge) {
    badge = document.createElement("span");
    badge.className = "notification-count";
    button.append(badge);
  }
  if (badge) {
    badge.textContent = count > 99 ? "99+" : String(count);
  }
  button?.classList.add("has-notifications");
  button?.setAttribute("aria-label", `Notifications, ${count} unread`);
}

async function submitNotificationAction(container, action, ids) {
  const response = await fetch(container.dataset.actionUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-CSRF-Token": container.dataset.csrfToken,
    },
    body: JSON.stringify({ action, ids }),
    credentials: "same-origin",
  });
  const result = await response.json();
  if (!response.ok || result.status !== "success") {
    throw new Error(result.message || "Unable to update notifications.");
  }
  updateNotificationUnreadCount(result.unread_count);
  return result;
}

document.querySelectorAll(".notification-menu [data-notification-id], .notification-inbox [data-notification-id]").forEach((item) => {
  item.addEventListener("click", async (event) => {
    if (!item.classList.contains("is-unread")) {
      return;
    }

    const container = item.closest(".notification-menu, .notification-inbox");
    const isDropdown = container.classList.contains("notification-menu");
    const errorMessage = container.querySelector(".notification-action-error");
    const destination = item.href;
    const originalCount = Number(document.querySelector(".notification-menu [data-unread-count]")?.textContent || 0);
    event.preventDefault();
    if (errorMessage) errorMessage.classList.add("d-none");
    item.classList.remove("is-unread");
    updateNotificationUnreadCount(Math.max(0, originalCount - 1));

    try {
      const response = await fetch(container.dataset.markReadUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": container.dataset.csrfToken,
        },
        body: JSON.stringify({ id: Number(item.dataset.notificationId) }),
        credentials: "same-origin",
      });
      const result = await response.json();
      if (!response.ok || result.status !== "success") {
        throw new Error(result.message || "Unable to mark the notification as read.");
      }

      updateNotificationUnreadCount(result.unread_count);
      if (isDropdown) {
        item.remove();
        if (!container.querySelector("[data-notification-id]")) {
          const emptyMessage = document.createElement("p");
          emptyMessage.className = "notification-empty";
          emptyMessage.innerHTML = '<i class="bi bi-check-circle me-2"></i>No unread notifications.';
          container.querySelector(".notification-list")?.replaceWith(emptyMessage);
        }
        window.location.assign(destination);
      } else {
        item.closest("tr")?.classList.add("notification-focused");
      }
    } catch (error) {
      console.error("Notification mark-as-read failed:", error);
      item.classList.add("is-unread");
      updateNotificationUnreadCount(originalCount);
      if (errorMessage) {
        errorMessage.textContent = "Could not mark this notification as read. Check your connection and try again.";
        errorMessage.classList.remove("d-none");
      }
    }
  });
});

const notificationInbox = document.querySelector(".notification-inbox");
if (notificationInbox) {
  const checkboxes = notificationInbox.querySelectorAll(".notification-checkbox");
  const selectColumn = notificationInbox.querySelectorAll(".notification-select-column");
  const toggleButton = notificationInbox.querySelector("[data-notification-toggle-selection]");
  const selectAllButton = notificationInbox.querySelector("[data-notification-select-all]");
  const trashButton = notificationInbox.querySelector("[data-notification-trash-selected]");
  const errorMessage = notificationInbox.querySelector(".notification-action-error");
  let selectionEnabled = false;

  const updateSelectionControls = () => {
    const selectedCount = notificationInbox.querySelectorAll(".notification-checkbox:checked").length;
    if (trashButton) trashButton.disabled = selectedCount === 0;
    const availableCount = notificationInbox.querySelectorAll(".notification-checkbox").length;
    if (selectAllButton) {
      selectAllButton.textContent = availableCount > 0 && selectedCount === availableCount ? "Deselect All" : "Select All";
    }
  };

  const toggleSelection = (enabled) => {
    selectionEnabled = enabled;
    selectColumn.forEach((column) => column.classList.toggle("d-none", !enabled));
    if (selectAllButton) selectAllButton.classList.toggle("d-none", !enabled);
    if (trashButton) trashButton.classList.toggle("d-none", !enabled);
    if (toggleButton) {
      toggleButton.textContent = enabled ? "Cancel Selection" : "Select";
      toggleButton.setAttribute("aria-pressed", String(enabled));
    }
    if (!enabled) notificationInbox.querySelectorAll(".notification-checkbox").forEach((checkbox) => { checkbox.checked = false; });
    updateSelectionControls();
  };

  if (toggleButton) {
    toggleButton.addEventListener("click", () => toggleSelection(!selectionEnabled));
  }
  if (selectAllButton) {
    selectAllButton.addEventListener("click", () => {
      const selectAll = selectAllButton.textContent === "Select All";
      notificationInbox.querySelectorAll(".notification-checkbox").forEach((checkbox) => { checkbox.checked = selectAll; });
      updateSelectionControls();
    });
  }
  checkboxes.forEach((checkbox) => checkbox.addEventListener("change", updateSelectionControls));
  
  notificationInbox.querySelectorAll("[data-notification-row]").forEach((row) => {
    row.addEventListener("click", (event) => {
      if (!selectionEnabled || event.target.closest("a, button, input, label, select, textarea")) return;
      const checkbox = row.querySelector(".notification-checkbox");
      if (checkbox) {
        checkbox.checked = !checkbox.checked;
        checkbox.dispatchEvent(new Event("change", { bubbles: true }));
      }
    });
  });

  if (trashButton) {
    trashButton.addEventListener("click", async () => {
      const selected = [...notificationInbox.querySelectorAll(".notification-checkbox:checked")];
      if (!selected.length) return;
      const confirmation = await Swal.fire({
        title: "Move notifications to Trash?",
        text: `${selected.length} notification(s) will be moved to Trash.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Move to Trash", // Yahan text change kar diya hai
        confirmButtonColor: "#d33",
      });
      if (!confirmation.isConfirmed) return;

      try {
        const result = await submitNotificationAction(notificationInbox, "trash", selected.map((checkbox) => Number(checkbox.value)));
        selected.forEach((checkbox) => checkbox.closest("tr").remove());
        const countLabel = notificationInbox.querySelector(".card-header strong");
        const remainingRows = notificationInbox.querySelectorAll("[data-notification-row]").length;
        if (countLabel) countLabel.textContent = `${remainingRows} notification(s)`;
        if (remainingRows === 0) {
          const emptyRow = document.createElement("tr");
          emptyRow.innerHTML = '<td colspan="4" class="text-center text-muted py-4">No notifications match these filters.</td>';
          notificationInbox.querySelector("tbody").append(emptyRow);
        }
        updateSelectionControls();
        
        // Success Popup add kar diya hai taake message show ho
        await Swal.fire({
          icon: "success",
          title: "Moved to Trash!",
          text: `${result.affected} notification(s) moved to trash successfully.`,
          timer: 1800,
          showConfirmButton: false,
        });

      } catch (error) {
        console.error("Moving notifications to trash failed:", error);
        if (errorMessage) {
          errorMessage.textContent = error.message;
          errorMessage.classList.remove("d-none");
        }
      }
    });
  }
}

const notificationTrash = document.querySelector(".notification-trash");
if (notificationTrash) {
  // (Baaki trash section ka code waisa hi rahega)
  // ...
}