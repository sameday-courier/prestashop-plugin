<?php
/**
 * PrestaShop 9+ cron status-sync endpoint (modules/*.php is blocked by .htaccess).
 *
 * @since 1.8.8
 */
class SamedaycourierSyncModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ssl = true;

    /** @var bool */
    public $ajax = true;

    public function init()
    {
        header('X-Robots-Tag: noindex, nofollow');

        parent::init();
    }

    public function initContent()
    {
        // Intentionally empty — sync handler emits plain text.
    }

    public function display()
    {
        // Handlers die() with their own output; avoid theme rendering.
    }

    public function postProcess()
    {
        include_once dirname(dirname(__DIR__)) . '/libs/sameday-php-sdk/src/Sameday/autoload.php';
        include_once dirname(dirname(__DIR__)) . '/classes/autoload.php';

        SamedaySyncHandler::run();
    }
}
