<?php // updated login failed trigger handle empty invoice name updated login form selector and added custom js waitfor and isEixts function

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

    // Server-Portal-ID: 516 - Last modified: 01.04.2025 14:10:12 UTC - User: 1

    // start script 

    public $baseUrl = 'https://www.zalando.de/';
    public $loginUrl = 'https://www.zalando.de/login/';
    public $invoicePageUrl = 'https://www.zalando.de/myaccount/orders/';
    public $username_selector = 'input[inputmode="email"], input#lookup-email , input[name="login.email"]';
    public $password_selector = 'form[name="login"] input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form[name="login"] button[data-name="sso_login"]';
    public $check_login_failed_selector = 'div[data-testid="login_error_notification"]';
    public $check_login_success_selector = 'a.z-navicat-header_navToolItemLink-empty div svg path[data-name*="Layer 1"], a[href="/myaccount/"]';
    public $isNoInvoice = true;
    public $totalPage = 2;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_unexpected_extensions();
        $this->exts->openUrl('chrome://settings/help');
        sleep(5);
        $this->exts->capture('chrome-version');
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(5);
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(10);
        $user_agent = $this->exts->executeSafeScript('return navigator.userAgent;');
        $this->exts->log('user_agent: ' . $user_agent);
        $this->exts->capture('1-init-page');

        $this->isExists('div[class*="navToolItem-profile"]');
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            //$this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->isExists('button#uc-btn-accept-banner')) {
                $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
                sleep(5);
            }
            $this->checkFillLogin();
            sleep(60);
            $this->checkFillTwoFactor();
            if ($this->exts->querySelector($this->check_login_success_selector) == null) {
                sleep(120);
            }
        }

        // then check user logged in or not
        $this->isExists('div[class*="navToolItem-profile"]');
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(10);

            $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
            sleep(3);

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if ($this->exts->querySelector('div.mainCol div.userArea.js-orders div.pager') != null && $restrictPages == 0) {
                $this->totalPage = 10;
            }

            for ($i = 0; $i < $this->totalPage; $i++) {
                if ($this->isExists('button[class*="list__load-more-button"]')) {
                    $this->exts->moveToElementAndClick('button[class*="list__load-more-button"]');
                    sleep(10);
                }
            }

            $this->processInvoices();

            $currentYear = date("Y");
            if ($restrictPages == 0) {
                $orderLinks = array();
                $orderLinks[] = 'https://www.zalando.de/myaccount/orders/?year=' . ($currentYear - 1);
                $orderLinks[] = 'https://www.zalando.de/myaccount/orders/?year=' . ($currentYear - 2);
                foreach ($orderLinks as $orderLink) {
                    $this->exts->openUrl($orderLink);
                    sleep(10);

                    for ($i = 0; $i < $this->totalPage; $i++) {
                        if ($this->isExists('button[class*="list__load-more-button"]')) {
                            $this->exts->moveToElementAndClick('button[class*="list__load-more-button"]');
                            sleep(10);
                        }
                    }

                    $this->processInvoices();
                }
            } else {
                //https://en.zalando.de/myaccount/orders/?year=2019
                if ($this->isNoInvoice) {
                    $orderLink = 'https://www.zalando.de/myaccount/orders/?year=' . ($currentYear - 1);
                    $this->exts->openUrl($orderLink);
                    sleep(10);

                    for ($i = 0; $i < $this->totalPage; $i++) {
                        if ($this->isExists('button[class*="list__load-more-button"]')) {
                            $this->exts->moveToElementAndClick('button[class*="list__load-more-button"]');
                            sleep(10);
                        }
                    }
                    $this->processInvoices();
                }
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $error_text = strtolower($this->exts->extract('form > div:nth-child(1) >  div[role="alert"] span'));
            $this->exts->log('error_text:: '. $error_text);

            if ($this->isExists($this->check_login_failed_selector)) {
                $this->exts->loginFailure(1);
            } else if (stripos($error_text, 'Bitte gib eine gültige') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function disable_unexpected_extensions()
    {
        $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
        sleep(2);
        $this->exts->executeSafeScript("
        if(document.querySelector('extensions-manager') != null) {
            if(document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view')  != null){
                var disable_button = document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]');
                if(disable_button != null){
                    disable_button.click();
                }
            }
        }
    ");
        sleep(1);

        $this->exts->openUrl('chrome://extensions/?id=ifibfemgeogfhoebkmokieepdoobkbpo');
        sleep(1);
        $this->exts->executeSafeScript("
        if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
            document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
        }
    ");

        sleep(2);
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }


    private function checkFillLogin()
    {
        $this->waitFor($this->username_selector, 10);
        if ($this->exts->querySelector($this->username_selector) != null) {
            sleep(5);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(5);

            $this->exts->log("Submit Username");
            sleep(4);
            if ($this->isExists('button[data-testid="verify-email-button"]')) {
                $this->exts->click_by_xdotool('button[data-testid="verify-email-button"]');
            }

            sleep(5);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(5);

            $this->exts->capture("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#otp.passcode';
        $two_factor_message_selector = 'span[data-testid="otp.emailInfo"] font';
        $two_factor_submit_selector = 'button[data-testid="otp.submitButton"]';

        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            $twoFaMessage = $this->exts->executeSafeScript('document.querySelector("' . $two_factor_message_selector . '").innerText');

            if ($twoFaMessage != null) {
                $this->exts->two_factor_notif_msg_en = "";
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
                $code_input = $this->exts->querySelector($two_factor_selector);
                $code_input->sendKeys($two_factor_code);
                $this->exts->log('"checkFillTwoFactor: Entered code ' . $two_factor_code);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function processInvoices($pageCount = 1)
    {
        sleep(15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('li.z-sos-order-list-item');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('a[href*="/myaccount/order-detail/"]', $row) != null || $this->exts->querySelector('a[href*="/benutzerkonto/bestellung-detail/"]', $row) != null) {
                if ($this->exts->querySelector('a[href*="/myaccount/order-detail/"]', $row) != null) {
                    $invoice_link = $this->exts->querySelector('a[href*="/myaccount/order-detail/"]', $row);
                    if ($invoice_link != null) {
                        $invoiceUrl = $this->exts->executeSafeScript('return arguments[0].href;', [$invoice_link]);
                    }
                    $invoiceName = explode(
                        '/',
                        end(explode('/order-detail/', $invoiceUrl))
                    )[0];
                    //$invoiceUrl = str_replace('/order-detail/','/order-detail/print/', $invoiceUrl);
                } else {
                    $invoice_link = $this->exts->querySelector('a[href*="/benutzerkonto/bestellung-detail/"]', $row);
                    if ($invoice_link != null) {
                        $invoiceUrl = $this->exts->executeSafeScript('return arguments[0].href;', [$invoice_link]);
                    }
                    $invoiceName = explode(
                        '/',
                        end(explode('/bestellung-detail/', $invoiceUrl))
                    )[0];
                    //$invoiceUrl = str_replace('/bestellung-detail/','/bestellung-detail/drunk/', $invoiceUrl);
                }

                //https://www.zalando.de/benutzerkonto/bestellung-detail/druck/
                //$invoiceUrl = 'https://www.zalando.de/myaccount/order-detail/print/' .$invoiceName;
                $invoiceDate = "";
                $invoiceAmount = "";

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceName: ' . $invoiceDate);
                $this->exts->log('invoiceName: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
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

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(8);

            $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
            sleep(3);

            $this->exts->execute_javascript("document.querySelectorAll(\"body\")[0].innerHTML = document.querySelectorAll(\"div#main-content\")[0].innerHTML;");
            sleep(5);

            $downloaded_file = $this->exts->download_current($invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
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
