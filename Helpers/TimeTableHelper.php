<?php

namespace Leantime\Plugins\TimeTable\Helpers;

use Carbon\CarbonImmutable;
use Leantime\Core\Language as LanguageCore;
use Leantime\Plugins\TimeTable\Services\TimeTable as TimeTableService;

/**
 * Helper class for TimeTable operations including date parsing, data aggregation, and sorting
 */
class TimeTableHelper
{
    /**
     * Constructor
     */
    public function __construct(
        private LanguageCore $language,
        private TimeTableService $timeTableService
    ) {
    }

    /**
     * Parses a date string that can be either relative (e.g., "+1 week", "-2 days") or absolute (e.g., "2025-01-15")
     *
     * @param string $dateParam    The date parameter to parse
     * @param bool   $isStartOfDay Whether to set the time to start of day
     * @return CarbonImmutable The parsed date
     * @throws \Exception If the date format is invalid.
     */
    public function parseDate(string $dateParam, bool $isStartOfDay = true): CarbonImmutable
    {
        $dateParam = trim($dateParam);

        // Check if it's a relative date (starts with + or - or contains 'day', 'week', 'month', etc.)
        if (
            $dateParam[0] === '+' ||
            $dateParam[0] === '-' ||
            preg_match('/\b(day|week|month|year)s?\b/i', $dateParam)
        ) {
            // Ensure + is present for positive relative dates
            if ($dateParam[0] !== '+' && $dateParam[0] !== '-') {
                $dateParam = '+' . $dateParam;
            }
            $date = CarbonImmutable::now()->startOfDay()->modify($dateParam);
        } else {
            // Try to parse as absolute date
            $date = CarbonImmutable::createFromFormat('Y-m-d', $dateParam);
            if ($isStartOfDay) {
                $date = $date->startOfDay();
            }
        }

        return $date;
    }

    /**
     * Generates an array of dates between fromDate and toDate, filtered by the configured week days
     *
     * @param CarbonImmutable $fromDate Start date
     * @param CarbonImmutable $toDate   End date
     * @return array<string, CarbonImmutable> Array of dates indexed by 'd-m-Y' format
     */
    public function generateWeekDates(CarbonImmutable $fromDate, CarbonImmutable $toDate): array
    {
        $days = explode(',', mb_strtolower($this->language->__('language.dayNames')));
        $days[] = array_shift($days);

        $weekDates = [];
        $dateIterator = $fromDate->setToUserTimezone()->copy();

        while ($dateIterator <= $toDate) {
            $dayOfWeek = strtolower($dateIterator->locale(session('usersettings.language'))->dayName);

            // If the day is a part of the week
            if (in_array($dayOfWeek, $days)) {
                $weekDates[$dateIterator->format('d-m-Y')] = $dateIterator->copy();
            }

            // Move on to the next day
            $dateIterator = $dateIterator->addDay();
        }

        return $weekDates;
    }

