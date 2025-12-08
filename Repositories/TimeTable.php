<?php

namespace Leantime\Plugins\TimeTable\Repositories;

use Carbon\CarbonInterface;
use Leantime\Core\Db\Db as DbCore;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Leantime\Plugins\TimeTable\DTO\WorklogDTO;
use PDO;

/**
 * This is the time table repository, that makes (hopefully) the relevant sql queries.
 */
class TimeTable
{
    /**
     * @var DbCore|null - db connection
     */
    private null|DbCore $db = null;

    private TicketRepository $ticketRepo;


    /**
     * __construct - get db connection
     *
     * @access public
     * @return void
     */
    public function __construct(DbCore $db, TicketRepository $ticketRepo)
    {
        $this->db = $db;
        $this->ticketRepo = $ticketRepo;
    }

    /**
     * @return array<array<string, string>>
     */
    public function getUniqueTicketIds(CarbonInterface $dateFrom, CarbonInterface $dateTo, int $userId): array
    {
        $sql = 'SELECT DISTINCT
        timesheet.ticketId,
        zp_tickets.headline
        FROM zp_timesheets AS timesheet
        LEFT JOIN zp_tickets ON timesheet.ticketId = zp_tickets.id
        WHERE timesheet.userId = :userId AND timesheet.workDate >= :dateFrom AND timesheet.workDate <= :dateTo
        ORDER BY zp_tickets.headline ASC';
        $stmn = $this->db->database->prepare($sql);

        if ($userId !== '') {
            $stmn->bindValue(':userId', $userId, PDO::PARAM_INT);
        }

        $stmn->bindValue(':dateFrom', $dateFrom, PDO::PARAM_STR);
        $stmn->bindValue(':dateTo', $dateTo, PDO::PARAM_STR);

        $stmn->execute();
        $values = $stmn->fetchAll();
        $stmn->closeCursor();

        return $values;
    }

    /**
     * getTimesheetByTicketIdAndWorkDate - Retrieves timesheet data based on a given ticket ID and work date,
     * optionally filtering by a search term.
     *
     * @access public
     * @param string          $ticketId   The ticket ID to filter the timesheet data.
     * @param CarbonInterface $workDate   The specific work date to filter the timesheet data.
     * @param int             $userId     The id of the user to grab data for.
     * @param string|null     $searchTerm An optional search term to further filter results by ticket ID or headline.
     * @return array<string, mixed> Returns an array of matching timesheet data.
     */
    public function getTimesheetByTicketIdAndWorkDate(string $ticketId, CarbonInterface $workDate, int $userId, ?string $searchTerm): array
    {

        $searchTermQuery = isset($searchTerm)
            ? " AND
        (zp_tickets.id LIKE CONCAT( '%', :searchTerm, '%') OR
        zp_tickets.headline LIKE CONCAT( '%', :searchTerm, '%')) "
            : '';

        $sql = 'SELECT
        timesheet.id,
        CAST(timesheet.workDate AS DATE) as workDate,
        timesheet.hours,
        timesheet.description,
        timesheet.ticketId,
        (SELECT SUM(hours) FROM zp_timesheets WHERE ticketId = zp_tickets.id) as hoursSum,
        zp_tickets.headline,
        zp_tickets.id as ticketId,
        zp_tickets.type as ticketType,
        zp_tickets.planHours,
        zp_tickets.hourRemaining,
        zp_projects.name
        FROM zp_timesheets AS timesheet
        LEFT JOIN zp_tickets ON timesheet.ticketId = zp_tickets.id
        LEFT JOIN zp_projects ON zp_tickets.projectId = zp_projects.id
        WHERE timesheet.userId = :userId AND timesheet.ticketId = :ticketId AND (timesheet.workDate BETWEEN :dateFrom AND :dateTo)' . $searchTermQuery . '
        GROUP BY timesheet.id, workDate, timesheet.description, timesheet.ticketId, zp_tickets.headline, zp_tickets.id, zp_tickets.type, zp_tickets.planHours, zp_tickets.hourRemaining, zp_projects.name';

        $stmn = $this->db->database->prepare($sql);

        if ($userId) {
            $stmn->bindValue(':userId', $userId, PDO::PARAM_INT);
        }
        if ($searchTerm !== null) {
            $stmn->bindValue(':searchTerm', $searchTerm, PDO::PARAM_STR);
        }

        $stmn->bindValue(':ticketId', $ticketId, PDO::PARAM_INT);
        $stmn->bindValue(':dateFrom', $workDate->startOfDay(), PDO::PARAM_STR);
        $stmn->bindValue(':dateTo', $workDate->endOfDay(), PDO::PARAM_STR);

        $stmn->execute();
        $values = $stmn->fetchAll();
        $stmn->closeCursor();
        return $values;
    }

