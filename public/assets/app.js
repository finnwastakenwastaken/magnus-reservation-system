(() => {
  const calendarRoots = document.querySelectorAll("[data-calendar-payload]");
  if (!calendarRoots.length || typeof window.FullCalendar === "undefined") {
    return;
  }

  const formatDateTimeLocal = (date) => {
    const pad = (value) => String(value).padStart(2, "0");
    return [
      date.getFullYear(),
      pad(date.getMonth() + 1),
      pad(date.getDate()),
    ].join("-") + "T" + [pad(date.getHours()), pad(date.getMinutes())].join(":");
  };

  const initCalendar = (root) => {
    const payloadId = root.getAttribute("data-calendar-payload");
    const payloadNode = payloadId ? document.getElementById(payloadId) : null;
    if (!payloadNode) {
      return;
    }

    const payload = JSON.parse(payloadNode.textContent || "{}");
    const rules = payload.rules || {};
    const isPublic = root.getAttribute("data-calendar-public") === "1";
    const modalElement = !isPublic ? document.getElementById("reservationQuickCreateModal") : null;
    const modal = modalElement && window.bootstrap ? new window.bootstrap.Modal(modalElement) : null;
    const form = modalElement ? modalElement.querySelector("#reservationQuickCreateForm") : null;
    const errorBox = modalElement ? modalElement.querySelector("[data-calendar-error]") : null;
    const submitButton = modalElement ? modalElement.querySelector("[data-calendar-submit]") : null;

    const showError = (message) => {
      if (!errorBox) {
        window.alert(message);
        return;
      }

      errorBox.textContent = message;
      errorBox.classList.remove("d-none");
    };

    const clearError = () => {
      if (!errorBox) {
        return;
      }

      errorBox.textContent = "";
      errorBox.classList.add("d-none");
    };

    const calendar = new window.FullCalendar.Calendar(root, {
      themeSystem: "standard",
      initialView: window.innerWidth < 768 ? "timeGridDay" : "timeGridWeek",
      headerToolbar: {
        left: "prev,next today",
        center: "title",
        right: "timeGridDay,timeGridWeek,listWeek",
      },
      height: "auto",
      nowIndicator: true,
      selectable: !isPublic,
      selectMirror: true,
      editable: false,
      allDaySlot: false,
      slotDuration: "00:30:00",
      snapDuration: "00:30:00",
      slotMinTime: `${String(rules.start_hour ?? 9).padStart(2, "0")}:00:00`,
      slotMaxTime: `${String(rules.end_hour ?? 22).padStart(2, "0")}:00:00`,
      firstDay: 1,
      eventSources: [
        {
          url: payload.feedUrl,
          method: "GET",
          failure: () => showError(payload.messages?.genericError || "Unable to load reservations."),
        },
      ],
      selectAllow: (selectionInfo) => !isPublic && selectionInfo.start < selectionInfo.end,
      select: (selectionInfo) => {
        if (!form || !modal) {
          return;
        }

        clearError();
        form.elements.start_datetime.value = formatDateTimeLocal(selectionInfo.start);
        form.elements.end_datetime.value = formatDateTimeLocal(selectionInfo.end);
        modal.show();
      },
      eventClick: async (info) => {
        const canCancel = info.event.extendedProps?.canCancel;
        if (!canCancel || isPublic) {
          return;
        }

        if (!window.confirm(payload.messages?.cancelConfirm || "Cancel this reservation?")) {
          return;
        }

        try {
          const url = (payload.cancelUrlTemplate || "").replace("__ID__", info.event.extendedProps.reservationId);
          const response = await fetch(url, {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            },
            body: new URLSearchParams({ _csrf: payload.csrfToken }),
          });

          if (!response.ok) {
            throw new Error(payload.messages?.genericError || "Unable to cancel reservation.");
          }

          calendar.refetchEvents();
        } catch (error) {
          showError(error.message);
        }
      },
      eventTimeFormat: {
        hour: "2-digit",
        minute: "2-digit",
        meridiem: false,
      },
    });

    calendar.render();

    if (submitButton && form) {
      submitButton.addEventListener("click", async () => {
        clearError();

        try {
          const response = await fetch(payload.createUrl, {
            method: "POST",
            body: new URLSearchParams(new FormData(form)),
          });
          const result = await response.json();

          if (!response.ok || !result.ok) {
            const firstError = Object.values(result.errors || {})[0] || payload.messages?.genericError || "Unable to save reservation.";
            throw new Error(String(firstError));
          }

          modal?.hide();
          calendar.unselect();
          calendar.refetchEvents();
        } catch (error) {
          showError(error.message);
        }
      });
    }

    window.addEventListener("resize", () => {
      const nextView = window.innerWidth < 768 ? "timeGridDay" : "timeGridWeek";
      if (calendar.view.type !== nextView && !calendar.view.type.startsWith("list")) {
        calendar.changeView(nextView);
      }
    });
  };

  calendarRoots.forEach(initCalendar);
})();
