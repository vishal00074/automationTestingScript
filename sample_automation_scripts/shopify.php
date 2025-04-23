<?php // handle empty invoiceName case

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

    // Server-Portal-ID: 6458 - Last modified: 11.03.2025 14:29:07 UTC - User: 1

    public $baseUrl = 'https://www.shopify.com/';
    public $username_selector = 'input[type="email"][name="account[email]"]:not([readonly])';
    public $password_selector = '*:not([aria-hidden="true"]) > input[name="account[password]"][type="password"]';
    public $submit_login_selector = 'button[name="commit"]:not([disabled])';

    public $download_client_invoices = 0;
    public $download_all_client_invoices = 0;
    public $shopify_payouts = 0;
    public $shop_name = "";
    public $totalFiles = 0;
    public $restrictPages = 3;
    public $login_with_google = 0;
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->download_client_invoices = isset($this->exts->config_array["download_client_invoices"]) ? (int)@$this->exts->config_array["download_client_invoices"] : 0;
        $this->download_all_client_invoices = isset($this->exts->config_array["download_all_client_invoices"]) ? (int)@$this->exts->config_array["download_all_client_invoices"] : 0;
        $this->shopify_payouts = isset($this->exts->config_array["shopify_payouts"]) ? (int)@$this->exts->config_array["shopify_payouts"] : 0;
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)$this->exts->config_array["login_with_google"] : $this->login_with_google;
        $this->shop_name = isset($this->exts->config_array["shop_name"]) ? trim($this->exts->config_array["shop_name"]) : "";
        if ($this->shop_name == '') {
            $this->shop_name = isset($this->exts->config_array["last_init_url"]) ? trim($this->exts->config_array["last_init_url"]) : "";
        }
        $this->shop_name = end(explode('//', explode('.myshopify.', $this->shop_name)[0]));

        if (strpos($this->shop_name, '.ca') !== false) {
            $this->shop_name = str_replace('.ca', '-ca', $this->shop_name);
        }
        $this->shop_name = str_replace(' ', '', $this->shop_name);
        $this->exts->log('SHOP NAME after cleaned: ' . $this->shop_name);
        $this->baseUrl = 'https://' . $this->shop_name . '.myshopify.com/admin';
        $this->exts->log('baseUrl: ' . $this->baseUrl);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        $this->check_solve_cloudflare_page();
        $this->exts->capture('1-init-page');
        if (!$this->checkLogin()) {
            if (
                filter_var($this->baseUrl, FILTER_VALIDATE_URL) === FALSE ||
                $this->exts->exists('#one-step-left-msg #one-step-left-hostname, #pg-store404') ||
                stripos($this->exts->extract('body'), '403 Forbidden') !== false ||
                (stripos(parse_url($this->exts->getUrl(), PHP_URL_HOST), 'myshopify.com') === false && stripos(parse_url($this->exts->getUrl(), PHP_URL_HOST), '.shopify.com') === false)
            ) {
                // If user input invalid shop custom url, We still be able to login via below url.
                $this->baseUrl = 'https://accounts.shopify.com/store-login';
                $this->exts->log('login via default: ' . $this->baseUrl);
                $this->exts->openUrl($this->baseUrl);
                $this->check_solve_cloudflare_page();
                sleep(5);

                $this->exts->capture('1-default-page');
            }
        }
        $met_blocked_page = $this->checkSolveBlockedPage();
        $this->check_solve_cloudflare_page();

        if (stripos($this->exts->extract('body'), 'Request Header Or Cookie Too Large') || $this->exts->exists('#main-frame-error button#reload-button')) {
            $this->exts->capture('error-after-solve-blocked');
            $this->exts->openUrl($this->baseUrl);
            $this->exts->refresh();
            sleep(10);
        }
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            sleep(3);
            if ($this->exts->exists('.account-picker a[href*="/select?"]')) {
                // This only solving one exception case
                $this->exts->capture('account-picker');
                $this->exts->moveToElementAndClick('.account-picker a[href*="/select?"]');
                $this->checkSolveBlockedPage();
                $this->check_solve_cloudflare_page();
            }

            if ($this->exts->exists('[aria-labelledby="StoresHeading"] li a[href*="myshopify.com/admin"], [aria-labelledby="StoresHeading"] li a[href*="/store/"]')) { // Added this because sometime by cookie it show shop selected shop, instead of login page
                $this->exts->capture('shop-selecting-page');
                $selected_shop_url = '';
                $shop_links = $this->exts->querySelectorAll('[aria-labelledby="StoresHeading"] li a[href*="myshopify.com/admin"], [aria-labelledby="StoresHeading"] li a[href*="/store/"]');
                foreach ($shop_links as $shop_link) {
                    $shop_name_label = $this->exts->extract('h2', $shop_link);
                    if (stripos($shop_name_label, $this->exts->config_array["shop_name"]) !== false) {
                        $selected_shop_url = $shop_link->getAttribute('href');
                        break;
                    }
                }
                if ($selected_shop_url == '') { // If didn't found matched shop name, select first
                    $selected_shop_url = $shop_links[0]->getAttribute('href');
                }
                $this->exts->openUrl($selected_shop_url);
                sleep(3);
                $this->checkSolveBlockedPage();
                $this->check_solve_cloudflare_page();
            }
            $this->checkSolveBlockedPage();
            $this->check_solve_cloudflare_page();

            $this->checkFillLogin();
            sleep(7);
            if ($this->exts->exists('#MerchantSpace [aria-busy="true"]')) {
                sleep(10);
            }
            if ($this->exts->exists('a.remind-me-later-link[href*="confirm_security_settings/confirm?prompt_later=true"]')) {
                $this->exts->moveToElementAndClick('a.remind-me-later-link[href*="confirm_security_settings/confirm?prompt_later=true"]');
            }
            $this->checkSolveBlockedPage();
            $this->check_solve_cloudflare_page();

            if (stripos($this->exts->extract('body'), 'Request Header Or Cookie Too Large') || $this->exts->exists('#main-frame-error button#reload-button')) { // It may display error loading page.
                $this->exts->capture('error-page');
                $this->exts->clearCookies();
                $this->exts->openUrl('https://accounts.shopify.com/store-login');
                $this->checkSolveBlockedPage();
                $this->check_solve_cloudflare_page();

                $this->check_solve_cloudflare_page();
                $this->checkFillLogin();
            }
            $this->checkSolveBlockedPage();
            $this->check_solve_cloudflare_page();

            $this->checkFillTwoFactor();
            $this->checkSolveBlockedPage();
            $this->check_solve_cloudflare_page();

            if ($this->exts->exists('a.remind-me-later-link[href*="confirm_security_settings/confirm?prompt_later=true"]')) {
                $this->exts->moveToElementAndClick('a.remind-me-later-link[href*="confirm_security_settings/confirm?prompt_later=true"]');
                sleep(10);
            }

            if (!$this->checkLogin() && $this->exts->urlContains('shopify.com/accounts/') && $this->exts->urlContains('/personal')) {
                $this->exts->capture('personal-page');
                $this->exts->openUrl('https://accounts.shopify.com/store-login?new_store_login=true');
                $this->checkSolveBlockedPage();
                $this->check_solve_cloudflare_page();

                if ($this->exts->exists('.account-picker a[href*="/select?"]')) {
                    // This only solving one exception case
                    $this->exts->capture('account-picker');
                    $this->exts->moveToElementAndClick('.account-picker a[href*="/select?"]');
                    $this->checkSolveBlockedPage();
                    $this->check_solve_cloudflare_page();
                }
            }

            //If the website show no permission, click on shop-list button
            if ($this->exts->getElement('//a//*[contains(text(), "Shop-Liste")]') != null && stripos($this->exts->extract('span.Polaris-Text--center'), 'keine Berechtigung') !== false) {
                $this->exts->click_element('//a//*[contains(text(), "Shop-Liste")]');
                sleep(10);
            }

            if ($this->exts->exists('[aria-labelledby="StoresHeading"] li a[href*="myshopify.com/admin"], [aria-labelledby="StoresHeading"] li a[href*="/store/"]')) {
                // Huy added this 2022-06-23
                // If user inputed display shop name instead of shop url, then maybe they required to choose shop in a multi shops list
                $this->exts->capture('shop-selecting-page');
                $selected_shop_url = '';
                $shop_links = $this->exts->querySelectorAll('[aria-labelledby="StoresHeading"] li a[href*="myshopify.com/admin"], [aria-labelledby="StoresHeading"] li a[href*="/store/"]');
                foreach ($shop_links as $shop_link) {
                    $shop_name_label = $this->exts->extract('h2', $shop_link);
                    if (stripos($shop_name_label, $this->exts->config_array["shop_name"]) !== false) {
                        $selected_shop_url = $shop_link->getAttribute('href');
                        break;
                    }
                }
                if ($selected_shop_url == '') { // If didn't found matched shop name, select first
                    $selected_shop_url = $shop_links[0]->getAttribute('href');
                }
                $this->exts->openUrl($selected_shop_url);
                sleep(3);
                $this->checkSolveBlockedPage();
                $this->check_solve_cloudflare_page();
            }
            if ($this->exts->exists('.account-picker a[href*="/select?"]')) {
                // This only solving one exception case
                $this->exts->capture('account-picker');
                $this->exts->moveToElementAndClick('.account-picker a[href*="/select?"]');
                $this->checkSolveBlockedPage();
                $this->check_solve_cloudflare_page();

                if ($this->exts->exists('#main-frame-error button#reload-button')) { // It may display error loading page.
                    $this->exts->capture('error-page');
                    $this->exts->openUrl('https://accounts.shopify.com/store-login');
                    $this->checkSolveBlockedPage();
                    $this->check_solve_cloudflare_page();

                    if ($this->exts->exists('.account-picker a[href*="/select?"]')) {
                        $this->exts->capture('account-picker');
                        $this->exts->moveToElementAndClick('.account-picker a[href*="/select?"]');
                        $this->checkSolveBlockedPage();
                        $this->check_solve_cloudflare_page();
                    }
                }
            }
            if ($this->exts->urlContains('/accounts_merge/prompts')) {
                // This only solving one exception case
                $this->exts->openUrl($this->baseUrl);
                $this->checkSolveBlockedPage();
                $this->check_solve_cloudflare_page();

                if ($this->exts->exists('.login-card__content .account-card, .login-card__content .account-picker__item')) {
                    $this->exts->moveToElementAndClick('.login-card__content .account-card, .login-card__content .account-picker__item');
                    sleep(5);
                }
                $this->exts->execute_javascript('history.back();');
                $this->checkSolveBlockedPage();
                $this->check_solve_cloudflare_page();
            }
            $this->exts->waitTillPresent('#AppFrameNav a[href*="/settings"]');
        }

        if ($this->exts->exists('#MerchantSpace [aria-busy="true"]')) {
            $this->checkSolveBlockedPage();
            $this->check_solve_cloudflare_page();
        }
        if ($this->exts->exists('a[href*="/dismiss-forever"]')) {
            $this->exts->moveToElementAndClick('a[href*="/dismiss-forever"]');
            $this->checkSolveBlockedPage();
            $this->check_solve_cloudflare_page();
        }
        $this->processAfterLogin();
    }
    public function checkSolveBlockedPage()
    {
        $this->exts->log(__FUNCTION__);
        // Blocked page is single page with recaptcha displayed
        $this->exts->waitTillPresent('.login-card__content textarea[name="g-recaptcha-response"]');
        if ($this->exts->exists('.login-card__content textarea[name="g-recaptcha-response"]')) {
            $this->exts->capture('1-blocked-page');
            $this->checkFillHcaptcha();
            $this->checkFillRecaptcha();
            $this->exts->moveToElementAndClick('form[action*="/challenge_feedback/"] button.captcha__submit:not([disabled]), div.login-card__content form button.captcha__submit:not([disabled])');
            sleep(7);
            if ($this->exts->exists('.login-card__content textarea[name="g-recaptcha-response"]') && !$this->exts->oneExists([$this->username_selector, $this->password_selector, 'input[type="text"]'])) {
                $this->checkFillHcaptcha();
                $this->checkFillRecaptcha();
                $this->exts->moveToElementAndClick('form[action*="/challenge_feedback/"] button.captcha__submit:not([disabled]), div.login-card__content form button.captcha__submit:not([disabled])');
                sleep(7);
            }
            if ($this->exts->exists('.login-card__content textarea[name="g-recaptcha-response"]') && !$this->exts->oneExists([$this->username_selector, $this->password_selector, 'input[type="text"]'])) {
                $this->checkFillHcaptcha();
                $this->checkFillRecaptcha();
                $this->exts->moveToElementAndClick('form[action*="/challenge_feedback/"] button.captcha__submit:not([disabled]), div.login-card__content form button.captcha__submit:not([disabled])');
                sleep(7);
            }
            $this->check_solve_cloudflare_page();
            if ($this->exts->exists('.login-card__content textarea[name="g-recaptcha-response"]') && !$this->exts->oneExists([$this->username_selector, $this->password_selector, 'input[type="text"]'])) {
                $this->checkFillHcaptcha();
                $this->checkFillRecaptcha();
                $this->exts->moveToElementAndClick('form[action*="/challenge_feedback/"] button.captcha__submit:not([disabled]), div.login-card__content form button.captcha__submit:not([disabled])');
                sleep(7);
            }
            if ($this->exts->exists('.login-card__content textarea[name="g-recaptcha-response"]') && !$this->exts->oneExists([$this->username_selector, $this->password_selector, 'input[type="text"]'])) {
                $this->checkFillHcaptcha();
                $this->checkFillRecaptcha();
                $this->exts->moveToElementAndClick('form[action*="/challenge_feedback/"] button.captcha__submit:not([disabled]), div.login-card__content form button.captcha__submit:not([disabled])');
                sleep(7);
            }
            return true;
        } else {
            $this->check_solve_cloudflare_page();
        }
        return false;
    }
    public function checkFillLogin()
    {
        $this->exts->capture("2-login-page");
        // Because cookie, so sometime it display choose card before login page.
        $this->exts->waitTillPresent('.login-card__content .account-card, .login-card__content .account-picker__item[href*="/select"]', 20);
        if ($this->exts->exists('.login-card__content .account-card, .login-card__content .account-picker__item[href*="/select"]')) {
            $this->exts->moveToElementAndClick('.login-card__content .account-card, .login-card__content .account-picker__item[href*="/select"]');
            sleep(10);
        }

        if ($this->login_with_google == 1) {
            $this->exts->moveToElementAndClick('a[href*="login/external/google"]');
            sleep(3);
            $google_login_tab = $this->exts->findTabMatchedUrl(['.google.com', '/signin/identifier', 'continue=', 'linkedin']);
            if ($google_login_tab != null) {
                $this->exts->switchToTab($google_login_tab);
            }
            $this->loginGoogleIfRequired();
            $this->exts->capture("after-login-google");
            $tab_buttons = $this->exts->querySelectorAll('div.sticky button');
            foreach ($tab_buttons as $key => $tab_button) {
                $tab_name = trim($tab_button->getAttribute('innerText'));
                if (stripos($tab_name, 'I Agree') !== false) {
                    $tab_button->click();
                    sleep(20);
                    break;
                }
            }
            $this->exts->capture("after-login-google-1");
            if ($google_login_tab != null) {
                $this->exts->closeTab($google_login_tab);
            }
        } elseif ($this->exts->exists($this->username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);
            // solve captcha to enable submit button, but if it failed, try again
            if ($this->exts->exists('#h-captcha iframe[src*="hcaptcha"]')) {
                $this->exts->refresh();
                $this->checkFillHcaptcha();
            } else {
                $this->checkFillRecaptcha();
            }
            if ($this->exts->exists($this->username_selector)) {
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
            }

            if (!$this->exts->exists($this->submit_login_selector) && $this->exts->exists($this->username_selector)) {
                if ($this->exts->exists('#h-captcha iframe[src*="hcaptcha"]')) {
                    $this->exts->refresh();
                    $this->checkFillHcaptcha();
                } else {
                    $this->checkFillRecaptcha();
                }
            }
            if ($this->exts->exists($this->username_selector)) {
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
            }
            $this->exts->capture("2.1-username-filled");
            if (!$this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
            sleep(3);
            $this->exts->capture("2.1-username-submitted");

            $met_blocked_page = $this->checkSolveBlockedPage();
            $this->check_solve_cloudflare_page();

            if ($met_blocked_page && $this->exts->exists($this->username_selector)) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);
                // solve captcha to enable submit button, but if it failed, try again
                if ($this->exts->exists('#h-captcha iframe[src*="hcaptcha"]')) {
                    $this->checkFillHcaptcha();
                } else {
                    $this->exts->refresh();

                    $this->checkFillRecaptcha();
                }
                if ($this->exts->exists($this->username_selector)) {
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                }
                if (!$this->exts->exists($this->submit_login_selector) && $this->exts->exists($this->username_selector)) {
                    if ($this->exts->exists('#h-captcha iframe[src*="hcaptcha"]')) {
                        $this->checkFillHcaptcha();
                    } else {
                        $this->checkFillRecaptcha();
                    }
                }
                if ($this->exts->exists($this->username_selector)) {
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                }
                $this->exts->capture("2.1-username-filled");
                if (!$this->exts->exists($this->password_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                }
                sleep(3);
                $met_blocked_page = $this->checkSolveBlockedPage();
                $this->check_solve_cloudflare_page();
            }

            if ($this->exts->urlContains('/signup')) {
                $this->exts->loginFailure(1);
            } elseif ($this->exts->urlContains('captcha_failed')) {
                $this->exts->waitTillPresent($this->username_selector, 10);
                if ($this->exts->exists($this->username_selector)) {
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                }
                $this->checkSolveBlockedPage();
            }
            $this->exts->capture("2.1-username-submitted");
        }

        if ($this->exts->allExists(['#log_in_another_way', '.login-primary #web_authn_btn_trigger']) && !$this->exts->exists($this->password_selector)) {
            $this->exts->moveToElementAndClick('#log_in_another_way');
            sleep(7);
            $this->checkSolveBlockedPage();
            $this->check_solve_cloudflare_page();

            $this->exts->moveToElementAndClick('#login_alternative_password_auth');
            sleep(7);
        }
        $this->exts->waitTillPresent($this->password_selector);
        if ($this->exts->exists($this->password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            // solve captcha to enable submit button, but if it failed, try again
            if ($this->exts->exists('#h-captcha iframe[src*="hcaptcha"]')) {
                $this->checkFillHcaptcha();
            } else {
                $this->checkFillRecaptcha();
            }
            if (!$this->exts->exists($this->submit_login_selector) && $this->exts->exists($this->password_selector)) {
                if ($this->exts->exists('#h-captcha iframe[src*="hcaptcha"]')) {
                    $this->checkFillHcaptcha();
                } else {
                    $this->checkFillRecaptcha();
                }
            }
            $this->exts->capture("2.2-password-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else if ($this->exts->exists('a[href*="/external-login/google/dismiss"]')) {
            $this->exts->moveToElementAndClick('a[href*="/external-login/google/dismiss"]');
            sleep(10);
        } else {
            $this->exts->log(__FUNCTION__ . '::Password input not found');
            $this->exts->capture("2-password-input-not-found");
        }
        $this->exts->capture('2.3-after-fill-login');
    }
    private function check_solve_cloudflare_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");

        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
                $this->exts->refresh();
                sleep(10);

                $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
                sleep(15);

                if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                    break;
                }
            } else {
                break;
            }
        }
    }
    public function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
            if (!$isCaptchaSolved) {
                $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
                $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
            }

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
                if(document.querySelector("[data-callback]") != null){
                    return document.querySelector("[data-callback]").getAttribute("data-callback");
                }

                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                    try {
                        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                return found ? "___grecaptcha_cfg.clients[0]." + result : null;
            ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(2);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    public function checkFillHcaptcha()
    {
        if (!$this->exts->urlContains('captcha_failed')) {
            $hcaptcha_iframe_selector = 'iframe[src*="hcaptcha"]';
            if ($this->exts->exists($hcaptcha_iframe_selector)) {
                $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
                $data_siteKey =  end(explode("&sitekey=", $iframeUrl));
                $data_siteKey =  explode("&", $data_siteKey)[0];
                $jsonRes = $this->exts->processHumanCaptcha($data_siteKey, $this->exts->getUrl());
                if (!$jsonRes) {
                    $jsonRes = $this->exts->processHumanCaptcha($data_siteKey, $this->exts->getUrl());
                    $this->exts->log("isCaptchaSolved - " . $jsonRes);
                }
                $captchaScript = '
                function submitToken(token) {
                document.querySelector("[name=g-recaptcha-response]").innerText = token;
                document.querySelector("[name=h-captcha-response]").innerText = token;
                document.querySelector("iframe[src*=hcaptcha][data-hcaptcha-response]").setAttribute("data-hcaptcha-response", token);
                }
                submitToken(arguments[0]);
                captchaCompletedCallback();
            ';
                $params = array($jsonRes);
                $this->exts->execute_javascript($captchaScript, $params);
                sleep(1);
                $callback_result = $this->exts->execute_javascript('
                try {
                    var completed = 1;
                    var button = document.querySelector("button[data-bind-disabled=captchaDisabled][disabled]");
                    if(button != null){
                        button.removeAttribute("disabled");
                        completed = 0;
                    }
                    return completed;
                } catch(ex){
                    // Enable submit button by js
                    var button = document.querySelector("button[data-bind-disabled=captchaDisabled][disabled]");
                    if(button != null){
                        button.removeAttribute("disabled");
                    }
                    return "ERORR: " + ex;
                }
            ');
                $this->exts->log('Callback result: ' . $callback_result);
                $this->exts->capture('hcaptcha-filled');
            }
        } else {
            $this->exts->openUrl('https://accounts.shopify.com/store-login');
            $this->checkFillHcaptcha();
        }
    }
    public function checkFillTwoFactor()
    {
        if ($this->exts->urlContains('/two-factor/web_authn') || $this->exts->urlContains('/two-factor/passkey')) {
            // If 2FA is Security USB method, We can not solve it, so select other method
            // then Authen app first then SMS
            if ($this->exts->exists('a[href*="/two-factor/app"]')) {
                $selected_option = $this->exts->querySelector('a[href*="/two-factor/app"]');
                $this->exts->execute_javascript('arguments[0].click();', [$selected_option]);
            } else if ($this->exts->exists('a[href*="/two-factor/sms"]')) {
                $selected_option = $this->exts->querySelector('a[href*="/two-factor/sms"]');
                $this->exts->execute_javascript('arguments[0].click();', [$selected_option]);
            } else if ($this->exts->exists('a[href*="/two-factor/recovery-code"]')) {
                $selected_option = $this->exts->querySelector('a[href*="/two-factor/recovery-code"]');
                $this->exts->execute_javascript('arguments[0].click();', [$selected_option]);
            }
            sleep(5);
        }

        $two_factor_selector = 'input[name="tfa_code"], input[name="merge_token"], form[action*="/login/checkpoint"] input#account_code';
        $two_factor_message_selector = 'p.stack-paragraph, .main-card-section__header +  p, form[action*="/login/checkpoint"] p';
        $two_factor_submit_selector = 'button[name="commit"]:not([disabled]), button.merge-card__action:not([disabled])';

        if ($this->exts->querySelector($two_factor_selector) != null) {
            $this->exts->two_factor_timeout = 4;
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor-" . $this->exts->two_factor_attempts);

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = '';
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                if (!$this->exts->exists($two_factor_submit_selector)) {
                    $this->checkFillHcaptcha();
                    $this->checkFillRecaptcha();
                }
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(5);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                    $this->exts->capture("Two-factor-solved-" . $this->exts->two_factor_attempts);
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
    public function clickElementByText($selector, $searchText)
    {
        $itemSelectors = $this->exts->querySelectorAll($selector);
        if (count($itemSelectors) > 0) {
            foreach ($itemSelectors as $itemSelector) {
                foreach ($searchText as $item) {
                    $elementText = trim($itemSelector->getAttribute('innerText'));
                    if (strtolower($elementText) == strtolower($item) || trim($elementText) == trim($item)) {
                        try {
                            $itemSelector->click();
                        } catch (\Exception $exception) {
                            $this->exts->execute_javascript('arguments[0].click();', [$itemSelector]);
                        }
                        break;
                    }
                }
            }
        }
    }
    public function checkLogin()
    {
        sleep(5);
        if ($this->exts->exists("a[href*='account/settings']")) {
            return true;
        }
        if ($this->exts->exists('#AppFrameNav a[href*="/settings"]')) {
            return true;
        }
    }

    // -------------------- GOOGLE login
    public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
    public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
    public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
    public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
    private function loginGoogleIfRequired()
    {
        if ($this->exts->urlContains('google.')) {
            if ($this->exts->urlContains('/webreauth')) {
                $this->exts->moveToElementAndClick('#identifierNext');
                sleep(6);
            }
            $this->googleCheckFillLogin();
            sleep(5);
            if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
                $this->exts->loginFailure(1);
            }

            // Click next if confirm form showed
            $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
            $this->googleCheckTwoFactorMethod();

            if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
                $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
                sleep(10);
            }
            if ($this->exts->exists('#tos_form input#accept')) {
                $this->exts->moveToElementAndClick('#tos_form input#accept');
                sleep(10);
            }
            if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
                $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('.action-button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')) {
                // SKIP setup 2FA
                $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
                sleep(10);
            }
            if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
                $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
                sleep(10);
            }
            if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
                $this->exts->moveToElementAndClick('input[name="later"]');
                sleep(7);
            }
            if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
                $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
                sleep(7);
            }
            if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
                $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
                sleep(10);
            }

            if ($this->exts->exists('#submit_approve_access')) {
                $this->exts->moveToElementAndClick('#submit_approve_access');
                sleep(10);
            } else if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
                // An application is requesting permission to access your Google Account.
                // Click allow
                $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
                sleep(10);
            }
            $this->exts->capture("3-google-before-back-to-main-tab");
        } else {
            $this->exts->log(__FUNCTION__ . '::Not required google login.');
            $this->exts->capture("3-no-google-required");
        }
    }
    private function googleCheckFillLogin()
    {
        if ($this->exts->exists('form ul li [role="link"][data-identifier]')) {
            $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
            sleep(5);
        }

        if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
            $this->exts->capture("google-verify-it-you");
            // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
        }

        $this->exts->capture("2-google-login-page");
        if ($this->exts->exists($this->google_username_selector)) {
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
                if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                    sleep(5);
                }
            } else if ($this->exts->urlContains('/challenge/recaptcha')) {
                $this->googlecheckFillRecaptcha();
                $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
                sleep(5);
            }

            // Which account do you want to use?
            if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
                $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
                sleep(5);
            }
            if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
                $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
                sleep(5);
            }
        }

        if ($this->exts->exists($this->google_password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }

            $this->exts->capture("2-google-login-page-filled");
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                if ($this->exts->exists('#captchaimg[src]')) {
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                }
                $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                sleep(5);
                if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                    $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                    sleep(1);
                    $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                    $this->exts->capture("2-google-login-pageandcaptcha-filled");
                    $this->exts->moveToElementAndClick($this->google_submit_password_selector);
                }
            } else {
                $this->googlecheckFillRecaptcha();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Google password page not found');
            $this->exts->capture("2-google-password-page-not-found");
        }
    }
    private function googleCheckTwoFactorMethod()
    {
        // Currently we met many two factor methods
        // - Confirm email account for account recovery
        // - Confirm telephone number for account recovery
        // - Call to your assigned phone number
        // - confirm sms code
        // - Solve the notification has sent to smart phone
        // - Use security key usb
        // - Use your phone or tablet to get a security code (EVEN IF IT'S OFFLINE)
        $this->exts->log(__FUNCTION__);
        sleep(5);
        $this->exts->capture("2.0-before-check-two-factor-google");
        // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
        if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
            $this->exts->moveToElementAndClick('#assistActionId');
            sleep(5);
        } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
            if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
                $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
                sleep(5);
            }
        } else if ($this->exts->urlContains('/sk/webauthn')) {
            $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
            exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
            sleep(3);
            $this->exts->capture("2.0-cancel-security-usb-google");
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        } else if ($this->exts->exists('input[name="ootpPin"]')) {
            // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
            // So, We try to click 'Choose another option' in order to select easier method
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(7);
            $this->exts->capture("2.0-backed-methods-list-google");
        }

        // STEP 1: Check if list of two factor methods showed, select first
        if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
            // We most RECOMMEND confirm security phone or email, then other method
            if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
                // We RECOMMEND method type = 6 is get code from Google Authenticator
                $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')) {
                // We second RECOMMEND method type = 9 is get code from SMS
                $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')) {
                // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
                $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
                // Use a smartphone or tablet to receive a security code (even when offline)
                $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
            } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
                // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
            } else {
                $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
            }
            sleep(10);
        } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        }

        // STEP 2: (Optional)
        if ($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')) {
            // If methos is recovery email, send 2FA to ask for email
            $this->exts->two_factor_attempts = 2;
            $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->recovery_email) && $this->recovery_email != '') {
                $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')) {
            // If methos confirm recovery phone number, send 2FA to ask
            $this->exts->two_factor_attempts = 3;
            $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
            $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool("Return");
                sleep(5);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
                sleep(5);
            }
        } else if ($this->exts->exists('input#phoneNumberId')) {
            // Enter a phone number to receive an SMS with a confirmation code.
            $this->exts->two_factor_attempts = 3;
            $input_selector = 'input#phoneNumberId';
            $message_selector = '[data-view-id] form section > div > div > div:first-child';
            $submit_selector = '';
            if (isset($this->security_phone_number) && $this->security_phone_number != '') {
                $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
                $this->exts->type_key_by_xdotool("Return");
                sleep(7);
            }
            if ($this->exts->exists($input_selector)) {
                $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            }
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
            // Method: insert your security key and touch it
            $this->exts->two_factor_attempts = 3;
            $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
            // choose another option: #assistActionId
        }

        // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
        if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
            // Sometime user must confirm before google send sms
            $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
            sleep(10);
        } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
            $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
            sleep(10);
        } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
            $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
            sleep(10);
        } else if (count($this->exts->querySelectorAll('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
            sleep(7);
        }


        // STEP 4: input code
        if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
            $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
            $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('input[name="Pin"]')) {
            $input_selector = 'input[name="Pin"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
            // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
            $this->exts->two_factor_attempts = 3;
            $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
            $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
            $this->googleFillTwoFactor(null, null, '');
            sleep(5);
        } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
            $input_selector = 'input[name="secretQuestionResponse"]';
            $message_selector = 'form > span > section > div > div > div:first-child';
            $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
            $this->exts->two_factor_attempts = 0;
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
        }
    }
    private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Google two factor page found.");
        $this->exts->capture("2.1-two-factor-google");

        if ($this->exts->querySelector($message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }

        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->querySelector($input_selector) != null) {
                if (substr(trim($two_factor_code), 0, 2) === 'G-') {
                    $two_factor_code = end(explode('G-', $two_factor_code));
                }
                if (substr(trim($two_factor_code), 0, 2) === 'g-') {
                    $two_factor_code = end(explode('g-', $two_factor_code));
                }
                $this->exts->log(__FUNCTION__ . ": Entering two_factor_code: " . $two_factor_code);
                $this->exts->moveToElementAndType($input_selector, '');
                $this->exts->moveToElementAndType($input_selector, $two_factor_code);
                sleep(1);
                if ($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])) {
                    $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                    sleep(1);
                }
                $this->exts->capture("2.2-google-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($submit_selector)) {
                    $this->exts->log(__FUNCTION__ . ": Clicking submit button.");
                    $this->exts->moveToElementAndClick($submit_selector);
                } else if ($submit_by_enter) {
                    $this->exts->type_key_by_xdotool("Return");
                }
                sleep(10);
                $this->exts->capture("2.2-google-two-factor-submitted-" . $this->exts->two_factor_attempts);
                if ($this->exts->querySelector($input_selector) == null) {
                    $this->exts->log("Google two factor solved");
                } else {
                    if ($this->exts->two_factor_attempts < 3) {
                        $this->exts->notification_uid = '';
                        $this->exts->two_factor_attempts++;
                        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
                    } else {
                        $this->exts->log("Google Two factor can not solved");
                    }
                }
            } else {
                $this->exts->log("Google not found two factor input");
            }
        } else {
            $this->exts->log("Google not received two factor code");
            $this->exts->two_factor_attempts = 3;
        }
    }
    private function googlecheckFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);
            $url = reset(explode('?', $this->exts->getUrl()));
            $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
                if(document.querySelector("[data-callback]") != null){
                    document.querySelector("[data-callback]").getAttribute("data-callback");
                } else {
                    var result = ""; var found = false;
                    function recurse (cur, prop, deep) {
                        if(deep > 5 || found){ return;}console.log(prop);
                        try {
                            if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                            } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
                                for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                            }
                        } catch(ex) { console.log("ERROR in function: " + ex); return; }
                    }

                    recurse(___grecaptcha_cfg.clients[0], "", 0);
                    found ? "___grecaptcha_cfg.clients[0]." + result : null;
                }
            ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }
    // End GOOGLE login

    public function processAfterLogin()
    {
        if ($this->exts->exists('[role="progressbar"][aria-valuenow]')) {
            $this->exts->waitTillPresent('[role="progressbar"][aria-valuenow]');
            if ($this->exts->exists('[role="progressbar"][aria-valuenow]')) {
                $this->exts->openUrl($this->baseUrl);
                sleep(7);
                if ($this->exts->exists('.account-picker a[href*="/select?"]')) {
                    // This only solving one exception case
                    $this->exts->capture('account-picker');
                    $this->exts->moveToElementAndClick('.account-picker a[href*="/select?"]');
                    sleep(5);
                    $this->checkSolveBlockedPage();
                }
            }
        }
        // then check user logged in or not
        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            if ($this->exts->exists('a[href*="/accounts_merge/postpone"]')) {
                $this->exts->moveToElementAndClick('a[href*="/accounts_merge/postpone"]');
                sleep(15);
            }

            $this->exts->waitTillPresent('button[aria-controls="view-bills"]');
            if ($this->exts->exists('button[aria-controls="view-bills"]')) {
                $this->exts->click_element('button[aria-controls="view-bills"]');
                sleep(1);
                $this->exts->click_element('a[href*=bills]');
                $this->processAccessAccountInvoices();
            }
            // There're have two difference type of url for same functionals, it's up to user
            if ($this->exts->urlContains('.myshopify.com/')) { // url pattern is store_name.myshopify.com/admin
                $current_url = $this->exts->getUrl();
                $paths = explode('/', $current_url);
                $currentDomainUrl = $paths[0] . '//' . $paths[2];

                $billing_page_url = $currentDomainUrl . '/admin/settings/billing/history';
                $store_orders_url = $currentDomainUrl . '/admin/orders?';
                $payout_url = $currentDomainUrl . '/admin/payments/payouts';
            } else {
                // url pattern is admin.shopify/store/store_name
                $current_url = $this->exts->getUrl();
                $store_name_path = reset(explode('/', end(explode('/store/', $current_url))));
                $store_name_path = reset(explode('?', $store_name_path));
                $this->exts->log("Store name in url: $store_name_path");

                $billing_page_url = "https://admin.shopify.com/store/$store_name_path/settings/billing";
                $store_orders_url = "https://admin.shopify.com/store/$store_name_path/orders?";
                $payout_url = "https://admin.shopify.com/store/$store_name_path/payments/payouts";
            }

            $this->exts->openUrl($billing_page_url);
            $this->processBillingPage(0);

            if ((int)@$this->download_client_invoices == 1) {
                // Go to order page and download orders payment document
                if ($this->restrictPages == 0) {
                    $start_date = date('Y-m-d', strtotime('-3 years'));
                    $store_orders_url = $store_orders_url . "processed_at_min=$start_date";
                } else {
                    $start_date = date('Y-m-d', strtotime('-33 days')); // if restrict download, back to 1 month
                    $store_orders_url = $store_orders_url . "processed_at_min=$start_date";
                }

                if ($this->download_all_client_invoices == 1) {
                    $store_orders_url = $store_orders_url . "&selectedView=all";
                } else {
                    $store_orders_url = $store_orders_url . "&financial_status=paid";
                }
                $store_orders_url = $store_orders_url . '&orderDirection=DESCENDING&order=PROCESSED_AT'; // sort orders newest to older by date.

                $this->exts->openUrl($store_orders_url);
                sleep(15);
                if ($this->exts->exists('a[href*="/accounts_merge/postpone"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/accounts_merge/postpone"]');
                    sleep(15);
                }
                $this->process_shop_orders();
            }

            if ((int)@$this->shopify_payouts == 1) {
                $this->exts->log('Process Payouts invoices..');
                $this->exts->openUrl($payout_url);
                if ($this->exts->exists('a[href*="/accounts_merge/postpone"]')) {
                    $this->exts->moveToElementAndClick('a[href*="/accounts_merge/postpone"]');
                    sleep(15);
                }
                $this->processPayoutInvoices(1, 1);
            }

            $this->exts->openUrl($payout_url);
            if ($this->exts->exists('a[href*="/accounts_merge/postpone"]')) {
                $this->exts->moveToElementAndClick('a[href*="/accounts_merge/postpone"]');
                sleep(15);
            }
            $this->processTaxDocuments();

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed ' . $this->exts->getUrl());
            $this->checkFillTwoFactor();

            if (
                $this->exts->urlContains('/admin/account/pricing') ||
                $this->exts->urlContains("/admin/subscription/edit_credit_card?dialog=true") ||
                $this->exts->urlContains("confirm_security_settings") ||
                $this->exts->urlContains("/access_account") ||
                $this->exts->exists('.status-code-500') ||
                $this->exts->exists('.page-auth-login.has-error form[action*="/login?select_account=true"]') ||
                $this->exts->exists('img[src*="/merge-card"]') ||
                $this->exts->exists('a[href*="/access_account/pay_bill"]')
            ) {
                $this->exts->account_not_ready();
            } else if (
                strpos(strtolower($this->exts->extract('input[name="account[email]"] ~ * .validation-error__message')), 'no matching email') !== false ||
                strpos(strtolower($this->exts->extract('input[name="account[email]"] ~ * .validation-error__message')), 'es gibt keine passende e-mail') !== false ||
                strpos(strtolower($this->exts->extract('.next-input-wrapper--is-error .validation-error__message')), 'incorrect password') !== false ||
                strpos(strtolower($this->exts->extract('.next-input-wrapper--is-error .validation-error__message')), 'falsches passwort' !== false)
            ) {
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->loginFailure(1);
            } else if (
                strpos(strtolower($this->exts->extract('div.login-card__content p')), 'deactivated') !== false
                || strpos(strtolower($this->exts->extract('div.login-card__content p')), 'deaktiviert') !== false
                || strpos(strtolower($this->exts->extract('div.login-card__content p')), 'desactiva') !== false
                || strpos(strtolower($this->exts->extract('div.login-card__content p')), 'désactivé') !== false
            ) {
                $this->exts->account_not_ready();
            } elseif (stripos($this->exts->extract('span.Polaris-Text--center'), 'keine Berechtigung') !== false) {
                $this->exts->no_permission();
            } elseif (!$this->checkLogin()) {
                $this->checkFillLogin();
                $this->checkFillTwoFactor();
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    public function processBillingPage($page)
    {
        $this->exts->log("Begin processBillingPage - " . $page);
        sleep(20);
        $this->exts->capture(__FUNCTION__);
        try {
            $rows = $this->exts->querySelectorAll("div#invoice_list div#all-invoices table tbody tr");
            if (count($rows) > 0) {
                $this->exts->log("Total Invoice Found  - " . count($rows));
                $total_rows = count($rows);
                for ($i = 0, $j = 1; $i < $total_rows; $i++, $j++) {
                    $columns = $this->exts->querySelectorAll("//*[@id=\"all-invoices\"]/table/tbody/tr[$j]/td");
                    $this->exts->log("Invoice Row columns- $i - " . count($columns));
                    if (count($columns) > 0 && $this->exts->querySelector("//*[@id=\"all-invoices\"]/table/tbody/tr[$j]/td[5]/a[contains(@href,\"myshopify.com/admin/invoices/\")]") != null) {
                        $invoice_date = trim($columns[0]->getAttribute('innerText'));
                        $this->exts->log("invoice_date - " . $invoice_date);

                        $detailPageEle = $this->exts->querySelector("//*[@id=\"all-invoices\"]/table/tbody/tr[$j]/td[1]/a[contains(@href,\"/admin/settings/billing/invoice/\")]");
                        if ($detailPageEle != null) {
                            $detail_page_url = $detailPageEle->getAttribute("href");

                            $tempArr = explode("/", $detail_page_url);
                            $invoice_number = trim($tempArr[count($tempArr) - 1]);
                            $this->exts->log("invoice_number - " . $invoice_number);

                            if (!$this->exts->invoice_exists($invoice_number)) {
                                $invoice_amount = trim($columns[1]->getAttribute('innerText'));
                                $invoice_amount = trim(str_replace("$", "", $invoice_amount));
                                $this->exts->log("invoice_amount - " . $invoice_amount);

                                $linkElement = $this->exts->querySelector("//*[@id=\"all-invoices\"]/table/tbody/tr[$j]/td[5]/a[contains(@href,\"myshopify.com/admin/invoices/\")]");
                                if ($linkElement != null) {
                                    $linkHref = $linkElement->getAttribute("href");
                                    $this->exts->log("invoice_url - " . $linkHref);

                                    //Parse the in YYYY-mm-dd format
                                    $parsed_date = $this->exts->parse_date($invoice_date);
                                    if (trim($parsed_date) != "") {
                                        $invoice_date = $parsed_date;
                                    }
                                    $this->exts->log("invoice_date - " . $invoice_date);

                                    $filename = $invoice_number . ".pdf";
                                    $this->exts->log("filename - " . $filename);

                                    if (stripos($linkHref, ".pdf") !== false) {
                                        $this->totalFiles += 1;
                                        $downloaded_file = $this->exts->custom_downloader($linkHref, "pdf", $filename);
                                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                                            $pdf_content = file_get_contents($downloaded_file);
                                            if (stripos($pdf_content, "%PDF") !== false) {
                                                $this->exts->new_invoice($invoice_number, $invoice_date, $invoice_amount, $filename);
                                            } else {
                                                $this->exts->log("Invalid PDF File");
                                            }
                                        } else {
                                            $this->exts->log("File Download Failed");
                                        }
                                    } else {
                                        $this->exts->log("Invoice PDF url not found");
                                    }
                                }
                            } else {
                                $this->exts->log('Invoice Number already Exists - ' . $invoice_number);
                            }
                        } else {
                            $this->exts->log("Detail Page link not found");
                        }
                    } else {
                        $this->exts->log("No Columns found for this row");
                    }
                }

                if ((int)@$this->restrictPages == 0) {
                    if ($this->exts->querySelector("#invoices-pagination > li:nth-child(2) > a.ui-button.disabled") == null) {
                        $this->exts->querySelector("#invoices-pagination > li:nth-child(2) > a.ui-button")->click();
                        sleep(4);

                        $page++;
                        $this->processBillingPage($page);
                    }
                }
            } else {
                $this->exts->capture("invoice_list");
                if ($this->exts->exists('[data-href*="/billing/invoice/"]')) {
                    for ($page = 1; $page < 20; $page++) {
                        $rows = $this->exts->querySelectorAll('[data-href*="/billing/invoice/"]');
                        foreach ($rows as $row) {
                            $download_button = $this->exts->querySelector('[class*="ResourceItem__Actions"] button', $row);
                            if ($download_button != null) {
                                $order_href = $row->getAttribute('data-href');
                                $temps = explode('/invoice/', $order_href);
                                $invoiceName = end($temps);
                                $invoiceDate = '';
                                $invoiceAmount = '';
                                $invoiceFileName = $invoiceName . '.pdf';

                                $this->exts->log('--------------------------');
                                $this->exts->log('invoiceName: ' . $invoiceName);
                                $this->exts->log('invoiceDate: ' . $invoiceDate);
                                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                                if ($this->exts->invoice_exists($invoiceName)) {
                                    $this->exts->log('Invoice Existed: ' . $invoiceName);
                                } else {
                                    try {
                                        $this->exts->log('Click download_button button');
                                        $this->exts->click_element($download_button);
                                    } catch (\Exception $exception) {
                                        $this->exts->log('Click download_button button by javascript');
                                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                                    }
                                    sleep(5);
                                    $this->exts->wait_and_check_download('pdf');
                                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                                    } else {
                                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                                    }
                                }
                                $this->totalFiles++;
                            } else {
                                $this->exts->log(__FUNCTION__ . '::Download button not found ');
                            }
                        }


                        if ($this->restrictPages == 0 && $this->exts->exists('button#nextURL:not([aria-disabled])')) {
                            $this->exts->moveToElementAndClick('button#nextURL:not([aria-disabled])');
                            sleep(7);
                        } else {
                            break;
                        }
                    }
                }
            }
            if ($this->exts->exists('table.Polaris-IndexTable__Table tbody tr')) {
                $rows = count($this->exts->querySelectorAll('table.Polaris-IndexTable__Table tbody tr'));
                for ($i = 0; $i < $rows; $i++) {
                    $row = $this->exts->querySelectorAll('table.Polaris-IndexTable__Table tbody tr')[$i];
                    $tags = $this->exts->querySelectorAll('td', $row);
                    if (count($tags) >= 6) {
                        $invoiceDate = trim($tags[1]->getAttribute("innerText"));
                        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';
                        $this->exts->click_element($row);
                        sleep(10);
                        $invoiceName = array_pop(explode('invoice/', $this->exts->getUrl()));
                        $invoiceFileName = $invoiceName . '.pdf';
                        $this->isNoInvoice = false;
                        $this->exts->log('--------------------------');
                        $this->exts->log('invoiceName: ' . $invoiceName);
                        $this->exts->log('invoiceDate: ' . $invoiceDate);
                        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                        $parsed_date = $this->exts->parse_date($invoiceDate, 'd. M Y', 'Y-m-d');
                        $this->exts->log('Date parsed: ' . $parsed_date);

                        // Download invoice if it not exisited
                        $this->exts->click_element('div.Polaris-ActionMenu-Actions__ActionsLayout button');
                        sleep(2);
                        $this->exts->moveToElementAndClick('input[value="PDF"]');
                        sleep(1);
                        $this->exts->moveToElementAndClick('button.Polaris-Button--variantPrimary');
                        sleep(5);
                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                        $this->exts->execute_javascript('history.back();');
                        sleep(10);
                        if ($page > 1) {
                            for ($j = 1; $j < $page; $j++) {
                                $this->exts->moveToElementAndClick('button#nextURL:not([class*="disable"])');
                                sleep(10);
                            }
                        }
                    }
                }

                $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
                if (
                    $restrictPages == 0 &&
                    $page < 50 &&
                    $this->exts->querySelector('button#nextURL:not([class*="disable"])') != null
                ) {
                    $page++;
                    $this->exts->moveToElementAndClick('button#nextURL:not([class*="disable"])');
                    $this->processBillingPage($page);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception processBillingPage " . $exception->getMessage());
        }
    }
    public function process_shop_orders()
    {
        $this->exts->capture('shop-orders');
        for ($paging_count = 1; $paging_count < 50; $paging_count++) {
            $invoice_data_arr = [];
            //Polaris Order Page App Activate in user account.
            if ($this->exts->exists('#AppFrameMain [class*="Polaris-Layout"] table tbody tr')) {
                $rows = count($this->exts->querySelectorAll('#AppFrameMain [class*="Polaris-Layout"] table tbody tr'));
                for ($r = 0; $r < $rows; $r++) {
                    $row = $this->exts->querySelectorAll('#AppFrameMain [class*="Polaris-Layout"] table tbody tr')[$r];
                    $columns = $this->exts->querySelectorAll('td', $row);
                    if (count($columns) > 0 && $this->exts->querySelector('a[href*="/orders/"]', $columns[1]) != null) {

                        //Fetch Polaris detail page URL
                        $detail_page_url = '';
                        $checkboxBtn = $this->exts->querySelector('label[class*="Polaris-Choice"]', $columns[0]);
                        try {
                            $checkboxBtn->click();
                        } catch (\Exception $exception) {
                            $this->exts->execute_javascript('arguments[0].click();', [$checkboxBtn]);
                        }
                        sleep(1);
                        if ($this->exts->exists('[class*="Polaris-BulkActions__BulkActionButton"] [class*="Polaris-Button--iconOnly"]')) {
                            $moreBtns = $this->exts->querySelectorAll('[class*="Polaris-BulkActions__BulkActionButton"] [class*="Polaris-Button--iconOnly"]');
                            try {
                                $moreBtns[count($moreBtns) - 1]->click();
                            } catch (\Exception $exception) {
                                $this->exts->execute_javascript('arguments[0].click();', [$moreBtns[count($moreBtns) - 1]]);
                            }
                            sleep(2);

                            // Select first Print with Order Printer Pro, If not available select Print with Order Printer
                            if ($this->exts->exists('[class*="Polaris-Popover"] a[href*="/extensions/print/"]')) {
                                $detail_page_url = $this->exts->extract('[class*="Polaris-Popover"] a[href*="/extensions/print/"]', null, 'href');
                            } else if ($this->exts->exists('[class*="Polaris-ActionList_"] a[href*="/order-printer/orders/"]')) {
                                $detail_page_url = $this->exts->extract('[class*="Polaris-ActionList_"] a[href*="/order-printer/orders/"]', null, 'href');
                            }

                            //Close the dropdown list of actionmenus
                            $moreBtns = $this->exts->querySelectorAll('[class*="Polaris-BulkActions__BulkActionButton"] [class*="Polaris-Button--iconOnly"]');
                            try {
                                $moreBtns[count($moreBtns) - 1]->click();
                            } catch (\Exception $exception) {
                                $this->exts->execute_javascript('arguments[0].click();', [$moreBtns[count($moreBtns) - 1]]);
                            }
                            // sleep(2);
                            //Uncheck the checkbox
                            $checkboxBtn = $this->exts->querySelector('label[class*="Polaris-Choice"]', $columns[0]);
                            try {
                                $checkboxBtn->click();
                            } catch (\Exception $exception) {
                                $this->exts->execute_javascript('arguments[0].click();', [$checkboxBtn]);
                            }
                            sleep(1);
                        }

                        if (trim($detail_page_url) == '' || empty($detail_page_url)) {
                            $temp_detail_page_url =  $this->exts->querySelector('a[href*="/orders/"]', $columns[1])->getAttribute("href");
                            $this->exts->log("detail_page_url - " . $temp_detail_page_url);

                            $detail_page_url = $temp_detail_page_url;
                            /*$orderId = trim(explode('?', end(explode('/orders/', $temp_detail_page_url)))[0]);
                        $shurl = trim(explode('/orders/', $temp_detail_page_url)[0]);
                        $shop_name = trim(explode("/",$shurl)[2]);
                        $detail_page_url = $shurl.'/apps/order-printer-emailer/orders?shop='.$shop_name.'&ids[]='.$orderId;*/
                        }
                        $this->exts->log("detail_page_url - " . $detail_page_url);
                        if ($detail_page_url != '') {
                            $invoice_number = trim($this->exts->querySelector('a[href*="/orders/"]', $columns[1])->getAttribute('innerText'));
                            $invoice_number = str_replace("#", "", $invoice_number);
                            $this->exts->log("invoice_number - " . $invoice_number);

                            if (!$this->exts->invoice_exists($invoice_number)) {
                                if ($this->exts->querySelector('div', $columns[3]) != null) {
                                    $invoice_date = trim($this->exts->querySelector('div', $columns[3])->getAttribute("title"));
                                    $this->exts->log("invoice_date - " . $invoice_date);

                                    //Parse the in YYYY-mm-dd format
                                    $parsed_date = $this->exts->parse_date($invoice_date);
                                    if (trim($parsed_date) != "") {
                                        $invoice_date = $parsed_date;
                                    }
                                    $this->exts->log("invoice_date - " . $invoice_date);
                                }

                                $invoice_url = $detail_page_url;
                                if (stripos($detail_page_url, $this->shop_name) === false && stripos($detail_page_url, 'https://') === false) {
                                    $invoice_url = 'https://' . $this->shop_name . $invoice_url;
                                }
                                $this->exts->log("detail_page_url - " . $invoice_url);

                                $invoice_data_arr[] = array(
                                    'invoice_number' => $invoice_number,
                                    'invoice_date'   => $invoice_date,
                                    'invoice_amount' => '',
                                    'invoice_url'    => $invoice_url,
                                );
                            } else {
                                $this->exts->log('Invoice Number already Exists - ' . $invoice_number);
                            }
                        } else {
                            $this->exts->log("Detail Page link not found");
                        }
                    } else {
                        $this->exts->log("No Columns found for this row");
                    }
                }
            } else {
                $rows = $this->exts->querySelectorAll('#AppFrameMain table tbody tr');
                foreach ($rows as $row) {
                    $columns = $this->exts->querySelectorAll('td', $row);
                    if (count($columns) > 0 && $this->exts->querySelector('a[href*="/admin/orders/"]', $columns[1]) != null) {

                        $detail_page_url =  $this->exts->querySelector('a[href*="/admin/orders/"]', $columns[1])->getAttribute("href");
                        $this->exts->log("detail_page_url - " . $detail_page_url);
                        if ($detail_page_url != '') {
                            $invoice_number = trim($this->exts->querySelector('a[href*="/admin/orders/"]', $columns[1])->getAttribute('innerText'));
                            $invoice_number = str_replace("#", "", $invoice_number);
                            $this->exts->log("invoice_number - " . $invoice_number);

                            if (!$this->exts->invoice_exists($invoice_number)) {
                                if ($this->exts->querySelector('div', $columns[3]) != null) {
                                    $invoice_date = trim($this->exts->querySelector('div', $columns[3])->getAttribute("title"));
                                    $this->exts->log("invoice_date - " . $invoice_date);

                                    //Parse the in YYYY-mm-dd format
                                    $parsed_date = $this->exts->parse_date($invoice_date);
                                    if (trim($parsed_date) != "") {
                                        $invoice_date = $parsed_date;
                                    }
                                    $this->exts->log("invoice_date - " . $invoice_date);
                                }

                                $invoice_url = $detail_page_url;
                                if (stripos($detail_page_url, $this->shop_name) === false && stripos($detail_page_url, 'https://') === false) {
                                    $invoice_url = 'https://' . $this->shop_name . $invoice_url;
                                }
                                $this->exts->log("detail_page_url - " . $invoice_url);

                                $invoice_data_arr[] = array(
                                    'invoice_number' => $invoice_number,
                                    'invoice_date'   => $invoice_date,
                                    'invoice_amount' => '',
                                    'invoice_url'    => $invoice_url,
                                );
                            } else {
                                $this->exts->log('Invoice Number already Exists - ' . $invoice_number);
                            }
                        } else {
                            $this->exts->log("Detail Page link not found");
                        }
                    } else {
                        $this->exts->log("No Columns found for this row");
                    }
                }
            }

            if (!empty($invoice_data_arr)) {
                $newTab = $this->exts->openNewTab();
                $this->downloading_order_invoice($invoice_data_arr);

                $this->exts->switchToInitTab();
                $this->exts->closeAllTabsButThis();
            }


            if ($this->exts->exists('button#nextURL:not([aria-disabled="true"])')) {
                $this->exts->moveToElementAndClick('button#nextURL:not([aria-disabled="true"])');
                sleep(5);
            } else {
                break;
            }
        }
    }

    public function processAccessAccountInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('table tbody tr', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $currentInvoiceUrl = $this->exts->getUrl();
        sleep(20);
        $rows = $this->exts->querySelectorAll('table tbody tr');
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(6)', $row) != null) {
                $invoiceName = $row->getAttribute('id');
                $invoiceUrl = str_replace("/bills", "/invoice/$invoiceName", $currentInvoiceUrl);
                $invoiceAmount =  $this->exts->extract('td:nth-child(6)', $row);
                $invoiceDate =  $this->exts->extract('td:nth-child(1)', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));

                $this->isNoInvoice = false;
            }
        }
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $this->exts->openUrl($invoice['invoiceUrl']);

            $this->exts->waitTillPresent('div.Polaris-ActionMenu-Actions__ActionsLayout button');
            sleep(3);
            $this->exts->click_element('div.Polaris-ActionMenu-Actions__ActionsLayout button');
            sleep(6);
            $this->exts->click_element('ul li:nth-child(2) label[class*="Polaris-Choice"]');
            sleep(3);
            $download_button = 'div[class*=Polaris-Modal] button[class*=variantPrimary]';

            // Download all invoices
            $this->exts->log('Invoices found: ' . count($invoices));
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);

            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'j. M. Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            $downloaded_file = $this->exts->click_and_download($download_button, 'pdf', $invoiceFileName);

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
        $this->exts->openUrl($currentInvoiceUrl);
        sleep(20);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        if (
            $restrictPages == 0 &&
            $paging_count < 10 &&
            $this->exts->querySelector(' button[id="nextURL"]:not([class*="disabled"])')
        ) {
            $paging_count++;
            $paginateButton = $this->exts->querySelector(' button[id="nextURL"]:not([class*="disabled"])');
            if ($paginateButton->click()) {
                sleep(5);
                $this->processAccessAccountInvoices($paging_count);
            }
        } else if (
            $restrictPages != 0 &&
            $paging_count < $restrictPages &&
            $this->exts->querySelector(' button[id="nextURL"]:not([class*="disabled"])')
        ) {
            $this->exts->log('Click paginateButton');
            $paging_count++;
            $paginateButton = $this->exts->querySelector(' button[id="nextURL"]:not([class*="disabled"])');
            if ($paginateButton->click()) {
                sleep(5);
                $this->processAccessAccountInvoices($paging_count);
            }
        }
    }

    public function downloading_order_invoice($invoice_data_arr)
    {
        foreach ($invoice_data_arr as $invoice_data) {
            $this->totalFiles += 1;
            $this->exts->log("Invoice Name - " . $invoice_data['invoice_number']);
            $this->exts->log("Invoice Date - " . $invoice_data['invoice_date']);
            $this->exts->log("Invoice Amount - " . $invoice_data['invoice_amount']);
            $this->exts->log("Invoice URL - " . $invoice_data['invoice_url']);

            $this->exts->openUrl($invoice_data['invoice_url']);
            sleep(1);
            $this->exts->waitTillPresent('#AppFrameMain [class*="Polaris-ButtonGroup"]', 25);
            sleep(2);

            $filename = $invoice_data['invoice_number'] . ".pdf";
            $this->exts->log("filename - " . $filename);

            if (stripos($invoice_data['invoice_url'], '/order-printer-emailer/') !== false) {
                if ($this->exts->exists('[class*="Polaris-Stack__Item"] button[class*="Polaris-Button--primary"][type="button"]')) {
                    $this->exts->moveToElementAndClick('[class*="Polaris-Stack__Item"] button[class*="Polaris-Button--primary"][type="button"]');
                } else {
                    $this->clickElementByText('[class*="Polaris-Button"]', ['print', 'drucken']);
                }
                sleep(2);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $pdf_content = file_get_contents($downloaded_file);
                    $file_size = number_format(filesize($downloaded_file) / 1024, 2);
                    $this->exts->log('File Size - ' . $file_size);
                    if (stripos($pdf_content, "%PDF") !== false) {
                        $this->exts->new_invoice($invoice_data['invoice_number'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename, 0, '', 0, 'sales_invoice');
                    } else {
                        $this->exts->log("Invalid PDF File");
                    }
                } else {
                    $this->exts->log("File Download Failed");
                }
            } else if (stripos($invoice_data['invoice_url'], '/apps/order-printer/orders/') !== false) {
                if ($this->exts->exists('.Document__Actions .Polaris-ButtonGroup__Item button')) {
                    $moreBtns = $this->exts->querySelectorAll('.Document__Actions .Polaris-ButtonGroup__Item button');
                    try {
                        $moreBtns[0]->click();
                    } catch (\Exception $exception) {
                        $this->exts->execute_javascript('arguments[0].click();', [$moreBtns[0]]);
                    }
                } else {
                    $this->clickElementByText('[class*="Polaris-Button"]', ['print', 'drucken']);
                }
                sleep(2);

                // $moreBtns = $this->exts->querySelectorAll('[class*="Polaris-BulkActions__BulkActionButton"] button');
                // try {
                //     $moreBtns[count($moreBtns)-1]->click();
                // }  catch(\Exception $exception) {
                //     $this->exts->execute_javascript('arguments[0].click();',[$moreBtns[count($moreBtns)-1]]);
                // }
                // sleep(2);

                // if($this->exts->exists('[class*="Polaris-Popover"] .Polaris-ActionList__Actions button.Polaris-ActionList__Item')) {
                //     $moreBtns = $this->exts->querySelectorAll('[class*="Polaris-Popover"] .Polaris-ActionList__Actions button.Polaris-ActionList__Item');
                //     try {
                //         $moreBtns[0]->click();
                //     }  catch(\Exception $exception) {
                //         $this->exts->execute_javascript('arguments[0].click();',[$moreBtns[0]]);
                //     }
                //     sleep(30);
                // }

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $filename);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $pdf_content = file_get_contents($downloaded_file);
                    $file_size = number_format(filesize($downloaded_file) / 1024, 2);
                    $this->exts->log('File Size - ' . $file_size);
                    if (stripos($pdf_content, "%PDF") !== false) {
                        $this->exts->new_invoice($invoice_data['invoice_number'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename, 0, '', 0, 'sales_invoice');
                    } else {
                        $this->exts->log("Invalid PDF File");
                    }
                } else {
                    $this->exts->log("File Download Failed");
                }
            } else {
                $downloaded_file = $this->exts->download_current($filename);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $pdf_content = file_get_contents($downloaded_file);
                    $file_size = number_format(filesize($downloaded_file) / 1024, 2);;
                    $this->exts->log('File Size - ' . $file_size);
                    if (stripos($pdf_content, "%PDF") !== false) {
                        $this->exts->new_invoice($invoice_data['invoice_number'], $invoice_data['invoice_date'], $invoice_data['invoice_amount'], $filename, 0, '', 0, 'sales_invoice');
                    } else {
                        $this->exts->log("Invalid PDF File");
                    }
                } else {
                    $this->exts->log("File Download Failed");
                }
            }
        }
    }
    public function processPayoutInvoices($count = 1, $page_count = 1)
    {
        sleep(5);
        if ($this->exts->querySelector('#transfers-results table tr a[href*="/payments/payouts/"], [class*="Polaris-DataTable_"] table tr a[href*="/payments/payouts/"]') != null) {
            $this->exts->log('Payout Invoices found');
            $this->exts->capture("4-Payout-opened");
            $invoices = [];

            if ($this->exts->exists('[class*="Polaris-DataTable_"] table tr a[href*="/payments/payouts/"]')) {
                $rows = $this->exts->querySelectorAll('[class*="Polaris-DataTable_"] table tr');
                foreach ($rows as $row) {
                    $tags = $this->exts->querySelectorAll('td', $row);
                    $tags1 = $this->exts->querySelectorAll('th', $row);
                    if (count($tags) >= 5 && count($tags1) > 0 && $this->exts->querySelector('a[href*="/payments/payouts/"]', $tags1[0]) != null) {
                        $invoiceUrl = $this->exts->querySelector('a[href*="/payments/payouts/"]', $tags1[0])->getAttribute("href");
                        $invoiceName = explode(
                            '/',
                            array_pop(explode('/payouts/', $invoiceUrl))
                        )[0];
                        $invoiceDate = trim($tags1[0]->getAttribute('innerText'));
                        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';

                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl
                        ));
                    }
                }
            } else {
                $rows = $this->exts->querySelectorAll('#transfers-results table tr');
                foreach ($rows as $row) {
                    $tags = $this->exts->querySelectorAll('td', $row);
                    if (count($tags) >= 6 && $this->exts->querySelector('a[href*="/payments/payouts/"]', $tags[0]) != null) {
                        $invoiceUrl = $this->exts->querySelector('a[href*="/payments/payouts/"]', $tags[0])->getAttribute("href");
                        $invoiceName = explode(
                            '/',
                            array_pop(explode('/payouts/', $invoiceUrl))
                        )[0];
                        $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[6]->getAttribute('innerText'))) . ' EUR';

                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => $invoiceAmount,
                            'invoiceUrl' => $invoiceUrl
                        ));
                    }
                }
            }

            // Download all invoices
            $this->exts->log('Invoices: ' . count($invoices));
            $count = 1;
            $totalFiles = count($invoices);

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            $date_from = $restrictPages == 0 ? strtotime('-2 years') : strtotime('-6 months');
            $this->exts->log("Download invoices from Date:" . date('m', $date_from) . '/' . date('Y', $date_from));

            foreach ($invoices as $invoice) {
                $this->totalFiles += 1;
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

                $this->exts->log('date before parse: ' . $invoice['invoiceDate']);

                $parsed_date = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
                $parsed_date == '' ? $this->exts->parse_date($invoice['invoiceDate'], 'd. M Y', 'Y-m-d', 'fr') : $parsed_date;
                $parsed_date == '' ? $this->exts->parse_date($invoice['invoiceDate'], 'M Y', 'Y-m-d', 'fr') : $parsed_date;
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $parsed_date);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                if ($parsed_date != null && $parsed_date != '') {
                    if (
                        date('Y', $date_from) > date('Y', strtotime($parsed_date))
                        || (date('m', $date_from) > date('m', strtotime($parsed_date))
                            && date('Y', $date_from) == date('Y', strtotime($parsed_date)))
                    ) {
                        $this->exts->log('Invoice files are too old, skipping download');
                        continue;
                    }
                }

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->log('Downloading invoice ' . $count . '/' . $totalFiles);

                    $downloaded_file = $this->exts->download_capture($invoice['invoiceUrl'], $invoiceFileName, 30);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $file_size = number_format(filesize($downloaded_file) / 1024, 2);;
                        $this->exts->log('File Size - ' . $file_size);
                        $this->exts->new_invoice($invoice['invoiceName'], $parsed_date, $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                        $count++;
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }

            if ($restrictPages == 0 && $this->exts->exists('nav button#nextURL') && $page_count < 10) {
                $this->exts->moveToElementAndClick('nav button#nextURL');
                sleep(15);
                $page_count++;
                $count = 1;
                $this->processPayoutInvoices($count, $page_count);
            }
        } else {
            if ($count < 5) {
                $count = $count + 1;
                $this->processPayoutInvoices($count, $page_count);
            } else {
                $this->exts->log('Timeout process payout Invoices');
                $this->exts->capture('4-no-payout-invoices');
                // $this->exts->no_invoice();
            }
        }
    }
    public function processTaxDocuments()
    {
        sleep(5);
        $current_url = $this->exts->getUrl();
        $this->clickElementByText('div[class*="ActionMenu"] button', ['Dokumente', 'Documents', 'Documenti', 'Documenten', 'Documentos']);
        sleep(15);
        $this->exts->capture("4-tax-document");
        $invoices = [];

        $links = $this->exts->querySelectorAll('[role="dialog"] section a[href*="/documents/"]');
        foreach ($links as $link) {
            $invoiceUrl = $link->getAttribute("href");
            $tempArr = explode('/documents/', $invoiceUrl);
            $invoiceName = end($tempArr);
            $invoiceDate = '';
            $invoiceAmount = '';

            array_push($invoices, array(
                'invoiceName' => $invoiceName,
                'invoiceDate' => $invoiceDate,
                'invoiceAmount' => $invoiceAmount,
                'invoiceUrl' => $invoiceUrl
            ));
        }

        // Download all invoices
        $this->exts->log('Invoices: ' . count($invoices));
        $newTab = $this->exts->openNewTab();
        // bypass blocked page if required
        $this->exts->openUrl($current_url);
        sleep(5);
        $this->checkSolveBlockedPage();
        //

        foreach ($invoices as $invoice) {
            $this->totalFiles += 1;
            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ');
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], "pdf", $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], '', '', $downloaded_file);
            } else {
                $this->exts->log('Timeout when download ' . $invoiceFileName);
            }
        }

        $this->exts->switchToInitTab();
        $this->exts->closeAllTabsButThis();
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
