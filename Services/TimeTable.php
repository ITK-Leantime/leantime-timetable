<?php

namespace Leantime\Plugins\TimeTable\Services;

use Carbon\CarbonInterface;
use Leantime\Plugins\TimeTable\DTO\TicketContextMenuDTO;
use Leantime\Plugins\TimeTable\DTO\WorklogDTO;
use Leantime\Plugins\TimeTable\Repositories\TimeTable as TimeTableRepository;

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
     * @return void
     */
    public function __construct(TimeTableRepository $timeTableRepo)
    {
        $this->timeTableRepo = $timeTableRepo;
    }

    /**
     * Install plugin.
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
     */
    public function updateOrAddTimelogOnTicket(WorklogDTO $worklog, int $originalId): void
    {
        $this->timeTableRepo->updateOrAddTimelogOnTicket($worklog, $originalId);
    }

    /**
     * Adds a timelog to a ticket.
     *
     * @param WorklogDTO $worklog
     * @param bool       $shouldOverwrite
     * @return void
     */
    public function addTimelogOnTicket(WorklogDTO $worklog, bool $shouldOverwrite)
    {
        $this->timeTableRepo->addTimelogOnTicket($worklog, $shouldOverwrite);
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

    public function getAllStateLabels(): array
    {
        $statusListSeed = $this->timeTableRepo->statusListSeed;

        return $this->timeTableRepo->getAllStateLabels($statusListSeed);
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
     * Retrieves all projects that the user has access to from the timetable repository.
     *
     * @param  int    $userId   The user ID to check project access for
     * @param  string $clientId The client ID of the user
     * @return string[] the list of projects or an empty array.
     */
    public function getAllProjects(int $userId, string $clientId): array
    {
        $projects = $this->timeTableRepo->getAllProjects($userId, $clientId);
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

    public function modifyTicketDetails(TicketContextMenuDTO $ticketContextMenuDTO)
    {
        $this->timeTableRepo->modifyTicketDetails($ticketContextMenuDTO);
    }

    /**
     * Get all unique tags from the system
     *
     * @return array Array of unique tag strings
     *
     * @api
     */
    public function getAllUniqueTags(): array
    {
        return $this->timeTableRepo->getAllUniqueTags();
    }

    /**
     * Get recently viewed ticket IDs for a user from tickethistory
     *
     * @param int $userId The user ID
     * @param int $limit  Maximum number of tickets to return
     * @return array Array of ticket IDs ordered by most recent first
     */
    public function getRecentlyViewedTicketIds(int $userId, int $limit = 20): array
    {
        return $this->timeTableRepo->getRecentlyViewedTicketIds($userId, $limit);
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
