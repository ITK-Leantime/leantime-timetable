@extends($layout)
@section('content')
    <!-- page header -->
    <div class="pageheader">
        <div class="pageicon"><span class="fa-regular fa-clock"></span></div>
        <div class="pagetitle">
            <h5>{{ __('label.table-columns') }}</h5>
            <h1>{{ __('timeTable.headline') }}</h1>
        </div>
        @if ($canCrossManage)
            <div class="timetable-manage-as">
                <form method="POST">
                    <input type="hidden" name="action" value="manageAs">
                    <label for="manageAsUserId">{{ __('timeTable.showing_calendar_for') }}</label>
                    <select name="manageAsUserId" onChange="this.form.submit()">
                        @foreach ($allUsers as $user)
                            <option value="{{ $user['id'] }}" {{ $userId == $user['id'] ? 'selected' : '' }}>
                                {{ $user['fullName'] }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        @endif
    </div>

    <!-- page header -->
    <div class="maincontent">
        <div class="maincontentinner">
            <div class="timetable">
                <div class="error-message {{ is_null($errorMessage) ? 'hidden' : '' }}">
                    <p data-tippy-content="{{ $errorMessage }}"><i class="fas fa-exclamation-circle"></i>
                        {{ __('timeTable.general-error-message') }}</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="adjustPeriod">
                    <div class="flex-container gap-3 tools">
                        <button type="submit" name="backward" value="1" class="timetable-week-prev btn btn-default">
                            <i class="fa fa-arrow-left"></i> {{ __('timeTable.button_prev_period') }}
                        </button>
                        <input type="text" name="dateRange" id="dateRange"
                            value="{{ $fromDate->format('d-m-Y') }} til {{ $toDate->format('d-m-Y') }}">
                        <button type="submit" name="forward" value="1" class="timetable-week-next btn btn-default">
                            {{ __('timeTable.button_next_period') }} <i class="fa fa-arrow-right"></i>
                        </button>
                        <button type="submit" name="showThisWeek" value="1"
                            class="timetable-to-today btn btn-default">{{ __('timeTable.button_show_this_week') }}</button>
                        <div class="recently-deleted-timelog-info hidden">
                            <p><i class="fas fa-info-circle"></i>
                                {{ __('timeTable.update_to_show_correct_sums') }}</p>
                        </div>
                        <div class="timetable-sort-menu">
                            <span class="sort-menu-trigger"><i class="fa-solid fa-ellipsis-vertical"></i></span>
                        </div>
                    </div>

                </form>

                <div class="timetable-scroll-container">
                    <table id="timetable" class="table">
                        <thead>
                            <tr>
                                <th class="th-ticket-title" scope="col" >{{ __('timeTable.title_table_header') }}
                                </th>
                                @if (isset($weekDays, $weekDates) && count($weekDates))
                                    <input type="hidden" name="timetable-current-week-first-day"
                                        value="{{ reset($weekDates)->format('Y-m-d') }}" />
                                    <input type="hidden" name="timetable-current-week-last-day"
                                        value="{{ end($weekDates)->format('Y-m-d') }}" />
                                    <input type="hidden" name="timetable-days-loaded" value="{{ count($weekDates) }}" />
                                    <input type="hidden" name="timetable-current-week"
                                        value="{{ reset($weekDates)->format('W') }}" />

                                    @foreach ($weekDates as $date => $day)
                                        @php
                                            $weekendClass = $day->isWeekend() ? 'weekend' : '';
                                            $todayClass = $day->isToday() ? 'today' : '';
                                            $newWeekClass = $day->isMonday() ? 'new-week' : '';
                                            $classes = trim("$weekendClass $todayClass $newWeekClass");
                                        @endphp
                                        <th @if ($classes) class="{{ $classes }}" @endif
                                            @if ($day->isMonday())
                                            data-week="{{ $day->weekOfYear }}"
                                    @endif>
                                    <div> <small>{{ $day->format('j/n') }}</small>
                                        <span>{{ $day->format('D') }}</span>
                                    </div>
                                    </th>
                                @endforeach
                                <th scope="col"><span>Total</span></th> <!-- Total Column Header -->
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            <?php $totalHours = []; ?>
                            @if (!empty($timesheetsByTicket))
                                @foreach ($timesheetsByTicket as $ticketId => $timesheet)
                                    <tr data-ticketId="{{ $ticketId }}">
                                        <td class="ticket-title" scope="row" data-status="{{ $timesheet['status'] ?? '' }}"
                                            data-datetofinish="{{ $timesheet['dateToFinish'] ?? '' }}"
                                            data-tags="{{ $timesheet['tags'] ?? '' }}">
                                            <div>
                                            <a href="{{ $timesheet['ticketLink'] }}"
                                                data-tippy-content="#{{ $timesheet['ticketId'] }} - {{ $timesheet['ticketTitle'] }} {{ $timesheet['ticketType'] !== 'task' ? '[ ' . $timesheet['ticketType'] . ' ]' : '' }} "
                                                data-tippy-placement="top">
                                                {{ $timesheet['ticketTitle'] }}
                                                @if(isset($timesheet['isFavorite']) && $timesheet['isFavorite'])
                                                    <i class="fa-regular fa-star" style="font-size: 12px; margin-left: 4px;" title="Favorite"></i>
                                                @endif
                                            </a>
                                            <span>{{ $timesheet['projectName'] }}</span>
                                        </div>
                                            <div class="ticket-context-menu" data-projectId="{{ $timesheet['projectId'] }}">
                                                <span
                                                    style="opacity: {{ !$timesheet['dateToFinishIsSet'] ? '1' : '0' }};"><i
                                                        class="fa-solid fa-calendar"></i></span>
                                                <span style="opacity: {{ !$timesheet['tagsIsSet'] ? '1' : '0' }};"><i
                                                        class="fa-solid fa-tags"></i></span>

                                                <span class="context-menu-trigger"><i class="fa-solid fa-ellipsis-vertical"></i></span>
                                            </div>
                                        </td>
                                        <?php $rowTotal = 0; ?>
                                        <!-- initializing row total -->
                                        @foreach ($weekDates as $weekDate)
                                            <?php
                                            $weekDateAccessor = isset($weekDate) ? $weekDate->format('Y-m-d') : null;
                                            $timesheetDate = isset($timesheet) ? $timesheet[$weekDateAccessor] : null;
                                            $id = $timesheetDate[0]['id'] ?? null;
                                            $headline = $timesheetDate[0]['headline'] ?? null;
                                            $hours = $timesheetDate[0]['hours'] ?? null;
                                            $hoursLeft = $timesheetDate[0]['hourRemaining'] ?? null;
                                            $description = $timesheetDate[0]['description'] ?? null;
                                            $requireTimeRegistrationComment = $requireTimeRegistrationComment ?? 0;
                                            $isMissingDescription = isset($hours) & (trim($description) === '') && $requireTimeRegistrationComment !== 0;

                                            // accumulate hours
                                            if ($hours) {
                                                if (isset($totalHours[$weekDateAccessor])) {
                                                    $totalHours[$weekDateAccessor] += $hours;
                                                } else {
                                                    $totalHours[$weekDateAccessor] = $hours;
                                                }
                                                $rowTotal += $hours; // add to row total
                                            }

                                            $weekendClass = isset($weekDate) && $weekDate->isWeekend() ? 'weekend' : '';
                                            $todayClass = isset($weekDate) && $weekDate->isToday() ? 'today' : '';
                                            $newWeekClass = isset($weekDate) && $weekDate->isMonday() ? 'new-week' : ''; // Add new-week class for Mondays
                                            ?>
                                            <td scope="row"
                                                class="timetable-edit-entry {{ $weekendClass }} {{ $todayClass }} {{ $newWeekClass }} {{ $isMissingDescription ? 'description-missing' : '' }}"
                                                data-id="{{ $id }}" data-ticketid="{{ $ticketId }}"
                                                data-hours="{{ $hours }}" data-hoursleft="{{ $hoursLeft }}"
                                                data-description="{{ $description }}"
                                                data-date="{{ $weekDate->format('Y-m-d') }}"
                                                data-headline="{{ $headline }}"
                                                title="{{ $isMissingDescription ? __('timeTable.description_missing') : '' }}">
                                                <span>{{ $hours }}</span>
                                                @if (!is_null($hours))
                                                    <div class="entry-copy-button"><i class="fa-solid fa-angle-right"></i>
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td>{{ $rowTotal }}</td> <!-- Row Total Column -->
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="add-new"><input class="timetable-tomselect form-control-lg">
                                        <i class='fa-regular fa-clock fa-spin'></i>
                                        <span>{{ __('timeTable.syncing_data') }}</span>
                                    </td>
                                    @foreach ($weekDates as $date)
                                        <td class="{{ $date->isMonday() ? 'new-week' : '' }}">—</td>
                                    @endforeach
                                    <td>—</td>
                                </tr>
                            @else
                                <!-- A little something for when the week has no logs -->
                                <tr class="empty-row">
                                    <td class="empty-row" colspan="{{ count($weekDates) + 2 }}">
                                        {{ __('timeTable.fairy-message') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="add-new"><input class="timetable-tomselect form-control-lg">
                                        <i class='fa-regular fa-clock fa-spin'></i>
                                        <span>{{ __('timeTable.syncing_data') }}</span>
                                    </td>
                                    @foreach ($weekDates as $date)
                                        <td>—</td>
                                    @endforeach
                                    <td>—</td>
                                </tr>
                            @endif
                            <!-- add total hours row here -->
                            <tr class="tr-total">
                                <td scope="row">{{ __('timeTable.total') }}</td>
                                @foreach ($weekDates as $weekDate)
                                    <td class="{{ $weekDate->isMonday() ? 'new-week' : '' }}">
                                        {{ $totalHours[$weekDate->format('Y-m-d')] ?? 0 }}
                                    </td>
                                @endforeach
                                <td>{{ array_sum($totalHours) }}</td> <!-- Grand Total Column -->
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    {{-- Modal for editing work logs --}}

    <div id="edit-time-log-modal" class="timetable edit-time-log-modal modal-syncing-loader">
        <form method="POST" class="edit-time-log-form">
            <input type="hidden" name="action" value="saveTicket">
            {{-- Hidden properties for post --}}
            <input type="hidden" name="timesheet-ticket-id" />
            <input type="hidden" name="timesheet-id" />
            <input type="hidden" name="timesheet-offset" />

            <input type="hidden" name="timesheet-date">

            <input type="hidden" class="fromdate-input" name="fromDate" value="{{ $fromDate->format('Y-m-d') }}"
                onchange="submit()" />
            <input type="hidden" class="todate-input" name="toDate" value="{{ $toDate->format('Y-m-d') }}"
                onchange="submit()" />
            <input type="hidden" name="manageAsUserId" value="{{ $userId }}" />

            <div class="timetable-hours">
                <div class="timesheet-input-wrapper">
                    <input type="number" name="timesheet-hours" step="0.01" placeholder="{{ __('timeTable.hours') }}"
                        required />
                    <div title="{{ __('timeTable.hours_left') }}" class="timetable-hours-left"
                        data-tippy-content="Resterende timer på opgaven">
                        <input type="number" name="timesheet-hours-left" disabled="disabled" />
                    </div>
                    <div class="timesheet-date-wrapper" data-tippy-content="Flyt tidslog til en anden dato">
                        <input type="hidden" name="timesheet-date-move" />
                    </div>

                </div>
            </div>


            {{-- Description input --}}
            <div class="description-wrapper">
                <textarea type="text" id="modal-description" name="timesheet-description"
                    placeholder="{{ __('timeTable.description') }}"
                    {{ $requireTimeRegistrationComment === '1' ? 'required' : '' }}></textarea>
            </div>
            <div class="timesheet-date-move-notifier hidden"><small><i class="fa fa-exclamation-circle"></i>
                    {{ __('timeTable.about_to_move') }}</small></div>
            {{-- Save or cancel buttons --}}
            <div class="buttons flex-container gap-1">
                <button type="button" class="timetable-modal-delete btn btn-danger"
                    data-loading="{{ __('timeTable.button_modal_deleting') }}"><i class="fa fa-trash"></i></button>
                <button type="button"
                    class="timetable-modal-cancel btn btn-default ml-auto">{{ __('timeTable.button_modal_close') }}</button>
                <button type="submit"
                    class="timetable-modal-submit btn btn-primary">{{ __('timeTable.button_modal_save') }}</button>
            </div>

        </form>
    </div>
    <div id="entry-copy-modal" class="modal-syncing-loader">
        <form method="POST" class="entry-copy-form">
            <input type="hidden" name="action" value="copyEntryForward">
            <input type="hidden" name="entryCopyTicketId" class="entry-copy-ticketId">
            <input type="hidden" name="entryCopyHours" class="entry-copy-hours">
            <input type="hidden" name="entryCopyDescription" class="entry-copy-description">
            <input type="hidden" name="entryCopyFromDate" />
            <input type="hidden" name="entryCopyToDate" />
            <input type="hidden" name="manageAsUserId" value="{{ $userId }}" />
            <p class="entry-copy-headline"></p>
            <p class="entry-copy-text"></p>
            <div class="entry-copy-checkboxes">
                <div class="entry-copy-overwrite-checkbox">
                    <input type="checkbox" name="entryCopyOverwrite" id="entry-copy-overwrite" />
                    <label
                        for="entry-copy-overwrite"><small>{{ __('timeTable.overwrite_already_logged') }}</small></label>
                </div>
                <div class="entry-copy-weekend-checkbox">
                    <input type="checkbox" name="entryCopyWeekend" id="entry-copy-weekend" />
                    <label for="entry-copy-weekend"><small>{{ __('timeTable.include_weekends') }}</small></label>
                </div>
            </div>
            <div class="buttons flex-container gap-1">
                <button type="button"
                    class="entry-copy-modal-cancel btn btn-default ml-auto">{{ __('timeTable.entry_copy_button_close') }}</button>
                <button type="submit"
                    class="entry-copy-modal-apply btn btn-primary">{{ __('timeTable.entry_copy_button_apply') }}</button>
            </div>

        </form>
    </div>
    <div id="ticket-context-menu-modal" class="modal-syncing-loader">
        <form method="POST" class="ticket-context-menu-form">
            <input type="hidden" name="action" value="ticketContextMenu">
            <input type="hidden" name="manageAsUserId" value="{{ $userId }}" />
            <input type="hidden" name="ticketId" class="ticket-context-menu-ticketId" />

            <div class="context-menu-field">
                <div class="context-menu-icon">
                    <i class="fa-solid fa-calendar-day"></i>
                </div>
                <input class="date-to-finish flatpickr flatpickr-input" type="text" placeholder="dateToFinish" name="dateToFinish"/>
            </div>

            <div class="context-menu-field">
                <div class="context-menu-icon">
                    <i class="fa-solid fa-circle-dot"></i>
                </div>
                <select class="ticket-status" name="status" autocomplete="off" readonly="readonly">

                </select>
            </div>

            <div class="context-menu-field">
                <div class="context-menu-icon">
                    <i class="fa-solid fa-tags"></i>
                </div>
                <select class="ticket-tags" name="tags[]" multiple autocomplete="off">

                </select>
            </div>

            <div class="buttons flex-container gap-1">
                <button type="button"
                        class="ticket-context-menu-cancel btn btn-default ml-auto">{{ __('timeTable.ticket_context_discard_changes') }}</button>
                <button type="submit"
                        class="ticket-context-menu-apply btn btn-primary">{{ __('timeTable.ticket_context_apply_changes') }}</button>
            </div>

        </form>
    </div>
    <div id="sort-menu-modal">
        <div class="sort-menu-header">
            <span>{{ __('timeTable.sort_options') }}</span>
        </div>
        <div class="sort-menu-options">
            <div class="sort-option" data-sort="ticket-name">
                <span>{{ __('timeTable.sort_by_ticket_name') }}</span>
            </div>
            <div class="sort-option" data-sort="project-name">
                <span>{{ __('timeTable.sort_by_project_name') }}</span>
            </div>
        </div>
        <div class="sort-menu-direction">
            <span>{{ __('timeTable.sort_direction') }}</span>
            <div class="sort-direction-toggle">
                <button class="sort-direction-btn" data-direction="asc">
                    <i class="fa-solid fa-arrow-down-a-z"></i>
                </button>
                <button class="sort-direction-btn" data-direction="desc">
                    <i class="fa-solid fa-arrow-up-z-a"></i>
                </button>
            </div>
        </div>
        <div class="sort-menu-actions">
            <button class="btn btn-default sort-menu-close">{{ __('buttons.close') }}</button>
            <button class="btn btn-primary sort-menu-save">{{ __('buttons.save') }}</button>
        </div>
    </div>

    <!-- Store sort order in a data attribute to avoid jQuery conflicts -->
    <div id="timetable-sort-data" data-sort-order="{{ $sortOrder ?? '' }}" style="display: none;"></div>
@endsection
