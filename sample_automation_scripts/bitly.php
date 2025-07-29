<?php // replace waitTillPresentAny and waitTillPresent to waitFor function and optimize the script code

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

    // Server-Portal-ID: 41965 - Last modified: 09.07.2025 14:42:22 UTC - User: 1

    // Script here
    public $baseUrl = 'https://app.bitly.com';
    public $username_selector = 'input[name="username"][autocomplete="username email"]';
    public $password_selector = 'input[name="password"][autocomplete="current-password"]';
    public $submit_login_selector = 'button[type="submit"]';
    public $check_login_failed_selector = '.error-message, aside[role="alert"]';
    public $check_login_success_selector = '.navigation--switch .main-menu .orb-dropdown, a[href*="sign_out"], .user-menu .user-name';
    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);
        $this->exts->capture('1-init-page');
        sleep(7);

        $this->waitFor($this->check_login_success_selector, 10);
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->checkFillLogin();
            sleep(5);
        }


        //check for verfication email button
        $this->waitFor('#send_verification_email');
        if ($this->exts->exists('#send_verification_email')) {
            $this->exts->log('verfication email send button found');
            $this->exts->capture('send_verification_email');
            if ($this->exts->exists('#send_verification_email')) {
                $this->exts->click_by_xdotool('#send_verification_email');
            }
            $verfication_link = $this->fetchVerificationLink();
            $this->exts->openUrl($verfication_link);
        } elseif ($this->exts->exists('div.email-modal')) {
            $this->checkFill2FAPushNotification();
        }

        $this->checkFillTwoFactor();

        // then check user logged in or not
        // $this->exts->click_by_xdotool('.navigation--switch .main-menu .orb-dropdown .selector-icon');
        $this->waitFor($this->check_login_success_selector);
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            $this->waitFor('div#pendo-guide-container button.bb-button');
            if ($this->exts->exists('div#pendo-guide-container button.bb-button')) {
                $this->exts->click_element('div#pendo-guide-container button.bb-button');
            }
            $this->processAfterLogin();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'Email / username or password is incorrect.') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->waitFor('//div[text()="Password"]/following-sibling::input');
        if ($this->exts->queryXpath('//div[text()="Password"]/following-sibling::input') != null) {
            $this->exts->click_by_xdotool('form');
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $username_element = $this->exts->queryXpath('//div[text()="Email"]/following-sibling::input');
            $this->exts->click_element($username_element);
            sleep(1);
            $this->exts->type_text_by_xdotool($this->username);
            // $this->exts->moveToElementAndType($username_element, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $password_element = $this->exts->queryXpath('//div[text()="Password"]/following-sibling::input');
            $this->exts->click_element($password_element);
            sleep(1);
            $this->exts->type_text_by_xdotool($this->password);
            // $this->exts->moveToElementAndType($password_element, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    private function checkFillTwoFactor(): void
    {
        $selector = 'input[maxlength="6"]';
        $message_selector = 'form h1 + p';
        $submit_selector = 'button[type="submit"]';

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
            $this->exts->moveToElementAndClick('form input[type="checkbox"]');
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

    private function checkFill2FAPushNotification()
    {
        $two_factor_message_selector = 'div.email-modal div.content p:first-child';
        $two_factor_submit_selector = '';
        $this->waitFor($two_factor_message_selector, 15);
        if ($this->exts->querySelector($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" when finished!!';
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $two_factor_code = trim(strtolower($this->exts->fetchTwoFactorCode()));
            if (!empty($two_factor_code) && trim($two_factor_code) == 'ok') {
                $this->exts->log("checkFillTwoFactorForMobileAcc: Entering two_factor_code." . $two_factor_code);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                sleep(15);
                if ($this->exts->querySelector($two_factor_message_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFill2FAPushNotification();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    public $two_factor_notif_msg_de = '';
    public $two_factor_notif_msg_en = 'Please enter the confirmation link to proceed with the login.';
    public $two_factor_notif_title_en = "%portal% - Two-Factor-Authorization";
    public $two_factor_notif_title_de = "%portal% - Zwei-Faktor-Anmeldung";
    public $two_factor_notif_msg_retry_en = " (Your last input was either wrong or too late)";
    public $two_factor_notif_msg_retry_de = " (Ihre letzte Eingabe war leider falsch oder zu spÃ¤t)";
    public $two_factor_timeout = 15;

    public function fetchVerificationLink()
    {
        $this->exts->log("--Fetching Two Factor Code--");
        $this->exts->capture("TwoFactorFetchCode");
        // if (!$this->two_factor_notif_msg_en || trim($this->two_factor_notif_msg_en) == "") {
        //     $this->two_factor_notif_msg_en = $this->exts->default_two_factor_notif_msg_en;
        // }
        // if (!$this->two_factor_notif_msg_de || trim($this->two_factor_notif_msg_de) == "") {
        //     $this->two_factor_notif_msg_de = $this->default_two_factor_notif_msg_de;
        // }
        $extra_data = array(
            "en_title" => $this->two_factor_notif_title_en,
            "en_msg" => $this->two_factor_notif_msg_en,
            "de_title" => $this->two_factor_notif_title_de,
            "de_msg" => $this->two_factor_notif_msg_de,
            "timeout" => $this->two_factor_timeout,
            "retry_msg_en" => $this->two_factor_notif_msg_retry_en,
            "retry_msg_de" => $this->two_factor_notif_msg_retry_de
        );

        $verfication_link = $this->exts->sendRequest($this->exts->process_uid, $this->exts->config_array['two_factor_shell_script'], $extra_data);
        $this->exts->log('verfication link');
        $this->exts->log($verfication_link);

        return $verfication_link;
    }

    private function processAfterLogin()
    {
        if ($this->exts->exists(selector_or_xpath: '.navigation--switch .main-menu .orb-dropdown .selector-icon')) {
            // Click Username and select  Organizations settings
            $this->exts->click_by_xdotool('.navigation--switch .main-menu .orb-dropdown .selector-icon');
            sleep(2);

            $organizationSettingSelector = '.orb-menu .orb-menu-item:nth-child(10)';
            if ($this->exts->exists($organizationSettingSelector)) {
                $this->exts->click_by_xdotool($organizationSettingSelector);
            } else {
                $accountSettingSelector = '.orb-menu .orb-menu-item:nth-child(6)';
                $this->exts->click_by_xdotool($accountSettingSelector);
            }
            sleep(10);


            //click billing tab
            $this->exts->click_by_xdotool('.tabs .title-row span:nth-child(2)');
            sleep(5);

            $this->processInvoicesNew();
        } else {
            $this->exts->click_by_xdotool('nav.side-nav .menu [data-test-id="settings"]');
            sleep(2);
            $this->exts->click_by_xdotool('.settings-nav a[href*=billing]');
            sleep(2);
            $this->processInvoices();
        }
    }

    private function processInvoicesNew($pageCount = 1)
    {
        $this->waitFor('.admin-section--MAIN > div:not(.account-detail--item) > div:not([class*="account"]) .account-detail--item', 15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->querySelectorAll('.admin-section--MAIN > div:not(.account-detail--item) > div:not([class*="account"]) .account-detail--item');
        foreach ($rows as $row) {
            $tags = $this->exts->querySelectorAll('div', $row);
            if (count($tags) >= 3) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->querySelector('a', $tags[3]);
                $invoiceName = trim($tags[3]->getAttribute('innerText'));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";

                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' USD';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = is_null($invoiceDate) ? null : $this->exts->parse_date($invoiceDate, 'm/d/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $invoiceUrl = $download_button->getAttribute('href');
                    $downloaded_file = $this->exts->download_capture($invoiceUrl, $invoiceFileName, 15);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $this->waitFor('table tbody tr', 15);
        sleep(10);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(4) button', $row) != null) {
                $invoiceName = '';
                $invoiceAmount = $this->exts->extract('td:nth-child(3)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);
                $downloadBtn = $this->exts->querySelector('td:nth-child(4) button', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'downloadBtn' => $downloadBtn
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);

            $this->exts->log('Click download button by javascript');
            $this->exts->click_element($invoice['downloadBtn']);

            //download pdf
            $this->waitFor('div[data-testid="overflow-panel"] div:nth-child(2)', 20);
            if ($this->exts->exists('div[data-testid="overflow-panel"] div:nth-child(2)')) {
                $this->exts->executeSafeScript("arguments[0].click()", [$this->exts->querySelector('div[data-testid="overflow-panel"] div:nth-child(2)')]);
            }

            sleep(5);
            $downloaded_file = $this->exts->find_saved_file('pdf');
            $invoiceFileName = basename($downloaded_file);
            $invoice['invoiceName'] = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
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
