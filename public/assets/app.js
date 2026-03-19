(() => {
  const calendarRoots = document.querySelectorAll("[data-calendar-payload]");
  if (!calendarRoots.length) {
    return;
  }

  const SLOT_MINUTES = 30;
  const SLOT_HEIGHT = 44;

  const pad = (value) => String(value).padStart(2, "0");

  const cloneDate = (date) => new Date(date.getTime());

  const addDays = (date, days) => {
    const next = cloneDate(date);
    next.setDate(next.getDate() + days);
    return next;
  };

  const startOfDay = (date) => {
    const next = cloneDate(date);
    next.setHours(0, 0, 0, 0);
    return next;
  };

  const startOfWeek = (date) => {
    const next = startOfDay(date);
    const weekday = (next.getDay() + 6) % 7;
    next.setDate(next.getDate() - weekday);
    return next;
  };

  const toIsoRangeValue = (date) =>
    [
      date.getFullYear(),
      pad(date.getMonth() + 1),
      pad(date.getDate()),
    ].join("-") +
    "T" +
    [pad(date.getHours()), pad(date.getMinutes())].join(":");

  const toRangeParam = (date) =>
    [
      date.getFullYear(),
      pad(date.getMonth() + 1),
      pad(date.getDate()),
    ].join("-") +
    " " +
    [pad(date.getHours()), pad(date.getMinutes()), "00"].join(":");

  const formatTime = (date, locale) =>
    new Intl.DateTimeFormat(locale, {
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    }).format(date);

  const formatDayLabel = (date, locale, compact = false) =>
    new Intl.DateTimeFormat(locale, compact
      ? { weekday: "short", day: "numeric", month: "short" }
      : { weekday: "long", day: "numeric", month: "short" }).format(date);

  const formatTitle = (view, anchorDate, locale) => {
    if (view === "day") {
      return formatDayLabel(anchorDate, locale, false);
    }

    const weekStart = startOfWeek(anchorDate);
    const weekEnd = addDays(weekStart, 6);
    const titleStart = new Intl.DateTimeFormat(locale, {
      day: "numeric",
      month: "short",
    }).format(weekStart);
    const titleEnd = new Intl.DateTimeFormat(locale, {
      day: "numeric",
      month: "short",
      year: "numeric",
    }).format(weekEnd);

    return `${titleStart} - ${titleEnd}`;
  };

  const rangeForView = (view, anchorDate) => {
    if (view === "day") {
      const start = startOfDay(anchorDate);
      return { start, end: addDays(start, 1) };
    }

    const start = startOfWeek(anchorDate);
    return { start, end: addDays(start, 7) };
  };

  const eventIntersectsDay = (event, dayStart, dayEnd) => {
    const eventStart = new Date(event.start);
    const eventEnd = new Date(event.end);
    return eventStart < dayEnd && eventEnd > dayStart;
  };

  const slotMinutes = (rules) => {
    const slots = [];
    const startMinutes = Number(rules.start_hour ?? 9) * 60;
    const endMinutes = Number(rules.end_hour ?? 22) * 60;
    for (let minute = startMinutes; minute < endMinutes; minute += SLOT_MINUTES) {
      slots.push(minute);
    }
    return slots;
  };

  const createElement = (tag, className, text) => {
    const element = document.createElement(tag);
    if (className) {
      element.className = className;
    }
    if (text !== undefined) {
      element.textContent = text;
    }
    return element;
  };

  const buildCalendar = (root) => {
    const payloadId = root.getAttribute("data-calendar-payload");
    const payloadNode = payloadId ? document.getElementById(payloadId) : null;
    if (!payloadNode) {
      return;
    }

    const payload = JSON.parse(payloadNode.textContent || "{}");
    const isPublic = root.getAttribute("data-calendar-public") === "1";
    const rules = payload.rules || {};
    const locale = payload.locale || document.documentElement.lang || "en";
    const strings = payload.messages || {};
    const modalElement = !isPublic ? document.getElementById("reservationQuickCreateModal") : null;
    const modal = modalElement && window.bootstrap ? new window.bootstrap.Modal(modalElement) : null;
    const form = modalElement ? modalElement.querySelector("#reservationQuickCreateForm") : null;
    const errorBox = modalElement ? modalElement.querySelector("[data-calendar-error]") : null;
    const submitButton = modalElement ? modalElement.querySelector("[data-calendar-submit]") : null;

    const state = {
      view: window.innerWidth < 768 ? "day" : "week",
      anchorDate: startOfDay(new Date()),
      events: [],
      loading: false,
      selection: null,
      requestToken: 0,
    };

    root.innerHTML = "";
    const shell = createElement("div", "calendar-widget");
    const toolbar = createElement("div", "calendar-toolbar");
    const leftControls = createElement("div", "calendar-toolbar-group");
    const prevButton = createElement("button", "btn btn-outline-light btn-sm", "<");
    const todayButton = createElement("button", "btn btn-outline-light btn-sm", strings.today || "Today");
    const nextButton = createElement("button", "btn btn-outline-light btn-sm", ">");
    const title = createElement("div", "calendar-toolbar-title");
    const rightControls = createElement("div", "calendar-toolbar-group");
    const dayButton = createElement("button", "btn btn-outline-light btn-sm", strings.day || "Day");
    const weekButton = createElement("button", "btn btn-outline-light btn-sm", strings.week || "Week");
    const listButton = createElement("button", "btn btn-outline-light btn-sm", strings.list || "List");
    const status = createElement("div", "calendar-status text-secondary small");
    const body = createElement("div", "calendar-render-surface");

    [prevButton, todayButton, nextButton].forEach((button) => leftControls.appendChild(button));
    [dayButton, weekButton, listButton].forEach((button) => rightControls.appendChild(button));
    toolbar.append(leftControls, title, rightControls);
    shell.append(toolbar, status, body);
    root.appendChild(shell);

    const setStatus = (message, type = "muted") => {
      status.className = `calendar-status small ${type === "danger" ? "text-danger" : "text-secondary"}`;
      status.textContent = message;
    };

    const showModalError = (message) => {
      if (!errorBox) {
        window.alert(message);
        return;
      }

      errorBox.textContent = message;
      errorBox.classList.remove("d-none");
    };

    const clearModalError = () => {
      if (!errorBox) {
        return;
      }

      errorBox.textContent = "";
      errorBox.classList.add("d-none");
    };

    const visibleDays = () => {
      const range = rangeForView(state.view, state.anchorDate);
      if (state.view === "day") {
        return [range.start];
      }

      return Array.from({ length: 7 }, (_, index) => addDays(range.start, index));
    };

    const loadEvents = async () => {
      const requestToken = ++state.requestToken;
      const range = rangeForView(state.view, state.anchorDate);
      const query = new URLSearchParams({
        start: toRangeParam(range.start),
        end: toRangeParam(range.end),
      });

      state.loading = true;
      setStatus(strings.loading || "Loading reservations...");

      try {
        const response = await fetch(`${payload.feedUrl}?${query.toString()}`, {
          headers: { Accept: "application/json" },
        });
        const result = await response.json();

        if (!response.ok) {
          throw new Error(strings.genericError || "Unable to load reservations.");
        }

        if (requestToken !== state.requestToken) {
          return;
        }

        state.events = Array.isArray(result.events) ? result.events : Array.isArray(result) ? result : [];
        setStatus(state.events.length ? "" : strings.noEvents || "No reservations in this range.");
        renderBody();
      } catch (error) {
        setStatus(error.message || strings.genericError || "Unable to load reservations.", "danger");
        state.events = [];
        renderBody();
      } finally {
        state.loading = false;
      }
    };

    const renderListView = () => {
      const range = rangeForView(state.view, state.anchorDate);
      const list = createElement("div", "calendar-list-view");
      const days = Array.from({ length: 7 }, (_, index) => addDays(range.start, index));

      days.forEach((day) => {
        const dayCard = createElement("section", "calendar-list-day");
        const heading = createElement("h3", "calendar-list-day-title", formatDayLabel(day, locale, false));
        const dayStart = startOfDay(day);
        const dayEnd = addDays(dayStart, 1);
        const dayEvents = state.events.filter((event) => eventIntersectsDay(event, dayStart, dayEnd));

        dayCard.appendChild(heading);
        if (!dayEvents.length) {
          dayCard.appendChild(createElement("div", "calendar-empty-state", strings.noEvents || "No reservations in this range."));
        } else {
          dayEvents.forEach((event) => {
            const eventStart = new Date(event.start);
            const eventEnd = new Date(event.end);
            const item = createElement("div", "calendar-list-event");
            const time = createElement("div", "calendar-list-time", `${formatTime(eventStart, locale)} - ${formatTime(eventEnd, locale)}`);
            const subject = createElement("div", "calendar-list-title", event.title || "");
            item.append(time, subject);
            dayCard.appendChild(item);
          });
        }

        list.appendChild(dayCard);
      });

      body.replaceChildren(list);
    };

    const selectionBlock = (dayColumn, startSlot, endSlot) => {
      dayColumn.replaceChildren();
      const overlay = createElement("div", "calendar-selection");
      overlay.style.top = `${Math.min(startSlot, endSlot) * SLOT_HEIGHT}px`;
      overlay.style.height = `${(Math.abs(endSlot - startSlot) + 1) * SLOT_HEIGHT}px`;
      dayColumn.appendChild(overlay);
    };

    const attachSelectionHandlers = (gridBody, days, selectionLayers) => {
      if (isPublic || !form) {
        return;
      }

      let activeSelection = null;

      const paintSelection = () => {
        selectionLayers.forEach((layer, index) => {
          layer.replaceChildren();
          if (state.selection && state.selection.dayIndex === index) {
            selectionBlock(layer, state.selection.startSlot, state.selection.endSlot);
          }
        });
      };

      const clearSelection = () => {
        activeSelection = null;
        state.selection = null;
        paintSelection();
      };

      gridBody.querySelectorAll("[data-slot-index]").forEach((cell) => {
        cell.addEventListener("mousedown", (event) => {
          if (event.target.closest(".calendar-event")) {
            return;
          }

          const dayIndex = Number(cell.dataset.dayIndex);
          const slotIndex = Number(cell.dataset.slotIndex);
          activeSelection = { dayIndex, startSlot: slotIndex, endSlot: slotIndex };
          state.selection = { ...activeSelection };
          paintSelection();
        });

        cell.addEventListener("mouseenter", () => {
          if (!activeSelection) {
            return;
          }

          const dayIndex = Number(cell.dataset.dayIndex);
          const slotIndex = Number(cell.dataset.slotIndex);
          if (dayIndex !== activeSelection.dayIndex) {
            return;
          }

          activeSelection.endSlot = slotIndex;
          state.selection = { ...activeSelection };
          paintSelection();
        });
      });

      document.addEventListener("mouseup", () => {
        if (!activeSelection) {
          return;
        }

        const day = days[activeSelection.dayIndex];
        const startSlot = Math.min(activeSelection.startSlot, activeSelection.endSlot);
        const endSlot = Math.max(activeSelection.startSlot, activeSelection.endSlot) + 1;
        const startMinutes = slotMinutes(rules)[startSlot];
        const endMinutes = slotMinutes(rules)[endSlot] ?? Number(rules.end_hour ?? 22) * 60;
        const start = startOfDay(day);
        start.setMinutes(startMinutes);
        const end = startOfDay(day);
        end.setMinutes(endMinutes);

        activeSelection = null;
        if (!modal || !form) {
          clearSelection();
          return;
        }

        clearModalError();
        form.elements.start_datetime.value = toIsoRangeValue(start);
        form.elements.end_datetime.value = toIsoRangeValue(end);
        modal.show();
        clearSelection();
      }, { once: true });
    };

    const renderGridView = () => {
      const days = visibleDays();
      const slots = slotMinutes(rules);
      const shell = createElement("div", "calendar-grid-shell");
      const header = createElement("div", `calendar-grid-header calendar-grid-header-${state.view}`);
      const bodyGrid = createElement("div", `calendar-grid-body calendar-grid-body-${state.view}`);
      const timeColumn = createElement("div", "calendar-time-column");
      const columnsWrap = createElement("div", "calendar-columns");
      const selectionLayers = [];

      header.appendChild(createElement("div", "calendar-time-header", ""));
      days.forEach((day) => {
        header.appendChild(createElement("div", "calendar-day-header", formatDayLabel(day, locale, state.view !== "day")));
      });

      slots.forEach((slotMinute) => {
        const labelDate = startOfDay(days[0]);
        labelDate.setMinutes(slotMinute);
        timeColumn.appendChild(createElement("div", "calendar-time-label", formatTime(labelDate, locale)));
      });
      bodyGrid.appendChild(timeColumn);

      days.forEach((day, dayIndex) => {
        const column = createElement("div", "calendar-day-column");
        column.style.height = `${slots.length * SLOT_HEIGHT}px`;
        const slotsLayer = createElement("div", "calendar-slots-layer");
        const selectionLayer = createElement("div", "calendar-selection-layer");
        const eventsLayer = createElement("div", "calendar-events-layer");
        selectionLayers.push(selectionLayer);

        slots.forEach((_, slotIndex) => {
          const slot = createElement("div", "calendar-slot");
          slot.dataset.dayIndex = String(dayIndex);
          slot.dataset.slotIndex = String(slotIndex);
          slot.style.height = `${SLOT_HEIGHT}px`;
          slotsLayer.appendChild(slot);
        });

        if (state.selection && state.selection.dayIndex === dayIndex) {
          selectionBlock(selectionLayer, state.selection.startSlot, state.selection.endSlot);
        }

        const dayStart = startOfDay(day);
        const dayEnd = addDays(dayStart, 1);
        const visibleStartMinutes = Number(rules.start_hour ?? 9) * 60;
        const visibleEndMinutes = Number(rules.end_hour ?? 22) * 60;

        state.events
          .filter((event) => eventIntersectsDay(event, dayStart, dayEnd))
          .forEach((event) => {
            const eventStart = new Date(event.start);
            const eventEnd = new Date(event.end);
            const clippedStart = Math.max((eventStart.getHours() * 60) + eventStart.getMinutes(), visibleStartMinutes);
            const clippedEnd = Math.min((eventEnd.getHours() * 60) + eventEnd.getMinutes(), visibleEndMinutes);
            const top = ((clippedStart - visibleStartMinutes) / SLOT_MINUTES) * SLOT_HEIGHT;
            const height = Math.max(((clippedEnd - clippedStart) / SLOT_MINUTES) * SLOT_HEIGHT, SLOT_HEIGHT - 8);
            const eventNode = createElement("button", `calendar-event ${(event.classNames || []).join(" ")}`);
            eventNode.type = "button";
            eventNode.style.top = `${top}px`;
            eventNode.style.height = `${height}px`;
            const timeLabel = createElement("span", "calendar-event-time", `${formatTime(eventStart, locale)} - ${formatTime(eventEnd, locale)}`);
            const titleLabel = createElement("span", "calendar-event-title", event.title || "");
            eventNode.append(timeLabel, titleLabel);

            if (!isPublic && event.extendedProps?.canCancel) {
              eventNode.addEventListener("click", async () => {
                if (!window.confirm(strings.cancelConfirm || "Cancel this reservation?")) {
                  return;
                }

                try {
                  const url = (payload.cancelUrlTemplate || "").replace("__ID__", event.extendedProps.reservationId);
                  const response = await fetch(url, {
                    method: "POST",
                    headers: {
                      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
                    },
                    body: new URLSearchParams({ _csrf: payload.csrfToken }),
                  });
                  const result = await response.json();

                  if (!response.ok || !result.ok) {
                    throw new Error(strings.genericError || "Unable to cancel reservation.");
                  }

                  await loadEvents();
                } catch (error) {
                  setStatus(error.message || strings.genericError || "Unable to cancel reservation.", "danger");
                }
              });
            } else {
              eventNode.disabled = true;
            }

            eventsLayer.appendChild(eventNode);
          });

        column.append(slotsLayer, selectionLayer, eventsLayer);
        columnsWrap.appendChild(column);
      });

      bodyGrid.appendChild(columnsWrap);
      shell.append(header, bodyGrid);
      body.replaceChildren(shell);
      attachSelectionHandlers(bodyGrid, days, selectionLayers);
    };

    const renderBody = () => {
      title.textContent = formatTitle(state.view, state.anchorDate, locale);
      [dayButton, weekButton, listButton].forEach((button) => button.classList.remove("active"));
      ({ day: dayButton, week: weekButton, list: listButton })[state.view].classList.add("active");

      if (state.view === "list") {
        renderListView();
      } else {
        renderGridView();
      }
    };

    const moveRange = (direction) => {
      if (state.view === "day") {
        state.anchorDate = addDays(state.anchorDate, direction);
      } else {
        state.anchorDate = addDays(state.anchorDate, direction * 7);
      }
      loadEvents();
    };

    prevButton.addEventListener("click", () => moveRange(-1));
    nextButton.addEventListener("click", () => moveRange(1));
    todayButton.addEventListener("click", () => {
      state.anchorDate = startOfDay(new Date());
      loadEvents();
    });
    dayButton.addEventListener("click", () => {
      state.view = "day";
      loadEvents();
    });
    weekButton.addEventListener("click", () => {
      state.view = "week";
      loadEvents();
    });
    listButton.addEventListener("click", () => {
      state.view = "list";
      loadEvents();
    });

    if (submitButton && form) {
      submitButton.addEventListener("click", async () => {
        clearModalError();

        try {
          const response = await fetch(payload.createUrl, {
            method: "POST",
            body: new URLSearchParams(new FormData(form)),
          });
          const result = await response.json();

          if (!response.ok || !result.ok) {
            const firstError = Object.values(result.errors || {})[0] || strings.genericError || "Unable to save reservation.";
            throw new Error(String(firstError));
          }

          modal?.hide();
          await loadEvents();
        } catch (error) {
          showModalError(error.message || strings.genericError || "Unable to save reservation.");
        }
      });
    }

    renderBody();
    loadEvents();
  };

  calendarRoots.forEach(buildCalendar);
})();
