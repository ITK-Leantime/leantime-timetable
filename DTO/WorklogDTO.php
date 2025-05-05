<?php

namespace Leantime\Plugins\TimeTable\DTO;

/**
 * Data Transfer Object for work log entries
 */
class WorklogDTO
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
        private int $userId,
        private int $ticketId,
        private string $workDate,
        private float $hours,
        private string $description,
        private string $kind = 'GENERAL_BILLABLE',
        private ?int $timesheetId = null,
        private int $invoicedEmpl = 0,
        private int $invoicedComp = 0,
        private string $invoicedEmplDate = '0000-00-00 00:00:00',
        private string $invoicedCompDate = '0000-00-00 00:00:00',
        private string $rate = '0',
        private int $paid = 0,
        private string $paidDate = '0000-00-00 00:00:00',
        private string $modified = '0000-00-00 00:00:00'
    ) {
    }

    /**
     * Get the user ID
     * @return int|null User ID
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * Set the user ID
     * @param int|null $userId User ID
     * @return void
     */
    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Get the timesheet ID
     * @return int|null Timesheet ID
     */
    public function getTimesheetId(): ?int
    {
        return $this->timesheetId;
    }

    /**
     * Set the timesheet ID
     * @param int|null $timesheetId Timesheet ID
     * @return void
     */
    public function setTimesheetId(?int $timesheetId): void
    {
        $this->timesheetId = $timesheetId;
    }

    /**
     * Get the ticket ID
     * @return int|null Ticket ID
     */
    public function getTicketId(): ?int
    {
        return $this->ticketId;
    }

    /**
     * Set the ticket ID
     * @param int|null $ticketId Ticket ID
     * @return void
     */
    public function setTicketId(?int $ticketId): void
    {
        $this->ticketId = $ticketId;
    }

    /**
     * Get the work date
     * @return string|null Work date
     */
    public function getWorkDate(): ?string
    {
        return $this->workDate;
    }

    /**
     * Set the work date
     * @param string|null $workDate Work date
     * @return void
     */
    public function setWorkDate(?string $workDate): void
    {
        $this->workDate = $workDate;
    }

    /**
     * Get hours worked
     * @return float|null Hours worked
     */
    public function getHours(): ?float
    {
        return $this->hours;
    }

    /**
     * Set hours worked
     * @param float|null $hours Hours worked
     * @return void
     */
    public function setHours(?float $hours): void
    {
        $this->hours = $hours;
    }

    /**
     * Get work description
     * @return string|null Work description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set work description
     * @param string|null $description Work description
     * @return void
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * Get work kind/type
     * @return string|null Work kind
     */
    public function getKind(): ?string
    {
        return $this->kind;
    }

    /**
     * Set work kind/type
     * @param string|null $kind Work kind
     * @return void
     */
    public function setKind(?string $kind): void
    {
        $this->kind = $kind;
    }

    /**
     * Get employee invoice status
     * @return int|null Invoice status (0/1)
     */
    public function getInvoicedEmpl(): ?int
    {
        return $this->invoicedEmpl;
    }

    /**
     * Set employee invoice status
     * @param int|null $invoicedEmpl Invoice status (0/1)
     * @return void
     */
    public function setInvoicedEmpl(?int $invoicedEmpl): void
    {
        $this->invoicedEmpl = $invoicedEmpl;
    }

    /**
     * Get company invoice status
     * @return int|null Invoice status (0/1)
     */
    public function getInvoicedComp(): ?int
    {
        return $this->invoicedComp;
    }

    /**
     * Set company invoice status
     * @param int|null $invoicedComp Invoice status (0/1)
     * @return void
     */
    public function setInvoicedComp(?int $invoicedComp): void
    {
        $this->invoicedComp = $invoicedComp;
    }

    /**
     * Get employee invoice date
     * @return string|null Invoice date
     */
    public function getInvoicedEmplDate(): ?string
    {
        return $this->invoicedEmplDate;
    }

    /**
     * Set employee invoice date
     * @param string|null $invoicedEmplDate Invoice date
     * @return void
     */
    public function setInvoicedEmplDate(?string $invoicedEmplDate): void
    {
        $this->invoicedEmplDate = $invoicedEmplDate;
    }

    /**
     * Get company invoice date
     * @return string|null Invoice date
     */
    public function getInvoicedCompDate(): ?string
    {
        return $this->invoicedCompDate;
    }

    /**
     * Set company invoice date
     * @param string|null $invoicedCompDate Invoice date
     * @return void
     */
    public function setInvoicedCompDate(?string $invoicedCompDate): void
    {
        $this->invoicedCompDate = $invoicedCompDate;
    }

    /**
     * Get hourly rate
     * @return string|null Hourly rate
     */
    public function getRate(): ?string
    {
        return $this->rate;
    }

    /**
     * Set hourly rate
     * @param string|null $rate Hourly rate
     * @return void
     */
    public function setRate(?string $rate): void
    {
        $this->rate = $rate;
    }

    /**
     * Get paid status
     * @return int|null Paid status (0/1)
     */
    public function getPaid(): ?int
    {
        return $this->paid;
    }

    /**
     * Set paid status
     * @param int|null $paid Paid status (0/1)
     * @return void
     */
    public function setPaid(?int $paid): void
    {
        $this->paid = $paid;
    }

    /**
     * Get payment date
     * @return string|null Payment date
     */
    public function getPaidDate(): ?string
    {
        return $this->paidDate;
    }

    /**
     * Set payment date
     * @param string|null $paidDate Payment date
     * @return void
     */
    public function setPaidDate(?string $paidDate): void
    {
        $this->paidDate = $paidDate;
    }

    /**
     * Get last modified date
     * @return string|null Modified date
     */
    public function getModified(): ?string
    {
        return $this->modified;
    }

    /**
     * Set last modified date
     * @param string|null $modified Modified date
     * @return void
     */
    public function setModified(?string $modified): void
    {
        $this->modified = $modified;
    }
}
