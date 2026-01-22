<?php

namespace Leantime\Plugins\TimeTable\DTO;

/**
 * Data Transfer Object for ticket context menu actions
 */
readonly class TicketContextMenuDTO
{
    /**
     * @param int         $ticketId       ID of the ticket being modified
     * @param int         $status         New status value for the ticket
     * @param int         $manageAsUserId User ID if managing as different user
     * @param string|null $dateToFinish   Due date for the ticket
     * @param string|null $tags           Comma-separated tags for the ticket
     */
    public function __construct(
        public int $ticketId,
        public int $status,
        public int $manageAsUserId,
        public ?string $dateToFinish = '0000-00-00 00:00:00',
        public ?string $tags = ''
    ) {
    }
}
