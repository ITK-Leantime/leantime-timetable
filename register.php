<?php

use Leantime\Domain\Setting\Services\Setting;
use Leantime\Plugins\TimeTable\Middleware\GetLanguageAssets;
use Leantime\Core\Events\EventDispatcher;
use Leantime\Plugins\TimeTable\Services\TimeTable as TimeTableService;

/**
 * Adds a menu point for adding fixture data.
 *
 * @param  array<string, array<int, array<string, mixed>>> $menuStructure The existing menu structure to which the new item will be added.
 * @return array<string, array<int, array<string, mixed>>> The modified menu structure with the new item added.
 */
function addTimeTableItemToMenu(array $menuStructure): array
{
    // In the menu array, timesheets occupies spot 15 in the array list, which menupoints are sorted by. TimeTable should be right after it.
    $menuStructure['personal'][16] = [
        'type' => 'item',
        'title' => '<span class="fas fa-fw fa-table"></span> Timetable',
        'icon' => 'fa fa-fw fa-table',
        'tooltip' => 'View TimeTable',
        'href' => '/TimeTable/TimeTable',
        'active' => ['TimeTable'],
        'module' => 'TimeTable', // First part of the path when visiting your plugin e.g. leantime.dk/{this part}/TimeTable
    ];

    return $menuStructure;
}

/**
 * Adds TimeTable to the personal menu
 * @param array<string, array<int, array<string, mixed>>> $sections The sections in the menu is to do with which menu is displayed on the current page.
 * @return array<string, string> - the sections array, where TimeTable.timetable is in the "personal" menu.
 */
function displayPersonalMenuOnEnteringTimeTable(array $sections): array
{
    $sections['TimeTable.TimeTable'] = 'personal';

    return $sections;
}

if (class_exists(EventDispatcher::class)) {
// https://github.com/Leantime/plugin-template/blob/main/register.php#L43-L46

    EventDispatcher::add_filter_listener(
        'leantime.core.http.httpkernel.handle.plugins_middleware',
        fn(array $middleware) => array_merge($middleware, [GetLanguageAssets::class]),
    );

    EventDispatcher::add_filter_listener('leantime.domain.menu.repositories.menu.getMenuStructure.menuStructures', 'addTimeTableItemToMenu');
    EventDispatcher::add_filter_listener('leantime.domain.menu.repositories.menu.getSectionMenuType.menuSections', 'displayPersonalMenuOnEnteringTimeTable');

    EventDispatcher::add_event_listener(
        'leantime.core.template.tpl.*.afterScriptLibTags',
        function () {
            if (null !== (session('userdata.id')) && str_contains($_SERVER['REQUEST_URI'], '/TimeTable/TimeTable')) {
                $timeTableUrl = '/dist/js/plugin-timeTable.js?' . http_build_query(['v' => '%%VERSION%%']);
                echo '<script type="module" src="' . htmlspecialchars($timeTableUrl) . '"></script>';
                $timeTableStyle = '/dist/css/plugin-timeTable.css?' . http_build_query(['v' => '%%VERSION%%']);
                echo '<link rel="stylesheet" href="' . htmlspecialchars($timeTableStyle) . '"></link>';
                $userId = htmlspecialchars(session('userdata.id'), ENT_QUOTES, 'UTF-8');
                $requireTimeRegistrationComment = app()->make(Setting::class)->getSetting('itk-leantime-timetable.requireTimeRegistrationComment') ?: '0';
                $timeTableService = app()->make(TimeTableService::class);
                $allStateLabels = $timeTableService->getAllStateLabels();
                $allTags = $timeTableService->getAllUniqueTags();

                // Get status translations
                $statusTranslations = [
                    'status.new' => __('status.new'),
                    'status.blocked' => __('status.blocked'),
                    'status.in_progress' => __('status.in_progress'),
                    'status.waiting_for_approval' => __('status.waiting_for_approval'),
                    'status.done' => __('status.done'),
                    'status.archived' => __('status.archived'),
                ];

                echo '<script>';
                echo 'const timetableSettings = ' . json_encode([
                        'settings' => [
                            'userId' => $userId,
                            'requireTimeRegistrationComment' => $requireTimeRegistrationComment,
                            'allStateLabels' => json_encode($allStateLabels),
                            'allTags' => $allTags,
                            'statusTranslations' => $statusTranslations,
                        ],
                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                echo '</script>';
            }
        },
        5
    );
}
