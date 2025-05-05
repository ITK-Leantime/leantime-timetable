<?php

namespace Leantime\Plugins\TimeTable\DTO;

class WorklogDTO
{
    public function __construct(
        private ?int $timesheetId = null,
        private int $userId,
        private int $ticketId,
        private string $workDate,
        private float $hours,
        private string $description,
        private string $kind = 'GENERAL_BILLABLE',
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
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public function getTimesheetId(): ?int
    {
        return $this->timesheetId;
    }

    public function setTimesheetId(?int $timesheetId): void
    {
        $this->timesheetId = $timesheetId;
    }

    public function getTicketId(): ?int
    {
        return $this->ticketId;
    }

    public function setTicketId(?int $ticketId): void
    {
        $this->ticketId = $ticketId;
    }

    public function getWorkDate(): ?string
    {
        return $this->workDate;
    }

    public function setWorkDate(?string $workDate): void
    {
        $this->workDate = $workDate;
    }

    public function getHours(): ?float
    {
        return $this->hours;
    }

    public function setHours(?float $hours): void
    {
        $this->hours = $hours;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function setKind(?string $kind): void
    {
        $this->kind = $kind;
    }

    public function getInvoicedEmpl(): ?int
    {
        return $this->invoicedEmpl;
    }

    public function setInvoicedEmpl(?int $invoicedEmpl): void
    {
        $this->invoicedEmpl = $invoicedEmpl;
    }

    public function getInvoicedComp(): ?int
    {
        return $this->invoicedComp;
    }

    public function setInvoicedComp(?int $invoicedComp): void
    {
        $this->invoicedComp = $invoicedComp;
    }

    public function getInvoicedEmplDate(): ?string
    {
        return $this->invoicedEmplDate;
    }

    public function setInvoicedEmplDate(?string $invoicedEmplDate): void
    {
        $this->invoicedEmplDate = $invoicedEmplDate;
    }

    public function getInvoicedCompDate(): ?string
    {
        return $this->invoicedCompDate;
    }

    public function setInvoicedCompDate(?string $invoicedCompDate): void
    {
        $this->invoicedCompDate = $invoicedCompDate;
    }

    public function getRate(): ?string
    {
        return $this->rate;
    }

    public function setRate(?string $rate): void
    {
        $this->rate = $rate;
    }

    public function getPaid(): ?int
    {
        return $this->paid;
    }

    public function setPaid(?int $paid): void
    {
        $this->paid = $paid;
    }

    public function getPaidDate(): ?string
    {
        return $this->paidDate;
    }

    public function setPaidDate(?string $paidDate): void
    {
        $this->paidDate = $paidDate;
    }

    public function getModified(): ?string
    {
        return $this->modified;
    }

    public function setModified(?string $modified): void
    {
        $this->modified = $modified;
    }
}
