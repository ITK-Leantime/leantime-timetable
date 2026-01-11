<?php

namespace Leantime\Plugins\TimeTable\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Core\Language as LanguageCore;
use Leantime\Core\UI\Template;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Leantime\Domain\Timesheets\Repositories\Timesheets as TimesheetRepository;
use Leantime\Plugins\TimeTable\Helpers\TimeTableActionHandler;
use Leantime\Plugins\TimeTable\Services\TimeTable as TimeTableService;
use Symfony\Component\HttpFoundation\Response;

/**
 * TimeTable controller.
 */
class TimeTable extends Controller
{
    private TimeTableService $timeTableService;

    protected LanguageCore $language;

    private SettingRepository $settings;

    protected Template $template;

    private TimesheetRepository $timesheetRepository;

    private TicketRepository $ticketRepository;

    /**
     * constructor
     *
     * @return void
     */
    public function init(TimeTableService $timeTableService, LanguageCore $language, SettingRepository $settings, Template $template, TimesheetRepository $timesheetRepository, TicketRepository $ticketRepository): void
    {
        $this->timeTableService = $timeTableService;
        $this->language = $language;
        $this->settings = $settings;
        $this->template = $template;
        $this->timesheetRepository = $timesheetRepository;
        $this->ticketRepository = $ticketRepository;
    }

    /**
     * Retrieves all tickets from the timetable service and returns them as a JSON response.
     *
     * @return JsonResponse The JSON response containing the list of tickets or an empty array.
     */
    public function getAllTickets(): JsonResponse
    {
        $userId = session('userdata.id');
        $allTicketsData = $this->timeTableService->getAllTickets();

        // Extract children array from the service response
        $tickets = $allTicketsData['children'] ?? [];

        // Get user's favorite tickets
        $favoriteTicketIds = [];
        if (class_exists('\Leantime\Plugins\FavoriteTasks\Services\FavoriteTasks')) {
            $favoriteService = app()->make('\Leantime\Plugins\FavoriteTasks\Services\FavoriteTasks');
            $favorites = $favoriteService->getUserFavouriteIssues();
            $favoriteTicketIds = array_column($favorites, 'id');
        }

        // Get recently viewed tickets from tickethistory
        $recentlyViewedIds = $this->timeTableService->getRecentlyViewedTicketIds($userId, 20);

        // Add relevance scoring to tickets
        foreach ($tickets as &$ticket) {
            $score = 0;
            $ticket['isFavorite'] = false;

            // Use 'id' field for ticket ID (from service format)
            $ticketId = $ticket['id'] ?? null;
            if (!$ticketId) {
                $ticket['relevanceScore'] = 0;
                continue;
            }

            // Highest priority: Favorites
            if (in_array($ticketId, $favoriteTicketIds)) {
                $score += 1000;
                $ticket['isFavorite'] = true;
            }

            // High priority: Recently viewed
            $recentIndex = array_search($ticketId, $recentlyViewedIds);
            if ($recentIndex !== false) {
                // More recent = higher score (100 - index * 5)
                $score += (100 - ($recentIndex * 5));
            }

            // Medium-low priority: Assigned to user
            if (isset($ticket['editorId']) && $ticket['editorId'] == $userId) {
                $score += 50;
            }

            $ticket['relevanceScore'] = $score;
        }

        // Sort by relevance score (desc), then alphabetically by text
        usort($tickets, function ($a, $b) {
            if ($a['relevanceScore'] !== $b['relevanceScore']) {
                return $b['relevanceScore'] - $a['relevanceScore'];
            }
            return strcasecmp($a['text'] ?? '', $b['text'] ?? '');
        });

        // Return with the same structure as the service
        return response()->json(['result' => ['children' => $tickets]]);
    }

