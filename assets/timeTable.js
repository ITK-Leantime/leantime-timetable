import TomSelect from "tom-select";
import flatpickr from "flatpickr";
import { Danish } from "flatpickr/dist/l10n/da.js";
import TimeTableApiHandler from "./timeTableApiHandler";
import "tom-select/dist/css/tom-select.default.css";
import "flatpickr/dist/flatpickr.min.css";

jQuery(document).ready(function ($) {
  const pluginSettings = {
    userId: $("select[name='manageAsUserId'] > option:selected").val(),
  };

  const allStateLabels = timetableSettings.settings.allStateLabels;
  const allTags = timetableSettings.settings.allTags || [];

  class TimeTable {
    constructor() {
      this.tomselect = null;
      // General selectors
      this.refreshPanel = $(".timetable-sync-panel");
      this.timeTableScrollContainer = $(".timetable-scroll-container");
      this.entryCopyButton = $("div.entry-copy-button");

      // Modal selectors
      this.timeEditModal = $("#edit-time-log-modal");
      this.entryCopyModal = $("#entry-copy-modal");
      this.ticketContextMenuModal = $("#ticket-context-menu-modal");
      this.ticketContextDateToFinish =
        this.ticketContextMenuModal.find(".date-to-finish");
      this.ticketContextStatus =
        this.ticketContextMenuModal.find(".ticket-status");
      this.ticketContextForm = this.ticketContextMenuModal.find(
        ".ticket-context-menu-form",
      );
      this.ticketContextButtonCancel = this.ticketContextMenuModal.find(
        ".ticket-context-menu-cancel",
      );
      this.ticketContextButtonApply = this.ticketContextMenuModal.find(
        ".ticket-context-menu-apply",
      );
      this.entryCopyForm = this.entryCopyModal.find(".entry-copy-form");
      this.entryCopyButtonClose = this.entryCopyModal.find(
        ".entry-copy-modal-cancel",
      );
      this.entryCopyButtonApply = this.entryCopyModal.find(
        ".entry-copy-modal-apply",
      );
      this.entryCopyCheckboxOverwrite = this.entryCopyModal.find(
        "#entry-copy-overwrite",
      );
      this.entryCopyCheckboxWeekend = this.entryCopyModal.find(
        "#entry-copy-weekend",
      );
      this.timeEditForm = this.timeEditModal.find(".edit-time-log-form");
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
      this.modalInputDateMove = this.timeEditModal.find(
        'input[name="timesheet-date-move"]',
      );
      this.modalInputDateMoveNotifier = this.timeEditModal.find(
        ".timesheet-date-move-notifier",
      );
      this.modalTicketIdInput = this.timeEditModal.find(
        'input[name="timesheet-ticket-id"]',
      );
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
      this.modalTicketInput = this.timeEditModal.find(
        ".timetable-ticket-input",
      );
      this.contextMenuTicketId = this.ticketContextMenuModal.find(
        ".ticket-context-menu-ticketId",
      );

      // Sort menu elements
      this.sortMenuModal = $("#sort-menu-modal");
      this.sortMenuTrigger = $(".timetable-sort-menu");
      this.sortOptions = $(".sort-option");
      this.sortDirectionBtns = $(".sort-direction-btn");
      this.sortMenuSaveBtn = $(".sort-menu-save");
      this.sortMenuCloseBtn = $(".sort-menu-close");

      // Get sort order from data attribute to avoid jQuery conflicts
      // Use an object to avoid jQuery trying to attach properties to strings
      const sortDataElement = document.getElementById("timetable-sort-data");
      const sortOrderValue = sortDataElement
        ? sortDataElement.getAttribute("data-sort-order")
        : "";

      this.sortState = {
        savedOrder:
          sortOrderValue && sortOrderValue !== ""
            ? String(sortOrderValue)
            : null,
        field: null,
        direction: "asc",
      };

      // Parse saved sort to extract field and direction
      if (this.sortState.savedOrder) {
        const parts = this.sortState.savedOrder.split("-");
        if (
          parts.length > 2 &&
          (parts[parts.length - 1] === "asc" ||
            parts[parts.length - 1] === "desc")
        ) {
          this.sortState.direction = parts.pop();
          this.sortState.field = parts.join("-");
        } else {
          this.sortState.field = this.sortState.savedOrder;
        }
      }

      // Weekend visibility elements
      this.weekendVisibilityToggle = $("#show-weekends-toggle");

      // Get initial weekend visibility state from checkbox
      this.showWeekends = this.weekendVisibilityToggle.is(":checked");

      // Apply initial weekend visibility
      this.applyWeekendVisibility();

      flatpickr("#dateRange", {
        mode: "range",
        dateFormat: "d-m-Y",
        allowInput: false,
        readonly: false,
        weekNumbers: true,
        locale: Danish,
        onChange: function (selectedDates, dateStr, instance) {
          if (selectedDates && selectedDates.length === 2) {
            instance.element.form.submit();
          }
        },
      });

      this.ticketContextDatePicker = flatpickr(this.ticketContextDateToFinish, {
        dateFormat: "d-m-Y",
        weekNumbers: true,
        locale: Danish,
        allowInput: false,
        readonly: false,
      });

      this.ticketContextStatus = new TomSelect(this.ticketContextStatus, {
        closeAfterSelect: true,
        controlInput: null,
        onChange: () => {
          this.ticketContextStatus.blur();
        },
      });

      this.ticketContextTags = this.ticketContextMenuModal.find(".ticket-tags");

      this.ticketContextTags = new TomSelect(this.ticketContextTags, {
        plugins: ["remove_button"],
        maxItems: 3,
        create: true,
        persist: false,
        openOnFocus: false,
        loadThrottle: 300,
        load: function (query, callback) {
          if (!query.length) {
            this.close();
            return callback();
          }

          // Filter tags that match the search query
          const filtered = allTags
            .filter((tag) => tag.toLowerCase().includes(query.toLowerCase()))
            .slice(0, 50) // Limit to 50 results
            .map((tag) => ({ value: tag, text: tag }));

          callback(filtered);
        },
        onItemAdd: function () {
          this.setTextboxValue("");
          this.close();
        },
        onType: function (str) {
          if (!str || str.length === 0) {
            this.close();
          }
        },
      });

      this.timelogDateChanger = flatpickr(this.modalInputDateMove, {
        dateFormat: "d-m-Y",
        weekNumbers: true,
        locale: Danish,
        onReady: (selectedDates, dateStr, instance) => {
          instance.calendarContainer.classList.add("flatpickr-move-timelog");
        },
        onChange: (selectedDates, dateStr, instance) => {
          const $wrapper = $(instance.element).closest(
            ".timesheet-date-wrapper",
          );
          $wrapper.removeClass("open");
          if (selectedDates && selectedDates.length > 0) {
            instance.element.value = dateStr;
          }

          const originalDate = flatpickr.formatDate(
            new Date($wrapper.attr("data-original")),
            "d-m-Y",
          );
          // Add/remove a class if the current value differs from the original one
          if (dateStr !== originalDate) {
            $wrapper.addClass("modified");
            this.modalInputDateMoveNotifier.removeClass("hidden");
          } else {
            $wrapper.removeClass("modified");
            this.modalInputDateMoveNotifier.addClass("hidden");
          }
        },
      });
      $(".timesheet-date-wrapper")
        .off("click")
        .on("click", (event) => {
          const $wrapper = $(event.currentTarget);

          if ($wrapper.hasClass("open")) {
            this.timelogDateChanger.close();
            $wrapper.removeClass("open");
          } else {
            this.timelogDateChanger.open();
            $wrapper.addClass("open");
          }
        });

      // Register event handlers
      this.registerEventHandlers();

      TimeTableApiHandler.fetchTicketData().then((data) => {
        this.removeLoadingClasses();
        let {
          value: { children: projects },
        } = data[0];
        let {
          value: { children: tickets },
        } = data[1];

        this.projects = projects;
        this.tickets = tickets;

        this.initTicketSearch(projects, tickets);
      });
    }

    removeLoadingClasses() {
      this.timeEditModal.removeClass("modal-syncing-loader");
      this.entryCopyModal.removeClass("modal-syncing-loader");
    }

    /**
     * Registers event handlers for the timetable module.
     *
     * @function registerEventHandlers
     *
     * @returns {void}
     */
    registerEventHandlers() {
      document.addEventListener(
        "mousedown",
        function (event) {
          if (
            $(this.timeEditModal).is(":visible") &&
            !this.timeEditModal[0].contains(event.target) &&
            !event.target.closest(".flatpickr-calendar")
          ) {
            this.closeEditTimeLogModal();
          }

          if (
            $(this.entryCopyModal).is(":visible") &&
            !this.entryCopyModal[0].contains(event.target)
          ) {
            this.closeEntryCopyModal();
          }

          if (
            $(this.ticketContextMenuModal).is(":visible") &&
            !this.ticketContextMenuModal[0].contains(event.target) &&
            !event.target.closest(".flatpickr-calendar")
          ) {
            this.closeTicketContextMenuModal();
          }
        }.bind(this),
      );

      // Edit entry
      $(document).on(
        "click",
        "td.timetable-edit-entry",
        function ({ target }) {
          const id = target.dataset.id ?? null;
          const ticketId = target.dataset.ticketid ?? null;
          const hours = target.dataset.hours ?? null;
          const headline = target.dataset.headline ?? null;
          const hoursLeft = parseFloat(target.dataset.hoursleft) ?? null;
          const description = target.dataset.description ?? null;
          const date = target.dataset.date ?? null;

          $(this.timeEditModal).toggleClass("new", !id);

          this.editTimeEntry(
            headline,
            id,
            ticketId,
            hours,
            hoursLeft,
            description,
            date,
          );

          const rect = target.getBoundingClientRect();

          this.timeEditModal
            .css({
              left: `${rect.left + window.scrollX - 215}px`, // Adjust horizontal position
              top: `${rect.top + window.scrollY + rect.height - 50}px`, // Adjust vertical position
            })
            .addClass("shown")
            .find('input[name="timesheet-hours"]')
            .focus();
        }.bind(this),
      );

      $(document).on("click", "div.ticket-context-menu", (event) => {
        const target = event.target;
        const rect = target.getBoundingClientRect();

        // Find the ticket-context-menu div (in case a child span was clicked)
        const $contextMenuDiv = $(target).closest("div.ticket-context-menu");

        // Get the parent td which contains all the data attributes
        const $ticketTd = $contextMenuDiv.parent();

        // Get the tr which contains the ticketId
        const $ticketTr = $ticketTd.parent();

        this.ticketContextMenuModal
          .css({
            left: `${rect.left + window.scrollX - 215}px`,
            top: `${rect.top + window.scrollY + rect.height - 50}px`,
          })
          .addClass("shown");

        const projectId = $contextMenuDiv.data("projectid");
        const stateLabels = allStateLabels[projectId];
        const ticketStatus = $ticketTd.data("status");
        const ticketId = $ticketTr.data("ticketid");
        const ticketDateToFinish = $ticketTd.data("datetofinish");
        const ticketTags = $ticketTd.data("tags") || "";

        this.contextMenuTicketId.val(ticketId);

        // Set date
        const dateToFinish =
          ticketDateToFinish === "0000-00-00 00:00:00"
            ? null
            : ticketDateToFinish?.split(" ")[0];
        const parsedDate = dateToFinish
          ? new Date(dateToFinish + "T00:00:00")
          : null;
        this.ticketContextDatePicker.setDate(parsedDate);

        // Add project status options to ticket context menu
        if (stateLabels) {
          const statusTranslations =
            timetableSettings.settings.statusTranslations || {};
          const statusOptions = Object.entries(stateLabels).map(
            ([value, { name, class: className }]) => ({
              value,
              text: statusTranslations[name] || name,
              className,
              selected: String(value) === String(ticketStatus),
            }),
          );

          this.ticketContextStatus.clearOptions();
          this.ticketContextStatus.addOptions(statusOptions);

          if (stateLabels[ticketStatus]) {
            this.ticketContextStatus.setValue(ticketStatus);
          }
        }

        // Handle tags
        this.ticketContextTags.clear();

        if (ticketTags) {
          const tagsArray = ticketTags
            .split(",")
            .map((tag) => tag.trim())
            .filter((tag) => tag);
          // Add any tags that aren't already in the options
          tagsArray.forEach((tag) => {
            if (!this.ticketContextTags.options[tag]) {
              this.ticketContextTags.addOption({ value: tag, text: tag });
            }
          });
          this.ticketContextTags.setValue(tagsArray);
        }
      });

      // Close modal
      this.modalCancelButton.click(() => this.closeEditTimeLogModal());
      $(document).keydown((e) => {
        // Escape key
        if (e.keyCode === 27) {
          this.closeEditTimeLogModal();
        }
      });

      this.boundClickOutsideModalHandler = (e) =>
        this.clickOutsideModalHandler(e);

      $(this.timeEditForm).on("submit", () => {
        this.modalSubmitButton.html(
          '<i class="fa-solid fa-arrows-rotate fa-spin"></i>',
        );
        this.modalSubmitButton.attr("disabled", "disabled");
      });

      // Delete timeentry
      this.modalDeleteButton.click(() => this.deleteTimeEntry());

      const weekNumbers = document.querySelectorAll("th.new-week");

      const observer = new IntersectionObserver(
        (entries) => {
          entries.forEach(({ target, isIntersecting }) => {
            if (isIntersecting) {
              target.classList.remove("sticky");
            } else {
              target.classList.add("sticky");
            }
          });
        },
        {
          root: document.querySelector(
            ".timetable-scroll-container.overflowing",
          ),
          threshold: 1,
          rootMargin: "0% 0% 0% -595px",
        },
      );

      weekNumbers.forEach((weekNumber) => observer.observe(weekNumber));

      this.checkOverflow(this.timeTableScrollContainer);

      this.entryCopyButton.click((e) => {
        e.stopPropagation();

        const eventTarget = e.target;

        const targetCount = this.handleHighlighting(eventTarget);

        const rect = eventTarget.getBoundingClientRect();

        this.entryCopyModal
          .css({
            left: `${rect.left + window.scrollX - 215}px`, // Adjust horizontal position
            top: `${rect.top + window.scrollY + rect.height - 50}px`, // Adjust vertical position
          })
          .addClass("shown");

        const parent = $(eventTarget).parent();
        const ticketId = parent.data("ticketid");
        const copyFromDate = parent.data("date");
        const formattedCopyFromDate = new Date(copyFromDate)
          .toLocaleDateString("da-DK", {
            day: "numeric",
            month: "numeric",
          })
          .replace(".", "/");

        const hours = parent.data("hours");
        const description = parent.data("description");
        const copyToDate = $(
          'input[name="timetable-current-week-last-day"]',
        ).val();

        const formattedCopyToDate = new Date(copyToDate)
          .toLocaleDateString("da-DK", {
            day: "numeric",
            month: "numeric",
          })
          .replace(".", "/");

        this.entryCopyForm
          .find('input[name="entryCopyTicketId"]')
          .val(ticketId);
        this.entryCopyForm.find('input[name="entryCopyHours"]').val(hours);
        this.entryCopyForm
          .find('input[name="entryCopyDescription"]')
          .val(description);
        this.entryCopyForm
          .find('input[name="entryCopyFromDate"]')
          .val(copyFromDate);
        this.entryCopyForm
          .find('input[name="entryCopyToDate"]')
          .val(copyToDate);

        this.setEntryCopyText({
          formattedCopyFromDate,
          formattedCopyToDate,
          targetCount,
        });

        this.entryCopyCheckboxOverwrite.off("change").change((e) => {
          const overwrite = $(e.target).is(":checked");
          const includeWeekends = this.entryCopyCheckboxWeekend.is(":checked");

          const targets = this.getEntryCopyTargets(
            eventTarget,
            overwrite,
            includeWeekends,
          );
          const targetCount = targets.length;

          this.setEntryCopyText({
            formattedCopyFromDate,
            formattedCopyToDate,
            targetCount,
          });

          this.handleHighlighting(eventTarget, overwrite, includeWeekends);
        });

        this.entryCopyCheckboxWeekend.off("change").change((e) => {
          const includeWeekends = $(e.target).is(":checked");
          const overwrite = this.entryCopyCheckboxOverwrite.is(":checked");

          const targets = this.getEntryCopyTargets(
            eventTarget,
            overwrite,
            includeWeekends,
          );
          const targetCount = targets.length;

          this.setEntryCopyText({
            formattedCopyFromDate,
            formattedCopyToDate,
            targetCount,
          });

          this.handleHighlighting(eventTarget, overwrite, includeWeekends);
        });
      });

      this.entryCopyButtonClose.click(() => {
        this.closeEntryCopyModal();
      });

      $(this.entryCopyForm).on("submit", () => {
        this.entryCopyButtonApply.html(
          '<i class="fa-solid fa-arrows-rotate fa-spin"></i>',
        );
        this.entryCopyButtonApply.attr("disabled", "disabled");
      });

      // Ticket context menu handlers
      this.ticketContextButtonCancel.click(() => {
        this.closeTicketContextMenuModal();
      });

      $(this.ticketContextForm).on("submit", () => {
        this.ticketContextButtonApply.html(
          '<i class="fa-solid fa-arrows-rotate fa-spin"></i>',
        );
        this.ticketContextButtonApply.attr("disabled", "disabled");
      });

      // Sort menu handlers
      this.sortMenuTrigger.click((e) => {
        e.stopPropagation();
        e.preventDefault();

        const rect = e.currentTarget.getBoundingClientRect();

        // Position the menu below and aligned to the right of the button
        this.sortMenuModal
          .css({
            right: `${window.innerWidth - rect.right - window.scrollX}px`,
            top: `${rect.bottom + window.scrollY - 50}px`,
            left: "auto",
          })
          .addClass("shown");

        // Mark current sort field and direction as active
        this.sortOptions.removeClass("active");
        if (this.sortState.field) {
          $(`.sort-option[data-sort="${this.sortState.field}"]`).addClass(
            "active",
          );
        }

        this.sortDirectionBtns.removeClass("active");
        $(
          `.sort-direction-btn[data-direction="${this.sortState.direction}"]`,
        ).addClass("active");

        // Close menu when clicking outside - delay to prevent immediate closure
        setTimeout(() => {
          $(document).one("click", () => {
            this.closeSortMenuModal();
          });
        }, 100);
      });

      // Prevent modal from closing when clicking inside it
      this.sortMenuModal.click((e) => {
        e.stopPropagation();
      });

      // Handle sort option selection (just highlight, don't save yet)
      this.sortOptions.click((e) => {
        e.stopPropagation();
        const sortField = $(e.currentTarget).data("sort");
        this.sortState.field = sortField;

        // Update active state
        this.sortOptions.removeClass("active");
        $(e.currentTarget).addClass("active");
      });

      // Handle direction toggle
      this.sortDirectionBtns.click((e) => {
        e.stopPropagation();
        const direction = $(e.currentTarget).data("direction");
        this.sortState.direction = direction;

        // Update active state
        this.sortDirectionBtns.removeClass("active");
        $(e.currentTarget).addClass("active");
      });

      // Handle weekend visibility toggle
      this.weekendVisibilityToggle.change((e) => {
        this.showWeekends = $(e.target).is(":checked");
      });

      // Handle save button
      this.sortMenuSaveBtn.click((e) => {
        e.stopPropagation();

        // Prepare settings to save
        const settings = {};

        // Add sort order if a field is selected
        if (this.sortState.field) {
          settings.sortOrder = `${this.sortState.field}-${this.sortState.direction}`;
        }

        // Add weekend visibility setting
        settings.showWeekends = this.showWeekends;

        // Save to backend
        fetch("/TimeTable/TimeTable/saveSettings", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
          credentials: "include",
          body: JSON.stringify(settings),
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              // Apply weekend visibility immediately
              this.applyWeekendVisibility();
              this.closeSortMenuModal();

              // Only reload if sort order changed
              if (settings.sortOrder) {
                window.location.reload();
              }
            }
          })
          .catch((error) => console.error("Error saving settings:", error));
      });

      // Handle close button
      this.sortMenuCloseBtn.click((e) => {
        e.stopPropagation();
        this.closeSortMenuModal();
      });
    }

    handleHighlighting(element, overwrite = false, includeWeekends = false) {
      this.clearHighlighting();
      const parentElement = $(element).parent();
      const valueToPreview = parentElement.children("span").text();

      const targets = this.getEntryCopyTargets(
        element,
        overwrite,
        includeWeekends,
      );
      parentElement.addClass("highlighting");

      const targetCount = targets.length;
      targets.each(function (index, el) {
        setTimeout(() => {
          $(el).addClass("highlight").attr("data-preview", valueToPreview);
        }, 50 * index);
      });

      return targetCount;
    }

    getEntryCopyTargets(element, overwrite, includeWeekends) {
      const parentElement = $(element).parent();
      const elements = parentElement.nextAll(".timetable-edit-entry");

      return elements.filter(function () {
        const isWeekend = $(this).hasClass("weekend");
        const span = $(this).children("span");
        const hasValue = span.length > 0 && span.text().trim() !== "";

        return (includeWeekends || !isWeekend) && (overwrite || !hasValue);
      });
    }

    setEntryCopyText({
      formattedCopyFromDate,
      formattedCopyToDate,
      targetCount,
    }) {
      this.entryCopyForm
        .find(".entry-copy-headline")
        .html(`<b>Kopier tidslog</b>`);
      this.entryCopyForm
        .find(".entry-copy-text")
        .html(
          `Fra d. ${formattedCopyFromDate} til og med d. ${formattedCopyToDate}<br>${targetCount} ${targetCount === 1 ? "dag bliver ændret" : "dage bliver ændret"}`,
        );
    }

    clearHighlighting() {
      $(".highlight").removeAttr("data-preview");
      $(".timetable-edit-entry").removeClass("highlighting highlight");
    }

    /**
     * Opens the Edit Time Log modal for editing an entry.
     *
     * @param {HTMLElement} target - The HTML element representing the ticket being selected.
     * @returns {void}
     */
    selectTicket({
      innerText: taskName,
      dataset: { value: taskId, hoursleft: hoursLeft },
    }) {
      // Set values from selected ticket
      this.modalTicketInput.val(taskName);
      this.modalTicketIdInput.val(taskId);
      this.modalInputHoursLeft
        .val(parseInt(hoursLeft) < 0 ? 0 : hoursLeft)
        .attr("data-value", hoursLeft);
    }

    /**
     * Updates a time entry.
     *
     * @param {number} id - Time entry ID.
     * @param {string} ticketId - Ticket ID.
     * @param {number} hours - Hours spent.
     * @param {number} hoursLeft - Hours left.
     * @param {string} description - Work done.
     * @param {string} date - Work date.
     * @return {boolean}
     */
    editTimeEntry(headline, id, ticketId, hours, hoursLeft, description, date) {
      if (id) {
        this.modalDeleteButton.show();
        this.modalInputDateMove.parent().show();
      } else {
        this.modalDeleteButton.hide();
        this.modalInputDateMove.parent().hide();
      }

      this.modalInputTimesheetId.val(id);
      this.modalInputTicketId.val(ticketId);
      this.modalInputTicketName.val(headline).attr("disabled", "disabled");
      this.modalInputHours.val(hours);

      this.modalInputHoursLeft
        .val(hoursLeft)
        .attr("data-value", hoursLeft)
        .toggleClass("estimate-exceeded", hoursLeft < 0);
      this.modalTextareaDescription.val(description);
      this.modalInputDate.val(date);
      this.timelogDateChanger.setDate(new Date(date));
      this.modalInputDateMove.parent().attr("data-original", date);
      this.modalInputHours.focus();

      $(this.modalTextareaDescription)
        .off("keydown")
        .keydown((e) => {
          if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            const parentForm = $(e.target).closest("form");
            if (parentForm.length) {
              parentForm.submit();
            }
          }
        });
    }

    deleteTimeEntry() {
      const timesheetId = this.modalInputTimesheetId.val();
      $(this.modalDeleteButton)
        .html('<i class="fa-solid fa-arrows-rotate"></i>')
        .addClass("deleting");

      fetch(window.location.href, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "deleteTicket",
          timesheetId: timesheetId,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.status === "success") {
            const $cell = $(
              'td.timetable-edit-entry[data-id="' + timesheetId + '"]',
            );
            const $row = $cell.closest("tr");

            // Clear the cell data and content
            $cell
              .children("span")
              .text("")
              .end()
              .attr("data-id", "")
              .attr("data-hours", "")
              .attr("data-description", "")
              .attr("data-hoursleft", "");

            // Ensure entry-copy-button exists (recreate if removed)
            if ($cell.find("div.entry-copy-button").length === 0) {
              $cell.append(
                '<div class="entry-copy-button"><i class="fa-solid fa-angle-right"></i></div>',
              );
            }

            // Update row total
            let rowTotal = 0;
            $row.find("td.timetable-edit-entry span").each(function () {
              const hours = parseFloat($(this).text()) || 0;
              rowTotal += hours;
            });
            $row.find("td:last").text(rowTotal || "");

            // Update column totals
            const cellIndex = $cell.index();
            let columnTotal = 0;
            $("table.timetable tbody tr:not(:last)")
              .find("td:eq(" + cellIndex + ") span")
              .each(function () {
                const hours = parseFloat($(this).text()) || 0;
                columnTotal += hours;
              });
            $("table.timetable tbody tr:last td:eq(" + cellIndex + ")").text(
              columnTotal || "",
            );

            // Update grand total (last cell of last row)
            let grandTotal = 0;
            $("table.timetable tbody tr:last td:not(:first)").each(function () {
              const total = parseFloat($(this).text()) || 0;
              grandTotal += total;
            });
            $("table.timetable tbody tr:last td:last").text(grandTotal || "");

            this.closeEditTimeLogModal();
          } else {
            alert("An error has occurred");
          }
        });
    }

    /**
     * Closes the edit time log modal.
     *
     * @returns {void}
     */
    closeEditTimeLogModal() {
      this.timeEditModal.removeClass("shown").removeAttr("data-value");
      this.timeEditModal
        .find("input:not([name='action'], [name='manageAsUserId']), textarea")
        .val("");
      this.modalInputDateMove.parent().removeAttr("data-original");
      this.modalInputDateMoveNotifier.addClass("hidden");
      $(".timesheet-date-wrapper").removeClass("modified open");
      $(this.modalDeleteButton)
        .html('<i class="fa fa-trash"></i>')
        .removeClass("deleting");
      $(document).off("mousedown", this.boundClickOutsideModalHandler);
    }

    closeEntryCopyModal() {
      this.entryCopyModal.removeClass("shown");
      this.entryCopyCheckboxOverwrite.prop("checked", false);
      this.entryCopyCheckboxWeekend.prop("checked", false);
      this.clearHighlighting();
    }

    closeTicketContextMenuModal() {
      this.ticketContextMenuModal.removeClass("shown");
    }

    closeSortMenuModal() {
      this.sortMenuModal.removeClass("shown");
    }

    /**
     * Applies weekend visibility based on the current setting
     *
     * @returns {void}
     */
    applyWeekendVisibility() {
      const $timetable = $(".timetable");
      if (this.showWeekends) {
        $timetable.removeClass("hide-weekends");
      } else {
        $timetable.addClass("hide-weekends");
      }
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
    }

    initTicketSearch(projects, tickets, autofocus = false) {
      const self = this;
      const pageSize = 50;
      const userId = pluginSettings.userId;

      // Exclude tickets that are already present in the table.
      const activeTicketIds = $("#timetable > tbody > tr[data-ticketid]")
        .map(function () {
          return $(this).data("ticketid");
        })
        .get();

      const options = tickets
        .filter((child) => !activeTicketIds.includes(child.id))
        .map((child) => {
          return {
            value: child.id,
            text: child.text,
            type: child.type,
            projectName: child.projectName,
            editorId: child.editorId,
            isFavorite: child.isFavorite || false,
            relevanceScore: child.relevanceScore || 0,
          };
        });

      if (this.tomselect) {
        this.tomselect.destroy();
        this.tomselect = null;
      }
      // Init tomselect
      this.tomselect = new TomSelect(".timetable-tomselect", {
        options: options,
        searchField: ["text", "value", "projectName"],
        loadingClass: "ts-loading",
        placeholder: "Tilføj ny registrering",
        create: function (input) {
          return { value: input, text: input };
        },
        render: {
          item: function (item, escape) {
            return `
<div>
    <span>
        ${escape(item.text)}
        <span>
            <i class="fa fa-angle-right fa-xs"></i> ${escape(item.projectName)}
            <small>(${escape(item.value)})</small>

            ${item.type !== "task" ? `<small>(${escape(item.type)})</small>` : ""}
        </span>
    </span>
</div>`;
          },
          option: function (item, escape) {
            // Style to match table rows - padding: 6px 12px to match .table td
            const favoriteIcon = item.isFavorite
              ? ' <i class="fa-regular fa-star" style="font-size: 12px; margin-left: 4px;" title="Favorite"></i>'
              : "";
            return `<div style="padding: 6px 12px;">
                            <div style="display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; font-size: 14px;">
                                ${escape(item.text)}${favoriteIcon}
                            </div>
                            <div style="color: #666; font-size: 12px; display: block;">
                                ${escape(item.projectName)}
                                <small style="margin-left: 4px;">(${escape(item.value)})</small>
                                ${parseInt(item.editorId) === parseInt(pluginSettings.userId) ? '<i class="your-task far fa-user" title="To-do is assigned to you" style="margin-left: 4px;"></i>' : ""}
                                ${item.type.toLowerCase() !== "task" ? `<small style="margin-left: 4px;">(${escape(item.type)})</small>` : ""}
                            </div>
                        </div>`;
          },
          option_create: function (data, escape) {
            return `<option data-value="add-new-ticket" class="create">+ Create new ticket with title: <strong>${escape(data.input)}</strong>&hellip;</option>`;
          },
        },
        load: function (query, callback) {
          if (!query.length) return callback();
          const term = query.toUpperCase();
          let results = options.filter(
            (e) =>
              (e.text && e.text.toUpperCase().includes(term)) ||
              (typeof e.value === "string" && e.value.toUpperCase() === term) ||
              (e.projectName && e.projectName.toUpperCase().includes(term)),
          );
          callback(results.slice(0, pageSize));
        },
        onChange: function (value) {
          const selectedOption = this.options[value];

          if (!selectedOption) {
            return false;
          }
          // Check if selected option is the "create new" one
          if (
            selectedOption.text === selectedOption.value &&
            typeof selectedOption.projectName === "undefined"
          ) {
            const projectOptions = [
              {
                value:
                  "Vælg projekt til din nye opgave: '" +
                  selectedOption.value +
                  "'",
                text:
                  "Vælg projekt til din nye opgave: '" +
                  selectedOption.value +
                  "'",
                disabled: "disabled",
              },
              ...projects
                .filter((project) => project.text.trim() !== "")
                .map((project) => ({ value: project.id, text: project.text })),
            ];
            let ticketName = selectedOption.value;

            const originalProjects = projects;
            const originalTickets = tickets;
            const parentContext = this;

            // Destroy select and populate with projects for the new ticket to be created in
            this.destroy();
            this.tomselect = null;
            this.tomselect = new TomSelect(".timetable-tomselect", {
              options: projectOptions,
              placeholder: "",
              onItemRemove: function () {
                // Reactivate the ticket search upon item removal
                this.destroy();
              },
              onChange: function () {
                const projectId = this.getValue();
                const projectName = this.options[projectId].text;
                this.disable();

                TimeTableApiHandler.createNewTicket(ticketName, projectId).then(
                  (data) => {
                    const ticketId = data[0].value.result[0];
                    if (ticketId && ticketName && projectName) {
                      timeTable.addRowToTimetable(
                        ticketId,
                        ticketName,
                        projectName,
                      );
                      this.enable();
                      this.destroy();
                      parentContext.tomselect.clear();
                      parentContext.tomselect.destroy();
                      timeTable.initTicketSearch(
                        originalProjects,
                        originalTickets,
                      );
                    }
                  },
                );
              },
            });
            this.tomselect.control_input.addEventListener(
              "keydown",
              function (e) {
                if (e.key === "Backspace" && !this.value) {
                  // Only trigger when input is empty
                  parentContext.tomselect.destroy();
                  // Create new instance using the original method
                  timeTable.initTicketSearch(
                    originalProjects,
                    originalTickets,
                    true,
                  );
                }
              },
            );

            this.tomselect.open();
            return;
          }
          timeTable.addRowToTimetable(
            value,
            selectedOption.text,
            selectedOption.projectName,
          );
          this.clear();
        },
      });

      if (autofocus) {
        this.tomselect.focus();
        this.tomselect.open();
      }
    }

    addRowToTimetable(ticketId, ticketText, projectName) {
      const firstDateOfWeek = new Date(
        $("input[name='timetable-current-week-first-day']").val(),
      );
      const daysRendered = parseInt(
        $('input[name="timetable-days-loaded"]').val(),
      );

      // Get date parameters to preserve week selection
      const fromDate = $(".fromdate-input").val();
      const toDate = $(".todate-input").val();

      // Create a new date object to ensure the original date is preserved
      let dateIterator = new Date(firstDateOfWeek.getTime());

      const newRow = `
    <tr class="newly-added-tr">
        <td class="ticket-title">
        <div>
            <a href="?showTicketModal=${ticketId}&fromDate=${fromDate}&toDate=${toDate}#/tickets/showTicket/${ticketId}">${ticketText}</a>
            <span>${projectName}</span>
            </div>
            <div></div>
        </td>
        ${Array.from({ length: daysRendered })
          .map((_, i) => {
            // Increment date
            if (i > 0) {
              dateIterator.setDate(dateIterator.getDate() + 1);
            }

            // Format date in YYYY-MM-DD format
            const formattedDate = dateIterator.toISOString().slice(0, 10);

            // Depending on the day of the week, add 'weekend' class
            const weekendClass = i === 5 || i === 6 ? "weekend" : "";

            return `<td class="timetable-edit-entry ${weekendClass}" data-ticketid=${ticketId} data-date="${formattedDate}" title="">
                        <span></span>
                    </td>`;
          })
          .join("")}
        <td></td>
    </tr>
`;

      $("td.add-new").parent().before(newRow);
    }

    // Function to check if the element is overflowing
    checkOverflow($element) {
      if (
        $element[0].scrollWidth > $element[0].offsetWidth ||
        $element[0].scrollHeight > $element[0].offsetHeight
      ) {
        $element.addClass("overflowing");
      } else {
        $element.removeClass("overflowing");
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
