<?php // handle empty invoice case and updated login failed selector

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

    // Server-Portal-ID: 532457 - Last modified: 08.07.2025 15:06:30 UTC - User: 1

    public $baseUrl = 'https://fleethub.shell.com/mfe/login';
    public $invoicePageUrl = 'https://fleethub.shell.com/mfe/finance/Invoices';
    public $username_selector = 'form[target="form-login"] input#signInEmailAddress, form[action*="authorize"] input#signInEmailAddress';
    public $password_selector = 'form[target="form-login"] input#currentPassword, form[action*="authorize"] input#currentPassword';
    public $submit_login_selector = 'form[target="form-login"] button[type="submit"], form[action*="authorize"] button[type="submit"]';

    public $check_login_failed_selector = 'div#messages div.alert-danger, div.visible.alert--danger';
    public $check_login_success_selector = 'a[onclick*="Administration"], div[data-set="primary-area-contents"] a[onclick*="Invoices"], .c-interstitial-results-panel__table [class*="next-cell"] [data-company-id], a[onclick*="SignOut"], [data-tabs-toggle="account"]';
    public $isNoInvoice = true;
    public $restrictPages = 3;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_unexpected_extensions();
        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        // Load cookies
        //$this->exts->loadCookiesFromFile();
        $this->disable2CaptchaExtension();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->moveToElementAndClick('[data-testid="login-btn"]');
            sleep(15);
            if ($this->exts->exists('button#_evidon-banner-acceptbutton')) {
                $this->exts->moveToElementAndClick('button#_evidon-banner-acceptbutton');
                sleep(2);
            }
            //$this->checkFillLogin();
            $this->checkFillLoginUndetected();
            sleep(10);

            if ($this->exts->exists('form[aria-label="Complete your profile"] a#forgot-password-link')) {
                $this->exts->moveToElementAndClick('form[aria-label="Complete your profile"] a#forgot-password-link');
                sleep(10);
            }

            for ($i = 0; $i < 10; $i++) {
                // Extract the error message from the page
                $err_msg1 = $this->exts->extract($this->check_login_failed_selector);
                $lowercase_err_msg = strtolower($err_msg1);

                // Define the substring to search for
                $substring = 'security error with the form submission';

                // Check if the error message contains the specified substring
                if (strpos($lowercase_err_msg, strtolower($substring)) !== false  ||  $this->exts->querySelector($this->username_selector) != null ||  $this->exts->querySelector($this->password_selector) != null) {
                    // Retry opening the URL
                    $this->exts->openUrl($this->baseUrl);
                    sleep(30);
                    $this->checkFillLogin();
                    sleep(30); // Wait for the login process to complete
                }

                // Check if the login was successful or failed
                if ($this->exts->querySelector($this->check_login_success_selector) !== null || $this->exts->querySelector($this->check_login_failed_selector) !== null) {
                    break;
                }
            }

            if ($this->exts->exists('form[aria-label="Complete your profile"] a#forgot-password-link')) {
                $this->exts->moveToElementAndClick('form[aria-label="Complete your profile"] a#forgot-password-link');
                sleep(10);
            }

            sleep(20);
            $this->checkFillTwoFactor();
            sleep(20);

            if (stripos($this->exts->extract('h1#title'), 'Setup an Authenticator App') !== false) {
                $this->exts->log("Account Not Ready");
                $this->exts->account_not_ready();
            }


            if ($this->exts->exists('a[aria-label="Skip for now"]')) {
                $this->exts->account_not_ready();
                sleep(10);
            }
        }
        if ($this->exts->exists('button#_evidon-banner-acceptbutton')) {
            $this->exts->moveToElementAndClick('button#_evidon-banner-acceptbutton');
            sleep(2);
        }
        if ($this->exts->exists('.wm-close-button.walkme-x-button')) {
            $this->exts->moveToElementAndClick('.wm-close-button.walkme-x-button');
            sleep(2);
        }
        if ($this->exts->querySelectorAll($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            if ($this->exts->exists('.c-interstitial-results-panel__table [class*="next-cell"] [data-company-id]')) {
                $this->processAccounts('');
            } else {
                // Open invoices url and download invoice
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(20);
                if ($this->exts->exists('button#_evidon-banner-acceptbutton')) {
                    $this->exts->moveToElementAndClick('button#_evidon-banner-acceptbutton');
                    sleep(2);
                }

                $this->processInvoices();
            }
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $err_msg1 = $this->exts->extract('div[class="visible alert alert--danger"]');
            $lowercase_err_msg = strtolower($err_msg1);
            $substrings = array('not authorized', 'authorized', 'biometric');
            foreach ($substrings as $substring) {
                if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                    $this->exts->log($err_msg1);
                    $this->exts->loginFailure(1);
                    break;
                }
            }
            //div.input-text--error span.input-text__message
            if (
                stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwort') !== false ||
                stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'password') !== false ||
                stripos(strtolower($this->exts->extract('.alert--danger p')), 'security error') !== false ||
                stripos(strtolower($this->exts->extract('div.input-text--error span.input-text__message')), 'Please enter a valid email address') !== false

            ) {
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
        $this->exts->executeSafeScript("if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
        document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
    }");
        sleep(2);
    }

    private function disable2CaptchaExtension()
    {
        $this->exts->openUrl('chrome://extensions/?id=ifibfemgeogfhoebkmokieepdoobkbpo');
        sleep(1);
        $this->exts->executeSafeScript("if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
            document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
        }");
        sleep(2);
    }

    private function checkFillLogin()
    {
        if ($this->exts->querySelectorAll($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(5);
            $this->exts->capture("2-login-page-filled");

            $this->exts->click_element($this->submit_login_selector);
            sleep(5);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[id="smsVerificationCode"]';
        $two_factor_message_selector = 'div[class="app-container__content"] > p';
        $two_factor_submit_selector = 'button[type="submit"]';
        $two_factor_resend_selector = 'a#resend:not(.medium.resend.hidden.disabled)';

        if ($this->exts->querySelector($two_factor_selector) != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

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
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(1);
                if ($this->exts->exists($two_factor_resend_selector)) {
                    $this->exts->moveToElementAndClick($two_factor_resend_selector);
                    sleep(1);
                }
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
        } else {
            $two_factor_selector = 'input#totp-code';
            $two_factor_message_selector = 'p.subtitle';
            $two_factor_submit_selector = 'button[type="submit"]';

            if ($this->exts->querySelector($two_factor_selector) != null) {
                $this->exts->log("Two factor page found.");
                $this->exts->capture("2.1-two-factor");

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
                    $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                    $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                    sleep(1);

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
    }

    private function checkFillLoginUndetected()
    {
        $this->exts->type_key_by_xdotool("Ctrl+l");
        sleep(3);
        $this->exts->type_text_by_xdotool($this->baseUrl);
        $this->exts->type_key_by_xdotool("Return");
        sleep(20);

        $this->exts->type_key_by_xdotool("Tab");
        sleep(2);

        $this->exts->log("Enter Username");
        $this->exts->type_text_by_xdotool($this->username);
        $this->exts->type_key_by_xdotool("Tab");
        sleep(2);

        $this->exts->log("Enter Password");
        $this->exts->type_text_by_xdotool($this->password);
        sleep(2);

        $this->exts->type_key_by_xdotool("Return");
        sleep(20);

        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->getElementByText('div[class="visible alert alert--danger"]', ['Bei der Formularübermittlung ist ein Sicherheitsfehler aufgetreten', 'There was a security error with the form submission'], null, false)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(10);
            } else {
                break;
            }
        }
        $this->exts->log('Print first ---------------------------------> 1');
        if (stripos($this->exts->extract('a[id="forgot-password-link"]'), 'Skip for now') !== false) {
            $this->exts->log('Print first ---------------------------------> 2');
            $this->exts->moveToElementAndClick('a[id="forgot-password-link"]');
        }
        sleep(5);

        $this->exts->log('Print second ---------------------------------> 1');
        if (stripos($this->exts->extract('a[id="forgot-password-link"]'), 'Skip for now') !== false) {
            $this->exts->log('Print second ---------------------------------> 2');
            $this->exts->moveToElementAndClick('a[id="forgot-password-link"]');
        }
        sleep(5);


        if (stripos(strtolower($this->exts->extract('div.input-text--error span.input-text__message')), 'Please enter a valid email address') !== false) {
            $this->exts->loginFailure(1);
        }
    }

    private function processAccounts($current_page_value = "", $page_limit = 1)
    {
        if ($this->exts->exists('li.is-current a.c-pagination__page-link')) {
            $current_page_value = $this->exts->extract('li.is-current a.c-pagination__page-link');
        }
        $accounts = $this->exts->querySelectorAll('.c-interstitial-results-panel__table [class*="next-cell"] [data-company-id]');
        $accounts_array = array();
        foreach ($accounts as $key => $account) {
            $account_id = $account->getAttribute('data-company-id');
            $this->exts->log("found account number: " . $account_id);
            array_push($accounts_array, $account_id);
        }
        foreach ($accounts_array as $account_id) {
            $this->exts->moveToElementAndClick('.c-interstitial-results-panel__table [class*="next-cell"] [data-company-id="' . $account_id . '"]');
            sleep(10);
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(15);
            if ($this->exts->exists('button#_evidon-banner-acceptbutton')) {
                $this->exts->moveToElementAndClick('button#_evidon-banner-acceptbutton');
                sleep(2);
            }

            $this->processInvoices();
            $this->exts->moveToElementAndClick('button[onclick*="HomePage"]');
            sleep(5);
            if ($this->exts->exists('a[href="/interstitial"]')) {
                $this->exts->moveToElementAndClick('a[href="/interstitial"]');
            } else {
                $this->exts->openUrl('https://fleethub.shell.com/interstitial');
            }
            sleep(10);
            if (!$this->exts->exists('.c-interstitial-results-panel__table [class*="next-cell"] [data-company-id]')) {
                sleep(10);
            }
            $current_page_buttons = $this->exts->getElements('a.c-pagination__page-link');
            $this->exts->log('Finding Completted trips button...');
            foreach ($current_page_buttons as $key => $current_page_button) {
                $current_page_name = trim($current_page_button->getText());
                if ($current_page_name == $current_page_value) {
                    $this->exts->log('Completted trips button found');
                    $current_page_button->click();
                    sleep(10);
                    break;
                }
            }
        }
        if ($this->exts->exists('a.c-pagination__arrow--next:not(.c-pagination__hidden)') && $page_limit < 10) {
            $page_limit++;
            $this->exts->moveToElementAndClick('a.c-pagination__arrow--next:not(.c-pagination__hidden)');
            sleep(15);
            $this->processAccounts('', $page_limit);
        }
    }

    private function processInvoices($paging_count = 1)
    {
        $this->exts->capture("4-invoices-page");
        $rows = count($this->exts->querySelectorAll('table > tbody > tr'));
        $this->exts->log("rows  :  " . $rows);
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('table > tbody > tr')[$i];
            $tags = $this->exts->querySelectorAll('td', $row);
            if ($this->exts->querySelector('button[data-testid="button-downloads"]:nth-child(1)', $row) != null) {
                $download_button = $this->exts->querySelector('button[data-testid="button-downloads"]:nth-child(1)', $row);
                $invoiceName = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getText()));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim($tags[2]->getText());
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getText())) . ' EUR';
                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                try {
                    $this->exts->log(__FUNCTION__ . ' trigger click.');
                    // $download_button->getLocationOnScreenOnceScrolledIntoView();
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    // Create new invoice if it not exisited
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                }
            }
        }
        if (
            $this->restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->querySelector('li.item_index_' . $paging_count) != null
        ) {
            $this->exts->moveToElementAndClick('li.item_index_' . $paging_count);
            $paging_count++;
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
