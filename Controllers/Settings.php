<?php

namespace Leantime\Plugins\TimeTable\Controllers;

use Leantime\Core\UI\Template;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;

/**
 * Settings Controller for TimeTable plugin
 *
 * @package    leantime
 * @subpackage plugins
 */
class Settings extends Controller
{
    private SettingRepository $settingsRepo;
    protected Template $template;

    /**
     * constructor
     * @access public
     *
     * @return void
     */
    public function init(SettingRepository $settingsRepo, Template $template): void
    {
        $this->settingsRepo = $settingsRepo;
        $this->template = $template;
    }

    /**
     * get method
     *
     * @return Response
     * @throws \Exception
     */
    public function get(): Response
    {
        try {
            $requireTimeRegistrationComment = ($this->settingsRepo->getSetting('itk-leantime-timetable.requireTimeRegistrationComment') ?: '0');
            $this->template->assign('requireTimeRegistrationComment', $requireTimeRegistrationComment);
        } catch (\Exception $e) {
            $this->template->setNotification('An error occurred while saving the settings. ' . $e, 'error');
        }

        return $this->template->display('TimeTable.settings');
    }

    /**
     * post method
     *
     * @param array<string, string> $params The parameters received in the request
     * @return RedirectResponse
     * @throws \Exception
     */
    public function post(array $params): RedirectResponse
    {
        try {
            // For requireTimeRegistrationComment:  0 is false, 1 is true.
            $this->settingsRepo->saveSetting('itk-leantime-timetable.requireTimeRegistrationComment', ($params['requireTimeRegistrationComment'] ?? '0'));
            $this->template->setNotification('The settings were successfully saved.', 'success');
        } catch (\Exception $e) {
            $this->template->setNotification('An error occurred while saving the settings. ' . $e, 'error');
        }


        return Frontcontroller::redirect(BASE_URL . '/TimeTable/settings');
    }
}
