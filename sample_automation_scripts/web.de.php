<?php // I have replace exts->processTwoFactorAuth undefined function processTwoFactorAuth added message to trigger loginfailedConfimed 

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

    // Server-Portal-ID: 318 - Last modified: 25.06.2025 14:39:10 UTC - User: 1

    public $baseUrl = 'https://mein.web.de/rechnungen?inner=true';
    public $loginUrl = 'https://mein.web.de/rechnungen?inner=true';
    public $username_selector = '#username';
    public $password_selector = '#password';
    public $remember_me_selector = '';
    public $submit_login_selector = '#submit';
    public $check_login_failed_selector = '#errorMessageDiv ul li';
    public $check_login_success_selector = 'a[href*="logout"]';
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
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(3);
        $this->exts->waitTillAnyPresent([$this->username_selector, $this->check_login_success_selector]);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            if ($this->exts->exists('#thirdPartyFrame_permission_dialog')) {
                $this->switchToFrame("#thirdPartyFrame_permission_dialog");
                sleep(1);
                if ($this->exts->exists("#permission-iframe")) {
                    $this->switchToFrame("#permission-iframe");
                    sleep(1);
                }
                if ($this->exts->exists('button#save-all-pur')) {
                    $this->exts->moveToElementAndClick('button#save-all-pur');
                    sleep(5);
                }
                $this->exts->switchToDefault();
            }
            if ($this->exts->exists('iframe.permission-core-iframe')) {
                $this->switchToFrame('iframe.permission-core-iframe');
                sleep(1);
                if ($this->exts->exists('iframe[sandbox*="allow-popups"]')) {
                    $this->switchToFrame('iframe[sandbox*="allow-popups"]');
                    sleep(1);
                }
                if ($this->exts->exists('button#save-all-conditionally')) {
                    $this->exts->moveToElementAndClick('button#save-all-conditionally');
                    sleep(5);
                }
                $this->exts->switchToDefault();
            }

            $this->checkFillLogin();

            $this->exts->waitTillPresent($this->check_login_success_selector, 20);
            //redirected you too many times.
            for ($i = 0; $i < 2 && $this->exts->exists('button#reload-button'); $i++) {
                $this->exts->moveToElementAndClick('button#reload-button');
                sleep(10);
            }
            if ($this->exts->exists('button#reload-button')) {
                $this->clearChrome();
                $this->exts->openUrl($this->loginUrl);
                sleep(3);
                $this->exts->waitTillPresent($this->username_selector, 20);
                if ($this->exts->exists('#thirdPartyFrame_permission_dialog')) {
                    $this->switchToFrame("#thirdPartyFrame_permission_dialog");
                    sleep(1);
                    if ($this->exts->exists("#permission-iframe")) {
                        $this->switchToFrame("#permission-iframe");
                        sleep(1);
                    }
                    if ($this->exts->exists('button#save-all-pur')) {
                        $this->exts->moveToElementAndClick('button#save-all-pur');
                        sleep(5);
                    }
                    $this->exts->switchToDefault();
                }
                if ($this->exts->exists('iframe.permission-core-iframe')) {
                    $this->switchToFrame('iframe.permission-core-iframe');
                    sleep(1);
                    if ($this->exts->exists('iframe[sandbox*="allow-popups"]')) {
                        $this->switchToFrame('iframe[sandbox*="allow-popups"]');
                        sleep(1);
                    }
                    if ($this->exts->exists('button#save-all-conditionally')) {
                        $this->exts->moveToElementAndClick('button#save-all-conditionally');
                        sleep(5);
                    }
                    $this->exts->switchToDefault();
                }
                $this->checkFillLogin();

                $this->exts->waitTillPresent($this->check_login_success_selector, 10);
            }

            if ($this->exts->exists('a[data-open-dialog-id="confirmPasswordDialog"]')) {
                $this->exts->moveToElementAndClick('a[data-open-dialog-id="confirmPasswordDialog"]');
                sleep(5);
            }

            if ($this->exts->exists('a[id*="skip-logout"]')) {
                $this->exts->moveToElementAndClick('a[id*="skip-logout"]');
                sleep(5);
            }

            if ($this->exts->exists('div.twoFa-code-input__input input.separated-input__field')) {
                $two_fa_selector = '.twoFa-code-input__input input.separated-input__field';
                $trusted_btn_selector = 'button.pos-button';
                $this->processTwoFactorAuth($two_fa_selector, $trusted_btn_selector);
            }
            $this->checkFillTwoFactor();
        }

        if ($this->exts->exists('input[name*=usernameInput]') && $this->exts->exists("input[name*=passwordInput]")) {
            $this->reCheckFillLogin();
            $this->exts->waitTillPresent($this->check_login_success_selector, 10);
        }


        if ($this->exts->exists('div.instruction__container')) {
            $this->checkFill2FA();
            $this->exts->waitTillPresent($this->check_login_success_selector, 10);
        }

        if ($this->exts->exists('div[class*="instruction__alternative-link"] a')) {
            $this->exts->moveToElementAndClick('div[class*="instruction__alternative-link"] a');
            sleep(3);
            if ($this->exts->exists('a[href*="PhoneAlternativesContainer"]')) {
                $this->exts->moveToElementAndClick('a[href*="PhoneAlternativesContainer"]');
                sleep(5);
                if ($this->exts->exists('form[id="mtanStartPageForm"] button') || $this->exts->exists('button[name="sendSmsCode"]')) {
                    $this->exts->moveToElementAndClick('form[id="mtanStartPageForm"] button, button[name="sendSmsCode"]');
                    sleep(3);
                    $this->checkFillTwoFactor();
                }
            }
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::User logged in');
            sleep(3);
            $this->exts->capture("3-login-success");
            if ($this->exts->exists('#thirdPartyFrame_permission_dialog')) {
                try {
                    $this->switchToFrame("#thirdPartyFrame_permission_dialog");
                    sleep(1);
                    if ($this->exts->exists("#permission-iframe")) {
                        $this->switchToFrame("#permission-iframe");
                        sleep(1);
                    }
                    if ($this->exts->exists('button#save-all-pur')) {
                        $this->exts->moveToElementAndClick('button#save-all-pur');
                        sleep(5);
                    }
                    $this->exts->switchToDefault();
                } catch (TypeError $e) {
                    $this->exts->log($e->getMessage());
                    $this->exts->switchToDefault();
                }
            }
            $this->exts->log('=======URL:' . $this->exts->getUrl());
            if (count($this->exts->getElements('iframe')) > 0) {
                $iframe = $this->exts->getElements('iframe');
                foreach ($iframe as $i) {
                    $this->exts->log("____" . $i->getAttribute('id'));
                }
            }
            // Open invoices url and download invoice
            if ($this->exts->urlContains('home') && $this->exts->exists('#thirdPartyFrame_home')) {
                $this->switchToFrame('#thirdPartyFrame_home');
                if ($this->exts->exists('a[data-iac-usecase="open_customercare"]')) {
                    $this->exts->moveToElementAndClick('a[data-iac-usecase="open_customercare"]');
                }
                sleep(3);
                $this->exts->switchToDefault();
                $this->switchToFrame('#thirdPartyFrame_customercare');
                if ($this->exts->exists('a#rechnungenAnchor')) {
                    $this->exts->moveToElementAndClick('a#rechnungenAnchor');
                }
                $this->exts->switchToDefault();
                sleep(3);
                $this->processInvoices();
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
            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('Ihre Anmeldung war nicht erfolgreich!')) !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('button[data-testid="confirm"]') && stripos($this->exts->extract('body h1'), 'Ist Ihre E-Mail-Kontaktadresse noch aktuell') !== false) {
                $this->exts->account_not_ready();
            } elseif ($this->exts->urlContains('/interception') && $this->exts->exists('h2#hintInterceptions')) {
                $this->exts->account_not_ready();
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

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
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
            sleep(3);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function reCheckFillLogin()
    {
        $usernameSelector = "input[name*=usernameInput]";
        $passwordSelector = "input[name*=passwordInput]";
        $submitSelector = "button[name*=submitButton]";
        $imageSelector = "img.captcha__image";
        $inputCaptcha = "input[name*=captchaPanel]";
        if ($this->exts->getElement($usernameSelector) != null) {
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($usernameSelector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($passwordSelector, $this->password);
            sleep(1);
            $this->exts->processCaptcha($imageSelector, $inputCaptcha);
            $this->exts->capture("Filled_image_captcha");

            $this->exts->capture("3-re-login-page-filled");
            $this->exts->moveToElementAndClick($submitSelector);
            sleep(3);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("3-re-login-page-not-found");
        }
    }

    public function processTwoFactorAuth($two_fa_selector, $trusted_btn_selector)
    {
        $this->exts->log("--TWO FACTOR AUTH--");

        try {
            $this->exts->capture("TwoFactorAuth");
        } catch (\Exception $exception) {
            $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
            //var_dump($exception);
        }

        if ($this->exts->getElement($two_fa_selector) != null) {

            $two_factor_code = $this->exts->fetchTwoFactorCode();
            if (trim($two_factor_code) !== "") {
                try {
                    /* @var WebDriverElement $element */

                    $codeString = strval($two_factor_code);
                    $digitsArray = str_split($codeString);
                    $this->log("SIGNIN_PAGE: Entering two_factor_code.");
                    foreach ($digitsArray as $key => $value) {
                        $this->exts->moveToElementAndType('.twoFa-code-input__input input.separated-input__field:nth-child(' . ($key + 1) . ')', $digitsArray[$key]);
                    }


                    if ($this->exts->getElement($trusted_btn_selector) != null) {
                        /* @var WebDriverElement $button */
                        $button = $this->exts->getElement($trusted_btn_selector);
                        $button->click();
                    }
                    $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
                    $this->exts->capture("TwoFactorAuth-Filled");

                    sleep(10);
                    if ($this->exts->getElement($two_fa_selector) != null && $this->exts->two_factor_attempts < 3) {
                        $this->exts->two_factor_attempts++;
                        $this->exts->notification_uid = "";
                        $this->processTwoFactorAuth($two_fa_selector, $trusted_btn_selector);
                    }
                } catch (\Exception $exception) {
                    $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
                }
            }
        } else {
            $this->exts->log("--TWO_FACTOR_REQUIRED--");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form input[inputmode="numeric"] , form[action*="login/totp"] input[inputmode="numeric"].separated-input__field, form[id="id7"] input[inputmode="numeric"].separated-input__field, form[id="idb"] input[inputmode="numeric"].separated-input__field';
        $two_factor_message_selector = '.content-title + .a-ta-c > p';
        $two_factor_submit_selector = 'form[action*="login/totp"] button.pos-button, form[id="id7"] button.pos-button, form[id="idb"] button.pos-button';

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
                $this->exts->click_by_xdotool($two_factor_selector);
                $this->exts->type_text_by_xdotool($two_factor_code);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(5);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = "";
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

    private function checkFill2FA()
    {
        $two_factor_message_selector = 'ol.instruction__steps-list li, .instruction__steps p';

        echo  $two_factor_message_selector . "---------dddd";

        $two_factor_submit_selector = '';
        if ($this->exts->getElement($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->getElementsAttribute($two_factor_message_selector, 'innerText'));
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . 'Please enter "OK" after verifying';
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
                if ($this->exts->getElement('div.instruction__container') == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFill2FA();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                if (gettype($two_factor_code) == 'integer') {
                    if ($this->exts->exists('div[class*="instruction__alternative-link"] a')) {
                        $this->exts->moveToElementAndClick('div[class*="instruction__alternative-link"] a');
                        sleep(3);
                        if ($this->exts->exists('a[href*="PhoneAlternativesContainer"]')) {
                            $this->exts->moveToElementAndClick('a[href*="PhoneAlternativesContainer"]');
                            sleep(5);
                            if ($this->exts->exists('form[id="mtanStartPageForm"] button') || $this->exts->exists('button[name="sendSmsCode"]')) {
                                $this->exts->moveToElementAndClick('form[id="mtanStartPageForm"] button, button[name="sendSmsCode"]');
                                sleep(3);
                                $this->checkFillTwoFactor();
                            }
                        }
                    }
                }
                $this->exts->log("Not received two factor code");
            }
        }
    }

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    public $totalInvoices = 0;

    private function processInvoices()
    {
        sleep(3);
        $this->exts->waitTillAnyPresent(['iframe[id*="thirdPartyFrame_csc"]', 'iframe[id*="thirdPartyFrame_customercare"]', 'table#invoices tbody[id*=invoice] tr']);
        if ($this->exts->exists('iframe[id*="thirdPartyFrame_csc"]')) {
            $this->switchToFrame('[id*="thirdPartyFrame_csc"]');
            sleep(2);
        }
        if ($this->exts->exists('iframe[id*="thirdPartyFrame_customercare"]')) {
            $this->switchToFrame('[id*="thirdPartyFrame_customercare"]');
            sleep(2);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->getElements('table#invoices tbody[id*=invoice] tr');

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="rechnungen"]', $tags[0]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="rechnungen"]', $tags[0])->getAttribute("href");
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceDate = trim($tags[3]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[7]->getAttribute('innerText'))) . ' EUR';

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
            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName =  !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                $this->totalInvoices++;
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
