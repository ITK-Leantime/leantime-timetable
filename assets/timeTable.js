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
          const hoursLeft = parseInt(target.dataset.hoursleft) ?? null;
          const description = target.dataset.description ?? null;
          const date = target.dataset.date ?? null;

          if (!id) {
            $(this.timeEditModal).addClass("new");
          } else {
            $(this.timeEditModal).removeClass("new");
          }

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
      console.log(parseInt(hoursLeft) < 0);
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
            $('td.timetable-edit-entry[data-id="' + timesheetId + '"]')
              .children("span")
              .text("")
              .end()
              .attr("data-id", "")
              .attr("data-hours", "")
              .attr("data-description", "")
              .attr("data-hoursleft", "")
              .end()
              .find("div.entry-copy-button")
              .remove();
            $(".recently-deleted-timelog-info").removeClass("hidden");
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
        placeholder: "+ New registration",
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
            // We only display to-do type if it is not "task", to reduce clutter.
            return `<div><span>${escape(item.text)} <span><i class="fa fa-angle-right fa-xs"></i> ${escape(item.projectName)} <small>(${escape(item.value)})</small> <small style="float: right;">${item.editorId === pluginSettings.userId ? '<i class="your-task far fa-user" title="To-do is assigned to you"></i>' : ""}${item.type.toLowerCase() !== "task" ? `(${escape(item.type)})` : ""}</small></span></span></div>`;
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
                value: "Select a project:",
                text: "Select a project:",
                disabled: "disabled",
              },
              { value: selectedOption.value, text: selectedOption.value },
              ...projects
                .filter((project) => project.text.trim() !== "")
                .map((project) => ({ value: project.id, text: project.text })),
            ];

            // Destroy select and populate with projects for the new ticket to be created in
            this.destroy();
            this.tomselect = null;
            this.tomselect = new TomSelect(".timetable-tomselect", {
              options: projectOptions,
              onItemRemove: function () {
                // Reactivate the ticket search upon item removal
                this.destroy();
              },
              onChange: function () {
                const selectedValues = this.getValue();
                const resultArray = selectedValues.split(",");
                if (resultArray.length === 2) {
                  const ticketName = resultArray[0];
                  const projectId = resultArray[1];
                  const projectName = this.options[projectId].text;

                  this.disable();

                  TimeTableApiHandler.createNewTicket(
                    ticketName,
                    projectId,
                  ).then((data) => {
                    const ticketId = data[0].value.result[0];
                    if (ticketId && ticketName && projectName) {
                      timeTable.addRowToTimetable(
                        ticketId,
                        ticketName,
                        projectName,
                      );
                      this.enable();
                      this.destroy();
                    }
                  });
                }
              },
            });
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

      // Create a new date object to ensure the original date is preserved
      let dateIterator = new Date(firstDateOfWeek.getTime());

      const newRow = `
    <tr class="newly-added-tr">
        <td class="ticket-title">
            <a href="?showTicketModal=${ticketId}#/tickets/showTicket/${ticketId}">${ticketText}</a>
            <span>${projectName}</span>
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
