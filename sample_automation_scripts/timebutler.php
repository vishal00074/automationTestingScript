<?php // i have added the not empty condition on invioceName and otimize the code 

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


    // Server-Portal-ID: 30557 - Last modified: 18.07.2025 14:37:46 UTC - User: 1

    // Script here
    public $baseUrl = 'https://timebutler.de/login/';
    public $loginUrl = 'https://timebutler.de/login/';
    public $invoicePageUrl = 'https://timebutler.de/do?ha=pay&ac=30';
    public $username_selector = 'input[name="login"]';
    public $password_selector = 'input[name="passwort"]';
    public $remember_me_selector = 'input[type="checkbox"]';
    public $submit_login_selector = 'button[type="submit"]';
    public $check_login_failed_selector = 'div[class="box-body"] p';
    public $check_login_success_selector = 'a[href="javascript:destroyFloating();print()"]';
    public $isNoInvoice = true;

    
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            sleep(5);
            $this->exts->execute_javascript('
                var shadow = document.querySelector("#cmpwrapper");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'span[id="cmpwelcomebtnyes"] a\').click();
                }
            ');

            $this->fillForm(0);
            sleep(5);
            if ($this->exts->exists('div[id="step1"]  a.btn-default')) {
                $this->exts->click_element('div[id="step1"]  a.btn-default');
            }
            $this->checkFillTwoFactor();
            sleep(10);
            $skip_button = $this->exts->getElementByText('a[href*="do"]', 'Hinweis nicht mehr anzeigen', null, false);
            if ($skip_button != null) {
                try {
                    $this->exts->log('Click download button');
                    $skip_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$skip_button]);
                }
            }

            $this->exts->waitTillPresent($this->check_login_success_selector);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            $this->exts->openUrl($this->invoicePageUrl);
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

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }

                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillTwoFactor(): void
    {
        $selector = 'input#tfadigits';
        $message_selector = 'div.registration-form-content > p';
        $submit_selector = 'button#tfasubmit';

        while ($this->exts->getElement($selector) !== null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            // Collect and log the 2FA instruction messages
            $this->exts->two_factor_notif_msg_en = "";
            $messages = $this->exts->getElements($message_selector);
            foreach ($messages as $msg) {
                $this->exts->two_factor_notif_msg_en .= $msg->getAttribute('innerText') . "\n";
            }

            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            // Add retry message if this is the final attempt
            if ($this->exts->two_factor_attempts === 2) {
                $this->exts->two_factor_notif_msg_en .= ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de .= ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $code = trim($this->exts->fetchTwoFactorCode());
            if ($code === '') {
                $this->exts->log("2FA code not received");
                break;
            }

            $this->exts->log("checkFillTwoFactor: Entering 2FA code: " . $two_factor_code);
            $this->exts->click_by_xdotool($selector);
            $this->exts->type_text_by_xdotool($code);
            $this->exts->moveToElementAndClick('input#nomore2fa');
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->moveToElementAndClick($submit_selector);
            sleep(5); // Added: Ensure time for 2FA processing

            if ($this->exts->getElement($selector) === null) {
                $this->exts->log("Two factor solved");
                break;
            }

            $this->exts->two_factor_attempts++;
        }

        if ($this->exts->two_factor_attempts >= 3) {
            $this->exts->log("Two factor could not be solved after 3 attempts");
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
        $this->exts->waitTillPresent('table tbody tr', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(5) a ', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('td:nth-child(2) ', $row);
                $invoiceAmount =  '';
                $invoiceDate =  '';

                $downloadBtn = $this->exts->querySelector('td:nth-child(5) a ', $row);

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
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. F Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);

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
