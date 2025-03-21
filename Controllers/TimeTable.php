<?php

namespace Leantime\Plugins\TimeTable\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Plugins\TimeTable\Helpers\TimeTableActionHandler;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Plugins\TimeTable\Services\TimeTable as TimeTableService;
use Leantime\Core\Language as LanguageCore;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Leantime\Domain\Timesheets\Repositories\Timesheets as TimesheetRepository;
use Leantime\Core\UI\Template;
use \Illuminate\Http\JsonResponse as JsonResponse;

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

    /**
     * constructor
     *
     * @param TimeTableService    $timeTableService
     * @param LanguageCore        $language
     * @param SettingRepository   $settings
     * @param Template            $template
     * @param TimesheetRepository $timesheetRepository
     * @return void
     */
    public function init(TimeTableService $timeTableService, LanguageCore $language, SettingRepository $settings, Template $template, TimesheetRepository $timesheetRepository): void
    {
        $this->timeTableService = $timeTableService;
        $this->language = $language;
        $this->settings = $settings;
        $this->template = $template;
        $this->timesheetRepository = $timesheetRepository;
    }

    public function getAllTickets(): JsonResponse
    {
        $allTickets = $this->timeTableService->getAllTickets() ?? [];
        return response()->json(['result' => $allTickets]);
    }

    public function getAllProjects(): JsonResponse
    {
        $allProjects = $this->timeTableService->getAllProjects() ?? [];
        return response()->json(['result' => $allProjects]);
    }

    /**
     * @return Response
     * @throws \Exception
     */
    public function post(): Response
    {
        if (!AuthService::userIsAtLeast(Roles::$editor)) {
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
                }, fn() => $redirectUrl)(),
                'copyEntryForward' => $actionHandler->copyEntryForward($_POST, $redirectUrl),
                'manageAs' => $actionHandler->manageAs($_POST, $redirectUrl),
                default => $redirectUrl,
            };
        }

        return Frontcontroller::redirect($redirectUrl);
    }

    /**
     * get
     *
     * @return Response
     *
     * @throws \Exception
     * @throws BindingResolutionException
     */
    public function get(): Response
    {
        $searchTermForFilter = null;
        $ticketCacheExpiration = $this->settings->getSetting('itk-leantime-timetable.ticketCacheExpiration') ?? 1200;
        $fromDate = CarbonImmutable::now()->startOfWeek()->startOfDay();
        $toDate = CarbonImmutable::now()->endOfWeek()->startOfDay();
        $allUsers = $this->timeTableService->getAllUsers();
        $canCrossManage = Auth::userIsAtLeast(Roles::$admin, true);
        $userId = $canCrossManage && isset($_GET['manageAsUserId']) ? $_GET['manageAsUserId'] : session('userdata.id');

        try {
            if (isset($_GET['fromDate']) && $_GET['fromDate'] !== '') {
                if ($_GET['fromDate'][0] === '+' || $_GET['fromDate'][0] === '-') {
                    $fromDate = CarbonImmutable::now()->startOfDay()->modify($_GET['fromDate']);
                } else {
                    $fromDate = CarbonImmutable::createFromFormat('Y-m-d', $_GET['fromDate']);
                    if ($fromDate !== false) {
                        $fromDate = $fromDate->startOfDay();
                    } else {
                        $fromDate = CarbonImmutable::createFromFormat('d/m/Y', $_GET['fromDate']);
                        $fromDate = $fromDate !== false ? $fromDate->startOfDay() : CarbonImmutable::now()->startOfWeek()->startOfDay();
                    }
                }
            }

            if (isset($_GET['toDate']) && $_GET['toDate'] !== '') {
                if ($_GET['toDate'][0] === '+' || $_GET['toDate'][0] === '-') {
                    $toDate = CarbonImmutable::now()->startOfDay()->modify($_GET['toDate']);
                } else {
                    $toDate = CarbonImmutable::createFromFormat('Y-m-d', $_GET['toDate']);
                    if ($toDate !== false) {
                        $toDate = $toDate->startOfDay();
                    } else {
                        $toDate = CarbonImmutable::createFromFormat('d/m/Y', $_GET['toDate']);
                        $toDate = $toDate !== false ? $toDate->startOfDay() : CarbonImmutable::now()->endOfWeek()->startOfDay();
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error($e);
            $fromDate = CarbonImmutable::now()->startOfWeek()->startOfDay();
            $toDate = CarbonImmutable::now()->endOfWeek()->startOfDay();
        }

        if ($fromDate instanceof CarbonImmutable) {
            $weekStartDateDb = $fromDate->setToDbTimezone();
        } else {
            // Handle invalid $fromDate gracefully
            $weekStartDateDb = null; // Or define your fallback behavior
        }

        if ($toDate instanceof CarbonImmutable) {
            $weekEndDateDb = $toDate->setToDbTimezone();
        } else {
            // Handle invalid $toDate gracefully
            $weekEndDateDb = null; // Or define your fallback behavior
        }

        $this->template->assign('currentSearchTerm', $searchTermForFilter);

        $days = explode(',', mb_strtolower($this->language->__('language.dayNames')));
        $days[] = array_shift($days);

        $weekDates = [];
        if ($fromDate instanceof CarbonImmutable) {
            $dateIterator = $fromDate->setToUserTimezone()->copy();
        } else {
            // Handle invalid $fromDate gracefully
            $dateIterator = null; // Or define fallback behavior
        }

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
            if (!$ticket['ticketId']) {
                continue;
            }
            $timesheetsSortedByWeekdate = [];
            foreach ($weekDates as $weekDate) {
                $timesheetsByTicketAndDate = $this->timeTableService->getTimesheetByTicketIdAndWorkDate($ticket['ticketId'], $weekDate->setToDbTimezone(), $userId, $searchTermForFilter);
                $timesheetsSortedByWeekdate[$weekDate->format('Y-m-d')] = $timesheetsByTicketAndDate;
                if (count($timesheetsByTicketAndDate) > 0) {
                    $timesheetsSortedByWeekdate['ticketTitle'] = $timesheetsByTicketAndDate[0]['headline'];
                    $timesheetsSortedByWeekdate['ticketLink'] = '?showTicketModal=' . $timesheetsByTicketAndDate[0]['ticketId'] . '#/tickets/showTicket/' . $timesheetsByTicketAndDate[0]['ticketId'];
                    $timesheetsSortedByWeekdate['projectName'] = $timesheetsByTicketAndDate[0]['name'];
                    $timesheetsSortedByWeekdate['ticketType'] = $timesheetsByTicketAndDate[0]['ticketType'];
                    $timesheetsSortedByWeekdate['ticketId'] = $timesheetsByTicketAndDate[0]['ticketId'];
                }
            }

            $timesheetsByTicket[$ticket['ticketId']] = $timesheetsSortedByWeekdate;
        }
        // All tickets assigned to the template
        $this->template->assign('timesheetsByTicket', $timesheetsByTicket);
        $this->template->assign('weekDays', $days);
        $this->template->assign('weekDates', $weekDates);
        $this->template->assign('ticketCacheExpiration', $ticketCacheExpiration);
        $this->template->assign('fromDate', $fromDate);
        $this->template->assign('toDate', $toDate);
        $this->template->assign('requireTimeRegistrationComment', $this->settings->getSetting('itk-leantime-timetable.requireTimeRegistrationComment') ?? 0);
        $this->template->assign('allUsers', $allUsers);
        $this->template->assign('userId', $userId);
        $this->template->assign('canCrossManage', $canCrossManage);
        return $this->template->display('TimeTable.timetable');
    }
}
