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

    // Server-Portal-ID: 314 - Last modified: 01.08.2025 15:22:05 UTC - User: 1

    // Start Script

    public $baseUrl = 'https://business.facebook.com/billing_hub/payment_activity';
    public $invoicePageUrl = 'https://www.facebook.com/ads/manager/billing/transactions/';
    public $isNoInvoice = true;
    public $orderPageUrl = "https://www.facebook.com/ads/manager/billing/transactions/";
    public $restrictPages = 3;
    public $last_state = array();
    public $current_state = array();
    public $account_uids = "";
    public $statement_only = 0;
    public $only_paid = 0;
    public $valid_payment_type = array("ZAHLUNG", "PAYMENT", "DIRECT DEBIT", "CREDIT CARD", "KREDITKARTE", "FACEBOOK COUPON", "COUPON FACEBOOK", "PAYPAL", "LASTSCHRIFT", "KREDITKORT", "KREDI KARTÄ±", "HITELK", "TARJETA DE CRÉDITO", 'Carte de crédit', "CARTÃO DE CRÉDITO", "KREDIETKAART", "Prélèvement bancaire", "Kredi Kartı", "Ad Credit", "Invoiced Credit", "Manual Payment", "Carrier Billing");
    public $total_invoices = 0;
    public $user_birthday = ""; // config variable
    public $only_billing_summary = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->disable_extensions();
        if ($this->exts->docker_restart_counter == 0) {
            $this->statement_only = isset($this->exts->config_array["statement_only"]) ? (int)@$this->exts->config_array["statement_only"] : $this->statement_only;
            $this->account_uids = isset($this->exts->config_array["fb_accounts"]) ? trim($this->exts->config_array["fb_accounts"]) : $this->account_uids;
            $this->only_billing_summary = isset($this->exts->config_array["only_billing_summary"]) ? (int)@$this->exts->config_array["only_billing_summary"] : $this->only_billing_summary;
            $this->user_birthday = isset($this->exts->config_array["birthday"]) ? (int)@$this->exts->config_array["birthday"] : $this->user_birthday;
            $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : $this->restrictPages;
            $this->only_paid = isset($this->exts->config_array['only_paid']) ? (int)trim($this->exts->config_array['only_paid']) : 0;
        } else {
            $this->last_state = $this->current_state;
        }

        $this->exts->openUrl($this->baseUrl);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture('1-init-page');

        // $this->exts->openUrl($this->facebook_loginUrl);
        if (!$this->isFacebookLoggedin()) {
            $this->loginFacebookIfRequired();
        }
        $this->processAfterFacebookLogin();
    }

    /**================================== FACEBOOK LOGIN =================================================**/
    public $facebook_baseUrl = 'https://www.facebook.com';
    public $facebook_loginUrl = 'https://www.facebook.com';
    public $facebook_username_selector = 'form input#email';
    public $facebook_password_selector = 'form input#pass';
    public $facebook_submit_login_selector = 'form button[type="submit"], #logginbutton, #loginbutton';
    public $facebook_check_login_failed_selector = '//span[text()="The password you entered is incorrect."]';
    public $facebook_check_login_success_selector = '#logoutMenu, #ssrb_feed_start, div[role="navigation"] a[href*="/me"], a[href="/notifications/"], [role="navigation"] [href*="facebook.com/friends/"], #globalNavNotificationsJewel, [data-pagelet="LeftNav"] a[href*="/settings"]';
    public $processCaptcha_img = 'img[src*="/captcha/"]';
    public $processCaptcha_input = 'input[type="text"]';
    public $processCaptcha_sub = '//span[normalize-space(text())="Continue"]';

    private function loginFacebookIfRequired()
    {
        if ($this->exts->urlContains('facebook.')) {
            $this->exts->log('Start login with facebook');
            $this->exts->openUrl($this->facebook_loginUrl);
            sleep(5);
            // Sometime it require accept cookie twice
            $this->accept_cookie_page();
            $this->accept_cookie_page();
            $this->checkFillFacebookLogin();
            sleep(2);
            $this->exts->processCaptcha($this->processCaptcha_img, $this->processCaptcha_input);
            sleep(1);
            $this->exts->click_element($this->processCaptcha_sub);
            sleep(5);
            if ($this->exts->exists('#login_form')) {
                $this->exts->capture("2-seconds-login-page");
                $this->checkFillFacebookLogin();
                sleep(2);
                $this->exts->processCaptcha($this->processCaptcha_img, $this->processCaptcha_input);
                sleep(1);
                $this->exts->click_element($this->processCaptcha_sub);
                if ($this->exts->exists('#login_form')) {
                    $this->clearChrome();
                    $this->exts->openUrl($this->facebook_loginUrl);
                    sleep(5);
                    // Sometime it require accept cookie twice
                    $this->accept_cookie_page();
                    $this->accept_cookie_page();
                    $this->checkFillFacebookLogin();
                    sleep(2);
                    $this->exts->processCaptcha($this->processCaptcha_img, $this->processCaptcha_input);
                    sleep(1);
                    $this->exts->click_element($this->processCaptcha_sub);
                    // [role="main"] h2.uiHeaderTitle
                    // bergehend blockiert
                    // Cette fonction est temporairement bloqu
                    // Blocco temporaneo
                    // Se te bloque
                    // Temporarily Blocked
                }
            }
            $mesg = strtolower($this->exts->extract('form.checkpoint > div, [role="dialog"] [data-tooltip-display="overflow"]', null, 'innerText'));
            if (
                strpos($mesg, 'temporarily blocked') !== false
                || strpos($mesg, 'Your account has been deactivated') !== false
                || strpos($mesg, 'Download your information') !== false
                || strpos($mesg, 'Your account has been suspended') !== false
                || strpos($mesg, 'Your account has been disabled') !== false
                || strpos($mesg, 'Your file is ready') !== false
                || strpos($mesg, 'bergehend blockiert') !== false
            ) {
                // account locked
                $this->exts->log('User login failed: ' . $this->exts->getUrl());
                $this->exts->account_not_ready();
            } elseif (
                strpos($this->exts->extract('body'), 'Dein Konto wurde gesperrt') !== false
                || strpos($this->exts->extract('body'), 'Dein Konto wurde deaktiviert') !== false
                || strpos($this->exts->extract('body'), 'Deine Informationen herunterladen') !== false
                || strpos($this->exts->extract('body'), 'Dein Konto wurde vorübergehend gesperrt') !== false
                || strpos($this->exts->extract('body'), 'Je account is uitgeschakeld') !== false
                || strpos($this->exts->extract('body'), 'Deine Datei steht bereit') !== false
                || strpos($this->exts->extract('body'), 'Suspended Your Account') !== false
            ) {
                // account locked
                $this->exts->log('User login failed: ' . $this->exts->getUrl());
                $this->exts->account_not_ready();
            }
            $this->checkAndCompleteFacebookTwoFactor();
            sleep(10);
            if ($this->exts->urlContains('two_factor/remember_browser') && $this->exts->exists('form + div > div > div[role="button"]')) {
                $this->exts->moveToElementAndClick('form + div > div > div[role="button"]');
                sleep(10);
            }
            $this->checkAndCompleteFacebookTwoFactor();
            if ($this->exts->urlContains('two_factor/remember_browser') && $this->exts->exists('form + div > div > div[role="button"]')) {
                $this->exts->moveToElementAndClick('form + div > div > div[role="button"]');
                sleep(10);
            }

            $this->accept_cookie_page();
            $this->accept_cookie_page();
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required facebook login.');
            $this->exts->capture("3-no-facebook-required");
        }
    }

    /**
     * Entry Method thats identify and click element by element text
     * Because many website use generated html, It did not have good selector structure, indentify element by text is more reliable
     * This function support seaching element by multi language text or regular expression
     * @param String $selector Selector string of element.
     * @param String $multi_language_texts the text label of element that want to click, decode html if string contain unicode character
     * @param Element $parent_element parent element when we search element inside.
     * @param Bool $is_absolutely_matched true if want seaching absolutely, false if want to seaching relatively.
     */
    private function find_and_click_by_text($selector, $multi_language_texts, $parent_element = null, $is_absolutely_matched = true)
    {
        $this->exts->log(__FUNCTION__);
        if (is_string($multi_language_texts)) {
            $multi_language_texts = array($multi_language_texts);
        }
        // Seaching matched element
        $object_elements = $this->exts->getElements($selector, $parent_element);
        foreach ($multi_language_texts as $searching_label) {
            $searching_label = urldecode($searching_label);
            foreach ($object_elements as $object_element) {
                $found = false;
                $element_text = $object_element->getAttribute('innerText');
                if ($is_absolutely_matched) {
                    $found = urlencode(trim($element_text)) == urlencode($searching_label);
                } else {
                    $found = stripos(urlencode(trim($element_text)), urlencode($searching_label)) !== false;
                }

                if ($found) {
                    $this->exts->log(__FUNCTION__ . " Found $selector $searching_label");
                    try {
                        $this->exts->log(__FUNCTION__ . ' trigger click.');
                        $object_element->click();
                    } catch (\Exception $exception) {
                        $this->exts->log(__FUNCTION__ . ' by javascript' . $exception);
                        $this->exts->executeSafeScript("arguments[0].click()", [$object_element]);
                    }
                    return true;
                }
            }
        }
        return null;
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
    private function checkAndCompleteFacebookTwoFactor()
    {
        $this->accept_cookie_page();
        $this->accept_cookie_page();
        $this->exts->capture('facebook-twofactor-checking');
        if ($this->exts->exists('a[href*="/recover/initiate"][href*="ars=login_challenges"]')) {
            $this->exts->capture('facebook-twofactor-device-list');
            $this->exts->moveToElementAndClick('a[href*="/recover/initiate"][href*="ars=login_challenges"]');
            sleep(5);
        }

        // Use USB 2FA device => choose different 2FA
        if ($this->exts->exists('input[name="checkpointU2Fauth"]') && $this->exts->exists('a[href*="/checkpoint/?next&no_fido=true"]')) {
            $this->exts->log('// Use USB 2FA device => choose different 2FA');
            $this->exts->openUrl('https://www.facebook.com/checkpoint/?next&no_fido=true');
            sleep(3);
            $this->exts->capture('facebook-twofactor-no_fido');
        }

        // choose 2FA verification method
        $facebook_two_factor_selector = 'form.checkpoint[action*="/checkpoint"] input[name*="captcha_response"], form.checkpoint[action*="/checkpoint"] input[name*="approvals_code"], input#recovery_code_entry';
        if ($this->exts->exists('img[alt="Warning"]') && $this->exts->getElement('//ul/li//u[text()="mobile" or text()="laptop"]', null, 'xpath') != null && $this->exts->exists('button[name="submit[_footer]"]')) {
            $this->exts->capture('confirm-login-required');
            $this->exts->moveToElementAndClick('button[name="submit[_footer]"]'); // Click back to Another method
            sleep(7);
            $this->exts->capture('backed-to-method-list');
        }

        if ($this->exts->exists('img[src*="Device-Mobile"]')) {
            $this->find_and_click_by_text(
                '[role="button"]',
                [
                    'Try another way',
                    'Andere Methode ausprobieren',
                    'Essayer%20d%E2%80%99une%20autre%20mani%C3%A8re',
                    'Andere Methode nutzen'
                ]
            );
            sleep(2);
            $this->find_and_click_by_text(
                '[role="dialog"] label',
                [
                    'Application ',
                    ' app',
                    '-App',
                    'WhatsApp',
                    'Whats-App',
                    'Authentifizierungs-App'
                ],
                null,
                false
            );
            sleep(2);
            $this->find_and_click_by_text(
                '[role="button"]',
                [
                    'Continue',
                    'Weiter',
                    'Continuer'
                ]
            );
            sleep(2);
            $this->exts->capture('backed-to-method-list');
        }

        if ($this->exts->exists('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]') && !$this->exts->exists($facebook_two_factor_selector)) {
            if (!$this->exts->exists('input[name="verification_method"]')) {
                $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
                sleep(3);
                if ($this->exts->exists('button#checkpointSubmitButton[disabled]')) {
                    sleep(10);
                }
            }
            $this->exts->capture('verification-method');
            //Approve your login on another computer - 14
            //Log in with your Google account - 35
            //Get a code sent to your email = Receive code by email - 37
            //Get code on the phone - 34
            // Choose send code to phone, if not available, choose send code to email.
            $facebook_verification_method = $this->exts->getElementByText('.uiInputLabelLabel', ['phone', 'telefon', 'telefoon', 'teléfono', 'puhelin', 'Telefone', 'téléphone', 'telephone', 'Telefon',], null, false);
            if ($facebook_verification_method == null) {
                $facebook_verification_method = $this->exts->getElementByText('.uiInputLabelLabel', ['email', 'e-mail', 'E-Mail', 'e-mailadres', 'electrónico', 'elektronisk', 'sähköposti', 'E-postana'], null, false);
            }
            if ($facebook_verification_method != null) {
                $this->exts->click_element($facebook_verification_method);
            } else {
                $this->exts->log('choose first option.');
                $this->exts->moveToElementAndClick('.uiInputLabelLabel');
            }
            $this->exts->capture('verification-method-selected');
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
            sleep(5);
            if ($this->exts->exists('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]') && !$this->exts->exists($facebook_two_factor_selector)) {
                // Click some Next button
                $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
                sleep(3);
                if ($this->exts->exists('button#checkpointSubmitButton[disabled]')) {
                    sleep(10);
                }
                if ($this->exts->exists('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]') && !$this->exts->exists($facebook_two_factor_selector)) {
                    $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
                    sleep(3);
                }
                if ($this->exts->exists('button#checkpointSubmitButton[disabled]')) {
                    sleep(10);
                }
            }
            $this->exts->log('fill code and continue: two_factor_response');
            $this->checkFillFacebookTwoFactor();
            $this->exts->capture('verification-method-after-solve');
            if ($this->exts->exists('[data-testid="dialog_title_close_button"]')) {
                $this->exts->moveToElementAndClick('[data-testid="dialog_title_close_button"]');
                sleep(1);
            }
            if ($this->exts->exists('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]') && !$this->exts->exists($facebook_two_factor_selector)) {
                $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
                sleep(5);
            }
        } else {
            $this->exts->log('fill code and continue: approvals_code');
            $this->checkFillFacebookTwoFactor();
            if ($this->exts->exists('[role="dialog"] [action="cancel"]')) {
                // Close the popup say: It looks like you were misusing this feature by going too fast. Youâ€™ve been temporarily blocked from using it
                $this->exts->moveToElementAndClick('[role="dialog"] [action="cancel"]');
            }
            if ($this->exts->exists('[data-testid="dialog_title_close_button"]')) {
                $this->exts->moveToElementAndClick('[data-testid="dialog_title_close_button"]');
                sleep(1);
            }
        }
    }
    private function accept_cookie_page()
    {
        if ($this->exts->exists('[data-testid="cookie-policy-dialog-accept-button"], [data-cookiebanner="accept_button"], [data-testid="cookie-policy-manage-dialog-accept-button"], div[aria-label="Accept All"]:not([aria-disabled="true"]), div[aria-label="Allow all cookies"]:not([aria-disabled="true"]), div[aria-label="Alle Cookies erlauben"]:not([aria-disabled="true"]), div[aria-label="Alle akzeptieren"]:not([aria-disabled="true"])')) {
            $this->exts->moveToElementAndClick('[data-testid="cookie-policy-dialog-accept-button"], [data-cookiebanner="accept_button"], [data-testid="cookie-policy-manage-dialog-accept-button"], div[aria-label="Accept All"]:not([aria-disabled="true"]), div[aria-label="Allow all cookies"]:not([aria-disabled="true"]), div[aria-label="Alle Cookies erlauben"]:not([aria-disabled="true"]), div[aria-label="Alle akzeptieren"]:not([aria-disabled="true"])');
            sleep(3);
        }
        $accept_cookie_button = $this->exts->getElementByText('[role="dialog"] div[role="button"]:not([aria-disabled]) span', ['Alle akzeptieren', 'Accept', 'Cookies erlauben', 'Autoriser tous les cookies', 'Allow All Cookies'], null, false);
        if ($accept_cookie_button !== null && $this->exts->urlContains('/user_cookie_prompt')) {
            $this->exts->click_element($accept_cookie_button);
            sleep(2);
        } else if ($this->exts->urlContains('/user_cookie_prompt')) {
            $accept_cookie_button = $this->exts->getElementByText('[role="dialog"] div[role="button"]:not([aria-disabled]) span', ['Cookie', 'cookie'], null, false);
            $this->exts->click_element($accept_cookie_button);
            sleep(2);
        }

        if ($this->exts->urlContains('/privacy/consent/reconciliation')) {
            $this->exts->capture("reconciliation-consent");
            $this->exts->moveToElementAndClick('[aria-label*="nicht verwenden"], [aria-label*="Do not"], [aria-label*="Don"]');
            sleep(7);
        } else if ($this->exts->urlContains('/consent/reconciliation_3pd_blocking/')) {
            $this->exts->capture("reconciliation-consent-close");
            $this->exts->moveToElementAndClick('[role="button"][aria-label="Schließen"], [role="button"][aria-label="Close"]');
            sleep(7);
        } else if ($this->exts->urlContains('ad_free_subscription') && $this->exts->urlContains('/consent')) {
            $this->exts->capture("ad-free-consent");
            $this->exts->moveToElementAndClick('[role="dialog"] [role="button"]');
            sleep(5);
            $use_free_button = $this->exts->getElementByText('[role="dialog"] div[role="button"]:not([aria-disabled]) span', ['Use for free', 'Kostenlose Nutzung', 'Kostenlose'], null, false);
            $this->exts->click_element($use_free_button);
            sleep(5);
            $agree_button = $this->exts->getElementByText('[role="dialog"] div[role="button"]:not([aria-disabled]) span', ['Agree', 'agree', 'Zustimmen'], null, false);
            $this->exts->click_element($agree_button);
            sleep(10);
        }
        sleep(5);
        if ($this->exts->exists('button[title="Allow all cookies"]')) {
            $this->exts->click_element('button[title="Allow all cookies"]');
        }
        sleep(5);
        if ($this->exts->exists('img[src*="captcha"]')) {
            $this->exts->processCaptcha('img[src*="captcha"]', 'input[type="text"]');
            $this->exts->capture('captcha-filled');
            $submitBtn = $this->exts->getElement("//div[@role='button' and .//*[contains(text(), 'Continue') or contains(text(), 'Weiter')]]", null, 'xpath');
            try {
                $this->exts->log('Click Continue button');
                $submitBtn->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click Continue button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$submitBtn]);
            }
            sleep(10);
        }
    }
    private function checkFillTwoFactorForAccountVerification()
    {
        $two_factor_selector = 'div[role="dialog"] input';
        $two_factor_message_selector = 'div[role="dialog"] div span:has(> strong)';

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
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->find_and_click_by_text('div[role="dialog"] div[role="button"]', ['Send', 'Senden', 'Envoyer']);
                sleep(5);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactorForAccountVerification();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    private function checkFillFacebookLogin()
    {
        sleep(5);
        $this->exts->waitTillPresent('[aria-label="Continue"]', 30);
        if ($this->exts->exists('[aria-label="Continue"]')) {
            $this->exts->click_element('[aria-label="Continue"]');
            sleep(3);
        }
        $this->exts->waitTillPresent($this->facebook_password_selector, 30);
        if ($this->exts->getElement($this->facebook_password_selector) != null || $this->exts->getElement($this->facebook_username_selector) != null) {
            if ($this->exts->exists('[role="dialog"] button[data-testid="cookie-policy-dialog-accept-button"], div[aria-label="Accept All"][tabindex="0"]')) {
                $this->exts->moveToElementAndClick('[role="dialog"] button[data-testid="cookie-policy-dialog-accept-button"], div[aria-label="Accept All"][tabindex="0"]');
                sleep(2);
            }
            $this->exts->capture("2-facebook-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndClick($this->facebook_username_selector);
            $this->exts->moveToElementAndType($this->facebook_username_selector, '');
            $this->exts->moveToElementAndType($this->facebook_username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndClick($this->facebook_password_selector);
            $this->exts->moveToElementAndType($this->facebook_password_selector, '');
            $this->exts->moveToElementAndType($this->facebook_password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-facebook-login-page-filled");
            $this->exts->moveToElementAndClick($this->facebook_submit_login_selector);
            // sleep(10);
            if ($this->exts->exists('input[name="pass"]') && $this->exts->getElement($this->facebook_username_selector) == null) {
                $this->exts->moveToElementAndType('input[name="pass"]', $this->password);
                sleep(1);
                $this->exts->moveToElementAndClick('form[action*="login"] input[type="submit"]');
                sleep(5);
            }
            $this->exts->capture("2-after-login-submit");
            $this->accept_cookie_page();

            $is_captcha = $this->solve_captcha_by_clicking(0);
            if ($is_captcha) {
                for ($i = 1; $i < 30; $i++) {
                    if ($is_captcha == false) {
                        break;
                    }
                    $this->accept_cookie_page();
                    $is_captcha = $this->solve_captcha_by_clicking($i);
                    $this->exts->switchToDefault();
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-facebook-login-page-not-found");
        }
    }
    private function checkFillFacebookTwoFactor()
    {
        $facebook_two_factor_selector = 'form input';
        $facebook_two_factor_message_selector = 'h2 + *';

        if (($this->exts->urlContains('/two_factor') || $this->exts->urlContains('auth_platform/codesubmit')) && $this->exts->exists($facebook_two_factor_selector)) {
            $this->exts->log("Facebook two factor page found.");
            $this->exts->capture("2.1-facebook-two-factor");

            if ($this->exts->getElement($facebook_two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($facebook_two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($facebook_two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Facebook Message:\n" . $this->exts->two_factor_notif_msg_en);

            $this->exts->two_factor_timeout = 2;
            $this->exts->notification_uid = ''; // set this to clear 2FA response cache
            $this->exts->reuseMfaSecret();
            $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (empty($facebook_two_factor_code)) {
                $this->exts->two_factor_timeout = 2;
                $this->exts->notification_uid = '';
                $this->exts->reuseMfaSecret();
                $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
            }
            if (empty($facebook_two_factor_code)) {
                $this->exts->two_factor_timeout = 7;
                $this->exts->notification_uid = '';
                $this->exts->reuseMfaSecret();
                $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
            }

            if (!empty($facebook_two_factor_code) && trim($facebook_two_factor_code) != '') {
                $this->exts->log("FacebookCheckFillTwoFactor: Entering facebook_two_factor_code." . $facebook_two_factor_code);
                if ($this->exts->exists('[role="dialog"] [action="cancel"]')) {
                    // Close the popup say: It looks like you were misusing this feature by going too fast. Youâ€™ve been temporarily blocked from using it
                    $this->exts->moveToElementAndClick('[role="dialog"] [action="cancel"]');
                }
                if ($this->exts->exists('[role="dialog"] [data-testid="dialog_title_close_button"]')) {
                    $this->exts->moveToElementAndClick('[role="dialog"] [data-testid="dialog_title_close_button"]');
                }
                $this->exts->moveToElementAndType($facebook_two_factor_selector, $facebook_two_factor_code);
                if ($this->exts->exists('[role="dialog"] [action="cancel"]')) {
                    // Close the popup say: It looks like you were misusing this feature by going too fast. Youâ€™ve been temporarily blocked from using it
                    $this->exts->moveToElementAndClick('[role="dialog"] [action="cancel"]');
                }
                $this->exts->capture("2.2-facebook-two-factor-filled-" . $this->exts->two_factor_attempts);
                if ($this->exts->exists('[role="dialog"] [action="cancel"]')) {
                    // Close the popup say: It looks like you were misusing this feature by going too fast. Youâ€™ve been temporarily blocked from using it
                    $this->exts->moveToElementAndClick('[role="dialog"] [action="cancel"]');
                }
                $this->find_and_click_by_text(
                    '[role="button"]',
                    [
                        'Continue',
                        'Weiter',
                        'Continuer',
                        'Continuar'
                    ]
                );
                sleep(2);
                $this->exts->capture('2.2-facebook-two-factor-submitted-' . $this->exts->two_factor_attempts);

                if ($this->exts->getElement($facebook_two_factor_selector) == null) {
                    $this->exts->log("Facebook two factor solved");
                    // Save device/ save browser
                    for ($i = 0; $i < 2; $i++) {
                        if ($this->exts->exists('input[value*="save_device"]')) {
                            $this->exts->moveToElementAndClick('input[value*="save_device"]');
                            $this->exts->moveToElementAndClick($facebook_two_factor_submit_selector);
                            $this->exts->capture('2.2-save-browser');
                            sleep(2);
                        } else if ($this->exts->exists('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]')) {
                            $this->exts->log('Review recent login (Continue)');
                            $this->exts->log('Review recent login (This was me)');
                            $this->exts->moveToElementAndClick('button#checkpointSubmitButton, form[action*="/recover/code"] button[type="submit"]');
                            sleep(2);
                            $this->exts->capture('2.3-save-browser');
                        }
                        //Skip password update
                        if ($this->exts->urlContains('checkpoint/?next') && $this->exts->exists('button#checkpointSecondaryButton[name="submit[Skip]"]')) {
                            $this->exts->capture('2.3-Update-password');
                            $this->exts->moveToElementAndClick('button#checkpointSecondaryButton[name="submit[Skip]"]');
                            sleep(5);
                            $this->exts->capture('2.3-After-skip-password-update');
                        }

                        sleep(7);
                    }
                }
                sleep(7);
                if ($this->exts->getElement('//*[text()="Trust this device"]/../../../../..', null, 'xpath') != null) {
                    $this->exts->click_element('//*[text()="Trust this device"]/../../../../..');
                }
            } else {
                $this->exts->log("Facebook failed to fetch two factor code!!!");
            }
        } else if ($this->exts->exists('.bizWebLoginContainer input[placeholder*="Code"], .bizWebLoginContainer input[placeholder*="code"]') && $this->exts->urlContains('/security/twofactor/reauth/')) {
            $this->exts->capture('2.2-facebook-business-2FA');
            $this->exts->two_factor_notif_msg_en = $this->exts->extract('.bizWebLoginContainer [aria-labelledby] > div > div:nth-child(2) > div >  div:nth-child(2)', null, 'innerText');
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Facebook Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = '';
            $this->exts->reuseMfaSecret();
            $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($facebook_two_factor_code) && trim($facebook_two_factor_code) != '') {
                $this->exts->log("FacebookCheckFillTwoFactor: Entering facebook_two_factor_code." . $facebook_two_factor_code);
                $this->exts->moveToElementAndType('.bizWebLoginContainer input[placeholder*="Code"], .bizWebLoginContainer input[placeholder*="code"]', $facebook_two_factor_code);
                sleep(1);
                $this->exts->capture('2.2-facebook-business-2FA-filled');
                $this->exts->moveToElementAndClick('.bizWebLoginContainer [role="button"]');
                sleep(7);
                $this->exts->capture('2.2-facebook-business-2FA-submitted');
            }
        }
        // 08/10/2020: 2FA by confirm login on other devices (click on noti and accept)
        if ($this->exts->exists('img[src*="UnifiedDelta-Device-"]')) {
            $this->exts->log('2FA by confirm login on other devices (click on noti and accept)');
            $this->exts->capture('2FA-by-confirm-login-on-other-devices');

            // $facebook_two_factor_selector = 'input#passcode';
            $facebook_two_factor_message_selector = 'h2 + span';
            $facebook_two_factor_submit_selector = 'button#checkpointSubmitButton, form[action*="/recover/code"] div.uiInterstitialBar button, form[action*="/recover/code"] button[type="submit"]';

            if ($this->exts->getElement($facebook_two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->log("Two factor page found.");
                $this->exts->capture("2.1-two-factor");

                if ($this->exts->getElement($facebook_two_factor_message_selector) != null) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->extract($facebook_two_factor_message_selector, null, 'innerText');
                    $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                }
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\nPlease reply \"OK\" when you have approved the connection and select \"Save this device\" notification in the Facebook app.";
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . "\nBitte antworten Sie mit \"OK\", wenn Sie die Verbindung genehmigt haben, und wählen Sie \"Dieses Gerät speichern\" in der Facebook App.";
                if ($this->exts->two_factor_attempts == 2) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
                }
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

                // set timeout to 2 minutes because this is timeout for 2FA; after 2mins, even if we receive 2FA respone, the portal still redirect to login page
                $this->exts->two_factor_timeout = 5;
                $this->exts->notification_uid = ''; // set this to clear 2FA response cache
                $this->exts->reuseMfaSecret();
                $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
                if (!empty($facebook_two_factor_code) && trim($facebook_two_factor_code) != '') {
                    $this->exts->log("2FA response: " . $facebook_two_factor_code);
                    sleep(3);
                    $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                    if ($this->exts->exists($facebook_two_factor_submit_selector)) {
                        $this->exts->moveToElementAndClick($facebook_two_factor_submit_selector);
                        sleep(15);
                    }
                } else {
                    $this->exts->log("Not received two factor code");
                }
            }
        }
    }
    private function disable_extensions()
    {
        $this->exts->openUrl('chrome://extensions/'); // disable Block origin extension
        sleep(2);
        $this->exts->execute_javascript("
        let manager = document.querySelector('extensions-manager');
        if (manager && manager.shadowRoot) {
            let itemList = manager.shadowRoot.querySelector('extensions-item-list');
            if (itemList && itemList.shadowRoot) {
                let items = itemList.shadowRoot.querySelectorAll('extensions-item');
                items.forEach(item => {
                    let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
                    if (toggle) toggle.click();
                });
            }
        }
    ");
    }

    private function switchToFrame($query_string)
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

    private function solve_captcha_by_clicking($count = 1)
    {
        $this->exts->log("Checking captcha");
        sleep(5);
        $this->accept_cookie_page();
        $this->exts->waitTillPresent('#captcha-recaptcha');
        if ($this->exts->exists('#captcha-recaptcha')) {
            $this->switchToFrame('#captcha-recaptcha');
            sleep(5);
        }
        $unsolved_captcha_submit_selector = 'iframe[title="reCAPTCHA"]';
        $captcha_challenger_wraper_selector = 'div[style*="visibility: visible"] > div > iframe[title="recaptcha challenge expires in two minutes"]';

        $this->exts->waitTillAnyPresent([$unsolved_captcha_submit_selector, $captcha_challenger_wraper_selector], 20);
        $this->accept_cookie_page();

        if ($this->exts->check_exist_by_chromedevtool($unsolved_captcha_submit_selector) || $this->exts->exists($captcha_challenger_wraper_selector)) {
            $this->exts->capture("mailjet-captcha");

            if (!$this->exts->check_exist_by_chromedevtool($captcha_challenger_wraper_selector)) {
                $this->exts->click_by_xdotool($unsolved_captcha_submit_selector);
                $this->exts->waitTillPresent($captcha_challenger_wraper_selector, 5);
            }
            $captcha_instruction = '';
            $language_code = '';

            //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
            sleep(5);

            if ($this->exts->exists($captcha_challenger_wraper_selector)) {
                $coordinates = $this->getCoordinates($captcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = false);
                if ($coordinates != '') {
                    foreach ($coordinates as $coordinate) {
                        $this->exts->click_by_xdotool($captcha_challenger_wraper_selector, (int) $coordinate['x'], (int) $coordinate['y']);
                    }

                    $this->exts->capture("mailjet-captcha-selected " . $count);
                    if ($this->exts->exists($captcha_challenger_wraper_selector)) {
                        $this->exts->makeFrameExecutable($captcha_challenger_wraper_selector)->click_element('button[id="recaptcha-verify-button"]');
                    }
                    sleep(10);
                    return true;
                }
            }
            return false;
        }
    }
    private function getCoordinates(
        $captcha_image_selector,
        $instruction = '',
        $lang_code = '',
        $json_result = false,
        $image_dpi = 75
    ) {
        $this->exts->log("--GET Coordinates By 2CAPTCHA--");
        $response = '';
        $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
        $source_image = imagecreatefrompng($image_path);
        imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', $image_dpi);

        $cmd = $this->exts->config_array['click_captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid . " --CAPTCHA_INSTRUCTION::" . urlencode($instruction) . " --LANG_CODE::" . urlencode($lang_code) . " --JSON_RESULT::" . urlencode($json_result);
        $this->exts->log('Executing command : ' . $cmd);
        exec($cmd, $output, $return_var);
        $this->exts->log('Command Result : ' . print_r($output, true));

        if (!empty($output)) {
            $output = trim($output[0]);
            if ($json_result) {
                if (strpos($output, '"status":1') !== false) {
                    $response = json_decode($output, true);
                    $response = $response['request'];
                }
            } else {
                if (strpos($output, 'coordinates:') !== false) {
                    $array = explode("coordinates:", $output);
                    $response = trim(end($array));
                    $coordinates = [];
                    $pairs = explode(';', $response);
                    foreach ($pairs as $pair) {
                        preg_match('/x=(\d+),y=(\d+)/', $pair, $matches);
                        if (!empty($matches)) {
                            $coordinates[] = ['x' => (int) $matches[1], 'y' => (int) $matches[2]];
                        }
                    }
                    $this->exts->log('>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>');
                    $this->exts->log(print_r($coordinates, true));
                    return $coordinates;
                }
            }
        }

        if ($response == '') {
            $this->exts->log("Can not get result from API");
        }
        return $response;
    }
    private function isFacebookLoggedin()
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->capture(__FUNCTION__);
        return $this->exts->exists($this->facebook_check_login_success_selector);
    }

    private function processAfterFacebookLogin()
    {
        $this->accept_cookie_page();
        $this->accept_cookie_page();

        // then check user logged in or not
        if ($this->isFacebookLoggedin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User facebook logged in');
            $this->exts->capture("3-facebook-login-success");

            // Do the rest of work below (e.g: download invoices...)
            // open invoice page and download
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(20);
            $this->checkFillFacebookLogin();
            sleep(2);
            $this->exts->processCaptcha($this->processCaptcha_img, $this->processCaptcha_input);
            sleep(1);
            $this->exts->click_element($this->processCaptcha_sub);
            sleep(5);
            $this->checkAndCompleteFacebookTwoFactor();

            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            //
            $this->exts->log(__FUNCTION__ . '::Use facebook login failed');
            $this->exts->log('::URL login failed:: ' . $this->exts->getUrl());
            $mesg = strtolower($this->exts->extract('form.checkpoint > div', null, 'innerText'));
            if (
                strpos($mesg, 'account has been temporarily blocked') !== false
                || strpos($mesg, 'your account has been deactivated') !== false
                || strpos($mesg, 'download your information') !== false
                || strpos($mesg, 'your account has been suspended') !== false
                || strpos($mesg, 'your account has been disabled') !== false
                || strpos($mesg, 'your file is ready') !== false
            ) {
                // account locked
                $this->exts->account_not_ready();
            } elseif (
                stripos($this->exts->extract('body'), 'dein konto wurde gesperrt') !== false
                || stripos($this->exts->extract('body'), 'dein konto wurde deaktiviert') !== false
                || stripos($this->exts->extract('body'), 'deine informationen herunterladen') !== false
                || stripos($this->exts->extract('body'), 'Danach wird dein Konto dauerhaft deaktiviert') !== false
                || stripos($this->exts->extract('body'), 'je account is uitgeschakeld') !== false
                || stripos($this->exts->extract('body'), 'our account has been disabled') !== false
                || stripos($this->exts->extract('body'), 'deine datei steht bereit') !== false
                || stripos($this->exts->extract('body'), 'account is currently unavailable due to a problem with the site') !== false
                || stripos($this->exts->extract('body'), 'dein konto ist derzeit wegen eines problems mit der seite nicht verf') !== false
                || stripos($this->exts->extract('body'), 'wir haben dein Konto gesperrt') !== false
                || stripos($this->exts->extract('body'), 'bloquer votre compte') !== false
                || stripos($this->exts->extract('body'), 'locked your account') !== false
                || stripos($this->exts->extract('body'), 'deinem Konto haben wir') !== false
            ) {
                // account locked
                $this->exts->account_not_ready();
            } elseif (stripos($this->exts->extract('h1'), 'something went wrong') !== false) {
                $this->exts->account_not_ready();
            } elseif (stripos($this->exts->extract('#login_form #error_box'), 'Wrong credentials') !== false) {
                $this->exts->loginFailure(1);
            } elseif (stripos($this->exts->extract('div.uiContextualLayer'), 'Der von dir eingegebene Anmeldecode entspricht nicht dem') !== false || stripos($this->exts->extract('div.uiContextualLayer'), 'The login code you entered does not match') !== false || ($this->exts->exists('form.checkpoint span[data-xui-error]') && stripos($this->exts->getElement('form.checkpoint span[data-xui-error]')->getAttribute('data-xui-error'), 'Der von dir eingegebene Anmeldecode entspricht nicht dem') !== false)) {
                $this->exts->loginFailure(1);
            } elseif (stripos($this->exts->extract('[aria-labelledby="Assistive Identification"]'), 'find an account matching the login info you entered, but') !== false) {
                $this->exts->loginFailure(1);
            } elseif (stripos($this->exts->extract('#login_form #error_box'), 'Access Denied') !== false) {
                $this->exts->account_not_ready();
            } elseif (
                $this->exts->exists('div.fileInputUpload')
                || ($this->exts->exists('[href="/checkpoint/dyi/create_file/"]') && strpos($this->exts->getUrl(), 'referrer=disabled_checkpoint') !== false)
            ) {
                // need to upload photo to prove identity (maybe account get reported tobe fake)
                $this->exts->account_not_ready();
            } else if ($this->exts->exists('[role="button"][aria-label="Facebook Protect aktivieren"]') || $this->exts->exists('[role="button"][aria-label*="Activate Facebook Protect"]')) {
                $this->exts->account_not_ready();
            } else if ($this->exts->allExists(['[role="main"] a[href*="community-standards"]', 'form[action*="/logout.php"]'])) {
                $this->exts->account_not_ready();
            } else if (
                $this->exts->urlContains('/business/dashboard')
                && strpos($this->exts->extract('p[jsselect="summary"]'), 'redirected you too many times') !== false
            ) {
                $this->exts->account_not_ready();
            } else if ($this->exts->allExists(['input[name="password_new"]', 'input[name="password_confirm"]'])) {
                $this->exts->account_not_ready();
            } elseif ($this->exts->exists('form input[type="text"][aria-invalid="true"]')) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->extract($this->facebook_check_login_failed_selector)) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    /** ================================= END FACEBOOK LOGIN =========================================== **/

    private function processInvoices()
    {
        sleep(10);
        $this->exts->capture('transactions-page');
        $accounts = $this->getAccountDropdown();
        $this->exts->log('1.ACCOUNT From dropdown: ' . count($accounts));
        print_r($accounts);

        // Huy added 2021-08
        $this->exts->update_process_lock();
        $this->exts->moveToElementAndClick('#bizsitePageContainer #global_scope_selector button, #global_scope_selector [role="button"]');
        sleep(3);
        $this->exts->capture('business_profiles-checking');
        $business_profiles = $this->exts->getElementsAttribute('.uiContextualLayerBelowLeft li a[href*="business_id="]', 'href');
        if (count($business_profiles) > 0) {
            foreach ($business_profiles as $business_profile_url) {
                $this->exts->log('Processing business profile: ' . $business_profile_url);
                $business_id = end(explode('business_id=', $business_profile_url));
                $business_id = reset(explode('&', $business_id));
                $ads_accounts_page = 'https://business.facebook.com/settings/ad-accounts?business_id=' . $business_id;
                $this->exts->openUrl($ads_accounts_page);
                sleep(10);
                $this->wait_if_block_page();

                //Check if the website asks user to verify account
                $verifyButton = $this->exts->getElementByText('div[role="dialog"] div[role="button"]', ['Konto verifizieren', 'Verify account', 'Vérifier le compte'], null, false);
                if ($verifyButton != null) {
                    $this->exts->click_element($verifyButton);
                    sleep(5);
                    $sendMailButton = $this->exts->getElementByText('div[role="dialog"] div[role="button"]', ['E-Mail senden', 'Send email', 'Envoyer un e-mail'], null, false);
                    if ($sendMailButton != null) {
                        $this->exts->click_element($sendMailButton);
                        sleep(5);
                    }
                    $this->checkFillTwoFactorForAccountVerification();
                }
                if ($this->exts->urlContains('/security/twofactor/reauth/')) {
                    $this->checkFillFacebookTwoFactor();
                }
                $accounts = $this->collect_accounts_in_business_profile($accounts);
            }
            $this->exts->log('2.ACCOUNTs after collect from Business profiles: ' . count($accounts));
            file_put_contents($this->exts->screen_capture_location . "accounts.txt", json_encode($accounts));
        }
        // End added 2021-08

        if (!empty($accounts)) {
            foreach ($accounts as $account) {
                $this->exts->log("Account ID - " . $account['account_id']);
                $this->exts->log("Account URL - " . $account['account_url']);

                //Update the process lock because if lots of account is there and in most account no document is there than process get terminated after 30mins.
                $this->exts->update_process_lock();

                // In restart mode, process only those account which is not processed yet
                if ($this->exts->docker_restart_counter > 0 && !empty($this->last_state['accounts']) && in_array($account['account_id'], $this->last_state['accounts'])) {
                    $this->exts->log("Restart: Already processed earlier - Account-ID  " . $account['account_id']);
                    continue;
                }
                $currentDate = date(strtotime("today"));
                if ((int)$this->restrictPages == 0) {
                    $backDate = date(strtotime("-2 years"));
                } else {
                    $backDate = date(strtotime("-2 months"));
                }
                $account_billing_url = explode('&date', $account['account_url'])[0] . '&date=' . $backDate . '_' . $currentDate;
                $this->exts->log("Account Billing URL - " . $account_billing_url);

                $this->exts->openUrl($account_billing_url);
                sleep(7);
                $this->wait_if_block_page();

                if ($this->exts->exists($this->facebook_username_selector) || $this->exts->exists($this->facebook_password_selector)) {
                    $this->checkFillFacebookLogin();
                    sleep(2);
                    $this->exts->processCaptcha($this->processCaptcha_img, $this->processCaptcha_input);
                    sleep(1);
                    $this->exts->click_element($this->processCaptcha_sub);

                    $this->checkAndCompleteFacebookTwoFactor();
                    sleep(5);
                } else if ($this->exts->urlContains('/security/twofactor/reauth/')) {
                    $this->checkFillFacebookTwoFactor();
                }
                if ($this->exts->exists('button[data-testid="cookie-policy-banner-accept"]')) {
                    $this->exts->moveToElementAndClick('button[data-testid="cookie-policy-banner-accept"]');
                    sleep(1);
                }
                $this->exts->capture(__FUNCTION__ . '-selected-account-' . trim($account['account_id']));

                $this->current_state['accounts'][] = $account['account_id'];
                // if ($this->only_billing_summary == 1) {
                //     $this->process_month_by_month($account);
                // } else {
                //     if ($this->statement_only === 0 && $this->only_paid === 0) {
                //         // download all invoice as zip (no filter)
                //         $download_option = 2;
                //         $this->processBillingPage($download_option, $billing_page, $backDate, $currentDate, $account['account_id'], $billingPageWihoutDateStr);
                //     }
                //     if ($this->statement_only === 1 || $this->only_paid === 1) {
                //         // download all individual invoice (filter)
                //         $download_option = 3;
                //         $this->processBillingPage($download_option, $billing_page, $backDate, $currentDate, $account['account_id'], $billingPageWihoutDateStr);
                //     }
                // }

                // Process WhatsApp account invoices
                $watsappAccountPageUrl = str_replace('payment_activity', 'accounts', $account_billing_url) . '/&account_type=whatsapp-business-account';
                $this->exts->openUrl($watsappAccountPageUrl);
                sleep(15);
                $this->wait_if_block_page();
                sleep(10);
                if (count($this->exts->querySelectorAll('table[aria-label*="WhatsApp"] tbody tr')) > 0) {
                    $watsappPaymentIds = $this->exts->getElementsAttribute('table[aria-label*="WhatsApp"] tbody tr td:nth-child(1) div[role="button"] span:last-child', 'innerText');
                    $ids = array_map(function ($id) {
                        $this->exts->log('Watsapp Account ID ' . $id);
                        return preg_replace('/\D/', '', $id); // remove all non-digits
                    }, $watsappPaymentIds);
                    $this->exts->log("Watsapp Payment Account count: " . count($ids));
                    foreach ($ids as $id) {
                        $watsappAccountPaymentActivityUrl = $watsappAccountPageUrl . '&payment_account_id=' . $id . '&date=' . $backDate . '_' . $currentDate;
                        $this->exts->openUrl($watsappAccountPaymentActivityUrl);
                        sleep(10);
                        $this->processWatsappAccounts();
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '-selected-account-' . trim($account['account_id'] . 'has not watsapp billing accounts'));
                }
            }
        }

        if ($this->total_invoices == 0) {
            $this->isNoInvoice = true;
        } else {
            $this->isNoInvoice = false;
        }
    }
    private function wait_if_block_page()
    {
        if ($this->exts->urlContains('facebook.com/security/block')) {
            $this->exts->capture('block-page-waiting');
            $message = $this->exts->extract('#content h3', null, 'innerText');
            $this->exts->log('Block time message: ' . $message);
            $this->exts->update_process_lock();
            sleep(3 * 60);
            $this->exts->update_process_lock();
            for ($i = 0; $i < 16; $i++) {
                sleep(60);
                $this->exts->refresh();
                sleep(3);
                $message = $this->exts->extract('#content h3', null, 'innerText');
                $this->exts->log('Block time message: ' . $message);
                if (!$this->exts->urlContains('facebook.com/security/block')) {
                    $this->exts->capture('block-page-waiting');
                    break;
                }
            }
        }
    }
    private function getAccountDropdown()
    {
        sleep(25);
        $account_dropdown_selector = 'div#globalContainer div[role="toolbar"] button[type="button"], div#globalContainer div[role="toolbar"] div[role="button"], div#globalContainer div[role="button"][aria-disabled="false"], [role="toolbar"] [role="combobox"][aria-haspopup="listbox"]';
        $this->exts->waitTillPresent($account_dropdown_selector);
        $this->exts->log("Begin " . __FUNCTION__);
        if (!$this->exts->exists($account_dropdown_selector)) {
            $this->exts->update_process_lock();
            sleep(30);
        }
        if (!$this->exts->exists($account_dropdown_selector)) {
            $this->exts->update_process_lock();
            sleep(30);
        }
        $accounts = array();
        if ($this->exts->exists($account_dropdown_selector)) {
            // click Account dropdown to show html dom for accounts
            $this->exts->moveToElementAndClick($account_dropdown_selector);

            sleep(5);
            $this->exts->capture('accounts-dropdown');
            if (!$this->exts->exists('.uiScrollableAreaContent li.UNIFIED_LOCAL_SCOPE_SELECTOR_ITEM_LIST-item, [aria-autocomplete="list"]')) {
                $dropdown_button = $this->exts->getElement($account_dropdown_selector);
                $this->exts->executeSafeScript('arguments[0].click()', [$dropdown_button]);
                sleep(5);
                $this->exts->capture('accounts-dropdow-1');
            }

            if ($this->exts->exists('.uiScrollableAreaContent li.UNIFIED_LOCAL_SCOPE_SELECTOR_ITEM_LIST-item')) {
                $accounts_sss = array();
                $accounts_sss = $this->collect_accounts_indropdown(); // HUY added 2021-05
                $this->exts->log('ACCOUNTS FOUND in dropdown: ' . count($accounts_sss));


                if (empty($accounts_sss)) {
                    $accounts_sss = array();
                    if ($this->exts->exists('.uiContextualLayerPositioner #local_scope_selector a[href*="/ads/manager/billing/?act"]')) {
                        // personal account does not have to load lazy (all account loaded to popup)
                        $personal_accounts_count = count($this->exts->getElements('a[href*="/ads/manager/billing/?act"]'));
                        for ($c = 0; $c < $personal_accounts_count; $c++) {
                            $personal_account = $this->exts->getElements('.uiContextualLayerPositioner #local_scope_selector a[href*="/ads/manager/billing/?act"]')[$c];
                            if ($personal_account == null) continue;

                            $personal_account_url = $this->exts->executeSafeScript('return arguments[0].href;', [$personal_account]);
                            if (!in_array(trim($personal_account_url), $accounts_sss)) {
                                array_push($accounts_sss, trim($personal_account_url));
                            }
                        }
                    } else if ($this->exts->exists('.uiContextualLayerParent #local_scope_selector a[href*="/ads/manager/billing/?act"]')) {
                        //$this->exts->getElements('.uiContextualLayerParent #local_scope_selector a[href*="/ads/manager/billing/?act"]');

                        $personal_accounts_count = count($this->exts->getElements('.uiContextualLayerParent #local_scope_selector a[href*="/ads/manager/billing/?act"]'));
                        for ($c = 0; $c < $personal_accounts_count; $c++) {
                            $personal_account = $this->exts->getElements('.uiContextualLayerParent #local_scope_selector a[href*="/ads/manager/billing/?act"]')[$c];
                            if ($personal_account == null) continue;

                            $personal_account_url = $this->exts->executeSafeScript('return arguments[0].href;', [$personal_account]);
                            if (!in_array(trim($personal_account_url), $accounts_sss)) {
                                array_push($accounts_sss, trim($personal_account_url));
                            }
                        }
                    } else {
                        // get all possible accounts url
                        $personal_accounts_count = count($this->exts->getElements('a[href*="/ads/manager/billing/?act"],a[href*="/ads/manager/billing_history/summary/?act"]'));
                        for ($c = 0; $c < $personal_accounts_count; $c++) {
                            $personal_account = $this->exts->getElements('a[href*="/ads/manager/billing/?act"],a[href*="/ads/manager/billing_history/summary/?act"]')[$c];
                            if ($personal_account == null) continue;
                            else {
                                $full_href = $this->exts->executeSafeScript('return arguments[0].href;', [$personal_account]);
                                $account_url = $full_href;
                                if (strpos($account_url, '&') !== false) {
                                    $account_url = substr($account_url, 0, strpos($account_url, '&'));
                                }
                                $this->exts->log('URL - ' . $account_url);
                                if (!in_array($account_url, $accounts_sss)) array_push($accounts_sss, $account_url);
                            }
                        }
                    }
                }

                $user_selected_accounts = array();
                if (trim($this->account_uids) != "" && !empty($this->account_uids)) {
                    $user_selected_accounts = explode(",", $this->account_uids);
                }
                foreach ($accounts_sss as $account_url) {
                    $account_id = '';
                    $temp_array = [];
                    $temp_array = explode('asset_id=', $account_url);
                    $account_id = end($temp_array);
                    $temp_array = explode('&', $account_id);
                    $account_id = reset($temp_array);

                    if (!empty($user_selected_accounts)) {
                        if (in_array(trim($account_id), $user_selected_accounts)) {
                            $accounts[] = array(
                                'account_id' => $account_id,
                                'account_url' => $account_url
                            );
                        }
                    } else {
                        $accounts[] = array(
                            'account_id' => $account_id,
                            'account_url' => $account_url
                        );
                    }
                }

                if (count($accounts_sss) === 0) {
                    $this->exts->log(__FUNCTION__ . ' NO ACCOUNT FOUND!');
                    $this->exts->capture(__FUNCTION__ . '-NO-ACCOUNT-2');
                }
            } else {
                $this->exts->log(__FUNCTION__ . ' NO DROPDOWN ACCOUNT FOUND!');
                $this->exts->capture(__FUNCTION__ . '-NO-ACCOUNT-1');
            }
        } else {
            $this->exts->log(__FUNCTION__ . ' NO DROPDOWN ACCOUNT FOUND!');
            $this->exts->capture(__FUNCTION__ . '-NO-ACCOUNT-DROPDOWN');
        }

        return $accounts;
    }
    private function collect_accounts_indropdown()
    {
        $accounts = [];
        $account_dropdown_scroll_selector = '.uiScrollableAreaContent > div > div > div';
        $this->exts->capture("collect_accounts_indropdown");

        if ($this->exts->exists('.uiScrollableAreaContent li.UNIFIED_LOCAL_SCOPE_SELECTOR_ITEM_LIST-item')) {
            $this->exts->executeSafeScript('
            var scrollBar = document.querySelector("' . $account_dropdown_scroll_selector . '");
            scrollBar.scrollTop = scrollBar.scrollHeight;
        ');
            sleep(3);
            $this->exts->capture("account-dropdown-bottom");
            if ($this->exts->exists('.uiScrollableAreaContent li:last-child:not(.UNIFIED_LOCAL_SCOPE_SELECTOR_ITEM_LIST-item) [role="button"]')) {
                $this->exts->moveToElementAndClick('.uiScrollableAreaContent li:last-child:not(.UNIFIED_LOCAL_SCOPE_SELECTOR_ITEM_LIST-item) [role="button"]');
                sleep(5);
                $this->exts->capture("account-dropdown-loaded-more");
            }

            $this->exts->executeSafeScript('
            var scrollBar = document.querySelector("' . $account_dropdown_scroll_selector . '");
            scrollBar.scrollTop = 0;
        ');
            sleep(3);

            // START finding account
            // IMPORTANT: Options in accounts dropdown is dynamic, row can be REMOVED or showed when scroll the list
            // Collecting account one by one, start from first option
            $next_option = $this->exts->getElements('.uiScrollableAreaContent li.UNIFIED_LOCAL_SCOPE_SELECTOR_ITEM_LIST-item')[0];
            //loop using $step_count to avoid infinity loop if somehow, the condition is wrong.
            for ($step_count = 1; $step_count < 300 && $next_option != null; $step_count++) {
                $this->exts->log('Finding account in option: ' . $step_count);
                $current_option = $next_option;
                // $current_option->getLocationOnScreenOnceScrolledIntoView();.scrollIntoView();
                $this->exts->executeSafeScript('
                arguments[0].scrollIntoView;
            ', [$current_option]);
                sleep(2);
                $account_link = $this->exts->getElement('a[href*="/ads/manager/"]', $current_option);
                if ($account_link == null) {
                    $account_link = $this->exts->getElement('a[href*="billing_hub/"]', $current_option);
                }
                if ($account_link != null) {
                    // $account_url = $account_link->getAttribute('href');
                    $account_url = $this->exts->executeSafeScript('return arguments[0].href;', [$account_link]);
                    array_push($accounts, $account_url);
                }
                // check if have next account option
                $next_option = $this->exts->getElement('./following-sibling::li', $current_option, 'xpath');
                if ($next_option == null) {
                    // If It don't have next option, try to scroll down with a height of 1.5 option, then it will load more option of the rest.
                    $this->exts->executeSafeScript('
                    var scrollBar = document.querySelector("' . $account_dropdown_scroll_selector . '");
                    var optionHeight = arguments[0].scrollHeight;
                    scrollBar.scrollTop = scrollBar.scrollTop + optionHeight*1.5;
                ', [$current_option]);
                    sleep(2);
                    $next_option = $this->exts->getElement('./following-sibling::li', $current_option, 'xpath');
                }
            }
        }
        $this->exts->capture("account-dropdown-after-scroll");
        return $accounts;
    }
    private function collect_accounts_in_business_profile($collected_accounts = [])
    {
        $this->exts->capture("business_profile-account-page");
        // $accounts = [];
        $current_url = $this->exts->getUrl();
        if ($this->exts->exists('.uiScrollableArea.fade[distancetoend="2000"] .uiScrollableAreaContent div > button.accessible_elem')) {
            // IMPORTANT: Accounts display in a scollable list, It will be loaded more when scrolling
            // Collecting account one by one, start from first option
            $next_option = $this->exts->getElements('.uiScrollableArea.fade[distancetoend="2000"] .uiScrollableAreaContent div > button.accessible_elem')[0];
            //loop using $step_count to avoid infinity loop if somehow, the condition is wrong.
            for ($step_count = 1; $step_count < 300 && $next_option != null; $step_count++) {
                $this->exts->log('Finding account in option: ' . $step_count);
                $current_option = $next_option;
                // $current_option->getLocationOnScreenOnceScrolledIntoView();
                $this->exts->executeSafeScript('
                arguments[0].scrollIntoView;
            ', [$current_option]);
                $this->exts->click_element($current_option);
                sleep(2);
                // When clicking account option, browser URL will be changed, it contain account Id
                // The url format like this https://business.facebook.com/settings/ad-accounts/[Ads_account_id]?business_id=[some_business_id]
                $url = $this->exts->getUrl();
                $account_id = end(explode('/ad-accounts/', $url));
                $account_id = explode('?', $account_id)[0];
                $account_url = str_replace('ACCOUNT_ID', $account_id, 'https://www.facebook.com/ads/manager/billing_history/summary/?act=ACCOUNT_ID&pid=p1&page=billing_history&tab=summary');
                $this->exts->log('Account Id: ' . $account_id);

                $account_existed = false;
                foreach ($collected_accounts as $collected_account) {
                    if ($collected_account['account_id'] == $account_id) {
                        $account_existed = true;
                        break;
                    }
                }
                if ($account_existed == false) {
                    if (trim($this->account_uids) != "") {
                        if (strpos($this->account_uids, $account_id) !== false) {
                            array_push($collected_accounts, array(
                                'account_id' => $account_id,
                                'account_url' => $account_url
                            ));
                        }
                    } else {
                        array_push($collected_accounts, array(
                            'account_id' => $account_id,
                            'account_url' => $account_url
                        ));
                    }
                } else {
                    $this->exts->log('Account Existed: ' . $account_id);
                }

                // check if have next account option
                $next_option = $this->exts->getElement('./following-sibling::div', $current_option, 'xpath');
                if ($next_option == null) {
                    // If It don't have next option, try to scroll down with a height of 1.5 option, then it will load more option of the rest.
                    $this->exts->executeSafeScript('
                    var scrollBar = document.querySelector(\'.uiScrollableArea.fade[distancetoend="2000"] .scrollable\');
                    scrollBar.scrollTop = scrollBar.scrollTop + 52*1.5;
                ');
                    sleep(2);
                    $next_option = $this->exts->getElement('./../../following-sibling::div', $current_option, 'xpath');
                }
            }
        }
        $this->exts->capture("business_profile-after-scroll");
        return $collected_accounts;
    }
    private function process_month_by_month($account)
    {
        $billingPageWihoutDateStr = $this->exts->getUrl();

        $minus_month = 1;
        $currentDate = date("Y-m-d", strtotime("now"));
        $max_loop_month = 2;
        if ((int)$this->restrictPages == 0) {
            $max_loop_month = 20;
        }
        while ($minus_month <= $max_loop_month) {
            $currentDate = date("Y-m-d", strtotime("-" . ($minus_month - 1) . " months"));
            $backDate = date("Y-m-d", strtotime("-" . ($minus_month) . " months 1 day"));
            $dateStr = $backDate . "_" . $currentDate;
            $this->exts->log('=====================Download docs each month start====================');
            $this->exts->log('$dateStr::: ' . $dateStr);
            if (stripos($billingPageWihoutDateStr, '/billing_history/summary/') !== false) {
                $currentDate = date("Y-m-d", strtotime("-" . ($minus_month - 1) . " months"));
                $backDate = date("Y-m-d", strtotime("-" . ($minus_month) . " months 1 day"));
                $dateStr = strtotime($backDate) . "_" . strtotime($currentDate);
            }
            if (stripos($billingPageWihoutDateStr, '&date=') !== false) {
                $billingPageWihoutDateStr = trim(explode('&date=', $billingPageWihoutDateStr)[0]);
            }
            $billing_page = $billingPageWihoutDateStr;

            if (stripos($billing_page, "?") === false) {
                $billing_page = $billing_page . "?date=" . $dateStr;
            } else {
                $billing_page = $billing_page . "&date=" . $dateStr;
            }

            $this->exts->log("billing_page URL to be opened==== " . $billing_page);
            if ($this->only_billing_summary == 1) {
                // download all invoice as billing_summary (as pdf) (no filter)
                $download_option = 1;
                $this->processBillingPage($download_option, $billing_page, $backDate, $currentDate, $account['account_id'], $billingPageWihoutDateStr);
            } else {
                if ($this->statement_only === 0 && $this->only_paid === 0) {
                    // download all invoice as zip (no filter)
                    $download_option = 2;
                    $this->processBillingPage($download_option, $billing_page, $backDate, $currentDate, $account['account_id'], $billingPageWihoutDateStr);
                }
                if ($this->statement_only === 1 || $this->only_paid === 1) {
                    // download all individual invoice (filter)
                    $download_option = 3;
                    $this->processBillingPage($download_option, $billing_page, $backDate, $currentDate, $account['account_id'], $billingPageWihoutDateStr);
                }
            }
            $this->exts->log('======================Download docs each month end===================');

            $minus_month++;
        }
    }
    private function processBillingPage($download_option, $billing_page = '', $backDate, $currentDate, $account_id, $billingPageWihoutDateStr)
    {
        if ($billing_page != '') {
            $this->exts->openUrl($billing_page);
            sleep(10);
        }
        //In new design we need to click on monthly tab and then start the download process
        if ($this->only_billing_summary == 1 && (int)$download_option == 1) {
            //Click on monthly statement tab
            if ($this->exts->exists('[role="tablist"] [role="tab"]:nth-child(2)')) {
                $this->exts->moveToElementAndClick('[role="tablist"] [role="tab"]:nth-child(2)');
                sleep(10);
            }
        }

        //If you updated the this line then update same in zip download because this is used as fallback if zip download fails
        $download_all_invoices_button = $this->exts->getElementByText('button div, [role="button"]', ['Download All', 'Laden Sie alle Rechnung herunter', 'Laden Sie alle', 'Alles herunterladen', 'Alle Rechnungen herunterladen', 'Download', 'Herunterladen', 'Hent', 'Descargar', 'Télécharger', 'Baixar', 'Tüm Faturaları İndir'], null, false);

        $invoice_number = $account_id . '_' . $backDate . "--" . $currentDate;
        $load_more = $this->exts->getElementByText('#globalContainer [role="button"]', ['Mehr anzeigen', 'Load More', 'show more', 'more'], null, false);

        if ($download_option === 1 && $download_all_invoices_button != null) {
            $this->exts->click_element($download_all_invoices_button);
            sleep(5);
            $this->download_billing_summary($invoice_number);
        } elseif ($download_option === 2 && $download_all_invoices_button != null && $load_more == null) {
            $this->exts->click_element($download_all_invoices_button);
            sleep(5);
            if (!$this->download_individual_invoice_zip($invoice_number, $this->statement_only, $this->only_paid, $billingPageWihoutDateStr, $backDate, $currentDate, $account_id)) {
                $this->download_individual_invoice_filter($invoice_number, $this->statement_only, $this->only_paid);
            }
        } elseif ($download_option === 3) {
            if ((!$this->exts->exists('a[href*="/ads/manage/billing_transaction"]') || $this->statement_only === 1) && $download_all_invoices_button != null && $this->exts->exists('a[href*="/home/billing/?business_id="]')  && $load_more == null) {
                //Sometime we get view invoice button so if we need only monthly statement then click that and this will open the montly list page
                if ($this->statement_only === 1) {
                    //"https://business.facebook.com/home/billing/?business_id=1664878543728974"
                    if ($this->exts->exists('a[href*="/home/billing/?business_id="]') && stripos($this->exts->getUrl(), '/home/billing/?business_id=') === false) {
                        $this->exts->moveToElementAndClick('a[href*="/home/billing/?business_id="]');
                        sleep(10);

                        //Again need to fecth this button because we have changed the page
                        //If you updated the this line then update same in zip download because this is used as fallback if zip download fails
                        $download_all_invoices_button = $this->exts->getElementByText('button div', ['Download All', 'Laden Sie alle Rechnung herunter', 'Laden Sie alle', 'Alles herunterladen', 'Alle Rechnungen herunterladen', 'Download', 'Herunterladen', 'Hent', 'Descargar', 'Télécharger', 'Baixar', 'Tüm Faturaları İndir'], null, false);
                    }
                }

                // this case is when: "This account is billed using invoices. You can view invoices for this account in Business Manager."; But the new page cannot filter "statement_only" neither "only_paid", we can only download_all as zip; moreover, it's complicated to filter by time, by account (which we did already here) and wait for the "prepare download" to complete. So, just download zip from this page.

                // https://business.facebook.com/home/billing/?business_id=743212615745578
                if ($download_all_invoices_button != null) {
                    $this->exts->click_element($download_all_invoices_button);
                    sleep(5);
                }
                if (!$this->download_individual_invoice_zip($invoice_number, $this->statement_only, $this->only_paid, $billingPageWihoutDateStr, $backDate, $currentDate, $account_id)) {
                    $this->download_individual_invoice_filter($invoice_number, $this->statement_only, $this->only_paid);
                }
            } else {
                $this->download_individual_invoice_filter($invoice_number, $this->statement_only, $this->only_paid);
            }
        } else if ($download_all_invoices_button == null) {
            $this->download_individual_invoice_filter($invoice_number, $this->statement_only, $this->only_paid);
        }
    }
    private function download_billing_summary($invoice_number)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->capture(__FUNCTION__);
        // select "Billing Summary as a PDF"
        $this->exts->moveToElementAndClick('a[href*="invoices_generator"][href*="report=true"][href*="format=pdf"]');
        sleep(1);
        $file_extension = 'pdf';
        $filename = $invoice_number . "_Invoice_Summary." . $file_extension;

        // Wait for 1min before checking if file is saved or not
        sleep(10);

        // Wait for completion of file download
        $this->exts->wait_and_check_download($file_extension);

        // find new saved file and return its path
        $downloaded_file = $this->exts->find_saved_file($file_extension, $filename);

        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
            $this->exts->new_invoice($invoice_number, "", "", $filename);
            sleep(1);
        } else {
            // click Download button sometime when you click on download all you will get again notice of preparation
            $download_button = $this->exts->getElementByText('button, [role="none"] [role="button"]', ['Download', 'Herunterladen', 'Hent', 'Descargar', 'Télécharger', 'Baixar', 'İndir'], $download_dialog_popup, false);
            $this->exts->click_element($download_button);

            // Wait for 1min before checking if file is saved or not
            sleep(60);

            // Wait for completion of file download
            $this->exts->wait_and_check_download($file_extension);

            // find new saved file and return its path
            $downloaded_file = $this->exts->find_saved_file($file_extension, $filename);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice_number, "", "", $filename);
                sleep(1);
            } else {
                $statement_rows = $this->exts->getElements('a[href*="/billing/download_invoice/"]');
                $this->exts->log('Total Rows - ' . count($statement_rows));
                foreach ($statement_rows as $statement_row) {
                    // $invoiceUrl = $statement_row->getAttribute('href');
                    $invoiceUrl = $this->exts->executeSafeScript('return arguments[0].href;', [$statement_row]);
                    $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', '');
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoice_name = basename($downloaded_file, '.pdf');
                        $this->exts->new_invoice($invoice_name, '', '', $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceUrl);
                    }
                }
            }
        }
        $this->total_invoices++;
    }
    //If you updated the function then check full function because sometime zip download fails because of lots of files so we have divided the date in small part and download zip
    private function download_individual_invoice_zip($invoice_number = '', $statement_only = false, $only_paid = false, $billingPageWihoutDateStr, $backDate, $currentDate, $account_id)
    {
        $this->exts->log(__FUNCTION__ . '::$invoice_number--' . $invoice_number);
        $this->exts->capture(__FUNCTION__);

        $download_success = false;

        $pstart_billing_page = $this->exts->getUrl();
        $this->exts->log(__FUNCTION__ . '-' . $pstart_billing_page);

        $all_invoice_count_element = $this->exts->getElement('div[footerdata][datakey="id"] div._4h2r span');
        $all_invoice_count = ($all_invoice_count_element != null) ? (int)$all_invoice_count_element->getAttribute('innerHTML') : 0;
        $this->exts->log("Total Invoice ZIP Found  - " . $all_invoice_count);

        // select "Individual Invoices" (as zip)
        if ($this->exts->exists('a[href*="invoices_generator"][href*="report=false"]')) {
            $this->exts->moveToElementAndClick('a[href*="invoices_generator"][href*="report=false"]');
            sleep(1);
            $file_extension = 'zip';
            $filename = $invoice_number . "_Transactions." . $file_extension;

            // Wait for 2min before checking if file is saved or not
            sleep(60);

            // Wait for completion of file download
            $this->exts->wait_and_check_download($file_extension);

            // find new saved file and return its path
            $downloaded_file = $this->exts->find_saved_file($file_extension, $filename);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice_number, "", "", $downloaded_file);
                sleep(1);
                $download_success = true;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $filename);
                //Since Zip download failed. Download Indivial Invoices
                if ($all_invoice_count > 0) {
                    $this->exts->openUrl($pstart_billing_page);
                    sleep(20);
                    for ($mi = 0; $mi <= 31; $mi = $mi + 10) {
                        $backDate = date("Y-m-d", strtotime("-" . ($mi + 10) . " days", strtotime($currentDate)));
                        if ($mi > 0) {
                            $currentDate = date("Y-m-d", strtotime("-" . ($mi) . " days"));
                        }
                        $dateStr = $backDate . "_" . $currentDate;
                        $this->exts->log('=====================Download docs each month start====================');
                        $this->exts->log('$dateStr::: ' . $dateStr);
                        $billing_page = $billingPageWihoutDateStr;
                        if (stripos($billing_page, "?") === false) {
                            $billing_page = $billing_page . "?date=" . $dateStr;
                        } else {
                            $billing_page = $billing_page . "&date=" . $dateStr;
                        }
                        $this->exts->log("billing_page URL to be opened==== " . $billing_page);

                        $this->exts->openUrl($billing_page);
                        sleep(20);

                        $download_all_invoices_button = $this->exts->getElementByText('button div', ['Download All', 'Laden Sie alle Rechnung herunter', 'Laden Sie alle', 'Alles herunterladen', 'Alle Rechnungen herunterladen', 'Download', 'Herunterladen', 'Hent', 'Descargar', 'Télécharger', 'Baixar', 'Tüm Faturaları İndir'], null, false);

                        $invoice_number = $account_id . '_' . $backDate . "--" . $currentDate;

                        if ($download_all_invoices_button != null) {
                            $this->exts->click_element($download_all_invoices_button);
                            sleep(5);

                            $all_invoice_count_element = $this->exts->getElement('div[footerdata][datakey="id"] div._4h2r span');
                            $all_invoice_count = ($all_invoice_count_element != null) ? (int)$all_invoice_count_element->getAttribute('innerHTML') : 0;
                            $this->exts->log("Total Invoice ZIP Found  - " . $all_invoice_count);

                            // select "Individual Invoices" (as zip)
                            $this->exts->moveToElementAndClick('.uiInputLabelInput input[value="report"]');
                            sleep(1);
                            $file_extension = 'zip';
                            $filename = $invoice_number . "_Transactions." . $file_extension;

                            // locate popup dialog
                            sleep(1);
                            $download_dialog_popup = $this->exts->getElement('//button[contains(@class, "layerCancel")]/ancestor::div[@role="dialog"]', null, 'xpath');
                            //button[contains(@class, 'layerCancel')]/../..

                            // click Download button
                            $download_button = $this->exts->getElementByText('button', ['Download', 'Herunterladen', 'Hent', 'Descargar', 'Télécharger', 'Baixar', 'İndir'], $download_dialog_popup, false);
                            $this->exts->click_element($download_button);

                            // Wait for 2min before checking if file is saved or not
                            sleep(60);
                            $this->exts->wait_and_check_download($file_extension);
                            $downloaded_file = $this->exts->find_saved_file($file_extension, $filename);
                            if (empty($downloaded_file)) {
                                $this->exts->update_process_lock();
                                sleep(45);
                                $this->exts->wait_and_check_download($file_extension);
                                $downloaded_file = $this->exts->find_saved_file($file_extension, $filename);
                                if (empty($downloaded_file)) {
                                    $this->exts->update_process_lock();
                                    sleep(60);
                                    $this->exts->wait_and_check_download($file_extension);
                                    $downloaded_file = $this->exts->find_saved_file($file_extension, $filename);
                                }
                            }

                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoice_number, "", "", $filename);
                                $download_success = true;
                            }
                        } else if ($download_all_invoices_button == null) {
                            $this->download_individual_invoice_filter($invoice_number, $this->statement_only, $this->only_paid);
                            $download_success = true;
                        }
                    }
                }
            }
            $this->total_invoices++;
        } else {

            $date1 = (stripos($currentDate, '-') !== false) ? strtotime($currentDate) : $currentDate;
            $date2 = time();

            $diff = abs($date2 - $date1);
            $years = floor($diff / (365 * 60 * 60 * 24));
            $end_month = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
            $this->exts->log('End Month - ' . $end_month);

            $date1 = (stripos($backDate, '-') !== false) ? strtotime($backDate) : $backDate;
            $date2 = time();

            $diff = abs($date2 - $date1);
            $years = floor($diff / (365 * 60 * 60 * 24));
            $start_months = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
            $this->exts->log('Start Month - ' . $start_months);

            //open calendar
            $calBtn = $this->exts->getElements('#globalContainer div[role="button"]');
            $this->exts->click_element($calBtn[3]);
            if ($start_months > 0) {
                for ($i = 0; $i < $start_months; $i++) {
                    $prevBtn = $this->exts->getElements('[data-testid="ContextualLayerRoot"] div[role="button"]')[0];
                    $this->exts->click_element($prevBtn);
                }
                $startDateElement = $this->exts->getElements('[data-testid="ContextualLayerRoot"] [role="row"] [role="gridcell"] [role="button"]')[0];
                $this->exts->click_element($startDateElement);

                //To reach the month where we have started
                for ($i = 0; $i < $start_months; $i++) {
                    $nextBtn = $this->exts->getElements('[data-testid="ContextualLayerRoot"] div[role="button"]')[1];
                    $this->exts->click_element($nextBtn);
                }
            } else {
                $startDateElement = $this->exts->getElements('[data-testid="ContextualLayerRoot"] [role="row"] [role="gridcell"] [role="button"]')[0];
                $this->exts->click_element($startDateElement);
            }

            if ($end_month > 0) {
                for ($i = 0; $i < $end_month; $i++) {
                    $prevBtn = $this->exts->getElements('[data-testid="ContextualLayerRoot"] div[role="button"]')[0];
                    $this->exts->click_element($prevBtn);
                }
                $EndDateElement = $this->exts->getElements('[data-testid="ContextualLayerRoot"] [role="row"] [role="gridcell"] [role="button"]');
                $this->exts->click_element($EndDateElement[count($EndDateElement) - 1]);

                $updateBtn = $this->exts->getElements('[data-testid="ContextualLayerRoot"] [role="none"] div[role="button"]')[1];
                $this->exts->click_element($updateBtn);
                sleep(15);
            } else {
                if ($this->exts->exists('[data-testid="ContextualLayerRoot"] [role="row"] [role="gridcell"] [role="button"].fvlrrmdj')) {
                    $EndDateElement = $this->exts->getElements('[data-testid="ContextualLayerRoot"] [role="row"] [role="gridcell"] [role="button"].fvlrrmdj');
                } else {
                    $EndDateElement = $this->exts->getElements('[data-testid="ContextualLayerRoot"] [role="row"] [role="gridcell"] [role="button"]');
                }
                $this->exts->click_element($EndDateElement[count($EndDateElement) - 1]);

                $updateBtn = $this->exts->getElements('[data-testid="ContextualLayerRoot"] [role="none"] div[role="button"]')[1];
                $this->exts->click_element($updateBtn);
                sleep(15);
            }

            $statement_rows = $this->exts->getElements('a[href*="/billing/download_invoice/"]');
            $this->exts->log('Total Rows - ' . count($statement_rows));
            foreach ($statement_rows as $statement_row) {
                // $invoiceUrl = $statement_row->getAttribute('href');
                $invoiceUrl = $this->exts->executeSafeScript('return arguments[0].href', [$statement_row]);
                $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', '');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoice_name = basename($downloaded_file, '.pdf');
                    $this->exts->new_invoice($invoice_name, '', '', $downloaded_file);
                    sleep(1);
                    $download_success = true;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceUrl);
                }
            }
        }
        return $download_success;
    }
    private function download_individual_invoice_filter($invoice_number = '', $statement_only = false, $only_paid = false)
    {
        $this->exts->log(__FUNCTION__ . '::invoice_number-' . $invoice_number . '::statement_only-' . $statement_only . '::$only_paid-' . $only_paid);

        if ($this->exts->exists('.fbDock.clearfix')) {
            $this->exts->executeSafeScript('var clear_div = document.querySelector(".fbDock.clearfix"); if (clear_div !== undefined) clear_div.remove();');
        }

        $this->exts->capture(__FUNCTION__);
        for ($try = 0; $try < 3; $try++) {
            $load_more = $this->exts->getElementByText('div[role="none"] [role="button"]', ['Mehr anzeigen', 'Load More', 'show more', 'more'], null, false);
            if ($load_more != null) {
                $this->exts->click_element($load_more);
                sleep(10);
            }
            $this->exts->capture(__FUNCTION__ . 'load-more');
        }
        $invoices = array();

        $rows = $this->exts->getElements('[role="row"]');
        foreach ($rows as $row) {
            $invoice_link = $this->exts->getElement('a[href*="pdf=true"]', $row);
            if ($invoice_link != null) {
                // $invoiceUrl = $invoice_link->getAttribute("href");
                $invoiceUrl = $this->exts->executeSafeScript('return arguments[0].href', [$invoice_link]);
                $invoiceName = explode(
                    '&',
                    array_pop(explode('&txid=', $invoiceUrl))
                )[0];
                $invoiceDate = '';
                $invoiceAmount = '';
                if ($this->only_paid == 1) {
                    $paid_status = $this->exts->getElementByText('td:nth-child(5) [role="status"]', [
                        'Paid',
                        'Bezahlt',
                        'Pagada',
                        'Pagado',
                        'Maksettu',
                        'maksullinen',
                        'payé',
                        'rémunéré',
                        'betalt',
                        'Ödendi'
                    ], $row, true);
                    if ($paid_status == null) {
                        $paid_status = $this->exts->getElementByText('td:nth-child(5)', [
                            'Paid',
                            'Bezahlt',
                            'Pagada',
                            'Pagado',
                            'Maksettu',
                            'maksullinen',
                            'payé',
                            'rémunéré',
                            'betalt',
                            'Ödendi'
                        ], $row, false);
                    }

                    if ($paid_status == null) {
                        $this->exts->log('This transaction is not paid ' . $invoiceName);
                        continue;
                    }
                }
                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->total_invoices++;
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

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function processWatsappAccounts()
    {
        $this->exts->waitTillPresent('[role="row"]', 20);
        $this->exts->capture(__FUNCTION__);
        for ($try = 0; $try < 3; $try++) {
            $load_more = $this->exts->getElementByText('div[role="none"] [role="button"]', ['Mehr anzeigen', 'Load More', 'show more', 'more'], null, false);
            if ($load_more != null) {
                $this->exts->click_element($load_more);
                sleep(10);
            }
            $this->exts->capture(__FUNCTION__ . 'load-more');
        }
        $invoices = array();

        $rows = $this->exts->getElements('[role="row"]');
        foreach ($rows as $row) {
            $invoice_link = $this->exts->getElement('a[href*="pdf=true"]', $row);
            if ($invoice_link != null) {
                // $invoiceUrl = $invoice_link->getAttribute("href");
                $invoiceUrl = $this->exts->executeSafeScript('return arguments[0].href', [$invoice_link]);
                $invoiceName = explode(
                    '&',
                    array_pop(explode('&txid=', $invoiceUrl))
                )[0];
                $invoiceDate = '';
                $invoiceAmount = '';
                if ($this->only_paid == 1) {
                    $paid_status = $this->exts->getElementByText('td:nth-child(5) [role="status"]', [
                        'Paid',
                        'Bezahlt',
                        'Pagada',
                        'Pagado',
                        'Maksettu',
                        'maksullinen',
                        'payé',
                        'rémunéré',
                        'betalt',
                        'Ödendi'
                    ], $row, true);
                    if ($paid_status == null) {
                        $paid_status = $this->exts->getElementByText('td:nth-child(5)', [
                            'Paid',
                            'Bezahlt',
                            'Pagada',
                            'Pagado',
                            'Maksettu',
                            'maksullinen',
                            'payé',
                            'rémunéré',
                            'betalt',
                            'Ödendi'
                        ], $row, false);
                    }

                    if ($paid_status == null) {
                        $this->exts->log('This transaction is not paid ' . $invoiceName);
                        continue;
                    }
                }
                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->total_invoices++;
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

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}