    /**
     * Aggregates timesheets by ticket for the given date range
     *
     * @param array<int, array<string, mixed>> $relevantTicketIds Array of ticket IDs to process
     * @param array<string, CarbonImmutable>   $weekDates         Array of dates to process
     * @param int                              $userId            User ID to fetch timesheets for
     * @param CarbonImmutable                  $fromDate          Start date for generating ticket links
     * @param CarbonImmutable                  $toDate            End date for generating ticket links
     * @return array<int, array<string, mixed>> Timesheets organized by ticket ID
     */
    public function aggregateTimesheetsByTicket(
        array $relevantTicketIds,
        array $weekDates,
        int $userId,
        CarbonImmutable $fromDate,
        CarbonImmutable $toDate
    ): array {
        $timesheetsByTicket = [];

        foreach ($relevantTicketIds as $ticket) {
            if (! $ticket['ticketId']) {
                continue;
            }

            $timesheetsSortedByWeekdate = [];

            foreach ($weekDates as $weekDate) {
                $timesheetsByTicketAndDate = $this->timeTableService->getTimesheetByTicketIdAndWorkDate(
                    $ticket['ticketId'],
                    $weekDate->setToDbTimezone(),
                    $userId
                );

                $timesheetsSortedByWeekdate[$weekDate->format('Y-m-d')] = $timesheetsByTicketAndDate;

                if (count($timesheetsByTicketAndDate) > 0) {
                    $firstTimesheet = $timesheetsByTicketAndDate[0];
                    $timesheetsSortedByWeekdate['ticketTitle'] = $firstTimesheet['headline'];
                    $timesheetsSortedByWeekdate['ticketLink'] = '?showTicketModal=' . $firstTimesheet['ticketId']
                        . '&fromDate=' . $fromDate->format('Y-m-d')
                        . '&toDate=' . $toDate->format('Y-m-d')
                        . '#/tickets/showTicket/' . $firstTimesheet['ticketId'];
                    $timesheetsSortedByWeekdate['projectId'] = $firstTimesheet['projectId'];
                    $timesheetsSortedByWeekdate['projectName'] = $firstTimesheet['name'];
                    $timesheetsSortedByWeekdate['ticketType'] = $firstTimesheet['ticketType'];
                    $timesheetsSortedByWeekdate['ticketId'] = $firstTimesheet['ticketId'];
                    $timesheetsSortedByWeekdate['dateToFinish'] = $firstTimesheet['dateToFinish'];
                    $timesheetsSortedByWeekdate['dateToFinishIsSet'] = $firstTimesheet['dateToFinish'] !== '0000-00-00 00:00:00';
                    $timesheetsSortedByWeekdate['tags'] = $firstTimesheet['tags'];
                    $timesheetsSortedByWeekdate['tagsIsSet'] = $firstTimesheet['tags'] !== '';
                    $timesheetsSortedByWeekdate['status'] = $firstTimesheet['status'];
                    $timesheetsSortedByWeekdate['hourRemaining'] = (float) $firstTimesheet['planHours'] - (float) $firstTimesheet['hoursSum'];
                }
            }

            $timesheetsByTicket[$ticket['ticketId']] = $timesheetsSortedByWeekdate;
        }

        return $timesheetsByTicket;
    }

    /**
     * Adds favorite status to timesheets array
     *
     * @param array<int, array<string, mixed>> $timesheetsByTicket Timesheets organized by ticket
     * @return array<int, array<string, mixed>> Timesheets with favorite status added
     */
    public function addFavoriteStatus(array $timesheetsByTicket): array
    {
        $favoriteTicketIds = [];

        if (class_exists('\Leantime\Plugins\FavoriteTasks\Services\FavoriteTasks')) {
            $favoriteService = app()->make('\Leantime\Plugins\FavoriteTasks\Services\FavoriteTasks');
            $favorites = $favoriteService->getUserFavouriteIssues();
            $favoriteTicketIds = array_column($favorites, 'id');
        }

        foreach ($timesheetsByTicket as $ticketId => &$timesheet) {
            $timesheet['isFavorite'] = in_array($ticketId, $favoriteTicketIds);
        }

        return $timesheetsByTicket;
    }

    /**
     * Sorts timesheets by the given sort order
     *
     * @param array<int, array<string, mixed>> $timesheetsByTicket Timesheets to sort
     * @param string                           $sortOrder          Sort order string (e.g., "ticket-name-asc", "project-name-desc")
     * @return array<int, array<string, mixed>> Sorted timesheets
     */
    public function sortTimesheets(array $timesheetsByTicket, string $sortOrder): array
    {
        if (! $sortOrder || empty($timesheetsByTicket)) {
            return $timesheetsByTicket;
        }

        // Parse sort order to extract field and direction (e.g., "ticket-name-asc")
        $parts = explode('-', $sortOrder);
        $direction = 'asc'; // default
        $sortField = $sortOrder;

        // Check if last part is a direction indicator
        if (count($parts) > 1 && in_array(end($parts), ['asc', 'desc'])) {
            $direction = array_pop($parts);
            $sortField = implode('-', $parts);
        }

        uasort($timesheetsByTicket, function ($a, $b) use ($sortField, $direction) {
            if ($sortField === 'ticket-name') {
                $aValue = strtolower($a['ticketTitle'] ?? '');
                $bValue = strtolower($b['ticketTitle'] ?? '');
            } elseif ($sortField === 'project-name') {
                $aValue = strtolower($a['projectName'] ?? '');
                $bValue = strtolower($b['projectName'] ?? '');
            } else {
                return 0;
            }

            $comparison = strcmp($aValue, $bValue);

            // Reverse comparison for descending order
            return $direction === 'desc' ? -$comparison : $comparison;
        });

        return $timesheetsByTicket;
    }
}