    /**
     * updateOrAddTimelogOnTicket - Updates or adds a timelog entry for a ticket
     *
     * @param WorklogDTO $worklog    Worklog DTO
     * @param int|null   $originalId (Optional) The original timelog id to check for updates or deletion
     *
     * @return void
     * @access public
     */
    public function updateOrAddTimelogOnTicket(WorklogDTO $worklog, ?int $originalId = null): void
    {
        $sql = 'SELECT * FROM zp_timesheets WHERE ticketId = :ticketId AND workDate = :date AND userId = :userId';

        $stmn = $this->db->database->prepare($sql);
        $stmn->bindValue(':ticketId', $worklog->ticketId);
        $stmn->bindValue(':date', $worklog->workDate);
        $stmn->bindValue(':userId', $worklog->userId, PDO::PARAM_INT);
        $stmn->execute();

        $timesheet = $stmn->fetch(PDO::FETCH_ASSOC);
        $stmn->closeCursor();

        if ($timesheet) {
            if ($originalId && $originalId == $timesheet['id']) {
                $sql = 'UPDATE zp_timesheets SET hours = :hours, description = :description, kind = :kind,
                    invoicedEmpl = :invoicedEmpl, invoicedComp = :invoicedComp,
                    invoicedEmplDate = :invoicedEmplDate, invoicedCompDate = :invoicedCompDate,
                    rate = :rate, paid = :paid, paidDate = :paidDate, modified = :modified
                    WHERE id = :id AND userId = :userId';
            } else {
                $sql = 'UPDATE zp_timesheets SET hours = hours + :hours, description = CONCAT(description, " ", :description),
                    kind = :kind, invoicedEmpl = :invoicedEmpl, invoicedComp = :invoicedComp,
                    invoicedEmplDate = :invoicedEmplDate, invoicedCompDate = :invoicedCompDate,
                    rate = :rate, paid = :paid, paidDate = :paidDate, modified = :modified
                    WHERE id = :id AND userId = :userId';
            }

            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':id', $timesheet['id'], PDO::PARAM_INT);
            $stmn->bindValue(':hours', $worklog->hours);
            $stmn->bindValue(':userId', $worklog->userId, PDO::PARAM_INT);
            $stmn->bindValue(':description', $worklog->description);
            $stmn->bindValue(':kind', $worklog->kind);
            $stmn->bindValue(':invoicedEmpl', $worklog->invoicedEmpl, PDO::PARAM_INT);
            $stmn->bindValue(':invoicedComp', $worklog->invoicedComp, PDO::PARAM_INT);
            $stmn->bindValue(':invoicedEmplDate', $worklog->invoicedEmplDate);
            $stmn->bindValue(':invoicedCompDate', $worklog->invoicedCompDate);
            $stmn->bindValue(':rate', $worklog->rate);
            $stmn->bindValue(':paid', $worklog->paid, PDO::PARAM_INT);
            $stmn->bindValue(':paidDate', $worklog->paidDate);
            $stmn->bindValue(':modified', $worklog->modified);
        } else {
            // else, insert new record
            $sql = 'INSERT INTO zp_timesheets (
                userId, ticketId, workDate, hours, description, kind,
                invoicedEmpl, invoicedComp, invoicedEmplDate, invoicedCompDate,
                rate, paid, paidDate, modified
            ) VALUES (
                :userId, :ticket, :date, :hours, :description, :kind,
                :invoicedEmpl, :invoicedComp, :invoicedEmplDate, :invoicedCompDate,
                :rate, :paid, :paidDate, :modified
            )';

            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':userId', $worklog->userId, PDO::PARAM_INT);
            $stmn->bindValue(':ticket', $worklog->ticketId);
            $stmn->bindValue(':date', $worklog->workDate);
            $stmn->bindValue(':kind', $worklog->kind);
            $stmn->bindValue(':description', $worklog->description);
            $stmn->bindValue(':hours', $worklog->hours);
            $stmn->bindValue(':invoicedEmpl', $worklog->invoicedEmpl, PDO::PARAM_INT);
            $stmn->bindValue(':invoicedComp', $worklog->invoicedComp, PDO::PARAM_INT);
            $stmn->bindValue(':invoicedEmplDate', $worklog->invoicedEmplDate);
            $stmn->bindValue(':invoicedCompDate', $worklog->invoicedCompDate);
            $stmn->bindValue(':rate', $worklog->rate);
            $stmn->bindValue(':paid', $worklog->paid, PDO::PARAM_INT);
            $stmn->bindValue(':paidDate', $worklog->paidDate);
            $stmn->bindValue(':modified', $worklog->modified);
        }

        $stmn->execute();
        $stmn->closeCursor();

        if ($originalId && (empty($timesheet) || $worklog->workDate == $timesheet['workDate'] && $worklog->timesheetId != $timesheet['id'])) {
            $sql = 'DELETE FROM zp_timesheets WHERE id = :id AND userId = :userId';
            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':id', $originalId, PDO::PARAM_INT);
            $stmn->bindValue(':userId', $worklog->userId, PDO::PARAM_INT);
            $stmn->execute();
            $stmn->closeCursor();
        }
    }

    /**
     * addTimelogOnTicket - Adds a timelog entry for a specific ticket.
     * If an entry for the same date, ticket, and user already exists, it checks
     * whether the entry should be overwritten or prevents duplicate insertion.
     *
     * @param WorklogDTO $worklogDTO
     * @param bool       $shouldOverwrite
     * @return void
     */
    public function addTimelogOnTicket(WorklogDTO $worklogDTO, bool $shouldOverwrite = false): void
    {
        // Check for an existing timelog
        $sql = 'SELECT id FROM zp_timesheets WHERE ticketId = :ticketId AND workDate = :date AND userId = :userId';
        $stmn = $this->db->database->prepare($sql);
        $stmn->bindValue(':ticketId', $worklogDTO->ticketId);
        $stmn->bindValue(':date', $worklogDTO->workDate);
        $stmn->bindValue(':userId', $worklogDTO->userId, PDO::PARAM_INT);
        $stmn->execute();

        $existingEntry = $stmn->fetch(PDO::FETCH_ASSOC);
        $stmn->closeCursor();

        // If 'entryCopyOverwrite' is set, delete the existing entry
        if ($existingEntry) {
            if ($shouldOverwrite) {
                $sql = 'DELETE FROM zp_timesheets WHERE id = :id';
                $stmn = $this->db->database->prepare($sql);
                $stmn->bindValue(':id', $existingEntry['id'], PDO::PARAM_INT);
                $stmn->execute();
                $stmn->closeCursor();
            } else {
                // If overwrite is not set, prevent duplicate addition
                return; // Exit without inserting
            }
        }

        // Insert the new timelog
        $sql = 'INSERT INTO zp_timesheets (
            userId, ticketId, workDate, hours, description, kind,
            invoicedEmpl, invoicedComp, invoicedEmplDate, invoicedCompDate,
            rate, paid, paidDate, modified
        ) VALUES (
            :userId, :ticketId, :date, :hours, :description, :kind,
            :invoicedEmpl, :invoicedComp, :invoicedEmplDate, :invoicedCompDate,
            :rate, :paid, :paidDate, :modified
        )';

        $stmn = $this->db->database->prepare($sql);
        $stmn->bindValue(':userId', $worklogDTO->userId, PDO::PARAM_INT);
        $stmn->bindValue(':ticketId', $worklogDTO->ticketId);
        $stmn->bindValue(':date', $worklogDTO->workDate);
        $stmn->bindValue(':hours', $worklogDTO->hours);
        $stmn->bindValue(':description', $worklogDTO->description);
        $stmn->bindValue(':kind', $worklogDTO->kind);
        $stmn->bindValue(':invoicedEmpl', $worklogDTO->invoicedEmpl, PDO::PARAM_INT);
        $stmn->bindValue(':invoicedComp', $worklogDTO->invoicedComp, PDO::PARAM_INT);
        $stmn->bindValue(':invoicedEmplDate', $worklogDTO->invoicedEmplDate);
        $stmn->bindValue(':invoicedCompDate', $worklogDTO->invoicedCompDate);
        $stmn->bindValue(':rate', $worklogDTO->rate);
        $stmn->bindValue(':paid', $worklogDTO->paid, PDO::PARAM_INT);
        $stmn->bindValue(':paidDate', $worklogDTO->paidDate);
        $stmn->bindValue(':modified', $worklogDTO->modified);
        $stmn->execute();
        $stmn->closeCursor();
    }

    /**
     * getAllStateLabels - Retrieves all state labels for projects based on a seed list of statuses and stored settings.
     *
     * @return array<string, array<int|string, mixed>> An associative array where keys are project IDs and values are arrays of state labels.
     */
    public function getAllStateLabels(int $projectId = null): array
    {
        // Default status object defined in app/Domain/Tickets/Repositories/Tickets.php:25
        $statusListSeed = $this->ticketRepo->statusListSeed;

        if ($projectId) {
            $sql = 'SELECT `key`, `value` FROM zp_settings WHERE `key` = :key';
            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':key', 'projectsettings.' . $projectId . '.ticketlabels', PDO::PARAM_STR);
        } else {
            $sql = 'SELECT `key`, `value` FROM zp_settings WHERE `key` LIKE :keyPattern';
            $stmn = $this->db->database->prepare($sql);
            $stmn->bindValue(':keyPattern', 'projectsettings.%.ticketlabels', PDO::PARAM_STR);
        }


        $stmn->execute();
        $results = $stmn->fetchAll(PDO::FETCH_ASSOC);
        $stmn->closeCursor();

        $allStatusLabels = [];

        foreach ($results as $row) {
            // Extract the project ID from the key
            $projectId = explode('.', $row['key'])[1];
            // Unserialize the value
            $values = @unserialize($row['value']);

            if ($values !== false) {
                $statusList = $statusListSeed;

                $statusList[-1] = $statusListSeed[-1];

                foreach ($values as $key => $status) {
                    if (is_int($key)) {
                        if (!is_array($status)) {
                            $statusList[$key] = $statusListSeed[$key];
                            if (is_array($statusList[$key]) && isset($statusList[$key]['name']) && $key !== -1) {
                                $statusList[$key]['name'] = $status;
                            }
                        } else {
                            $statusList[$key] = $status;
                        }
                    }
                }

                uasort($statusList, function ($a, $b) {
                    return $a['sortKey'] <=> $b['sortKey'];
                });

                $allStatusLabels[$projectId] = $statusList;
            }
        }

        if (!empty($allStatusLabels)) {
            // Ensure every project has a state list
            // Fetch all project IDs separately
            $projectIds = $this->getAllProjectIds();
            foreach ($projectIds as $projectId) {
                if (!isset($allStatusLabels[$projectId])) {
                    // Default to $statusListSeed if no state list exists
                    $allStatusLabels[$projectId] = $statusListSeed;
                }
            }
        }


        return $allStatusLabels;
    }

    /**
     * getAllProjectIds - Retrieve all project IDs from the database
     *
     * @access private
     * @return array<string, string> Array of project IDs
     */
    private function getAllProjectIds(): array
    {
        $sql = 'SELECT id FROM zp_projects';
        $stmn = $this->db->database->prepare($sql);
        $stmn->execute();
        // Fetch all project IDs as a 1-dimensional array
        $projectIds = $stmn->fetchAll(PDO::FETCH_COLUMN, 0);
        $stmn->closeCursor();

        return $projectIds;
    }

    /**
     * getAllUsers - Retrieves a list of all users from the database
     *
     * @access public
     * @return array<string, mixed> An array containing user details, including ID, full name, and role
     */
    public function getAllUsers(): array
    {
        $sql = 'SELECT * FROM zp_user WHERE status = "a" AND (source IS NULL OR source != "api")';
        $stmn = $this->db->database->prepare($sql);
        $stmn->execute();
        $users = $stmn->fetchAll(PDO::FETCH_ASSOC);
        $stmn->closeCursor();

        return array_map(function ($user) {
            return [
                'id' => $user['id'],
                'fullName' => $user['firstname'] . ' ' . $user['lastname'],
                'role' => $user['role'],
            ];
        }, $users);
    }

    /**
     * getAllTickets - Retrieves all tickets for the authenticated user, filters them based on their status,
     * and includes additional information such as project details and the last work date.
     *
     * @access public
     * @return array<int<0, max>,mixed> An array of filtered tickets with their associated details.
     */
    public function getAllTickets(): array
    {
        $userId = session('userdata.id');
        $allStateLabels = $this->getAllStateLabels();

        $sql = 'SELECT
                t.id,
                t.headline,
                LOWER(t.type) as type,
                t.tags,
                t.projectId,
                p.name as projectName, -- Fetch project name via JOIN
                t.editorId,
                t.hourRemaining,
                t.date
            FROM zp_tickets t
            LEFT JOIN zp_projects p ON t.projectId = p.id
            LEFT JOIN zp_timesheets ts ON t.id = ts.ticketId AND ts.userId = :userId
            WHERE t.type NOT IN (:story, :milestone)
            GROUP BY t.id
            ORDER BY (t.editorId = :userId) DESC, t.date DESC, id DESC';
        $stmn = $this->db->database->prepare($sql);
        $stmn->bindValue(':story', 'story', PDO::PARAM_STR);
        $stmn->bindValue(':milestone', 'milestone', PDO::PARAM_STR);
        $stmn->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmn->execute();
        $tickets = $stmn->fetchAll(PDO::FETCH_ASSOC);
        $stmn->closeCursor();

        // Pre-compute status label mappings
        $stateLabelMappings = array_map(function ($labels) {
            return array_column($labels, 'statusType');
        }, $allStateLabels);

        // Filter tickets using pre-computed mappings
        $filteredTickets = [];
        foreach ($tickets as $ticket) {
            $projectId = $ticket['projectId'] ?? null;
            $status = $ticket['status'] ?? null;
            if (!isset($stateLabelMappings[$projectId][$status]) || $stateLabelMappings[$projectId][$status] !== 'DONE') {
                $filteredTickets[] = $ticket;
            }
        }

        return $filteredTickets;
    }

    /**
     * getAllProjects - Retrieves all projects for the authenticated user
     *
     * @access public
     * @return array<array<string, mixed>> An array of projects with their associated details.
     */
    public function getAllProjects(): array
    {
        $sql = 'SELECT id, name FROM zp_projects';
        $stmn = $this->db->database->prepare($sql);
        $stmn->execute();
        $projects = $stmn->fetchAll(PDO::FETCH_ASSOC);
        $stmn->closeCursor();

        return array_map(function ($project) {
            return [
                'id' => $project['id'],
                'name' => $project['name'],
                'type' => 'project',
            ];
        }, $projects);
    }
}