    /**
     * Creates a new ticket with the provided input values and saves it.
     *
     * @param  string[] $input The input data for creating the new ticket, which should contain:
     *                         - 'headline' (string): The title of the ticket.
     *                         - 'projectId' (int): The project to which the ticket belongs.
     * @return JsonResponse Returns a JSON response containing the result of the ticket creation.
     */
    public function createNewTicket(array $input): JsonResponse
    {
        $userId = session('userdata.id');
        $clientId = session('userdata.clientId') ?? '';
        $projectId = (int) $input['projectId'];

        // Verify user has access to the project
        $accessibleProjects = $this->timeTableService->getAllProjects($userId, $clientId);
        $hasAccess = false;

        foreach ($accessibleProjects['children'] as $project) {
            if ((int) $project['id'] === $projectId) {
                $hasAccess = true;
                break;
            }
        }

        if (! $hasAccess) {
            return response()->json(['error' => 'Unauthorized: You do not have access to this project'], 403);
        }

        // Get the correct "NEW" status for this project
        $allStateLabels = $this->timeTableService->getAllStateLabels();
        $newStatus = 3; // Default fallback

        if (isset($allStateLabels[$projectId])) {
            foreach ($allStateLabels[$projectId] as $statusKey => $statusData) {
                if (isset($statusData['statusType']) && $statusData['statusType'] === 'NEW') {
                    $newStatus = $statusKey;
                    break;
                }
            }
        }

        $ticketValues = [
            'headline' => $input['headline'],
            'type' => 'task',
            'projectId' => $projectId,
            'editorId' => $userId,
            'userId' => $userId,
            'description' => '',
            'date' => date('Y-m-d H:i:s'),
            'dateToFinish' => '',
            'status' => $newStatus,
            'storypoints' => '',
            'hourRemaining' => '',
            'planHours' => '',
            'priority' => '',
            'sprint' => '',
            'acceptanceCriteria' => '',
            'tags' => '',
            'editFrom' => '',
            'editTo' => '',
            'dependingTicketId' => '',
            'milestoneid' => '',
        ];
        $result = $this->ticketRepository->addTicket($ticketValues);

        return response()->json(['result' => [$result]]);
    }

    /**
     * Saves the user's timetable sort preference
     *
     * @param  array<string, mixed> $input The input data containing:
     *                                     - 'sortOrder' (string): The sort order ('ticket-name' or 'project-name')
     * @return JsonResponse Returns a JSON response indicating success or failure
     */
    public function saveSortOrder(array $input): JsonResponse
    {
        $userId = session('userdata.id');
        $sortOrder = $input['sortOrder'] ?? '';

        // Validate sort order format: field-direction (e.g., "ticket-name-asc")
        $validFields = ['ticket-name', 'project-name'];
        $validDirections = ['asc', 'desc'];

        $parts = explode('-', $sortOrder);
        if (count($parts) < 2) {
            return response()->json(['error' => 'Invalid sort order format'], 400);
        }

        $direction = array_pop($parts);
        $field = implode('-', $parts);

        if (!in_array($field, $validFields) || !in_array($direction, $validDirections)) {
            return response()->json(['error' => 'Invalid sort order'], 400);
        }

        $userService = app()->make(\Leantime\Domain\Users\Services\Users::class);
        $userService->updateUserSettings('timetable', 'sortOrder', $sortOrder);

        return response()->json(['success' => true]);
    }

    /**
     * Retrieves all projects that the user has access to from the timetable service and returns them as a JSON response.
     *
     * @return JsonResponse The JSON response containing the list of projects or an empty array.
     */
    public function getAllProjects(): JsonResponse
    {
        $userId = session('userdata.id');
        $clientId = session('userdata.clientId') ?? '';
        $allProjects = $this->timeTableService->getAllProjects($userId, $clientId);

        return response()->json(['result' => $allProjects]);
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function post(): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->template->displayJson(['Error' => 'Not Authorized'], 403);
        }
        $redirectUrl = BASE_URL . '/TimeTable/TimeTable';
        $actionHandler = new TimeTableActionHandler($this->timeTableService, $this->timesheetRepository);

        if (isset($_POST['action'])) {
            $redirectUrl = match ($_POST['action']) {
                'adjustPeriod' => $actionHandler->adjustPeriod($_POST, $redirectUrl),
                'saveTicket' => $actionHandler->saveTicket($_POST, $redirectUrl),
                'deleteTicket' => tap(function () use ($actionHandler, $redirectUrl) {
                    $actionHandler->deleteTicket($_POST, $redirectUrl);
                }, fn () => $redirectUrl)(),
                'copyEntryForward' => $actionHandler->copyEntryForward($_POST, $redirectUrl),
                'manageAs' => $actionHandler->manageAs($_POST, $redirectUrl),
                'ticketContextMenu' => $actionHandler->ticketContextMenu($_POST, $redirectUrl),
                default => $redirectUrl,
            };
        }

