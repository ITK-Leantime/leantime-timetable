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
use Leantime\Plugins\TimeTable\Helpers\TimeTableHelper;
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

    private TimeTableHelper $timeTableHelper;

    /**
     * constructor
     *
     * @return void
     */
    public function init(TimeTableService $timeTableService, LanguageCore $language, SettingRepository $settings, Template $template, TimesheetRepository $timesheetRepository, TicketRepository $ticketRepository, TimeTableHelper $timeTableHelper): void
    {
        $this->timeTableService = $timeTableService;
        $this->language = $language;
        $this->settings = $settings;
        $this->template = $template;
        $this->timesheetRepository = $timesheetRepository;
        $this->ticketRepository = $ticketRepository;
        $this->timeTableHelper = $timeTableHelper;
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
     * Saves the user's timetable settings (sort order and display preferences)
     *
     * @param  array<string, mixed> $input The input data containing:
     *                                     - 'sortOrder' (string|null): The sort order ('ticket-name-asc', etc.)
     *                                     - 'showWeekends' (bool): Whether to show weekend columns
     * @return JsonResponse Returns a JSON response indicating success or failure
     */
    public function saveSettings(array $input): JsonResponse
    {
        $userId = session('userdata.id');
        $userService = app()->make(\Leantime\Domain\Users\Services\Users::class);

        // Validate and save sort order if provided
        if (isset($input['sortOrder']) && $input['sortOrder'] !== '') {
            $sortOrder = $input['sortOrder'];
            $validFields = ['ticket-name', 'project-name'];
            $validDirections = ['asc', 'desc'];

            $parts = explode('-', $sortOrder);
            if (count($parts) >= 2) {
                $direction = array_pop($parts);
                $field = implode('-', $parts);

                if (in_array($field, $validFields) && in_array($direction, $validDirections)) {
                    $userService->updateUserSettings('timetable', 'sortOrder', $sortOrder);
                } else {
                    return response()->json(['error' => 'Invalid sort order'], 400);
                }
            } else {
                return response()->json(['error' => 'Invalid sort order format'], 400);
            }
        }

        // Save weekend visibility preference
        if (isset($input['showWeekends'])) {
            $showWeekends = (bool) $input['showWeekends'];
            $userService->updateUserSettings('timetable', 'showWeekends', $showWeekends);
        }

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
        // Explicitly define first and last day of week to avoid timezone issues across environments.
        $fromDate = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $toDate = CarbonImmutable::now()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();

        // Get all users
        $allUsers = $this->timeTableService->getAllUsers();

        // Determine if the user can manage other users' timesheets.'
        $canCrossManage = Auth::userIsAtLeast(Roles::$admin, true);

        // Get userId, either from the URL parameter or from session (if cross-user management is not allowed)
        $userId = $canCrossManage && isset($_GET['manageAsUserId']) ? $_GET['manageAsUserId'] : session('userdata.id');

        // Get eventual error message
        $errorMessage = isset($_GET['errorMessage']) ? urldecode($_GET['errorMessage']) : null;

        // Try to parse dates from URL parameters
        try {
            if (isset($_GET['fromDate']) && $_GET['fromDate'] !== '') {
                $fromDate = $this->timeTableHelper->parseDate($_GET['fromDate']);
            }

            if (isset($_GET['toDate']) && $_GET['toDate'] !== '') {
                $toDate = $this->timeTableHelper->parseDate($_GET['toDate']);
            }
        } catch (\Exception $e) {
            Log::error($e);
            $fromDate = CarbonImmutable::now()->startOfWeek()->startOfDay();
            $toDate = CarbonImmutable::now()->endOfWeek()->startOfDay();
        }

        // Convert dates to UTC timezone for the database
        $weekStartDateDb = $fromDate->setToDbTimezone();
        $weekEndDateDb = $toDate->setToDbTimezone();

        $days = explode(',', mb_strtolower($this->language->__('language.dayNames')));
        $days[] = array_shift($days);

        // Generate week dates using helper
        $weekDates = $this->timeTableHelper->generateWeekDates($fromDate, $toDate);

        $relevantTicketIds = $this->timeTableService->getUniqueTicketIds($weekStartDateDb, $weekEndDateDb, $userId);

        // Aggregate timesheets by ticket using helper
        $timesheetsByTicket = $this->timeTableHelper->aggregateTimesheetsByTicket(
            $relevantTicketIds,
            $weekDates,
            $userId,
            $fromDate,
            $toDate
        );

        // Add favorite status to timesheets
        $timesheetsByTicket = $this->timeTableHelper->addFavoriteStatus($timesheetsByTicket);

        // Get user's preferences
        $userRepository = app()->make(\Leantime\Domain\Users\Repositories\Users::class);
        $sortOrder = $userRepository->getUserSettings($userId, 'timetable.sortOrder') ?? '';

        // Get showWeekends setting and convert to boolean properly
        $showWeekendsRaw = $userRepository->getUserSettings($userId, 'timetable.showWeekends');
        // If setting doesn't exist (null), default to true. Otherwise convert to boolean
        $showWeekends = $showWeekendsRaw === null ? true : (bool) $showWeekendsRaw;

        // Sort timesheets using helper
        $timesheetsByTicket = $this->timeTableHelper->sortTimesheets($timesheetsByTicket, $sortOrder);

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
        $this->template->assign('showWeekends', $showWeekends);

        return $this->template->display('TimeTable.timetable');
    }
}
