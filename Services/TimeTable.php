<?php

namespace Leantime\Plugins\TimeTable\Services;

use Carbon\CarbonInterface;
use Leantime\Plugins\TimeTable\DTO\WorklogDTO;
use Leantime\Plugins\TimeTable\Repositories\TimeTable as TimeTableRepository;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;

/**
 * Time table services file.
 */
class TimeTable
{
    private TimeTableRepository $timeTableRepo;

    /**
     * @var array<string, string>
     */
    private static array $assets = [
        // source => target
        __DIR__ . '/../dist/js/timeTable.js' => APP_ROOT . '/public/dist/js/plugin-timeTable.js',
        __DIR__ . '/../dist/css/timeTable.css' => APP_ROOT . '/public/dist/css/plugin-timeTable.css',
    ];

    /**
     * constructor
     *
     * @param  TimeTableRepository $timeTableRepo
     * @return void
     */
    public function __construct(TimeTableRepository $timeTableRepo)
    {
        $this->timeTableRepo = $timeTableRepo;
    }

    /**
     * Install plugin.
     *
     * @return void
     */
    public function install(): void
    {
        foreach (self::getAssets() as $source => $target) {
            if (file_exists($target)) {
                unlink($target);
            }
            symlink($source, $target);
        }
    }

    /**
     * Uninstall plugin.
     *
     * @return void
     */
    public function uninstall(): void
    {
        foreach (self::getAssets() as $target) {
            if (file_exists($target)) {
                unlink($target);
            }
        }
    }

    /**
     * Get assets
     *
     * @return array|string[]
     */
    private static function getAssets(): array
    {
        return self::$assets;
    }

    /**
     * @return array<array<string, string>>
     */
    public function getUniqueTicketIds(CarbonInterface $dateFrom, CarbonInterface $dateTo, int $userId): array
    {
        return $this->timeTableRepo->getUniqueTicketIds($dateFrom, $dateTo, $userId);
    }

    /**
     * @return array<array<string, string>>
     */
    public function getTimesheetByTicketIdAndWorkDate(string $ticketId, CarbonInterface $workDate, int $userId, ?string $searchTerm): array
    {
        return $this->timeTableRepo->getTimesheetByTicketIdAndWorkDate($ticketId, $workDate, $userId, $searchTerm);
    }

    /**
     * updateTime - update specific time entry
     * @param WorklogDTO $worklog
     * @return void
     */
    public function updateOrAddTimelogOnTicket(WorklogDTO $worklog, int $originalId): void
    {
        $this->timeTableRepo->updateOrAddTimelogOnTicket($worklog, $originalId);
    }

    /**
     * Adds a timelog to a ticket.
     *
     * @param array<string, mixed> $values The data required to add the timelog on the ticket.
     * @return void
     */
    public function addTimelogOnTicket(array $values)
    {
        $this->timeTableRepo->addTimelogOnTicket($values);
    }

    /**
     * Retrieves all users from the repository.
     *
     * @return array<string, string> List of users.
     */
    public function getAllUsers(): array
    {
        return $this->timeTableRepo->getAllUsers();
    }

    /**
     * Retrieves all tickets from the timetable service and returns them as a JSON response.
     *
     * @return array<string,mixed> the list of tickets or an empty array.
     */
    public function getAllTickets(): array
    {
        $tickets = $this->timeTableRepo->getAllTickets();

        $formattedTickets = [
            'children' => array_map(function ($ticket) {
                return [
                    'id' => $ticket['id'],
                    'text' => $ticket['headline'],
                    'type' => $ticket['type'],
                    'tags' => $ticket['tags'],
                    'projectName' => $ticket['projectName'] ?? 'Removed project',
                    'projectId' => $ticket['projectId'],
                    'editorId' => $ticket['editorId'],
                    'hoursLeft' => $ticket['hourRemaining'],
                    'createdDate' => $ticket['date'],
                ];
            }, $tickets),
        ];

        return $formattedTickets;
    }

    /**
     * Retrieves all projects from the timetable repository.
     *
     * @return string[]  the list of pronjects or an empty array.
     */
    public function getAllProjects(): array
    {
        $projects = $this->timeTableRepo->getAllProjects();
        $projectGroup = [
            'id' => 'project',
            'text' => 'Projects',
            'children' => [],
            'index' => 1,
        ];

        foreach ($projects as $project) {
            $projectGroup['children'][] = [
                'id' => $project['id'],
                'text' => $project['name'],
                'type' => 'project',
                'client' => $project['clientName'] ?? null,
            ];
        }

        return $projectGroup;
    }

    /**
     * Retrieves all state labels associated with a specific project or all projects if no project ID is provided.
     *
     * @param int|null $projectId The ID of the project to filter state labels, or null for all projects.
     * @return array<int|string,array<string,mixed>> An array of state labels.
     */
    public function getAllStateLabels(int $projectId = null): array
    {
        return $this->timeTableRepo->getAllStateLabels($projectId);
    }
}
