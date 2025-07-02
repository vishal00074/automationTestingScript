<?php

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package uwa
 *
 * @copyright   GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/remote-chrome/utils/');

$gmi_browser_core = realpath('/var/www/remote-chrome/utils/GmiChromeManager.php');
require_once($gmi_browser_core);
class PortalScriptCDP
{

    private $exts;
    public $setupSuccess = false;
    private $chrome_manage;
    private $username;
    private $password;

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/remote-chrome/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $username, $password);
        $this->setupSuccess = true;
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            try {
                // Start portal script execution
                $this->initPortal(0);
            } catch (\Exception $exception) {
                $this->exts->log('Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }


            $this->exts->log('Execution completed');

            $this->exts->process_completed();
            $this->exts->dump_session_files();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    public $baseUrl = 'https://www.unitymedia.de/kundencenter/meine-rechnungen/alle-rechnungen/';
    public $loginUrl = 'https://www.unitymedia.de/benutzerkonto/login/zugangsdaten';
    public $invoicePageUrl = 'https://www.unitymedia.de/kundencenter/meine-rechnungen/alle-rechnungen/';
    public $username_selector = 'form.lgi-form.lgi-oim-form input[name="userId"], input#txtUsername';
    public $password_selector = 'form.lgi-form.lgi-oim-form input[name="password"], input#txtPassword';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form.lgi-form.lgi-oim-form button.upc_button6, .login-onelogin button[type="submit"]';
    public $check_login_failed_selector = 'form > div.login-onelogin p.notification-text';
    public $check_login_success_selector = '.indicator[style="display: block;"], a[href="/logout"]';
    public $capt_image_selector = "form ol-captcha img";
    public $capt_input_selector = "input#captchaField";
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int) $this->exts->config_array["login_with_google"] : $this->login_with_google;
        $this->login_with_apple = isset($this->exts->config_array["login_with_apple"]) ? (int) $this->exts->config_array["login_with_apple"] : $this->login_with_apple;

        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('button#dip-consent-summary-accept-all')) {
                $this->exts->moveToElementAndClick('button#dip-consent-summary-accept-all');
                sleep(5);
            } else {
                $this->exts->log('Not found selector button#dip-consent-summary-accept-all');
            }
            $this->checkSolveCaptcha();
            $this->checkFillLogin();
            sleep(20);
            $this->checkSolveCaptcha();
            $this->checkFillTwoFactor();
        }

        // then check user logged in or not
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElementByCssSelector($this->check_login_success_selector) == null; $wait_count++) {
        // 	$this->exts->log('Waiting for login...');
        // 	sleep(5);
        // }
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            // $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            // if ($restrictPages == 0) {
            // 	$this->exts->openUrl('https://www.unitymedia.de/kundencenter/meine-rechnungen/alle-rechnungen?months=12');
            // }else{
            // 	$this->exts->openUrl($this->invoicePageUrl);
            // }
            $this->exts->waitTillPresent('a[automation-id="meineRechnungen_Link"]', 100);
            if ($this->exts->exists('a[automation-id="meineRechnungen_Link"]')) {
                $this->exts->click_element('a[automation-id="meineRechnungen_Link"]');
                sleep(10);
            } else {
                $this->exts->log('Not foun selector a[automation-id="meineRechnungen_Link"]');
            }


            if ($this->exts->exists('div.tiles  > tile-component:nth-child(4)  a')) {
                $this->exts->click_element('div.tiles  > tile-component:nth-child(4)  a');
            }


            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos($this->exts->extract($this->check_login_failed_selector), 'bitte Deine Eingabe') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent('form.lgi-form.lgi-oim-form button.upc_button6, .login-onelogin button[type="submit"]', 100);
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#totpcontrol';
        $two_factor_message_selector = 'p[automation-id="totpcodeTxt_tv"]';
        $two_factor_submit_selector = '[automation-id="SUBMITCODEBTN_btn"] button.login-btn[type="submit"]';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    public function checkSolveCaptcha()
    {
        for ($i = 0; $i < 30 && $this->exts->urlContains('/captcha'); $i++) {
            $this->exts->processCaptcha('form[action*="/captcha"] img.captcha', 'form[action*="/captcha"] input#captchaField');
            $this->exts->capture('captcha-filled');
            $this->exts->moveToElementAndClick('form[action*="/captcha"] [type="submit"]');
            sleep(10);
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent("//button[normalize-space(text())='Mehr anzeigen']", 100);
        $maxAttempts = 10;
        $attempt = 0;
        while ($attempt < $maxAttempts && $this->exts->exists("//button[normalize-space(text())='Mehr anzeigen']")) {
            $this->exts->click_element("//button[normalize-space(text())='Mehr anzeigen']");
            $attempt++;
            sleep(5);
        }

        if ($this->exts->querySelector('button.gdpr_accept_all , button#dip-consent-summary-accept-all') != null) {
            $this->exts->moveToElementAndClick('button.gdpr_accept_all , button#dip-consent-summary-accept-all');
            sleep(2);
        }
        if ($this->exts->querySelector('button#kplDeferButton') != null) {
            $this->exts->moveToElementAndClick('button#kplDeferButton');
            sleep(2);
        }

        $this->exts->waitTillPresent('table tbody tr', 50);
        sleep(10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(4) > div span:nth-child(2) svg', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = '';
                $invoiceAmount = $this->exts->extract('td:nth-child(2)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);
                $downloadBtn = $this->exts->querySelector('td:nth-child(4) > div span:nth-child(2) svg', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            // $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            // $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'y-m-d', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            sleep(2);
            $this->exts->click_element($invoice['downloadBtn']);
            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);

            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
