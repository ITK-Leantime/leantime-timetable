<?php

namespace Leantime\Plugins\TimeTable\Helpers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Log\Logger;
use Leantime\Domain\Timesheets\Repositories\Timesheets as TimesheetRepository;
use Leantime\Plugins\TimeTable\DTO\TicketContextMenuDTO;
use Leantime\Plugins\TimeTable\DTO\WorklogDTO;
use Leantime\Plugins\TimeTable\Services\TimeTable as TimeTableService;
use Carbon\CarbonImmutable;

/**
 *
 */
class TimeTableActionHandler
{
    /**
     * @var \Illuminate\Foundation\Application|Logger|mixed|object
     */
    private mixed $logger;

    /**
     * Initialize the TimeTableService and TimesheetRepository dependencies.
     *
     * @param TimeTableService    $timeTableService    The TimeTableService instance to set
     * @param TimesheetRepository $timesheetRepository The TimesheetRepository instance to set
     * @return void
     */
    public function __construct(private readonly TimeTableService $timeTableService, private readonly TimesheetRepository $timesheetRepository)
    {
        $this->logger = app(Logger::class);
    }
    /**
     * Adjusts the period based on the provided POST data.
     * The adjustment happens when the "previous period" or "next period" button is pressed in the UI.
     *
     * Depending on the date-span viewed, this will take that into consideration and shift the date-span either forwards or backwards.
     *
     * @param array<string, mixed> $postData The POST data containing fromDate, toDate, and backward flag.
     * @return string The adjusted redirect URL.
     */
    public function adjustPeriod(array $postData, string $redirectUrl): string
    {
        $queryParams = [];

        if (isset($postData['showThisWeek'])) {
            $now = CarbonImmutable::now();
            $queryParams['fromDate'] = $now->startOfWeek()->format('Y-m-d');
            $queryParams['toDate'] = $now->endOfWeek()->format('Y-m-d');
        } elseif (isset($postData['dateRange'])) {
            list($postData['fromDate'], $postData['toDate']) = explode(' til ', $postData['dateRange']);
        }

        if (isset($postData['fromDate']) && empty($postData['showThisWeek'])) {
            $queryParams['fromDate'] = $postData['fromDate'];
        }

        if (isset($postData['toDate']) && empty($postData['showThisWeek'])) {
            $queryParams['toDate'] = $postData['toDate'];
        }

        if (isset($postData['fromDate']) && isset($postData['toDate'])) {
            $fromDate = CarbonImmutable::createFromFormat('d-m-Y', $postData['fromDate']);
            $toDate = CarbonImmutable::createFromFormat('d-m-Y', $postData['toDate']);
            $interval = $fromDate->diffInDays($toDate) + 1;

            if (isset($postData['backward']) && $postData['backward'] == '1') {
                $fromDate = $fromDate->subDays($interval);
                $toDate = $toDate->subDays($interval);
            } elseif (isset($postData['forward']) && $postData['forward'] == '1') {
                $fromDate = $fromDate->addDays($interval);
                $toDate = $toDate->addDays($interval);
            }

            $queryParams['fromDate'] = $fromDate->format('Y-m-d');
            $queryParams['toDate'] = $toDate->format('Y-m-d');
        }

        // Use appendQueryParams to handle final redirection
        return $this->appendQueryParams($queryParams, $redirectUrl);
    }

