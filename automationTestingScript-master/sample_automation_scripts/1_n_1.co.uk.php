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

    // Server-Portal-ID: 8689 - Last modified: 19.03.2024 03:18:06 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://my.ionos.co.uk/';
    public $loginUrl = 'https://login.ionos.co.uk/';
    public $invoicePageUrl = 'https://my.ionos.co.uk/invoices';

    public $username_selector = 'input#username';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = '#login-form > p.input-byline--error > a[href*="login.ionos.co.uk"], section.sheet__section--critical a[href*="login.ionos.fr"], p.input-byline--error';
    public $check_login_success_selector = 'li.oao-navi-flyout-container.oao-navi-flyout-customer';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        sleep(30);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        if ($this->exts->exists('[level="error"]') && strpos($this->exts->extract('[level="error"]'), 'reloading') !== false) {
            // after loading cookies, site shows:
            // Sorry, a system error has occurred. If you haven't already, we recommend reloading the page to see if that fixes the problem.
            // try clear cookies and relogin.
            $this->exts->clearCookies();
            $this->exts->executeSafeScript('window.localStorage.clear(); window.sessionStorage.clear();');
            sleep(1);
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
        }

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);

            if ($this->exts->exists('input#login-form-additionaldata, input#additionaldata')) {
                $this->checkFillNotRobot();
            }

            if ($this->exts->exists('iframe[src*="/account-locked"]')) {
                // if we see the popup to ask for human verify
                $this->exts->switchToFrame('iframe[src*="/account-locked"]');
                sleep(1);
                $this->exts->capture('account-locked');
                if ($this->exts->exists('input[name*="recaptcha.enableCaptcha"] ~ * button[type="submit"]')) {
                    $this->exts->moveToElementAndClick('input[name*="recaptcha.enableCaptcha"] ~ * button[type="submit"]');
                    sleep(20);
                    $this->exts->capture('account-locked-button');
                    $this->exts->openUrl($this->loginUrl);
                    sleep(15);
                    $this->checkFillLogin();
                    sleep(20);
                } else {
                    // $this->exts->switchToDefault();
                    // if cannot try with captchar, we will use email 2FA
                    // this case, 2FA button belong to iframe
                    if ($this->exts->exists('input[name*="email.sendUnlockEmail"] ~ * button[type="submit"]')) {
                        $this->exts->log('2FA email frame');
                        $this->exts->moveToElementAndClick('input[name*="email.sendUnlockEmail"] ~ * button[type="submit"]');
                        $this->checkFillTwoFactor();
                    } else {
                        // this case, 2FA button belong to default frame
                        $this->exts->log('2FA email default');
                        $this->exts->switchToDefault();

                        if ($this->exts->exists('input[name*="email.sendUnlockEmail"] ~ * button[type="submit"]')) {
                            $this->exts->log('have button 2FA email');
                            $this->exts->moveToElementAndClick('input[name*="email.sendUnlockEmail"] ~ * button[type="submit"]');
                            $this->checkFillTwoFactor();
                        }
                    }
                }
            } else if ($this->exts->exists('input#passcode')) {
                $this->exts->log('2FA');
                $this->checkFillTwoFactor_otp();
            }
        }

        // then check user logged in or not
        // for ($wait_count = 1; $wait_count <= 10 && $this->exts->getElement($this->check_login_success_selector) == null; $wait_count++) {
        // 	$this->exts->log('Waiting for login...');
        // 	sleep(5);
        // }
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(15);

            if (strpos($this->exts->getUrl(), 'password.ionos.co.uk') && $this->exts->exists('button[type="submit"]')) {
                $this->exts->account_not_ready();
            }
            if (strpos($this->exts->getUrl(), 'password.ionos.co.uk') && $this->exts->exists('button[type="submit"]')) {
                $this->exts->account_not_ready();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function checkFillTwoFactor()
    {
        $two_factor_message_selector = '#mfa-login-block > p';

        if ($this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            $this->exts->two_factor_notif_msg_en = 'We will send you an email with a confirmation link.' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = 'Wir senden Ihnen eine EMail mit einem BestÃ¤tigungslink.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->openUrl($this->loginUrl);
                sleep(15);
                $this->checkFillLogin();
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    private function checkFillTwoFactor_otp()
    {
        $two_factor_selector = 'input#passcode';
        $two_factor_message_selector = 'p.paragraph';
        $two_factor_submit_selector = 'button[class*="InputSubmit"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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

    private function checkFillNotRobot()
    {
        $two_factor_selector = 'input#login-form-additionaldata, input#additionaldata';
        $two_factor_message_selector = 'form#login-form p.paragraph, form label[for="additionaldata"]';
        $two_factor_submit_selector = '#login-form-submit, form button[type="submit"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(2);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillNotRobot();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function processInvoices()
    {
        sleep(10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $maxPage = 100;
        if ($this->exts->config_array["restrictPages"] == 3) {
            $maxPage = 3;
        }
        for ($i = 0; $i < $maxPage; $i++) {
            $rows = $this->exts->getElements('#invoices-table > table > tbody > tr');
            if (count($rows) == 0) continue;
            foreach ($rows as $row) {
                $invoiceName = $this->exts->extract('td.column-4.number', $row, 'innerText');
                $invoiceDate = $this->exts->extract('td.column-2.date', $row, 'innerText');
                $invoiceAmountText = $this->exts->extract('td.column-5.amount', $row, 'innerText');
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmountText)) . ' GBP';

                $this->exts->log('>>>>>>>>>>>>>>>>>>>>invoiceName: ' . $invoiceName);
                $this->exts->log('>>>>>>>>>>>>>>>>>>>>invoiceDate: ' . $invoiceDate);
                $this->exts->log('>>>>>>>>>>>>>>>>>>>>invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if ($this->exts->getElement('td.column-6.pdf > a', $row) != null) {
                    $invoiceUrl = $this->exts->extract('td.column-6.pdf > a', $row, 'href');
                } else {
                    $invoiceUrl = $this->exts->extract('td.pdf.download > a', $row, 'href');
                }
                $this->exts->log('>>>>>>>>>>>>>>>>>>>>invoiceUrl: ' . $invoiceUrl);
                if (trim($invoiceUrl) != '') {
                    $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                } else {
                    $invoiceBtn = $this->exts->getElement('td.pdf > a', $row);
                    if ($invoiceBtn == null) continue;
                    try {
                        $invoiceBtn->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('ERROR in click on element. Could not locate element - ' . $exception->getMessage());
                        $this->exts->executeSafeScript('arguments[0].click();', [$invoiceBtn]);
                    }
                    sleep(5);

                    // Wait for completion of file download
                    $this->exts->wait_and_check_download('pdf');
                    $this->exts->wait_and_check_download('pdf');

                    // find new saved file and return its path
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }

                $this->isNoInvoice = false;
            }
            if ($this->exts->exists('#invoices-pagination > ul > li.next-item[data-item-active="true"]')) {
                $this->exts->moveToElementAndClick('#invoices-pagination > ul > li.next-item[data-item-active="true"]');
                sleep(3);
            } else {
                break;
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
