@extends($layout)



@section('content')
    <!-- page header -->
    <div class="pageheader">
        <div class="pageicon"><span class="fa-regular fa-clock"></span></div>
        <div class="pagetitle">
            <h5>{{ __('label.table-columns') }}</h5>
            <h1>{{ __('timeTable.headline') }}</h1>
        </div>
    </div>
    <!-- page header -->
    <div class="maincontent">
        <div class="maincontentinner">
            <div class="timetable">
                <input type="hidden" name="timetable-ticket-ids" value="{{ $ticketIds }}" />
                <input type="hidden" id="timetable-ticketCacheExpiration" name="timetable-ticket-cache"
                    value="{{ $ticketCacheExpiration }}" />
                <div class="flex-container gap-3 tools">
                    <button type="button" class="timetable-week-prev btn btn-default"><i class="fa fa-arrow-left"></i>
                        {{ __('timeTable.button_prev_week') }}</button>
                    <button type="button"
                        class="timetable-week-next btn btn-default">{{ __('timeTable.button_next_week') }} <i
                            class="fa fa-arrow-right"></i></button>
                    <!-- TODO: Translate button, and have it navigate to the current week/day -->
                    <button type="button"
                        class="timetable-to-today btn btn-default">{{ __('timeTable.button_to_today') }}</button>
                    <button class="timetable-new-entry btn btn-primary ml-auto"
                        type="button">{{ __('timeTable.button_add_time_log') }} <i class="fa fa-plus"></i></button>
                </div>
                <table id="timetable" class="table">
                    <thead>
                        <tr>
                            <th class="th-ticket-title" scope="col">{{ __('timeTable.title_table_header') }}</th>
                            @if (isset($weekDays, $weekDates))
                                <input type="hidden" name="timetable-current-week-first-day" value="<?php echo isset($weekDates[0]) ? $weekDates[0]->format('Y-m-d') : ''; ?>" />
                                <input type="hidden" name="timetable-current-week" value="<?php echo isset($weekDates[0]) ? $weekDates[0]->format('W') : ''; ?>" />
                                @foreach ($weekDays as $key => $day)
                                    @php
                                        $weekDate = $weekDates[$key];
                                        $weekendClass = $weekDate->isWeekend() ? 'weekend' : '';
                                        $todayClass = $weekDate->isToday() ? 'today' : '';
                                        $classes = trim("$weekendClass $todayClass");
                                    @endphp
                                    <th @if ($classes) class="{{ $classes }}" @endif>
                                        <small>{{ $weekDate->format('d/n') }}</small>
                                        <span>{{ $weekDate->format('D') }}</span>
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
                                <tr>
                                    <td class="ticket-title" scope="row"><a
                                            href="{{ $timesheet['ticketLink'] }}">{{ $timesheet['ticketTitle'] }}</a>
                                        <span>{{ $timesheet['projectName'] }}</span>
                                    </td>
                                    <?php $rowTotal = 0; ?>
                                    <!-- initializing row total -->
                                    @foreach ($weekDates as $weekDate)

                                        <?php
                                        $weekDateAccessor = isset($weekDate) ? $weekDate->format('Y-m-d') : null;
                                        $timesheetDate = isset($timesheet) ? $timesheet[$weekDateAccessor] : null;
                                        $id = $timesheetDate[0]['id'] ?? null;
                                        $hours = $timesheetDate[0]['hours'] ?? null;
                                        $description = $timesheetDate[0]['description'] ?? null;
                                        $isMissingDescription = isset($hours) && trim($description) === '';
                                        
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
                                        ?>
                                        <td scope="row"
                                            class="timetable-edit-entry {{ $weekendClass }} {{ $todayClass }} {{ $isMissingDescription ? 'description-missing' : '' }}"
                                            data-id="{{ $id }}" data-ticketid="{{ $ticketId }}"
                                            data-hours="{{ $hours }}" data-description="{{ $description }}"
                                            data-date="{{ $weekDate->format('Y-m-d') }}"
                                            title="{{ $isMissingDescription ? __('timeTable.description_missing') : '' }}">
                                            <span>{{ $hours }}</span>
                                        </td>
                                    @endforeach
                                    <td>{{ $rowTotal }}</td> <!-- Row Total Column -->
                                </tr>
                            @endforeach
                        @else
                            <!-- A little something for when the week has no logs -->
                            <tr>
                                <td colspan="{{ count($weekDays) + 2 }}">
                                    {{ __("It seems the 'WORK-IT' fairy forgot to sprinkle her magic dust here! 🧚‍🪄✨") }}
                                </td>
                            </tr>
                        @endif
                        <!-- add total hours row here -->
                        <tr class="tr-total">
                            <td scope="row">Total</td>
                            @foreach ($weekDates as $weekDate)
                                <td> {{ $totalHours[$weekDate->format('Y-m-d')] ?? 0 }} </td>
                            @endforeach
                            <td>{{ array_sum($totalHours) }}</td> <!-- Grand Total Column -->
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal for editing work logs --}}

    <div id="edit-time-log-modal" class="timetable nyroModalBg edit-time-log-modal">
        <form method="post" class="edit-time-log-form shadow">
            <div class="timetable-close-modal">
                <span>×</span>
            </div>
            {{-- Hidden properties for post --}}
            <input type="hidden" name="timesheet-ticket-id" />
            <input type="hidden" name="timesheet-id" />
            <input type="hidden" name="timesheet-offset" />

            <input type="date" name="timesheet-date">

            {{-- copy paste from https://www.w3schools.com/howto/howto_js_filter_dropdown.asp - also entries in timeTable.css and timeTable.js --}}
            <div class="timetable-ticket-search">
                <input class="timetable-ticket-input" type="text" data-placeholder="{{ __('timeTable.search_tickets') }}"
                    data-loading="{{ __('timeTable.filtering_tickets') }}"
                    placeholder="{{ __('timeTable.search_tickets') }}" />
                <div class="timetable-ticket-results"></div>
            </div>

            {{-- Hours input --}}
            <input type="number" name="timesheet-hours" step="0.01" placeholder="{{ __('timeTable.hours') }}" required />

            {{-- Description input --}}
            <div class="description-wrapper">
                <textarea type="text" id="modal-description" name="timesheet-description"
                    placeholder="{{ __('timeTable.description') }}" required></textarea>
            </div>

            {{-- Save or cancel buttons --}}
            <div class="buttons flex-container gap-3">
                <button type="button" class="timetable-modal-delete btn btn-danger"
                    data-loading="{{ __('timeTable.button_modal_deleting') }}"> <i class="fa fa-trash"></i></button>
                <button type="button"
                    class="timetable-modal-cancel btn btn-default ml-auto">{{ __('timeTable.button_modal_close') }}</button>
                <button type="submit"
                    class="timetable-modal-submit btn btn-primary">{{ __('timeTable.button_modal_save') }}</button>
            </div>
        </form>
        <div class="timetable-sync-panel">
            <div><button class="timetable-sync-tickets"><span><i class="fa-solid fa-arrows-rotate"></i>Sync
                        data</span></button></div>
            <div><span></span></div>
        </div>
    </div>
    <div id="edit-time-sync-modal" class="nyroModalBg edit-time-sync-modal">
        <div><span><i
                    class="fa-solid fa-spinner fa-2xl fa-spin"></i></span><span>{{ __('timeTable.synchronizing') }}</span>
        </div>
    </div>
@endsection
