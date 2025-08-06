<?php // invoicename added condition to handle empty invoices and replace waitTillpresent function to waitFor javascript function

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

    // Server-Portal-ID: 178811 - Last modified: 29.07.2025 09:56:34 UTC - User: 1

    public $baseUrl = 'https://app.lemlist.com/campaigns/';
    public $invoicePageUrl = 'https://app.lemlist.com/settings/billing/invoices';

    public $username_selector = '.login-page input[type="email"], input[name="email"]';
    public $password_selector = '.login-page input[type="password"]';
    public $submit_login_selector = '.login-page button[data-test="signin-button"]';

    public $check_login_success_selector = 'span.myaccount-logout button.js-logout, span.my-account-logout, div[data-test="logout"], div[data-test="user-menu-dropdown"]';

    public $isNoInvoice = true;
    public $errorMessage = '';

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page-before-loadcookies');
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        if ($this->exts->getElement('div.user .avatar') != null) {
            $this->exts->moveToElementAndClick('div.user .avatar');
            sleep(5);
        }
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(15);
            $this->checkFillTwoFactor();
            sleep(10);
            if ($this->exts->getElement('div.user .avatar') != null) {
                $this->exts->moveToElementAndClick('div.user .avatar');
                sleep(5);
            }
        }

        if ($this->exts->getElement('div.onboarding-container') != null) {
            $this->exts->log('Account Not Ready!!');
            $this->exts->account_not_ready();
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->waitFor('a[data-test="js-pc-close"]');
            if ($this->exts->exists('a[data-test="js-pc-close"]')) {
                $this->exts->click_element('a[data-test="js-pc-close"]');
                sleep(3);
            }

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(30);
            $this->exts->capture("3-open-inovicepage-success");
            if (!$this->exts->getElement('a[href*="https://repo.octobat.com/customers/"]') != null) {
                sleep(15);
            }
            if ($this->exts->getElement('a[href*="https://repo.octobat.com/customers/"]') != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="https://repo.octobat.com/customers/"]')->getAttribute("href");
                $this->exts->openUrl($invoiceUrl);
                sleep(30);
            } else if ($this->exts->getElement('[data-test="user-menu-dropdown"]') != null) {
                $this->exts->moveToElementAndClick('[data-test="user-menu-dropdown"]');
                sleep(3);
                $this->exts->moveToElementAndClick('a[href*="/billing"]');
                sleep(15);
                $this->exts->moveToElementAndClick('a[data-test="open-invoices-list"]');
                sleep(5);
            } else {
                $this->exts->openUrl('https://app.lemlist.com/settings/users');
                sleep(25);
                $this->exts->moveToElementAndClick('a[href="/settings/billing"]');
                sleep(15);
                $this->exts->moveToElementAndClick('a[href="/settings/billing/invoices"]');
                sleep(20);
                if ($this->exts->getElement('a[href*="https://repo.octobat.com/customers/"]') != null) {
                    $invoiceUrl = $this->exts->getElement('a[href*="https://repo.octobat.com/customers/"]')->getAttribute("href");
                    $this->exts->openUrl($invoiceUrl);
                    sleep(30);
                }
            }
            if ($this->exts->getElement('div.modal .modal-body button.js-close') != null) {
                $this->exts->moveToElementAndClick('div.modal .modal-body button.js-close');
                sleep(5);
            }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos($this->errorMessage, 'Incorrect login') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
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
            $this->exts->moveToElementAndClick('form button[class*="validate-email"]');
            sleep(3);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->capture("2-login-page-filled");
            if ($this->exts->getElement($this->submit_login_selector) != null) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(2);
            }

            if ($this->exts->getElement('[role="alert"] .noty_body') != null) {
                $this->errorMessage = $this->exts->extract('[role="alert"] .noty_body', null, 'innerText');
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function checkFillTwoFactor()
    {
        $this->exts->capture("2-2fa-checking");

        if ($this->exts->getElement('div.login-form input[type="text"]') != null && $this->exts->urlContains('/campaigns/')) {
            $two_factor_selector = 'div.login-form input[type="text"]';
            $two_factor_message_selector = 'div.login-form div.ui-group-first';
            $two_factor_submit_selector = 'div.login-form button[type="submit"]';
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(10);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
        if ($this->exts->getElement('.js-confirmation-code.js-confirm-2fa') != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            $two_factor_selector = '.js-confirmation-code.js-confirm-2fa';
            $two_factor_message_selector = '.text-light';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                        $this->exts->moveToElementAndType($two_factor_selector . ':nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                        // $code_input->sendKeys($resultCodes[$key]);
                    } else {
                        $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                    }
                }
                $this->waitFor('div#noty_layout__bottomRight div.noty_bar');
                if (stripos(strtolower($this->exts->extract('div#noty_layout__bottomRight div.noty_bar')), 'Incorrect login') !== false) {
                    $this->exts->loginFailure(1);
                    sleep(5);
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }
    public $totalInvoices = 0;
    private function processInvoices()
    {
        sleep(30);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        if ($this->exts->getElement('div.invoice-modal-list') != null) {
            $rows = $this->exts->getElements('div.invoice-modal-list div.row');

            for ($i = 0; $i < count($rows); $i++) {
                if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                    return;
                }
                $row = $this->exts->getElements('div.invoice-modal-list div.row')[$i];
                $tags = $this->exts->getElements('div.row-item', $row);
                if (count($tags) >= 5 && $this->exts->getElement('//*[contains(@class,"fa-cloud-arrow-down")]/..', $tags[4], 'xpath') != null) {
                    $download_button = $this->exts->getElement('//*[contains(@class,"fa-cloud-arrow-down")]/..', $tags[4], 'xpath');
                    $invoiceName = trim($tags[0]->getAttribute('innerText'));
                    $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' USD';

                    $this->isNoInvoice = false;
                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                    $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';
                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        try {
                            $this->exts->log('Click download invoice...');
                            $download_button->click();
                        } catch (Exception $e) {
                            $this->exts->log("Click download invoice by javascript ");
                            $this->exts->execute_javascript('arguments[0].click()', [$download_button]);
                        }
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                            $this->totalInvoices++;
                        } else {
                            $this->exts->log('Timeout when download ' . $invoiceFileName);
                        }
                    }
                }
            }
        } else {
            $rows = $this->exts->getElements('div.billing-invoices div.row');
            foreach ($rows as $index => $row) {
                if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                    return;
                }
                $tags = $this->exts->getElements('div.row-item', $row);
                if (count($tags) >= 4 && $this->exts->getElement('div.fa-download', $tags[3]) != null) {
                    $invoiceSelector = $this->exts->getElement('div.fa-download', $tags[3]);
                    $this->exts->execute_javascript("arguments[0].setAttribute('id', 'custom-pdf-button-" . $index . "');", [$invoiceSelector]);
                    $invoiceName = trim($tags[2]->getAttribute('innerText'));
                    $invoiceName = explode('.pdf', $invoiceName)[0];
                    $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' USD';

                    $this->isNoInvoice = false;
                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                    $invoiceFileName = !empty($invoiceName) ? $invoiceName  . '.pdf' : '';
                    // Download invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        // click and download invoice
                        $this->exts->moveToElementAndClick('div#custom-pdf-button-' . $index);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                            $this->totalInvoices++;
                        } else {
                            $this->exts->log('Timeout when download ' . $invoiceFileName);
                        }
                    }
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
