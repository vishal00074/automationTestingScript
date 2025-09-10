<?php // handle empty invoiceName condition added custom waitFor js function 
 
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

    // Server-Portal-ID: 4831 - Last modified: 21.03.2025 14:09:25 UTC - User: 1

    public $baseUrl = 'https://www.inwx.de/de/accounting/invoices';
    public $loginUrl = 'https://www.inwx.de/de/customer/login';
    public $invoicePageUrl = 'https://www.inwx.de/de/accounting/invoices';

    public $username_selector = '#maincontent form[action*="/customer/login"] input[name="usr"]';
    public $password_selector = '#maincontent form[action*="/customer/login"] input[name="pwd"]';
    public $remember_me_selector = '';
    public $submit_login_selector = '#maincontent form[action*="/customer/login"] input#btn_subm';

    public $check_login_failed_selector = 'div#realcontent p[style="color:red"]';
    public $check_login_success_selector = 'a[href*="/logout"]';

    public $isNoInvoice = true;

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
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        $this->waitFor($this->check_login_success_selector, 10);
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->openUrl($this->loginUrl);

            $this->checkFillHcaptcha();

            $this->checkFillLogin();
            sleep(15);
            if ($this->exts->getElement($this->password_selector) != null && ($this->exts->urlContains('login/wrongprovider') || $this->exts->getElement('//p[contains(text(),"falschen Provider anzumelden")]') != null)) {
                $this->checkFillLogin();
            }

            $this->checkFillHcaptcha();
            $this->waitFor('iframe[src*="/unlock"]');

            if ($this->exts->exists('iframe[src*="/unlock"]')) {

                $this->switchToFrame('iframe[src*="/unlock"]');
                $this->checkFillTwoFactor();
                $this->exts->switchToDefault();
            }
        }


        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->moveToElementAndClick('#maincontent a[href*="accounting/invoices"]');
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null && strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos($this->exts->extract('#realcontent h3', null, 'innerText'), ' gesperrt') !== false || strpos($this->exts->extract('#realcontent h3', null, 'innerText'), ' suspended') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function waitFor($selector = null, $seconds = 10)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for selector.....');
            sleep($seconds);
        }
    }

    private function checkFillLogin()
    {

        // $this->exts->waitTillPresent($this->username_selector,10);
        $this->waitFor($this->username_selector);

        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->remember_me_selector != '')
                $this->exts->click_element($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $this->exts->log('In frame');
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $this->exts->log('In frame 1');
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->log('In frame 2');
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form input[id="otp"]';
        $two_factor_message_selector = 'div#realcontent_small > div.inwx-col2-force > div >p';
        $two_factor_submit_selector = 'input[name="btn_submit"]';
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

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);


                $this->exts->click_by_xdotool($two_factor_submit_selector);
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

    private function checkFillHcaptcha($count = 0)
    {
        $hcaptcha_iframe_selector = 'iframe[src*="hcaptcha"]';
        if ($this->exts->exists($hcaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
            $data_siteKey =  end(explode("&sitekey=", $iframeUrl));
            $this->exts->processHumanCaptcha("", $data_siteKey, $this->exts->getUrl(), true);
            sleep(5);
            if ($this->exts->exists($hcaptcha_iframe_selector) && $count < 5) {
                $count++;
                $this->checkFillHcaptcha($count);
            }
        }
    }

    private function processInvoices()
    {
        $this->waitFor('div#realcontent div.generic-table > div:not(.header)');
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('div#realcontent div.generic-table > div:not(.header)');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('div:nth-child(9) > div > i[class="fa fa-download"]', $row) != null) {
                $invoiceUrl = '';
                $invoiceName = $this->exts->extract('div:nth-child(4)', $row);
                $invoiceAmount = $this->exts->extract('div:nth-child(5)', $row);
                $invoiceDate =  $this->exts->extract('div:nth-child(1)', $row);

                $downloadBtn = $this->exts->querySelector('div:nth-child(9) > div > i[class="fa fa-download"]', $row);

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

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf': '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


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