    /**
     * Processes the ticket data and updates or adds a time log entry in the system.
     * Redirects with updated query parameters if applicable.
     *
     * @param array<string, mixed> $postData    The data for the ticket including timesheet details, user information, and other parameters
     * @param string               $redirectUrl The URL to redirect to after processing
     * @return string The updated redirect URL with query parameters if applicable
     * @throws BindingResolutionException
     */
    public function saveTicket(array $postData, string $redirectUrl): string
    {
        $timesheetId = isset($postData['timesheet-id']) ? (int)$postData['timesheet-id'] : 0;
        if (isset($postData['timesheet-date-move'])) {
            $workDate = CarbonImmutable::createFromFormat('d-m-Y', $postData['timesheet-date-move'])->startOfDay();
        } else {
            $workDate = CarbonImmutable::createFromFormat('Y-m-d', $postData['timesheet-date'])->startOfDay();
        }
        $workDate = $workDate->setToDbTimezone();
        $modifiedDate = CarbonImmutable::now()->format('Y-m-d H:i:s');

        try {
            $worklog = new WorklogDTO(
                userId: $postData['manageAsUserId'],
                ticketId: $postData['timesheet-ticket-id'],
                workDate: $workDate,
                hours: $postData['timesheet-hours'],
                description: $postData['timesheet-description'],
                timesheetId: $postData['timesheet-id'],
                modified: $modifiedDate
            );

            $this->timeTableService->updateOrAddTimelogOnTicket($worklog, $timesheetId);
        } catch (\Error | \Exception $e) {
            $postData['errorMessage'] = urlencode($e->getMessage());
            $this->logger->error($e->getMessage(), [
                'postData' => $postData,
                'errorMessage' => $e->getMessage(),
                'error' => $e,
            ]);
            return $this->appendQueryParams($postData, $redirectUrl);
        }


        // Delegate query parameter addition to appendQueryParams
        return $this->appendQueryParams($postData, $redirectUrl);
    }

    /**
     * Deletes a ticket based on the provided POST data and redirects to the specified URL.
     * Outputs a JSON-encoded response indicating success or failure status.
     *
     * @param array<string, mixed> $postData    Postdata
     * @param string               $redirectUrl Redirect url.
     * @return void Json encoded return.
     */
    public function deleteTicket(array $postData, string $redirectUrl): void
    {
        $timesheetId = $postData['timesheetId'];
        $redirectUrl = $this->appendQueryParams($postData, $redirectUrl);

        if ($timesheetId) {
            try {
                $this->timesheetRepository->deleteTime($timesheetId);
                exit(json_encode(['status' => 'success', 'redirectUrl' => $redirectUrl]));
            } catch (\Exception $e) {
                exit(json_encode(['status' => 'error', 'error' => $e->getMessage()]));
            }
        } else {
            exit(json_encode(['status' => 'error', 'error' => 'Missing timesheetId in POST data.']));
        }
    }

    /**
     * Appends appropriate query parameters based on POST data to the given redirect URL.
     *
     * @param array<string, mixed> $postData    The POST data for query parameters.
     * @param string               $redirectUrl The base URL to which query parameters will be appended.
     * @return string The updated redirect URL with appended query parameters.
     */
    private function appendQueryParams(array $postData, string $redirectUrl): string
    {
        $queryParams = [];

        // Use fromDate and toDate from POST if set, otherwise check GET
        $queryParams['fromDate'] = $postData['fromDate'] ?? $_GET['fromDate'] ?? null;
        $queryParams['toDate'] = $postData['toDate'] ?? $_GET['toDate'] ?? null;
        $queryParams['manageAsUserId'] = $postData['manageAsUserId'] ?? $_GET['manageAsUserId'] ?? null;
        $queryParams['errorMessage'] = $postData['errorMessage'] ?? null;

        // Remove null values
        $queryParams = array_filter($queryParams);

        // Add query parameters to the URL if necessary
        if (!empty($queryParams)) {
            $urlComponents = parse_url($redirectUrl);
            $existingQuery = $urlComponents['query'] ?? '';
            parse_str($existingQuery, $queryArray);

            // Merge existing and new query parameters
            $queryArray = array_merge($queryArray, $queryParams);

            // Rebuild the URL
            $redirectUrl = ($urlComponents['scheme'] ?? 'https') . '://' .
                ($urlComponents['host'] ?? '') .
                ($urlComponents['path'] ?? '') .
                '?' . http_build_query($queryArray);
        }

        return $redirectUrl;
    }

