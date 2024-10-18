<?php

namespace Leantime\Plugins\TimeTable\Controllers;

use Carbon\CarbonImmutable;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use mysql_xdevapi\Exception;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Plugins\TimeTable\Services\TimeTable as TimeTableService;
use Leantime\Core\Language as LanguageCore;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Leantime\Domain\Timesheets\Repositories\Timesheets as TimesheetRepository;
use Leantime\Core\Template;

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

    /**
     * @return Response
     * @throws \Exception
     */
    public function post(): Response
    {
        if (!AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->template->displayJson(['Error' => 'Not Authorized'], 403);
        }
        $jsonPayload = json_decode(file_get_contents('php://input'), true);
        if (isset($jsonPayload['action']) && $jsonPayload['action'] === 'delete') {
            $timesheetId = $jsonPayload['timesheetId'];
            if ($timesheetId) {
                try {
                    $this->timesheetRepository->deleteTime($timesheetId);
                    exit(json_encode(['status' => 'success']));
                } catch (Exception $e) {
                    exit(json_encode(['status' => 'error', 'error' => $e->getMessage()]));
                }
            }
        }
        if (isset($_POST['timesheet-id']) && $_POST['timesheet-id'] !== '') {
            $workDate = new CarbonImmutable($_POST['timesheet-date'], session('usersettings.timezone'));
            $workDate = $workDate->setToDbTimezone();
            $values = [
                'workDate' => $workDate,
                'hours' => $_POST['timesheet-hours'],
                'description' => $_POST['timesheet-description'],
                'id' => $_POST['timesheet-id'],
            ];
            $this->timeTableService->updateTime($values);
        } else {
            $workDate = new CarbonImmutable($_POST['timesheet-date'], session('usersettings.timezone'));
            $workDate = $workDate->setToDbTimezone();

            $values = [
                'userId' => session('userdata.id'),
                'hours' => $_POST['timesheet-hours'],
                'workDate' => $workDate,
                'ticketId' => $_POST['timesheet-ticket-id'],
                'description' => $_POST['timesheet-description'],
                'kind' => 'GENERAL_BILLABLE',
            ];
            $this->timeTableService->logTimeOnTicket($values);
        }

        $redirectUrl = BASE_URL . '/TimeTable/TimeTable';
        if (isset($_GET['offset'])) {
            $redirectUrl = $redirectUrl . '?offset=' . $_GET['offset'];
        }

        return Frontcontroller::redirect($redirectUrl);
    }

    /**
     * get
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function get(): Response
    {
        // Filters for the sql select
        $userIdForFilter = null;
        $searchTermForFilter = null;
        $now = CarbonImmutable::now();
        $ticketCacheExpiration = $this->settings->getSetting('itk-leantime-timetable.ticketCacheExpiration') ?? 1200;

        if (isset($_GET['searchTerm'])) {
            $searchTerm = $_GET['searchTerm'];
        }

        if (isset($_GET['offset'])) {
            // Multiply offset by 7 days.
            if ((int) $_GET['offset'] > 0) {
                $now = $now->addDays((int) $_GET['offset'] * 7);
            } else {
                $now = $now->subDays(abs((int) $_GET['offset']) * 7);
            }
        }
        if (isset($_GET['searchTerm']) && $_GET['searchTerm'] !== '') {
            $searchTermForFilter = $_GET['searchTerm'];
        }

        $weekStartDateDb = $now->startOfWeek()->setToDbTimezone();
        $weekEndDateDb = $now->endOfWeek()->setToDbTimezone();
        $weekStartDateFrontend = $now->startOfWeek()->setToUserTimezone();

        $this->template->assign('currentSearchTerm', $searchTermForFilter);

        $days = explode(',', $this->language->__('language.dayNames'));
        // Make the first day of week monday, by shifting sunday to the back of the array.
        $days[] = array_shift($days);
        $weekDates = [];
        foreach ($days as $key => $day) {
            $weekDates[$key] = $weekStartDateFrontend->addDays($key);
        }
        $relevantTicketIds = $this->timeTableService->getUniqueTicketIds($weekStartDateDb, $weekEndDateDb);

        $timesheetsByTicket = [];
        $ticketIds = [];
        foreach ($relevantTicketIds as $ticket) {
            if (!$ticket['ticketId']) {
                continue;
            }
            $ticketIds[] = intval($ticket['ticketId']);
            $timesheetsSortedByWeekdate = [];
            foreach ($weekDates as $weekDate) {
                $timesheetsByTicketAndDate = $this->timeTableService->getTimesheetByTicketIdAndWorkDate($ticket['ticketId'], $weekDate->setToDbTimezone(), $searchTermForFilter);

                $timesheetsSortedByWeekdate[$weekDate->format('Y-m-d')] = $timesheetsByTicketAndDate;
                if (count($timesheetsByTicketAndDate) > 0) {
                    $timesheetsSortedByWeekdate['ticketTitle'] = $timesheetsByTicketAndDate[0]['headline'];
                    $timesheetsSortedByWeekdate['ticketLink'] = '?showTicketModal=' . $timesheetsByTicketAndDate[0]['ticketId'] . '#/tickets/showTicket/' . $timesheetsByTicketAndDate[0]['ticketId'];
                    $timesheetsSortedByWeekdate['projectName'] = $timesheetsByTicketAndDate[0]['name'];
                    $timesheetsSortedByWeekdate['ticketId'] = $timesheetsByTicketAndDate[0]['ticketId'];
                }
            }

            $timesheetsByTicket[$ticket['ticketId']] = $timesheetsSortedByWeekdate;
        }
        // All tickets assignet to the template
        $this->template->assign('ticketIds', implode(',', $ticketIds));
        $this->template->assign('timesheetsByTicket', $timesheetsByTicket);
        $this->template->assign('weekDays', $days);
        $this->template->assign('weekDates', $weekDates);
        $this->template->assign('ticketCacheExpiration', $ticketCacheExpiration);

        return $this->template->display('TimeTable.timetable');
    }
}
