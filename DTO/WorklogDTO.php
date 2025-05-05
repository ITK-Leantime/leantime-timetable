<?php

namespace Leantime\Plugins\TimeTable\DTO;

/**
 * Data Transfer Object for work log entries
 */
readonly class WorklogDTO
{
    /**
     * @param int      $userId           User ID who logged the work
     * @param int      $ticketId         Associated ticket ID
     * @param string   $workDate         Date when work was performed
     * @param float    $hours            Hours worked
     * @param string   $description      Description of work performed
     * @param string   $kind             Type of work (default: GENERAL_BILLABLE)
     * @param int|null $timesheetId      Optional timesheet ID
     * @param int      $invoicedEmpl     Invoice status for employee (0/1)
     * @param int      $invoicedComp     Invoice status for company (0/1)
     * @param string   $invoicedEmplDate Date employee was invoiced
     * @param string   $invoicedCompDate Date company was invoiced
     * @param string   $rate             Hourly rate
     * @param int      $paid             Payment status (0/1)
     * @param string   $paidDate         Date payment was made
     * @param string   $modified         Last modified timestamp
     */
    public function __construct(
        public int $userId,
        public int $ticketId,
        public string $workDate,
        public float $hours,
        public string $description,
        public string $kind = 'GENERAL_BILLABLE',
        public ?int $timesheetId = null,
        public int $invoicedEmpl = 0,
        public int $invoicedComp = 0,
        public string $invoicedEmplDate = '0000-00-00 00:00:00',
        public string $invoicedCompDate = '0000-00-00 00:00:00',
        public string $rate = '0',
        public int $paid = 0,
        public string $paidDate = '0000-00-00 00:00:00',
        public string $modified = '0000-00-00 00:00:00'
    ) {}
}