    /**
     * Copies time log entries forward from a specified start date to an end date for a given ticket.
     *
     * @param array<string, mixed> $postData    Postdata
     * @param string               $redirectUrl Redirect url
     * @return string The redirect URL with appended query parameters after processing.
     */
    public function copyEntryForward(array $postData, string $redirectUrl): string
    {
        try {
            $copyFromDate = CarbonImmutable::createFromFormat('Y-m-d', $postData['entryCopyFromDate'])->startOfDay();
            $copyFromDate = $copyFromDate->setToDbTimezone();
            $copyToDate = CarbonImmutable::createFromFormat('Y-m-d', $postData['entryCopyToDate'])->startOfDay();
            $copyToDate = $copyToDate->setToDbTimezone();
        } catch (\Exception $e) {
            exit(json_encode(['status' => 'error', 'error' => 'Invalid date format. Expected format: Y-m-d']));
        }

        $ticketId = $postData['entryCopyTicketId'];
        $hours = $postData['entryCopyHours'];
        $description = $postData['entryCopyDescription'];
        $userId = $_GET['manageAsUserId'] ?? $postData['manageAsUserId'];

        // Move to the next day to skip the first date
        $currentDate = $copyFromDate;
        $currentDate->addDay();
        $copyToDateUserTimezone = $copyToDate->setToUserTimezone();
        while ($currentDate <= $copyToDateUserTimezone) {
            $currentDateUserTimezone = $currentDate->setToUserTimezone();
            // Skip weekends if 'entryCopyWeekend' is not set
            if (!isset($postData['entryCopyWeekend']) && ($currentDateUserTimezone->isSaturday() || $currentDateUserTimezone->isSunday())) {
                $currentDate = $currentDate->addDay();
                continue;
            }
            $values = [
                'userId' => $userId,
                'ticketId' => $ticketId,
                'workDate' => $currentDate,
                'hours' => $hours,
                'description' => $description,
                'kind' => 'GENERAL_BILLABLE',
            ];

            // Use $postData (correct case)
            if (isset($postData['entryCopyOverwrite'])) {
                $values['entryCopyOverwrite'] = $postData['entryCopyOverwrite'];
            }


            try {
                $this->timeTableService->addTimelogOnTicket($values);
                $currentDate = $currentDate->addDay();
            } catch (\Exception $e) {
                exit(json_encode(['status' => 'error', 'error' => $e->getMessage()]));
            }
        }

        // Delegate query parameter addition to appendQueryParams
        return $this->appendQueryParams($postData, $redirectUrl);
    }

    /**
     * Updates the managed user
     *
     * @param array<string, mixed> $postData    The POST data containing query parameters.
     * @param string               $redirectUrl The initial redirect URL to be modified.
     * @return string The redirect URL updated with additional query parameters.
     */
    public function manageAs(array $postData, string $redirectUrl): string
    {
        return $this->appendQueryParams($postData, $redirectUrl);
    }

    public function ticketContextMenu(array $postData, string $redirectUrl)
    {
        $dateToFinish = $postData['dateToFinish'] ?? null;
        if ($dateToFinish && !empty(trim($dateToFinish))) {
            $dateToFinish = CarbonImmutable::createFromFormat('d-m-Y', $dateToFinish)
                ->setTime(0, 0, 0)
                ->setToDbTimezone()
                ->format('Y-m-d H:i:s');
        } else {
            $dateToFinish = '0000-00-00 00:00:00';
        }

        // Process tags - TomSelect sends as array
        $tags = $postData['tags'] ?? '';
        if (is_array($tags)) {
            $tags = implode(',', $tags);
        }

        $ticketContextMenuDTO = new TicketContextMenuDTO(
            ticketId: (int) $postData['ticketId'],
            status: (int) $postData['status'],
            manageAsUserId: (int) $postData['manageAsUserId'],
            dateToFinish: $dateToFinish,
            tags: $tags
        );

        $this->timeTableService->modifyTicketDetails($ticketContextMenuDTO);

        // Delegate query parameter addition to appendQueryParams
        return $this->appendQueryParams($postData, $redirectUrl);
    }
}
