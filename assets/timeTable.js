import TimeTableApiHandler from "./plugin-timeTableApiHandler.js?v=%%VERSION%%";

jQuery(document).ready(function ($) {
  class TimeTable {
    constructor() {
      this.currentViewWeek = $("input[name='timetable-current-week']").val();
      this.currentViewFirstDay = $(
        "input[name='timetable-current-week-first-day']",
      ).val();
      // General selectors
      this.prevWeekButton = $("button.timetable-week-prev");
      this.nextWeekButton = $("button.timetable-week-next");
      this.todayButton = $("button.timetable-to-today");
      this.searchInput = $("input.timetable-search");
      this.newEntryButton = $("button.timetable-new-entry");
      this.editEntryCell = $("td.timetable-edit-entry");
      this.syncButton = $("button.timetable-sync-tickets");
      this.refreshPanel = $(".timetable-sync-panel");

      // Modal selectors
      this.timeEditModal = $("#edit-time-log-modal");
      this.timeEditForm = this.timeEditModal.find(".edit-time-log-form");
      this.timeEditSyncModal = $("#edit-time-sync-modal");
      this.modalInputTimesheetId = this.timeEditModal.find(
        'input[name="timesheet-id"]',
      );
      this.modalInputTicketId = this.timeEditModal.find(
        'input[name="timesheet-ticket-id"]',
      );
      this.modalInputTicketName = this.timeEditModal.find(
        "input.timetable-ticket-input",
      );
      this.modalInputHours = this.timeEditModal.find(
        'input[name="timesheet-hours"]',
      );
      this.modalInputHoursLeft = this.timeEditModal.find(
        'input[name="timesheet-hours-left"]',
      );
      this.modalTextareaDescription = this.timeEditModal.find(
        'textarea[name="timesheet-description"]',
      );
      this.modalInputDate = this.timeEditModal.find(
        'input[name="timesheet-date"]',
      );
      this.modalTicketIdInput = this.timeEditModal.find(
        'input[name="timesheet-ticket-id"]',
      );

      this.modalCloseButton = this.timeEditModal.find(".timetable-close-modal");
      this.modalDeleteButton = this.timeEditModal.find(
        ".timetable-modal-delete",
      );
      this.modalCancelButton = this.timeEditModal.find(
        ".timetable-modal-cancel",
      );
      this.modalSubmitButton = this.timeEditModal.find(
        ".timetable-modal-submit",
      );
      this.modalInputDate = this.timeEditModal.find(
        'input[name="timesheet-date"]',
      );
      this.modalTicketSearch = this.timeEditModal.find(
        ".timetable-ticket-search",
      );
      this.modalTicketInput = this.timeEditModal.find(
        ".timetable-ticket-input",
      );
      this.modalTicketResults = this.timeEditModal.find(
        ".timetable-ticket-results",
      );

      // Register event handlers
      this.registerEventHandlers();

      this.isFetching = true;
      TimeTableApiHandler.fetchTicketData().then((availableTags) => {
        this.isFetching = false;
        this.populateLastUpdated();
      });
    }

    /**
     * Registers event handlers for the timetable module.
     *
     * @function registerEventHandlers
     *
     * @returns {void}
     */
    registerEventHandlers() {
      // Week back
      this.prevWeekButton.click(() => this.changeWeek(-1));
      // Week forward
      this.nextWeekButton.click(() => this.changeWeek(1));
      // Filter tickets
      this.searchInput.change((e) =>
        this.updateLocation("searchTerm", e.target.value),
      );
      // New entry
      this.newEntryButton.click(() => {
        if (this.isFetching) {
          let intervalId = setInterval(() => {
            if (!this.isFetching) {
              clearInterval(intervalId);
              this.newTimeEntry();
            }
          }, 500);
        } else {
          this.newTimeEntry();
        }
      });
      // Edit entry
      this.editEntryCell.click((e) => {
        const id = e.target.dataset.id ?? null;
        const ticketId = e.target.dataset.ticketid ?? null;
        const hours = e.target.dataset.hours ?? null;
        const hoursLeft = e.target.dataset.hoursleft ?? null;
        const description = e.target.dataset.description ?? null;
        const date = e.target.dataset.date ?? null;

        if (this.isFetching) {
          let intervalId = setInterval(() => {
            if (!this.isFetching) {
              clearInterval(intervalId);
              this.editTimeEntry(
                id,
                ticketId,
                hours,
                hoursLeft,
                description,
                date,
              );
            }
          }, 500);
        } else {
          this.editTimeEntry(id, ticketId, hours, hoursLeft, description, date);
        }
      });

      // Close modal
      this.modalCloseButton.click(() => this.closeEditTimeLogModal());
      this.modalCancelButton.click(() => this.closeEditTimeLogModal());
      $(document).keydown((e) => {
        // Escape key
        if (e.keyCode === 27) {
          this.closeEditTimeLogModal();
        }
      });

      this.boundClickOutsideModalHandler = (e) =>
        this.clickOutsideModalHandler(e);

      $("#modal-form").on("submit", (e) => {
        this.modalSubmitButton.attr("disabled", "disabled");
      });

      // Delete timeentry
      this.modalDeleteButton.click(() => this.deleteTimeEntry());

      // Return to current week.
      this.todayButton.click(() => {
        const params = new URLSearchParams(document.location.search);
        const offset = params.get("offset");
        if (offset) {
          const oppositeOffset = offset * -1;
          this.changeWeek(oppositeOffset);
        }
      });
    }

    /**
     * Opens the Edit Time Log modal for a new entry.
     *
     * @return {void}
     */
    newTimeEntry() {
      this.populateLastUpdated();
      this.openEditTimeLogModal();

      // Set date today
      let currentWeekNumber = new Date().getWeek();
      let viewWeekNumber = parseInt(this.currentViewWeek, 10);

      this.modalInputDate[0].valueAsDate =
        currentWeekNumber === viewWeekNumber
          ? new Date()
          : new Date(this.currentViewFirstDay);

      // Init ticket search
      this.modalInputTicketName.removeAttr("disabled");
      this.modalTicketInput.focus().keyup((e) => this.filterFunction(e));
      this.modalDeleteButton.hide();

      // Ticket result click event
      let context = this;
      this.timeEditForm.on("click", function (e) {
        if ($(e.target).is(".timetable-ticket-result-item")) {
          context.selectTicket(e.target);
        }
      });
    }

    /**
     * Opens the Edit Time Log modal for editing an entry.
     *
     * @param {HTMLElement} target - The HTML element representing the ticket being selected.
     * @returns {void}
     */
    selectTicket(target) {
      const {
        innerText: taskName,
        dataset: { value: taskId, hoursleft: hoursLeft },
      } = target;

      // Set values from selected ticket
      this.modalTicketInput.val(taskName);
      this.modalTicketIdInput.val(taskId);
      this.modalInputHoursLeft.val(hoursLeft).attr("data-value", hoursLeft);

      // Reset search
      this.modalTicketResults.empty();
    }

    /**
     * Filters the timetable ticket results based on the input value.
     *
     * @param event
     *
     * @returns {void}
     */
    filterFunction(event) {
      const { value } = event.target;
      const dropDownElement = $(".timetable-ticket-results");

      // Reset search
      if (value.length <= 1) {
        dropDownElement.empty();
      }

      // Perform search
      if (value.length > 1) {
        const { data: tickets } = TimeTableApiHandler.readFromCache("tickets");
        this.ticketSearch(tickets, value);
      }
    }

    /**
     * Search for tickets in an object using a query.
     *
     * @param {Object} obj - The object to search in.
     * @param {string} query - The query to search for.
     * @return {Object} results
     */
    ticketSearch(obj, query) {
      const { id, text, children } = obj;

      const lowerCaseQuery = query.toLowerCase();

      let results = [];

      // Checks if `obj`'s `text` or `id` contains `lowerCaseQuery` and not already added to timetable.

      if (
        "text" in obj &&
        typeof text === "string" &&
        (text.toLowerCase().includes(lowerCaseQuery) ||
          (id && id.toString().toLowerCase().includes(lowerCaseQuery)))
      ) {
        const index = text.toLowerCase().indexOf(lowerCaseQuery);
        const relevance = index === -1 ? 0 : text.length - index;
        results.push({ ...obj, relevance });
      }

      // If `children` is an array, searches each `child` for `query` and merges results.
      if (Array.isArray(children)) {
        children.forEach((child) => {
          const childResults = this.ticketSearch(child, query);
          results = results.concat(childResults);
        });
      }

      results.sort((a, b) => b.relevance - a.relevance);

      this.modalTicketResults.empty();
      if (results.length === 0) {
        this.modalTicketResults.append(
          `<div class="timetable-ticket-result-item-no-results" data-value="">No results</div>`,
        );
      }
      results.forEach(({ id, text, projectName, hoursLeft }) => {
        this.modalTicketResults.append(
          `<div class="timetable-ticket-result-item" data-project="${projectName}" data-hoursleft="${hoursLeft}" data-value="${id}"><span>${text}</span></div>`,
        );
      });
      return results;
    }

    /**
     * Updates a time entry.
     *
     * @param {number} id Time entry ID.
     * @param {string} ticketId Ticket ID.
     * @param {number} hours Hours spent.
     * @param {string} description Work done.
     * @param {string} date Work date.
     * @param {number} offset
     * @return {boolean}
     */
    editTimeEntry(id, ticketId, hours, hoursLeft, description, date, offset) {
      // Find ticket in cache
      const ticket = TimeTableApiHandler.getTicketDataFromCache(
        parseInt(ticketId),
      );

      if (!ticket) {
        this.openEditTimeSyncModal();
        TimeTableApiHandler.fetchTicketDatum(ticketId).then((availableTags) => {
          this.closeEditTimeSyncModal();
          this.editTimeEntry(
            id,
            ticketId,
            hours,
            hoursLeft,
            description,
            date,
            offset,
          );
        });
        return false;
      }

      this.populateLastUpdated();
      this.openEditTimeLogModal();

      if (id) {
        this.modalDeleteButton.show();
      } else {
        this.modalDeleteButton.hide();
      }

      this.modalInputTimesheetId.val(id);
      this.modalInputTicketId.val(ticket.id);
      this.modalInputTicketName.val(ticket.text).attr("disabled", "disabled");
      this.modalInputHours.val(hours);
      this.modalInputHoursLeft.val(hoursLeft).attr("data-value", hoursLeft);
      this.modalTextareaDescription.val(description);
      this.modalInputDate.val(date);

      this.modalInputHours.focus();
    }

    /**
     * Changes the week offset based on the provided value.
     *
     * @param {number} offset - The value to offset the week.
     * @return {void}
     */
    changeWeek(offset) {
      let params = new URLSearchParams(document.location.search);
      if (params.get("offset")) {
        offset += parseInt(params.get("offset"));
      }
      offset === ""
        ? this.updateLocation("offset", "")
        : this.updateLocation("offset", offset);
    }

    /**
     * Update the URL location with the given key-value pair.
     *
     * @param {string} key - The key corresponding to the value to be updated or deleted.
     * @param {string} value - The new value to be added or updated. Use an empty string to delete the key-value pair.
     * @return {void}
     */
    updateLocation(key, value) {
      let params = new URLSearchParams(document.location.search);
      if (params.has(key)) {
        params.delete(key);
      }
      if (value !== "") {
        params.append(key, value);
      }
      window.location = `?${params.toString()}`;
    }

    /**
     * This method handles the button press event for refreshing data.
     *
     * @return {boolean}
     */
    refreshButtonPress() {
      const loadingHTML =
        '<i class="fa-solid fa-arrows-rotate fa-spin"></i>Syncing data';
      if (this.isFetching) {
        return false;
      }
      $(this.syncButton).children("span").html(loadingHTML);
      this.refreshTicketSearch();
    }

    /**
     * Refreshes the ticket search by removing tickets from the cache and setting new ticket data.
     *
     * @return {void}
     */
    refreshTicketSearch() {
      TimeTableApiHandler.removeFromCache("tickets");
      this.setTicketData();
    }

    /**
     * Sets the ticket data.
     *
     * @return {void}
     */
    setTicketData() {
      if (this.isFetching) {
        setTimeout(() => {
          // If already fetching, recall for cached result.
          this.setTicketData();
        }, 500);
      } else {
        this.isFetching = true;
        TimeTableApiHandler.fetchTicketData().then((availableTags) => {
          this.isFetching = false;
          this.populateLastUpdated();
        });
      }
    }

    /**
     * Populates the last updated information in the panel.
     *
     * @returns {void}
     */
    populateLastUpdated() {
      let ticketsLastUpdated =
        TimeTableApiHandler.readFromCache("tickets").expiration;

      let ticketsLastUpdatedElement =
        "<span>Tickets: " +
        Math.round((Date.now() - ticketsLastUpdated) / 60000) +
        " min ago.</span>";

      $(this.syncButton)
        .children("span")
        .html(
          '<span><i className="fa-solid fa-arrows-rotate"></i>Sync data</span>',
        );
      $(this.refreshPanel)
        .children("div")
        .last()
        .html(ticketsLastUpdatedElement);
    }

    deleteTimeEntry() {
      const timesheetId = this.modalInputTimesheetId.val();
      $(this.modalDeleteButton)
        .html('<i class="fa-solid fa-arrows-rotate"></i>')
        .addClass("deleting");
      fetch(window.location.href, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "delete",
          timesheetId: timesheetId,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            window.location.reload();
          } else {
            alert("An error has occurred");
          }
        });
      console.log(timesheetId);
    }

    /**
     * Closes the edit time log modal.
     *
     * @returns {void}
     */
    closeEditTimeLogModal() {
      $(this.timeEditModal).hide().find("input, textarea").val("");
      $(this.modalTicketResults).empty();
      $(this.modalInputHoursLeft).removeAttr("data-value");
      $(document).off("mousedown", this.boundClickOutsideModalHandler);
    }

    /**
     * Opens the edit time log modal.
     *
     * @returns {void}
     */
    openEditTimeLogModal() {
      $(this.timeEditModal).show().css("display", "flex");
      $(document).on("mousedown", this.boundClickOutsideModalHandler);
    }

    openEditTimeSyncModal() {
      $(this.timeEditSyncModal).show().css("display", "flex");
    }

    closeEditTimeSyncModal() {
      $(this.timeEditSyncModal).hide();
    }

    /**
     * Handles the click outside the modal.
     *
     * @param {Event} event
     *
     * @return {void}
     */
    clickOutsideModalHandler(event) {
      if ($(event.target).find(this.timeEditForm).length > 0) {
        this.closeEditTimeLogModal();
      }
      if ($(event.target).hasClass("timetable-sync-tickets")) {
        this.refreshButtonPress();
      }
    }
  }

  let timeTable = new TimeTable();
});

/**
 * Retrieves the current date's week number in the year.
 * 86400000 is the number of milliseconds in a day used to convert time between dates into days.
 *
 * @returns {Number} — Week number of the year for this date.
 */
Date.prototype.getWeek = function () {
  const firstDayOfYear = new Date(this.getFullYear(), 0, 1);
  const pastDaysOfYear = (this - firstDayOfYear) / 86400000;
  return Math.ceil((pastDaysOfYear + firstDayOfYear.getDay() + 1) / 7);
};
