<?php //

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

    // Server-Portal-ID: 1769549 - Last modified: 08.07.2025 08:42:05 UTC - User: 1

    // Script here
    public $baseUrl = "https://acrobat.adobe.com/link/documents/files";
    public $loginUrl = "https://acrobat.adobe.com/link/documents/files";
    public $username_selector = "input#EmailPage-EmailField";
    public $password_selector = "input#PasswordPage-PasswordField";
    public $signin_button_selector = 'div.profile-signed-out button[data-test-id="unav-profile--sign-in"]';
    public $continue_1_button_selector = 'button[data-id="EmailPage-ContinueButton"]';
    public $continue_2_button_selector = 'button[data-id="PasswordPage-ContinueButton"]';
    public $submit_button_selector = 'button[id="continue-btn-unknown login-button"]';
    public $check_invalid_email_address = 'label[data-id="EmailPage-EmailField-Error"]';

    public $check_login_success_selector = 'div#unav-profile';
    public $login_tryout = 0;
    public $isNoInvoice = true;
    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->fillForm(0);
            $this->exts->waitTillPresent($this->check_login_success_selector, 50);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            sleep(2);
            $this->processInvoices();
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->loginFailure();
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->exts->waitTillPresent($this->username_selector, 10);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                sleep(1);

                $this->exts->click_by_xdotool($this->continue_1_button_selector);
                sleep(5); // Portal itself has one second delay after showing toast
                $this->checkFillTwoFactor();
            }
            $this->exts->waitTillPresent($this->password_selector, 10);
            if ($this->exts->querySelector($this->password_selector) != null) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->click_by_xdotool($this->continue_2_button_selector);
                sleep(5);
            } else {
                $this->exts->waitTillPresent($this->check_invalid_email_address, 10);
                if ($this->exts->querySelector($this->check_invalid_email_address) != null) {
                    $this->exts->log("Invalid email address !!!!");
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillTwoFactor()
    {
        $this->exts->waitTillPresent('button[data-id="Page-PrimaryButton"]', 20);
        if ($this->exts->exists('button[data-id="Page-PrimaryButton"]')) {
            $this->exts->click_element('button[data-id="Page-PrimaryButton"]');
        }

        $two_factor_selector = 'input[class="spectrum-Textfield CodeInput-Digit"]';
        $two_factor_message_selector = 'div[data-id="ChallengeCodePage-email"]';
        $two_factor_submit_selector = '';
        $this->exts->waitTillPresent($two_factor_selector, 10);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
                $this->exts->click_by_xdotool($two_factor_selector);
                sleep(2);
                $this->exts->type_text_by_xdotool($two_factor_code);
                // $resultCodes = str_split($two_factor_code);
                // $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
                // foreach ($code_inputs as $key => $code_input) {
                //     if (array_key_exists($key, $resultCodes)) {
                //         $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                //         $this->exts->moveToElementAndType('i[class*="cobeItem"]:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                //         // $code_input->sendKeys($resultCodes[$key]);
                //     } else {
                //         $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                //     }
                // }

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                // if ($this->exts->exists('span[role="checkbox"] input')) {
                //     $this->exts->click_by_xdotool('span[role="checkbox"] input');
                //     sleep(1);
                // }

                // $this->exts->click_by_xdotool($two_factor_submit_selector);
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
    /**

     * Method to Check where user is logged in or not

     * return boolean true/false

     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
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
        sleep(5);
        $this->exts->waitTillPresent('.react-spectrum-TableView-row.spectrum-Table-row', 25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->querySelectorAll('.react-spectrum-TableView-row.spectrum-Table-row');
        $menu = 'button[class*="ActionButton"]';
        $count = 0;
        foreach ($rows as $row) {
            $count++;
            $invoiceName = ''; // use custom name no invoice name found in portal
            $invoiceDate =  $this->exts->extract('div[class*="Date"]', $row);
            $actionMenu = $this->exts->querySelector($menu, $row);

            $this->exts->execute_javascript("arguments[0].click();", [$actionMenu]);
            sleep(7);
            $downloadBtn = $this->exts->querySelector('li[data-testid="more-menu-download-action-item"]');
            if ($downloadBtn != null) {

                $this->isNoInvoice = false;

                $invoiceFileName =  '';

                $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                sleep(5);

                $this->exts->no_margin_pdf = 1;
                // find new saved file and return its path
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceName = basename($downloaded_file, '.pdf');

                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->new_invoice($invoiceName,  $invoiceDate, '', $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            } else {
                $this->exts->log(__FUNCTION__ . '::No download');
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
