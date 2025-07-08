<?php //  updated download code.

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

    // Server-Portal-ID: 771826 - Last modified: 12.03.2025 14:25:51 UTC - User: 1

    // Script here
    public $baseUrl = 'https://app.xano.com/login';
    public $loginUrl = 'https://app.xano.com/login';
    public $invoicePageUrl = 'https://app.xano.com/billing';

    public $username_selector = 'input[name="account"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'div.toast-header';
    public $check_login_success_selector = 'a[data-pw="nav-admin-billing"],a[class="dropdown-toggle nav-link"] ';
    public $isNoInvoice = true;

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        sleep(3);
        $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->type_key_by_xdotool('F5');
            sleep(5);
            $this->fillForm(0);
            $this->exts->waitTillAnyPresent([$this->check_login_success_selector, $this->check_login_failed_selector, 'input[name="code_2fa"]']);
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'access denied') !== false) {
                $this->exts->log("Site blocked");
                $this->exts->capture('2-access-denied');
                $this->clearChrome();
                $this->exts->openUrl($this->loginUrl);
                $this->exts->loginFailure(1);
                $this->exts->type_key_by_xdotool('F5');
                sleep(5);
                $this->fillForm(0);
            }
            $this->checkFillTwoFactor();
            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(4);

            // if ($this->exts->exists('app-page-header-actions button:last-child')) {
            //     $this->exts->click_element('app-page-header-actions button:last-child');
            //     sleep(3);
            // }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#pages").querySelector("#clearFromBasic").shadowRoot.querySelector("#dropdownMenu").value = 4;');
        sleep(1);
        $this->exts->capture("clear-page");
        $this->exts->execute_javascript('document.querySelector("settings-ui").shadowRoot.querySelector("settings-main").shadowRoot.querySelector("settings-basic-page").shadowRoot.querySelector("settings-privacy-page").shadowRoot.querySelector("settings-clear-browsing-data-dialog").shadowRoot.querySelector("#clearButton").click();');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            if ($this->exts->check_exist_by_chromedevtool($this->username_selector) != null) {

                $this->exts->capture_by_chromedevtool("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->click_by_xdotool($this->username_selector);
                $this->exts->type_key_by_xdotool('Ctrl+a');
                $this->exts->type_key_by_xdotool('Delete');
                $this->exts->type_text_by_xdotool($this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->click_by_xdotool($this->password_selector);
                $this->exts->type_key_by_xdotool('Ctrl+a');
                $this->exts->type_key_by_xdotool('Delete');
                $this->exts->type_text_by_xdotool($this->password);
                sleep(1);

                $this->exts->capture_by_chromedevtool("1-login-page-filled");
                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(5);
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[name="code_2fa"]';
        $two_factor_message_selector = 'p.intro';
        $two_factor_submit_selector = 'button[type="submit"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(5);

                if ($this->exts->getElement($two_factor_selector) == null) {
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

    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }


        return $isLoggedIn;
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(3);
        // Wait until the invoice items are present
        $this->exts->waitTillPresent('app-sort-table div.table-row', 30);
        $this->exts->capture("4-invoices-page");

        $invoices = [];
        // Select all the invoice items
        $invoiceItems = $this->exts->getElements('app-sort-table div.table-row');

        foreach ($invoiceItems as $item) {

            try {
                $item->click();
            } catch (\Exception $exception) {
                $this->exts->execute_javascript("arguments[0].click();", [$item]);
            }
            sleep(10);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