        return Frontcontroller::redirect($redirectUrl);
    }

    /**
     * get
     *
     * @return Response
     * @throws \Exception
     * @throws BindingResolutionException
     */
    public function get(): Response
    {
        $searchTermForFilter = null;
        // Explicitly define first and last day of week to avoid timezone issues across environments.
        $fromDate = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $toDate = CarbonImmutable::now()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
        $allUsers = $this->timeTableService->getAllUsers();
        $canCrossManage = Auth::userIsAtLeast(Roles::$admin, true);
        $userId = $canCrossManage && isset($_GET['manageAsUserId']) ? $_GET['manageAsUserId'] : session('userdata.id');
        $errorMessage = isset($_GET['errorMessage']) ? urldecode($_GET['errorMessage']) : null;
        try {
            if (isset($_GET['fromDate']) && $_GET['fromDate'] !== '') {
                $fromDateParam = trim($_GET['fromDate']);
                // Check if it's a relative date (starts with + or - or contains 'day', 'week', 'month', etc.)
                if (
                    $fromDateParam[0] === '+' ||
                    $fromDateParam[0] === '-' ||
                    preg_match('/\b(day|week|month|year)s?\b/i', $fromDateParam)
                ) {
                    // Ensure + is present for positive relative dates
                    if ($fromDateParam[0] !== '+' && $fromDateParam[0] !== '-') {
                        $fromDateParam = '+' . $fromDateParam;
                    }
                    $fromDate = CarbonImmutable::now()->startOfDay()->modify($fromDateParam);
                } else {
                    $fromDate = CarbonImmutable::createFromFormat('Y-m-d', $fromDateParam);
                    $fromDate = $fromDate->startOfDay();
                }
            }

            if (isset($_GET['toDate']) && $_GET['toDate'] !== '') {
                $toDateParam = trim($_GET['toDate']);
                // Check if it's a relative date (starts with + or - or contains 'day', 'week', 'month', etc.)
                if (
                    $toDateParam[0] === '+' ||
                    $toDateParam[0] === '-' ||
                    preg_match('/\b(day|week|month|year)s?\b/i', $toDateParam)
                ) {
                    // Ensure + is present for positive relative dates
                    if ($toDateParam[0] !== '+' && $toDateParam[0] !== '-') {
                        $toDateParam = '+' . $toDateParam;
                    }
                    $toDate = CarbonImmutable::now()->startOfDay()->modify($toDateParam);
                } else {
                    $toDate = CarbonImmutable::createFromFormat('Y-m-d', $toDateParam);
                    $toDate = $toDate->startOfDay();
                }
            }
        } catch (\Exception $e) {
            Log::error($e);
            $fromDate = CarbonImmutable::now()->startOfWeek()->startOfDay();
            $toDate = CarbonImmutable::now()->endOfWeek()->startOfDay();
        }

        $weekStartDateDb = $fromDate->setToDbTimezone();

        $weekEndDateDb = $toDate->setToDbTimezone();

        $this->template->assign('currentSearchTerm', $searchTermForFilter);

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
        $relevantTicketIds = $this->timeTableService->getUniqueTicketIds($weekStartDateDb, $weekEndDateDb, $userId);

        $timesheetsByTicket = [];
        foreach ($relevantTicketIds as $ticket) {
            if (! $ticket['ticketId']) {
                continue;
            }
            $timesheetsSortedByWeekdate = [];
            foreach ($weekDates as $weekDate) {
                $timesheetsByTicketAndDate = $this->timeTableService->getTimesheetByTicketIdAndWorkDate($ticket['ticketId'], $weekDate->setToDbTimezone(), $userId, $searchTermForFilter);
                $timesheetsSortedByWeekdate[$weekDate->format('Y-m-d')] = $timesheetsByTicketAndDate;
                if (count($timesheetsByTicketAndDate) > 0) {
                    $timesheetsSortedByWeekdate['ticketTitle'] = $timesheetsByTicketAndDate[0]['headline'];
                    $timesheetsSortedByWeekdate['ticketLink'] = '?showTicketModal=' . $timesheetsByTicketAndDate[0]['ticketId'] . '&fromDate=' . $fromDate->format('Y-m-d') . '&toDate=' . $toDate->format('Y-m-d') . '#/tickets/showTicket/' . $timesheetsByTicketAndDate[0]['ticketId'];
                    $timesheetsSortedByWeekdate['projectId'] = $timesheetsByTicketAndDate[0]['projectId'];
                    $timesheetsSortedByWeekdate['projectName'] = $timesheetsByTicketAndDate[0]['name'];
                    $timesheetsSortedByWeekdate['ticketType'] = $timesheetsByTicketAndDate[0]['ticketType'];
                    $timesheetsSortedByWeekdate['ticketId'] = $timesheetsByTicketAndDate[0]['ticketId'];
                    $timesheetsSortedByWeekdate['dateToFinish'] = $timesheetsByTicketAndDate[0]['dateToFinish'];
                    $timesheetsSortedByWeekdate['dateToFinishIsSet'] = $timesheetsByTicketAndDate[0]['dateToFinish'] !== '0000-00-00 00:00:00';
                    $timesheetsSortedByWeekdate['tags'] = $timesheetsByTicketAndDate[0]['tags'];
                    $timesheetsSortedByWeekdate['tagsIsSet'] = $timesheetsByTicketAndDate[0]['tags'] !== '';
                    $timesheetsSortedByWeekdate['status'] = $timesheetsByTicketAndDate[0]['status'];
                    $timesheetsSortedByWeekdate['hourRemaining'] = (float) $timesheetsByTicketAndDate[0]['planHours'] - (float) $timesheetsByTicketAndDate[0]['hoursSum'];
                }
            }

            $timesheetsByTicket[$ticket['ticketId']] = $timesheetsSortedByWeekdate;
        }

        // Get user's favorite tickets and mark them
        $favoriteTicketIds = [];
        if (class_exists('\Leantime\Plugins\FavoriteTasks\Services\FavoriteTasks')) {
            $favoriteService = app()->make('\Leantime\Plugins\FavoriteTasks\Services\FavoriteTasks');
            $favorites = $favoriteService->getUserFavouriteIssues();
            $favoriteTicketIds = array_column($favorites, 'id');
        }

        // Add favorite status to timesheets
        foreach ($timesheetsByTicket as $ticketId => &$timesheet) {
            $timesheet['isFavorite'] = in_array($ticketId, $favoriteTicketIds);
        }

        // Get user's sort preference and sort the timesheets accordingly
        $userRepository = app()->make(\Leantime\Domain\Users\Repositories\Users::class);
        $sortOrder = $userRepository->getUserSettings($userId, 'timetable.sortOrder') ?? '';

        if ($sortOrder && !empty($timesheetsByTicket)) {
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
        }

        // All tickets assigned to the template
        $this->template->assign('errorMessage', $errorMessage);
        // Get all unique tags for autocomplete
        $allTags = $this->timeTableService->getAllUniqueTags();
        // Get all state labels for context menu
        $allStateLabels = $this->timeTableService->getAllStateLabels();

        $this->template->assign('timesheetsByTicket', $timesheetsByTicket);
        $this->template->assign('weekDays', $days);
        $this->template->assign('weekDates', $weekDates);
        $this->template->assign('fromDate', $fromDate);
        $this->template->assign('toDate', $toDate);
        $this->template->assign('requireTimeRegistrationComment', $this->settings->getSetting('itk-leantime-timetable.requireTimeRegistrationComment') ?? 0);
        $this->template->assign('allUsers', $allUsers);
        $this->template->assign('userId', $userId);
        $this->template->assign('canCrossManage', $canCrossManage);
        $this->template->assign('allTags', $allTags);
        $this->template->assign('allStateLabels', $allStateLabels);
        $this->template->assign('sortOrder', $sortOrder);

        return $this->template->display('TimeTable.timetable');
    }
}
