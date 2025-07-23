<?php // migrated updated download code. and updated login code

/**
 * updated login success selector replaced getElementByText with base class function updated login url
 *  added check in case invoice is empty and added totalInvoices valraiable to download only 50 invoices
 */

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

    // Server-Portal-ID: 9477 - Last modified: 02.07.2025 14:44:46 UTC - User: 1

    // Start Script 

    public $baseUrl = 'https://mijn.kpn.com/';
    public $loginUrl = 'https://mijn.kpn.com/';
    public $invoicePageUrl = 'https://mijn.kpn.com/#/facturen';
    public $username_selector = 'input[id="e-mail"]';
    public $password_selector = 'input[id="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[data-test="loginForm"] kpn-button[type="submit"]';
    public $check_login_failed_selector = 'div[data-test="loginRegister"] [variant="error"]';
    public $check_login_success_selector = 'a[href="#/profiel"]';
    public $isNoInvoice = true;
    public $err_msg = '';

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);

            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
            sleep(5);
            $this->checkFillLogin();
            sleep(20);
            $this->checkFillTwoFactor();
            sleep(10);
            $tab_buttons = $this->exts->getElementByText('button[type="submit"]', 'ga verder');
            if ($tab_buttons != null) {
                try {
                    $this->exts->log('Click tab_buttons button');
                    $tab_buttons->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click tab_buttons button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$tab_buttons]);
                }
            }
            sleep(10);
        }

        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);

            if ($this->exts->exists('div.sc-kOHTFB.bybnmV a.sc-gsFSXq.bDjPAt')) {
                $accounts_len = count($this->exts->querySelectorAll('div.sc-kOHTFB.bybnmV a.sc-gsFSXq.bDjPAt'));
                for ($i = 0; $i < $accounts_len; $i++) {
                    $account = $this->exts->querySelectorAll('div.sc-kOHTFB.bybnmV a.sc-gsFSXq.bDjPAt')[$i];
                    $account_number = trim($this->exts->extract('div.sc-kOHTFB.bybnmV a.sc-gsFSXq.bDjPAt span.sc-aXZVg.iTzQGF.sc-fhzFiK.jBUmHO', $account, 'innerText'));
                    $this->exts->log('account_number' . $account_number);
                    try {
                        $this->exts->log('Click account button');
                        $account->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click account button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$account]);
                    }
                    sleep(15);
                    $this->processInvoices($account_number);
                    sleep(5);
                    $this->exts->openUrl($this->invoicePageUrl);
                    sleep(12);
                }
            } else {
                $this->processInvoices();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->err_msg != null && (stripos($this->check_login_failed_selector, 'we do not recognize this username and/or password') !== false || stripos($this->err_msg, 'deze gebruikersnaam en/of dit wachtwoord herkennen we niet') !== false)) {
                $this->exts->log("Login Failed Confirmed");
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
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function checkFillLogin()
    {
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
            sleep(10);

            $this->exts->waitTillPresent($this->check_login_failed_selector, 10);
            $this->err_msg = $this->exts->extract($this->check_login_failed_selector);
            if (stripos(strtolower($this->err_msg), 'we do not recognize this username and/or password') !== false || stripos($this->err_msg, 'deze gebruikersnaam en/of dit wachtwoord herkennen we niet') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            }

            $this->exts->waitTillPresent('[data-test="skip-mfa-link"]', 15);
            if ($this->exts->exists('[data-test="skip-mfa-link"]')) {
                $this->exts->execute_javascript('
            var shadow = document.querySelector("[data-test=\'skip-mfa-link\']");
            if(shadow) {
                shadow.shadowRoot.querySelector("button[type=\'button\']").click();
            }
        ');
            }

            if ($this->exts->querySelector('kpn-button[name]') != null) {
                $this->exts->click_by_xdotool('kpn-button[name]');
                sleep(15);
            }

            if ($this->exts->querySelector('form kpn-button[variant="secondary"]') != null) {
                $this->exts->click_by_xdotool('form kpn-button[variant="secondary"]');
                sleep(15);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input.code-input__input';
        $two_factor_message_selector = 'form[data-test="mfaForm"] p[data-v-6baac505]';

        if ($this->exts->exists("div.mfa-modal__header") && $this->exts->two_factor_attempts < 3) {
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
                $this->exts->execute_javascript('
                var shadow = document.querySelector("[data-test=\'codeInput\']").shadowRoot;
                if (shadow) {
                    var inputs = shadow.querySelectorAll("input[class=\' code-input__input \']");
                    var code = "' . $two_factor_code . '"; 
            
                    // Loop through each input field and enter the 2FA code
                    for (var i = 0; i < inputs.length; i++) {
                        if (inputs[i]) {
                            inputs[i].focus();  // Focus on the input field
                            inputs[i].value = code[i];  // Type each digit of the 2FA code
                            inputs[i].dispatchEvent(new Event("input"));  // Trigger the input event for some browsers
                        }
                    }
            ');

                // $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                // JavaScript code for clicking the submit button in the shadow DOM
                $this->exts->execute_javascript('
                var shadow = document.querySelector("[data-test=\'submit\']");
                if(shadow) {
                    shadow.shadowRoot.querySelector("button[type=\'submit\']").click();
                }
            ');
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

    public $totalInvoices = 0;

    private function processInvoices($account_number = '')
    {
        sleep(15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows_len = count($this->exts->querySelectorAll('div[class*="nvoice_list"] ul a'));
        for ($i = 0; $i < $rows_len; $i++) {

            if ($this->totalInvoices >= 50) {
                return;
            }
            $row = $this->exts->querySelectorAll('div[class*="nvoice_list"] ul a')[$i];
            $tags = $this->exts->querySelectorAll('li span', $row);
            if (count($tags) >= 3) {
                $download_button = $row;
                $invoiceDate = trim($tags[0]->getText());
                $invoiceName = preg_replace('/[\s\-]/', '', $account_number) . str_replace(' ', '', $invoiceDate);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getText())) . ' EUR';

                $this->isNoInvoice = false;

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd M Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if ($this->exts->document_exists($invoiceFileName)) {
                    continue;
                }

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }
                sleep(2);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
