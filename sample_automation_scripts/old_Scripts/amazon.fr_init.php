public $baseUrl = "https://www.amazon.fr";
public $orderPageUrl = "https://www.amazon.fr/gp/css/order-history/ref=nav_youraccount_orders";
public $messagePageUrl = "https://www.amazon.fr/gp/message?ref_=ya_d_l_msg_center#!/inbox";
public $alt_messagePageUrl = "https://www.amazon.fr/gp/message?ie=UTF8&ref_=ya_mc_bsm&#!/inbox";
public $businessPrimeUrl = "https://www.amazon.fr/businessprime";
public $loginLinkPrim = "div[id=\"nav-flyout-ya-signin\"] a";
public $loginLinkSec = "div[id=\"nav-signin-tooltip\"] a";
public $loginLinkThr = "div#nav-tools a#nav-link-yourAccount";
public $username_selector = 'input[autocomplete="username"]';
public $password_selector = "#ap_password";
public $submit_button_selector = "#signInSubmit, input[type='submit']";
public $continue_button_selector = "#continue, #continue.a-button";
public $logout_link = "a#nav-item-signout";
public $remember_me = "input[name=\"rememberMe\"]";
public $login_tryout = 0;
public $msg_invoice_triggerd = 0;
public $restrictPages = 3;
public $all_processed_orders = array();
public $amazon_download_overview;
public $download_invoice_from_message;
public $auto_request_invoice;
public $procurment_report = 0;
public $only_years;
public $auto_tagging;
public $marketplace_invoice_tags;
public $order_overview_tags;
public $amazon_invoice_tags;
public $start_page = 0;
public $dateLimitReached = 0;
public $msgTimeLimitReached = 0;
public $last_invoice_date = "";
public $last_state = array();
public $current_state = array();
public $isBusinessUser = false;
public $invalid_filename_keywords = array('agb', 'terms', 'datenschutz', 'privacy', 'rechnungsbeilage', 'informationsblatt', 'gesetzliche', 'retouren', 'widerruf', 'allgemeine gesch', 'mfb-buchung', 'informationen zu zahlung', 'nachvertragliche', 'retourenschein', 'allgemeine_gesch', 'rcklieferschein');

public $invalid_filename_pattern = '';
public $start_date = '';
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    if ($this->exts->docker_restart_counter == 0) {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $this->amazon_download_overview = isset($this->exts->config_array["download_overview_pdf"]) ? (int)$this->exts->config_array["download_overview_pdf"] : 0;
        $this->download_invoice_from_message = isset($this->exts->config_array["download_invoice_from_message"]) ? (int)$this->exts->config_array["download_invoice_from_message"] : 0;
        $this->auto_request_invoice = isset($this->exts->config_array["auto_request_invoice"]) ? (int)$this->exts->config_array["auto_request_invoice"] : 0;
        $this->procurment_report = isset($this->exts->config_array["procurment_report"]) ? (int)$this->exts->config_array["procurment_report"] : 0;
        $this->only_years = isset($this->exts->config_array["only_years"]) ? $this->exts->config_array["only_years"] : '';
        $this->auto_tagging = isset($this->exts->config_array["auto_tagging"]) ? $this->exts->config_array["auto_tagging"] : '';
        $this->marketplace_invoice_tags = isset($this->exts->config_array["marketplace_invoice_tags"]) ? $this->exts->config_array["marketplace_invoice_tags"] : '';
        $this->order_overview_tags = isset($this->exts->config_array["order_overview_tags"]) ? $this->exts->config_array["order_overview_tags"] : '';
        $this->amazon_invoice_tags = isset($this->exts->config_array["amazon_invoice_tags"]) ? $this->exts->config_array["amazon_invoice_tags"] : '';
        $this->start_page = isset($this->exts->config_array["start_page"]) ? $this->exts->config_array["start_page"] : '';
        $this->last_invoice_date = isset($this->exts->config_array["last_invoice_date"]) ? $this->exts->config_array["last_invoice_date"] : '';
        $this->start_date = (isset($this->exts->config_array["start_date"]) && !empty($this->exts->config_array["start_date"])) ? trim($this->exts->config_array["start_date"]) : "";

        if (!empty($this->start_date)) {
            $this->start_date = strtotime($this->start_date);
        }
        $this->invalid_filename_pattern = '';
        if (!empty($this->invalid_filename_keywords)) {
            $this->invalid_filename_pattern = '';
            foreach ($this->invalid_filename_keywords as $s) {
                if ($this->invalid_filename_pattern != '') $this->invalid_filename_pattern .= '|';
                $this->invalid_filename_pattern .= preg_quote($s, '/');
            }
        }
    } else {
        $this->last_state = $this->current_state;
    }

    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    $this->check_solve_captcha_page();
    $this->exts->capture("Home-page-without-cookie");
    if ($this->exts->exists('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]')) {
        $this->exts->click_by_xdotool('a[href="/gp/css/homepage.html/ref=nav_bb_ya"]');
        sleep(2);
    }

    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->orderPageUrl);
    sleep(5);
    $this->check_solve_captcha_page();

    if ($this->checkLogin()) {
        // 2024-Jan-15 Huy added this because a bug, 
        // with cookie, user maybe logged in, order page loaded successfully, BUT when click "invoice" dropdown, it displays "Session is expired"
        // So do this as double check for cokkie and login status
        $this->exts->openUrl('https://www.amazon.fr/gp/message');
        sleep(5);
        $this->check_solve_captcha_page();
        $this->exts->capture("login-cookie-double-check");
    }

    if (!$this->checkLogin()) {
        // $this->exts->openUrl('https://www.amazon.fr/gp/message');
        $this->exts->openUrl($this->orderPageUrl);
        sleep(5);

        if (stripos($this->exts->getUrl(), "/gp/css/homepage.html?") !== false) {
            $this->exts->click_by_xdotool('a[href*="/gp/your-account/order-history?"]');
            sleep(5);
        }

        $this->check_solve_captcha_page();
        $this->fillForm(0);
        // retry if captcha showed
        if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
            $this->fillForm(0);
        }
        if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
            $this->fillForm(0);
        }
        if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && !$this->isIncorrectCredential()) {
            $this->fillForm(0);
            sleep(5);
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                $this->fillForm(0);
                sleep(5);
            }
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                $this->fillForm(0);
                sleep(5);
            }
            if ($this->exts->allExists([$this->password_selector, 'input#auth-captcha-guess']) && $this->captcha_required() && !$this->isIncorrectCredential()) {
                $this->fillForm(0);
                sleep(5);
            }
        }
        $this->check_solve_captcha_page();
        $this->checkFillExternalLogin();
        // Captcha and Two Factor Check
        $this->checkFillTwoFactor();
        sleep(5);

        // retry otp in code is invalid
        if (
            stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), "Le code que vous avez saisi n'est pas valide. Veuillez réessayer.") !== false ||
            stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), "The code you entered is invalid. Please try again.") !== false ||
            stripos($this->exts->extract('div#invalid-otp-code-message', null, 'innerText'), "Le code que vous avez saisi n'est pas valide. Veuillez vérifiez le code et réessayez.") !== false
        ) {
            if ($this->exts->exists('a#auth-get-new-otp-link')) {
                $this->exts->moveToElementAndClick('a#auth-get-new-otp-link');
                sleep(5);
            }

            if ($this->exts->exists('div#invalid-otp-code-message')) {
                sleep(5);
                $this->exts->waitTillPresent('a[class="a-link-normal"]#resend-approval-link', 25);
                $this->exts->moveToElementAndClick('a[class="a-link-normal"]#resend-approval-link');
                sleep(5);
            }
            $this->checkFillTwoFactor();
        }

        $this->check_solve_captcha_page();
        if ($this->exts->exists('a#ap-account-fixup-phone-skip-link')) {
            $this->exts->click_by_xdotool('a#ap-account-fixup-phone-skip-link');
            sleep(2);
        }
        $this->check_solve_captcha_page();
    }

    if ($this->checkLogin()) {
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    } else {
        if ($this->isIncorrectCredential() || $this->exts->exists('div#auth-email-invalid-claim-alert')) {
            $this->exts->loginFailure(1);
        } else if (
            stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), "Le code que vous avez saisi n'est pas valide. Veuillez réessayer.") !== false ||
            stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), "The code you entered is invalid. Please try again.") !== false ||
            stripos($this->exts->extract('div#invalid-otp-code-message', null, 'innerText'), "Le code que vous avez saisi n'est pas valide. Veuillez vérifiez le code et réessayez.") !== false
        ) {
            $this->exts->loginFailure(1);
        }
        $this->exts->loginFailure();
    }
}
private function fillForm($count)
{
    $this->check_solve_captcha_page();
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->log("Begin fillForm URL - " . $this->exts->getUrl());

    if ($this->exts->querySelector("button.a-button-close.a-declarative") != null) {
        $this->exts->click_element("button.a-button-close.a-declarative");
    }

    $this->exts->capture("account-switcher");
    $account_switcher_elements = $this->exts->querySelectorAll("div.cvf-account-switcher-profile-details-after-account-removed");
    if (count($account_switcher_elements) > 0) {
        $this->exts->log("click account-switcher");
        $this->exts->click_element($account_switcher_elements[0]);
        sleep(4);
    }
    sleep(10);
    if ($this->exts->urlContains('google.com/')) {
        $this->loginGoogleIfRequired();
    } else if ($this->exts->exists('input#okta-signin-username') || $this->exts->exists('input#okta-signin-password') || $this->exts->urlContains('okta.com')) {
        $this->checkFillExternalLogin();
    } else if ($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')) {
        $this->loginMicrosoftIfRequired();
    } else {
        if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->username_selector) != null) {
            $this->exts->capture("1-pre-login");
            $formType = $this->exts->querySelector($this->password_selector);
            if ($formType == null) {
                $this->exts->log("Form with Username Only");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
                if ($this->exts->exists('input#auth-captcha-guess')) {
                    $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
                }
                $this->exts->log("Username form button click");
                $this->exts->click_by_xdotool($this->continue_button_selector);
                sleep(5);
                $this->exts->capture("1.1-pre-login");
                sleep(10);
                if ($this->exts->urlContains('google.com/')) {
                    $this->loginGoogleIfRequired();
                } else if ($this->exts->exists('input#okta-signin-username') || $this->exts->exists('input#okta-signin-password') || $this->exts->urlContains('okta.com')) {
                    $this->checkFillExternalLogin();
                } else if ($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')) {
                    $this->loginMicrosoftIfRequired();
                } else {
                    if ($this->exts->exists($this->username_selector)) {
                        $this->exts->moveToElementAndType($this->username_selector, $this->username);
                        sleep(2);
                        if ($this->exts->exists('input#auth-captcha-guess')) {
                            $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
                        }
                        $this->exts->type_key_by_xdotool('Return');
                        sleep(5);
                    }
                    sleep(5);
                    if ($this->exts->exists('form input[id="continue"]')) {
                        $this->exts->click_element('form input[id="continue"]');
                        sleep(15);
                        $this->checkFillTwoFactor();
                    } else {
                        $this->exts->click_element('Not Found button');
                    }
                    $this->check_solve_captcha_page();
                    if ($this->exts->exists($this->password_selector)) {
                        $this->exts->log("Enter Password");
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(1);
                        if ($this->exts->exists('input[name="rememberMe"]:not(:checked)')) {
                            $this->exts->click_by_xdotool('input[name="rememberMe"]:not(:checked)');
                        }
                        if ($this->exts->exists('input#auth-captcha-guess')) {
                            $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
                        }
                        $this->exts->capture("1-filled-login");
                        $this->exts->click_by_xdotool($this->submit_button_selector);
                        sleep(5);
                        if ($this->exts->exists($this->submit_button_selector)) {
                            $this->exts->click_element($this->submit_button_selector);
                        }
                        $this->loginGoogleIfRequired();
                    }
                }
            } else {
                if ($this->exts->querySelector($this->username_selector) != null && $this->exts->querySelector("input#ap_email[type=\"hidden\"]") == null) {
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(1);
                }

                if ($this->exts->querySelector($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(1);
                }
                if ($this->exts->exists('input#auth-captcha-guess')) {
                    $this->processCaptcha('img#auth-captcha-image', 'input#auth-captcha-guess');
                }
                if ($this->exts->exists('input[name="rememberMe"]:not(:checked)')) {
                    $this->exts->click_by_xdotool('input[name="rememberMe"]:not(:checked)');
                }
                $this->exts->capture("2-filled-login");
                $this->exts->click_by_xdotool($this->submit_button_selector);
                sleep(5);
                if ($this->exts->exists($this->submit_button_selector)) {
                    $this->exts->click_element($this->submit_button_selector);
                }
            }
            sleep(11);
        }
        $this->exts->log("END fillForm URL - " . $this->exts->getUrl());
    }
}
private function checkFillTwoFactor()
{
    $this->exts->capture("2.0-two-factor-checking");
    if ($this->exts->exists('div.auth-SMS input[type="radio"], input[type="radio"][value="mobile"]')) {
        $this->exts->click_by_xdotool('div.auth-SMS input[type="radio"]:not(:checked), input[type="radio"][value="mobile"]:not(:checked)');
        sleep(2);
        $this->exts->click_by_xdotool('input#auth-send-code, input#continue');
        sleep(5);
    } else if ($this->exts->exists('div.auth-TOTP input[type="radio"], input[type="radio"][value="email"]')) {
        $this->exts->click_by_xdotool('div.auth-TOTP input[type="radio"]:not(:checked), input[type="radio"][value="email"]:not(:checked)');
        sleep(2);
        $this->exts->click_by_xdotool('input#auth-send-code, input#continue');
        sleep(5);
    } else if ($this->exts->allExists(['input[type="radio"]', 'input#auth-send-code']) || $this->exts->exists('input[name="OTPChallengeOptions"]')) {
        $this->exts->click_by_xdotool('input[type="radio"]');
        sleep(2);
        $this->exts->click_by_xdotool('input#auth-send-code, input#continue');
        sleep(5);
    }

    if ($this->exts->exists('#verification-code-form input[name="code"] , input[name="otpCode"]:not([type="hidden"]), input[name="code"], input#input-box-otp')) {
        $two_factor_selector = '#verification-code-form input[name="code"] , input[name="otpCode"]:not([type="hidden"]), input[name="code"], input#input-box-otp';
        $two_factor_message_selector = '#verification-code-form h1 , #auth-mfa-form h1 + p, #verification-code-form > .a-spacing-small > .a-spacing-none, #channelDetailsForOtp';
        $two_factor_submit_selector = '#cvf-submit-otp-button input[class="a-button-input"] , #auth-signin-button, #verification-code-form input[type="submit"]';
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $this->exts->notification_uid = "";
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code)) {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code: " . $two_factor_code);
            if (!$this->exts->exists($two_factor_selector)) { // by some javascript reason, sometime selenium can not find the input
                $this->exts->refresh();
                sleep(5);
                $this->exts->capture("2.1-two-factor-refreshed." . $this->exts->two_factor_attempts);
            }
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            if ($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')) {
                $this->exts->click_by_xdotool('label[for="auth-mfa-remember-device"]');
            }
            sleep(1);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            if ($this->exts->exists('#cvf-submit-otp-button input[type="submit"]')) {
                $this->exts->click_by_xdotool('#cvf-submit-otp-button input[type="submit"]');
            } else {
                $this->exts->click_by_xdotool($two_factor_submit_selector);
            }

            sleep(5);
            $this->exts->waitTillPresent('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', 7);
            $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
        } else {
            $this->exts->log("Not received two factor code");
        }

        // Huy added this 2022-12 Retry if incorrect code inputted
        if ($this->exts->exists($two_factor_selector)) {
            if (
                stripos($this->exts->extract('#auth-error-message-box .a-alert-content', null, 'innerText'), 'Der eingegebene Code ist ung') !== false ||
                stripos($this->exts->extract('#auth-error-message-box .a-alert-content, #invalid-otp-code-message', null, 'innerText'), 'you entered is not valid') !== false ||
                stripos($this->exts->extract('#invalid-otp-code-message', null, 'innerText'), 'Code ist ung') !== false
            ) {
                $temp_text = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
                if (!empty($temp_text)) {
                    $this->exts->two_factor_notif_msg_en = $temp_text;
                }
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
                for ($t = 2; $t <= 3; $t++) {
                    $this->exts->log("Retry 2FA Message:\n" . $this->exts->two_factor_notif_msg_en);
                    $this->exts->notification_uid = "";
                    $this->exts->two_factor_attempts++;
                    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                    if (!empty($two_factor_code)) {
                        $this->exts->log("Retry 2FA: Entering two_factor_code: " . $two_factor_code);
                        if (!$this->exts->exists($two_factor_selector)) { // by some javascript reason, sometime selenium can not find the input
                            $this->exts->refresh();
                            sleep(5);
                            $this->exts->capture("2.1-two-factor-refreshed." . $this->exts->two_factor_attempts);
                        }

                        $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                        if ($this->exts->exists('label[for="auth-mfa-remember-device"] input[name="rememberDevice"]:not(:checked)')) {
                            $this->exts->click_by_xdotool('label[for="auth-mfa-remember-device"]');
                        }
                        sleep(1);
                        $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                        if ($this->exts->exists('#cvf-submit-otp-button input[type="submit"]')) {
                            $this->exts->click_by_xdotool('#cvf-submit-otp-button input[type="submit"]');
                        } else {
                            $this->exts->click_by_xdotool($two_factor_submit_selector);
                        }
                        sleep(10);
                        $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
                        if (!$this->exts->exists($two_factor_selector)) {
                            break;
                        }
                    } else {
                        $this->exts->log("Not received Retry two factor code");
                    }
                }
            }
        }
    } else if ($this->exts->exists('div.otp-input-box-container input[name*="otc"]')) {
        $two_factor_selector = 'div.otp-input-box-container input[name*="otc"]';
        $two_factor_message_selector = 'div#channelDetailsForOtp';
        $two_factor_submit_selector = 'form#verification-code-form input[type="submit"][aria-labelledby="cvf-submit-otp-button-announce"]';

        if ($this->exts->querySelector($two_factor_selector) != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);

                $resultCodes = str_split($two_factor_code);
                $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
                foreach ($code_inputs as $key => $code_input) {
                    if (array_key_exists($key, $resultCodes)) {
                        $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                        $this->exts->moveToElementAndType('div.otp-input-box-container input[name*="otc"]:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                        // $code_input->sendKeys($resultCodes[$key]);
                    } else {
                        $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                    }
                }

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->exists($two_factor_selector)) {
                    $temp_text = trim($this->exts->extract($two_factor_message_selector, null, 'innerText'));
                    if (!empty($temp_text)) {
                        $this->exts->two_factor_notif_msg_en = $temp_text;
                    }
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                    $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
                    for ($t = 2; $t <= 3; $t++) {
                        $this->exts->log("Retry 2FA Message:\n" . $this->exts->two_factor_notif_msg_en);
                        $this->exts->notification_uid = "";
                        $this->exts->two_factor_attempts++;
                        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
                        if (!empty($two_factor_code)) {
                            $this->exts->log("Retry 2FA: Entering two_factor_code: " . $two_factor_code);
                            $resultCodes = str_split($two_factor_code);
                            $code_inputs = $this->exts->getElements($two_factor_selector);
                            $resultCodes = str_split($two_factor_code);
                            $code_inputs = $this->exts->querySelectorAll($two_factor_selector);
                            foreach ($code_inputs as $key => $code_input) {
                                if (array_key_exists($key, $resultCodes)) {
                                    $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                                    $this->exts->moveToElementAndType('div.otp-input-box-container input[name*="otc"]:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                                } else {
                                    $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                                }
                            }
                            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                            $this->exts->click_by_xdotool($two_factor_submit_selector);
                            sleep(10);
                            $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
                            if (!$this->exts->exists($two_factor_selector)) {
                                break;
                            }
                        } else {
                            $this->exts->log("Not received Retry two factor code");
                        }
                    }
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    } else if ($this->exts->exists('[name="transactionApprovalStatus"], form[action*="/approval/poll"]')) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");
        $message_selector = '.transaction-approval-word-break, #channelDetails, #channelDetailsWithImprovedLayout';
        $this->exts->two_factor_notif_msg_en = join(' ', $this->exts->getElementsAttribute($message_selector, 'innerText'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirmation";
        $this->exts->log($this->exts->two_factor_notif_msg_en);

        $this->exts->notification_uid = "";
        $this->exts->two_factor_attempts++;
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        // Huy added this 2023-02
        if (!empty($two_factor_code) && stripos($two_factor_code, 'OK') !== false) {
            sleep(7);
        } else {
            sleep(5 * 60);
            $this->exts->update_process_lock();
            if ($this->exts->exists('[name="transactionApprovalStatus"], form[action*="/approval/poll"]')) {
                $this->exts->two_factor_expired();
            }
        }
    }
}
private function isIncorrectCredential()
{
    $incorrect_credential_keys = [
        'Es konnte kein Konto mit dieser',
        'dass die eingegebene Nummer korrekt ist oder melde dich',
        't find an account with that',
        'Falsches Passwort',
        'password is incorrect',
        'password was incorrect',
        'Passwort war nicht korrekt',
        'Impossible de trouver un compte correspondant',
        'Votre mot de passe est incorrect',
        'Je wachtwoord is onjuist',
        'La tua password non',
        'a no es correcta'
    ];
    $error_message = $this->exts->extract('#auth-error-message-box');
    foreach ($incorrect_credential_keys as $incorrect_credential_key) {
        if (strpos(strtolower($error_message), strtolower($incorrect_credential_key)) !== false) {
            return true;
        }
    }
    return false;
}
private function processCaptcha($captcha_image_selector, $captcha_input_selector)
{
    $this->exts->log("--IMAGE CAPTCHA--");
    if ($this->exts->exists($captcha_image_selector)) {
        $image_path = $this->exts->captureElement($this->exts->process_uid, $captcha_image_selector);
        $source_image = imagecreatefrompng($image_path);
        imagejpeg($source_image, $this->exts->screen_capture_location . $this->exts->process_uid . '.jpg', 90);

        if (!empty($this->exts->config_array['captcha_shell_script'])) {
            $cmd = $this->exts->config_array['captcha_shell_script'] . " --PROCESS_UID::" . $this->exts->process_uid;
            $this->exts->log('Executing command : ' . $cmd);
            exec($cmd, $output, $return_var);
            $this->exts->log('Command Result : ' . print_r($output, true));
            $captcha_code = '';
            if (!empty($output)) {
                $output_text = $output[0];
                if (stripos($output_text, 'OK|') !== false) {
                    $captcha_code = trim(end(explode("OK|", $output_text)));
                } else if (stripos($output_text, ':') !== false) {
                    $captcha_code = trim(end(explode(":", $output_text)));
                } else {
                    $captcha_code = trim(end(explode(":", $output_text)));
                }
            }
            if ($captcha_code == '') {
                $this->exts->log("Can not get result from API");
            } else {
                $this->exts->moveToElementAndType($captcha_input_selector, $captcha_code);
                return true;
            }
        }
    } else {
        $this->exts->log("Image does not found!");
    }

    return false;
}
private function check_solve_captcha_page()
{
    $captcha_iframe_selector = '#aa-challenge-whole-page-iframe';
    $image_selector = 'img[src*="captcha"]';
    $captcha_input_selector = 'input#captchacharacters, input#aa_captcha_input, input[name="cvf_captcha_input"]';
    $captcha_submit_button = 'button[type="submit"], [name="submit_button"], [type="submit"][value="verifyCaptcha"]';
    if ($this->exts->exists($captcha_iframe_selector)) {
        $this->switchToFrame($captcha_iframe_selector);
    }
    if ($this->exts->allExists([$image_selector, $captcha_input_selector, $captcha_submit_button])) {
        $this->processCaptcha($image_selector, $captcha_input_selector);
        $this->exts->click_by_xdotool($captcha_submit_button);
        sleep(5);
        $this->exts->switchToDefault();
        if ($this->exts->exists($captcha_iframe_selector)) {
            $this->switchToFrame($captcha_iframe_selector);
        }
        if ($this->exts->allExists([$image_selector, $captcha_input_selector, $captcha_submit_button])) {
            $this->processCaptcha($image_selector, $captcha_input_selector);
            $this->exts->click_by_xdotool($captcha_submit_button);
            sleep(5);
        }
        $this->exts->switchToDefault();
        if ($this->exts->exists($captcha_iframe_selector)) {
            $this->switchToFrame($captcha_iframe_selector);
        }
        if ($this->exts->allExists([$image_selector, $captcha_input_selector, $captcha_submit_button])) {
            $this->processCaptcha($image_selector, $captcha_input_selector);
            $this->exts->click_by_xdotool($captcha_submit_button);
            sleep(5);
        }
    }
    $this->exts->switchToDefault();
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
private function captcha_required()
{
    // Supporting de, fr, en, es, it, nl language
    $captcha_required_keys = [
        'wie sie auf dem Bild erscheinen',
        'die in der Abbildung unten gezeigt werden',
        'Geben Sie die Zeichen so ein, wie sie auf dem Bild erscheinen',
        'the characters as they are shown in the image',
        'Enter the characters as they are given',
        'luego introduzca los caracteres que aparecen en la imagen',
        'Introduce los caracteres tal y como aparecen en la imagen',
        "dans l'image ci-dessous",
        "apparaissent sur l'image",
        'quindi digita i caratteri cos',
        'Inserire i caratteri cos',
        'en voer de tekens in zoals deze worden weergegeven in de afbeelding hieronder om je account',
        'Voer de tekens in die je uit veiligheidsoverwegingen moet'
    ];
    $error_message = $this->exts->extract('#auth-error-message-box, #auth-warning-message-box');
    foreach ($captcha_required_keys as $captcha_required_key) {
        if (strpos(strtolower($error_message), strtolower($captcha_required_key)) !== false) {
            return true;
        }
    }
    return false;
}
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->querySelector($this->logout_link) != null) {
            $isLoggedIn = true;
        } else {
            if ($this->exts->querySelector("a[id='nav-link-yourAccount'],div#nav-tools a#nav-link-accountList") != null) {
                $href = $this->exts->querySelector("a[id='nav-link-yourAccount'],div#nav-tools a#nav-link-accountList")->getAttribute("href");
                if (trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
                    $isLoggedIn = true;
                }
            } else if ($this->exts->querySelector("a#nav-item-signout-sa") != null) {
                $isLoggedIn = true;
            } else if ($this->exts->querySelector("div#nav-tools a#nav-link-yourAccount") != null) {
                $href = $this->exts->querySelector("div#nav-tools a#nav-link-yourAccount")->getAttribute("href");
                if (trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
                    $isLoggedIn = true;
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception);
        if ($this->exts->querySelector("div#nav-tools a#nav-link-accountList") != null) {
            $href = $this->exts->querySelector("div#nav-tools a#nav-link-accountList")->getAttribute("href");
            if (trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
                $isLoggedIn = true;
            }
        } else if ($this->exts->querySelector("a#nav-item-signout-sa") != null) {
            $isLoggedIn = true;
        } else if ($this->exts->querySelector("div#nav-tools a#nav-link-yourAccount") != null) {
            $href = $this->exts->querySelector("div#nav-tools a#nav-link-yourAccount")->getAttribute("href");
            if (trim($href) != "" && stripos($href, "/gp/css/homepage.html") !== false) {
                $isLoggedIn = true;
            }
        }
    }

    return $isLoggedIn;
}
private function accept_cookies()
{
    if ($this->exts->exists('#sp-cc-accept')) {
        $this->exts->click_by_xdotool('#sp-cc-accept');
        sleep(1);
    }
}


//*********** Microsoft Login
public $microsoft_username_selector = 'input[name="loginfmt"]';
public $microsoft_password_selector = 'input[name="passwd"]';
public $microsoft_remember_me_selector = 'input[name="KMSI"] + span';
public $microsoft_submit_login_selector = 'input[type="submit"]#idSIButton9';

public $microsoft_account_type = 0;
public $microsoft_phone_number = '';
public $microsoft_recovery_email = '';
/**
    * Entry Method thats called for a portal
    * @param Integer $count Number of times portal is retried.
    */
private function loginMicrosoftIfRequired($count = 0)
{
    $this->microsoft_phone_number = isset($this->exts->config_array["phone_number"]) ? $this->exts->config_array["phone_number"] : '';
    $this->microsoft_recovery_email = isset($this->exts->config_array["recovery_email"]) ? $this->exts->config_array["recovery_email"] : '';
    $this->microsoft_account_type = isset($this->exts->config_array["account_type"]) ? (int)@$this->exts->config_array["account_type"] : 0;

    if ($this->exts->urlContains('microsoft') || $this->exts->urlContains('live.')) {
        $this->checkFillMicrosoftLogin();
        sleep(10);
        $this->checkMicrosoftTwoFactorMethod();

        if ($this->exts->exists('input#newPassword')) {
            $this->exts->account_not_ready();
        } else if ($this->exts->querySelector('#passwordError a[href*="ResetPassword"], #passwordError a[href*="passwordreset"]') != null) {
            $this->exts->loginFailure(1);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not required microsoft login.');
        $this->exts->capture("3-no-microsoft-required");
    }
}

private function checkFillMicrosoftLogin()
{
    $this->exts->log(__FUNCTION__);
    // When open login page, sometime it show previous logged user, select login with other user.
    $this->exts->waitTillPresent('[role="listbox"] .row #otherTile[role="option"], div#otherTile', 20);
    if ($this->exts->exists('[role="listbox"] .row #otherTile[role="option"], div#otherTile')) {
        $this->exts->click_by_xdotool('[role="listbox"] .row #otherTile[role="option"], div#otherTile');
        sleep(10);
    }

    $this->exts->capture("2-microsoft-login-page");
    if ($this->exts->querySelector($this->microsoft_username_selector) != null) {
        sleep(3);
        $this->exts->log("Enter microsoft Username");
        $this->exts->moveToElementAndType($this->microsoft_username_selector, $this->username);
        sleep(1);
        $this->exts->click_by_xdotool($this->microsoft_submit_login_selector);
        sleep(10);
    }

    //Some user need to approve login after entering username on the app
    if ($this->exts->exists('div#idDiv_RemoteNGC_PollingDescription')) {
        $this->exts->two_factor_timeout = 5;
        $polling_message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->extract($polling_message_selector)));
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
            }
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
            $this->exts->two_factor_timeout = 15;
        } else {
            if ($this->exts->exists('a#idA_PWD_SwitchToPassword')) {
                $this->exts->click_by_xdotool('a#idA_PWD_SwitchToPassword');
                sleep(5);
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    if ($this->exts->exists('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]')) {
        // if site show: Already login with .. account, click logout and login with other account
        $this->exts->click_by_xdotool('a[data-bind*="href: svr.urlSwitch"][href*="/logout"]');
        sleep(10);
    }
    if ($this->exts->exists('a#mso_account_tile_link, #aadTile, #msaTile')) {
        // if site show: This Email is being used with multiple Microsoft accounts, Select depending on account_type variable
        //if account type is 1 then only personal account will be selected otherwise business account.
        if ($this->microsoft_account_type == 1) {
            $this->exts->click_by_xdotool('#msaTile');
        } else {
            $this->exts->click_by_xdotool('a#mso_account_tile_link, #aadTile');
        }
        sleep(10);
    }
    if ($this->exts->exists('form #idA_PWD_SwitchToPassword')) {
        $this->exts->click_by_xdotool('form #idA_PWD_SwitchToPassword');
        sleep(5);
    } else if ($this->exts->exists('#idA_PWD_SwitchToCredPicker')) {
        $this->exts->moveToElementAndClick('#idA_PWD_SwitchToCredPicker');
        sleep(5);
        $this->exts->moveToElementAndClick('[role="listitem"] img[src*="password"]');
        sleep(3);
    }


    if ($this->exts->querySelector($this->microsoft_password_selector) != null) {
        $this->exts->log("Enter microsoft Password");
        $this->exts->moveToElementAndType($this->microsoft_password_selector, $this->password);
        sleep(1);
        $this->exts->click_by_xdotool($this->microsoft_remember_me_selector);
        sleep(2);
        $this->exts->capture("2-microsoft-password-page-filled");
        $this->exts->click_by_xdotool($this->microsoft_submit_login_selector);
        sleep(10);
        $this->exts->capture("2-microsoft-after-submit-password");
    } else {
        $this->exts->log(__FUNCTION__ . '::microsoft Password page not found');
    }

    $this->checkConfirmMicrosoftButton();
}

private function checkFillExternalLogin()
{
    $this->exts->log(__FUNCTION__);
    if ($this->exts->urlContains('idaptive.app/login')) {
        $this->exts->capture("2-login-external-page");
        if ($this->exts->querySelector('#usernameForm:not(.hidden) input[name="username"]') != null) {
            sleep(3);
            $this->exts->log("Enter idaptive Username");
            $this->exts->moveToElementAndType('#usernameForm:not(.hidden) input[name="username"]', $this->username);
            sleep(1);
            $this->exts->click_by_xdotool('#usernameForm:not(.hidden) [type="submit"]');
            sleep(5);
        }
        if ($this->exts->querySelector('.login-form:not(.hidden) input[name="answer"][type="password"]') != null) {
            $this->exts->log("Enter idaptive Password");
            $this->exts->moveToElementAndType('.login-form:not(.hidden) input[name="answer"][type="password"]', $this->password);
            sleep(1);
            $this->exts->click_by_xdotool('.login-form:not(.hidden) [name="rememberMe"]');
            $this->exts->capture("2-login-external-filled");
            $this->exts->click_by_xdotool('.login-form:not(.hidden) [type="submit"]');
            sleep(5);
        }

        if ($this->exts->extract('#errorForm:not(.hidden) .error-message, #usernameForm:not(.hidden ) .error-message:not(.hidden )') != '') {
            $this->exts->loginFailure(1);
        }
        sleep(10);

        if ($this->exts->exists('#answerInputForm:not(.hidden) [type="submit"]')) {
            $this->exts->capture("2-login-idaptive-2fa");
            $input_selector = '#answerInputForm:not(.hidden) [type="submit"]';
            $message_selector = '#answerInputForm:not(.hidden) label';
            $remember_selector = '';
            $submit_selector = '#answerInputForm:not(.hidden) [type="submit"]';
            $this->exts->two_factor_attempts = 0;
            $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    } else if ($this->exts->urlContains('okta.com')) {
        $this->exts->capture("2-login-external-page");
        if ($this->exts->querySelector('input[name="username"],  input[name="identifier"]') != null) {
            sleep(3);
            $this->exts->log("Enter okta Username");
            $this->exts->moveToElementAndType('input[name="username"],  input[name="identifier"]', $this->username);
            sleep(1);
            $this->exts->click_by_xdotool('input[type="submit"]');
            sleep(7);
            $is_captcha = $this->solve_captcha_by_clicking(0);
            if ($is_captcha) {
                for ($i = 1; $i < 15; $i++) {
                    if ($is_captcha == false || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                        break;
                    }
                    $is_captcha = $this->solve_captcha_by_clicking($i);
                }
            }
            if ($this->exts->exists('input[type="submit"]')) {
                $this->exts->click_by_xdotool('input[type="submit"]');
            }
            sleep(7);
        }
        if ($this->exts->querySelector('input[name="password"], input[type="password"]') != null) {
            $this->exts->log("Enter okta Password");
            $this->exts->moveToElementAndType('input[name="password"], input[type="password"]', $this->password);
            sleep(1);
            $this->exts->capture("2-login-external-filled");
            $this->exts->click_by_xdotool('form input[type="submit"]');
            sleep(5);
        }

        if ($this->exts->extract('[data-se="factor-password"] .infobox-error p') != '' || $this->exts->exists('div.infobox-error')) {
            $this->exts->loginFailure(1);
        }
        sleep(10);
        if ($this->exts->exists('[data-se="factor-totp"] input[name="answer"]')) {
            $this->exts->capture("2-login-okta-2fa");
            $input_selector = '[data-se="factor-totp"] input[name="answer"]';
            $message_selector = '[data-se="factor-totp"] p.okta-form-subtitle';
            $remember_selector = '[data-se="factor-totp"] [data-se-for-name="rememberDevice"]';
            $submit_selector = '[data-se="factor-totp"] [type="submit"]';
            $this->exts->two_factor_attempts = 0;
            $this->fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    } else if ($this->exts->urlContains('onelogin.com')) {
        $this->exts->capture("2-login-external-page");
        if ($this->exts->querySelector('input[name="username"]') != null) {
            sleep(3);
            $this->exts->log("Enter onelogin Username");
            $this->exts->moveToElementAndType('input[name="username"]', $this->username);
            sleep(1);
            $this->exts->click_by_xdotool('form button[type="submit"]');
            sleep(7);
        }
        if ($this->exts->querySelector('input[name="password"]') != null) {
            $this->exts->log("Enter onelogin Password");
            $this->exts->moveToElementAndType('input[name="password"]', $this->password);
            sleep(1);
            $this->exts->capture("2-login-external-filled");
            $this->exts->click_by_xdotool('form button[type="submit"]');
            // sleep(5);
        }

        $this->exts->waitTillPresent('[type="error"]');
        sleep(1);
        if ($this->exts->extract('[type="error"]') != '') {
            $this->exts->loginFailure(1);
        }
        sleep(5);
    } else if ($this->exts->urlContains('remerge.io')) {
        $this->exts->capture("2-login-external-page");
        if ($this->exts->querySelector('span[class*="okta-form-input-field"] input[name="identifier"]') != null) {
            sleep(3);
            $this->exts->log("Enter onelogin Username");
            $this->exts->moveToElementAndType('span[class*="okta-form-input-field"] input[name="identifier"]', $this->username);
            sleep(1);
            $this->exts->click_by_xdotool('main#okta-sign-in form input[type="submit"]');
            sleep(7);
        }

        if ($this->exts->querySelector('span[class*="okta-form-input-field"] input[name="credentials.passcode"]') != null) {
            $this->exts->log("Enter onelogin Password");
            $this->exts->moveToElementAndType('span[class*="okta-form-input-field"] input[name="credentials.passcode"]', $this->password);
            sleep(1);
            $this->exts->capture("2-login-external-filled");
            $this->exts->click_by_xdotool('main#okta-sign-in form input[type="submit"]');
            // sleep(5);
        }

        $this->exts->waitTillPresent('.okta-form-infobox-error.infobox.infobox-error p');
        sleep(1);
        if ($this->exts->extract('.okta-form-infobox-error.infobox.infobox-error p') != '') {
            $this->exts->loginFailure(1);
        }
        sleep(5);
    }
}

private function solve_captcha_by_clicking($count = 1)
{
    $this->exts->log("Checking captcha");
    $language_code = '';
    $this->exts->waitTillPresent('div[style*="visibility: visible;"] iframe[title="recaptcha challenge expires in two minutes"]', 20);
    $recaptcha_challenger_wraper_selector = 'div[style*="visibility: visible;"] iframe[title="recaptcha challenge expires in two minutes"]';
    if ($this->exts->exists($recaptcha_challenger_wraper_selector)) {
        // Check if challenge images hasn't showed yet, Click checkbox to show images challenge

        $this->exts->capture("tesla-captcha");

        $captcha_instruction = '';

        //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
        $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
        sleep(5);
        $captcha_wraper_selector = 'div[style*="visibility: visible;"] iframe[title="recaptcha challenge expires in two minutes"]';

        if ($this->exts->exists($captcha_wraper_selector)) {
            $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


            // if($coordinates == '' || count($coordinates) < 2){
            //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
            // }
            if ($coordinates != '') {
                // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                foreach ($coordinates as $coordinate) {
                    $this->click_hcaptcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                }

                $this->exts->capture("tesla-captcha-selected " . $count);
                $this->exts->makeFrameExecutable('iframe[title="recaptcha challenge expires in two minutes"]')->click_element('button[id="recaptcha-verify-button"]');
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
                        $coordinates[] = ['x' => (int)$matches[1], 'y' => (int)$matches[2]];
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


private function fillTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector)
{
    $two_factor_selector = $input_selector;
    $two_factor_message_selector =  $message_selector;
    $two_factor_remember_selector_selector = $remember_selector;
    $two_factor_submit_selector = $submit_selector;
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

            if ($this->exts->exists($two_factor_remember_selector_selector)) {
                $this->exts->click_by_xdotool($two_factor_remember_selector_selector);
                sleep(1);
            }

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

private function checkConfirmMicrosoftButton()
{
    // After submit password, It have many button can be showed, check and click it
    if ($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"], input#idSIButton9[aria-describedby="KmsiDescription"]')) {
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->click_by_xdotool('form input[name="DontShowAgain"] + span');
        sleep(3);
        $this->exts->click_by_xdotool('form[action*="/kmsi"] input#idSIButton9, input#idSIButton9[aria-describedby="KmsiDescription"]');
        sleep(10);
    }
    if ($this->exts->exists('input#btnAskLater')) {
        $this->exts->click_by_xdotool('input#btnAskLater');
        sleep(10);
    }
    if ($this->exts->exists('a[data-bind*=SkipMfaRegistration]')) {
        $this->exts->click_by_xdotool('a[data-bind*=SkipMfaRegistration]');
        sleep(10);
    }
    if ($this->exts->exists('input#idSIButton9[aria-describedby="KmsiDescription"]')) {
        $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby="KmsiDescription"]');
        sleep(10);
    }
    if ($this->exts->exists('input#idSIButton9[aria-describedby*="landingDescription"]')) {
        $this->exts->click_by_xdotool('input#idSIButton9[aria-describedby*="landingDescription"]');
        sleep(3);
    }
    if ($this->exts->querySelector("#verifySetup a#verifySetupCancel") != null) {
        $this->exts->click_by_xdotool("#verifySetup a#verifySetupCancel");
        sleep(10);
    }
    if ($this->exts->querySelector('#authenticatorIntro a#iCancel') != null) {
        $this->exts->click_by_xdotool('#authenticatorIntro a#iCancel');
        sleep(10);
    }
    if ($this->exts->querySelector("input#iLooksGood") != null) {
        $this->exts->click_by_xdotool("input#iLooksGood");
        sleep(10);
    }
    if ($this->exts->exists("input#StartAction") && !$this->exts->urlContains('/Abuse?')) {
        $this->exts->click_by_xdotool("input#StartAction");
        sleep(10);
    }
    if ($this->exts->querySelector(".recoveryCancelPageContainer input#iLandingViewAction") != null) {
        $this->exts->click_by_xdotool(".recoveryCancelPageContainer input#iLandingViewAction");
        sleep(10);
    }
    if ($this->exts->querySelector("input#idSubmit_ProofUp_Redirect") != null) {
        $this->exts->click_by_xdotool("input#idSubmit_ProofUp_Redirect");
        sleep(10);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__11')) {
        // Great job! Your security information has been successfully set up. Click "Done" to continue login.
        $this->exts->click_by_xdotool(' #id__11');
        sleep(10);
    }
    if ($this->exts->querySelector('div input#iNext') != null) {
        $this->exts->click_by_xdotool('div input#iNext');
        sleep(10);
    }
    if ($this->exts->querySelector('input[value="Continue"]') != null) {
        $this->exts->click_by_xdotool('input[value="Continue"]');
        sleep(10);
    }
    if ($this->exts->querySelector('form[action="/kmsi"] input#idSIButton9') != null) {
        $this->exts->click_by_xdotool('form[action="/kmsi"] input#idSIButton9');
        sleep(10);
    }
    if ($this->exts->querySelector('a#CancelLinkButton') != null) {
        $this->exts->click_by_xdotool('a#CancelLinkButton');
        sleep(10);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__7')) {
        // Confirm your info.
        $this->exts->click_by_xdotool(' #id__7');
        sleep(10);
    }
    if ($this->exts->urlContains('mysignins.microsoft.com/register') && $this->exts->exists('#id__11')) {
        // Great job! Your security information has been successfully set up. Click "Done" to continue login.
        $this->exts->click_by_xdotool(' #id__11');
        sleep(10);
    }
    if ($this->exts->exists('form[action*="/kmsi"] input[name="DontShowAgain"]')) {
        // if site show: Do this to reduce the number of times you are asked to sign in. Click yes
        $this->exts->click_by_xdotool('form[action*="/kmsi"] input[name="DontShowAgain"] + span');
        sleep(3);
        $this->exts->click_by_xdotool('form[action*="/kmsi"] input#idSIButton9');
        sleep(10);
    }
}

private function checkMicrosoftTwoFactorMethod()
{
    // Currently we met 4 two factor methods
    // - Email
    // - Text Message
    // - Approve request in Microsoft Authenticator app
    // - Use verification code from mobile app
    $this->exts->log(__FUNCTION__);
    sleep(5);
    $this->exts->capture("2.0-microsoft-two-factor-checking");
    // STEP 1: Check if list of two factor methods showed, select first
    if ($this->exts->exists('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]')) {
        $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs_Section #idDiv_SAOTCS_Proofs [role="option"]');
        sleep(10);
    } else if ($this->exts->exists('#iProofList input[name="proof"]')) {
        $this->exts->click_by_xdotool('#iProofList input[name="proof"]');
        sleep(10);
    } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"]')) {
        // Updated 11-2020
        if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]')) { // phone SMS
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="OneWaySMS"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]')) { // phone SMS
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="3:"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]')) { // Email 
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value^="1:"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]')) {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppOTP"]');
        } else if ($this->exts->exists('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]')) {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"] [data-value="PhoneAppNotification"]');
        } else {
            $this->exts->click_by_xdotool('#idDiv_SAOTCS_Proofs [role="listitem"]');
        }
        sleep(5);
    }

    // STEP 2: (Optional)
    if ($this->exts->exists('#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc')) {
        // If method is click some number on Microsoft Authenticator app, send 2FA to ask user to do click it
        $message_selector = '#idDiv_RemoteNGC_PollingDescription, #idRemoteNGC_DisplaySign, .confirmIdentityPageControl #iPollSessionDesc';
        $this->exts->two_factor_notif_msg_en = trim(join("\n", $this->exts->extract($message_selector)));
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";

        $this->exts->two_factor_attempts = 2;
        $this->fillMicrosoftTwoFactor('', '', '', '');
    } else if ($this->exts->exists('[data-bind*="Type.TOTPAuthenticatorV2"]')) {
        // If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
        // Then wait. If not success, click to select two factor by code from mobile app
        $input_selector = '';
        $message_selector = 'div#idDiv_SAOTCAS_Description';
        $remember_selector = 'label#idLbl_SAOTCAS_TD_Cb';
        $submit_selector = '';
        $this->exts->two_factor_notif_msg_en = $this->exts->extract($message_selector, null, 'innerText');
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->exts->two_factor_attempts = 2;
        $this->exts->two_factor_timeout = 5;
        $this->fillMicrosoftTwoFactor('', '', $remember_selector, $submit_selector);
        // sleep(30);

        if ($this->exts->exists('a#idA_SAASTO_TOTP')) {
            $this->exts->click_by_xdotool('a#idA_SAASTO_TOTP');
            sleep(5);
        }
    } else if ($this->exts->exists('input[value="TwoWayVoiceOffice"]') && $this->exts->exists('div#idDiv_SAOTCC_Description')) {
        // If method is Microsoft Authenticator app: send 2FA to ask user approve on Microsoft app.
        // Then wait. If not success, click to select two factor by code from mobile app
        $input_selector = '';
        $message_selector = 'div#idDiv_SAOTCC_Description';
        $this->exts->two_factor_notif_msg_en = $this->exts->extract($message_selector, null, 'innerText');
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "\n>>>Enter \"OK\" after confirming on device";
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->exts->two_factor_attempts = 2;
        $this->exts->two_factor_timeout = 5;
        $this->fillMicrosoftTwoFactor('', '', '', '');
    } else if ($this->exts->exists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])')) {
        // If method is email code or phone code, This site may be ask for confirm phone/email first, So send 2FA to ask user phone/email
        $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="email"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="email"]:not([type="hidden"])';
        $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
        $remember_selector = '';
        $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"], input[type="submit"]';
        $this->exts->two_factor_attempts = 1;
        if ($this->microsoft_recovery_email != '' && filter_var($this->recovery_email, FILTER_VALIDATE_EMAIL) !== false) {
            $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
            sleep(1);
            $this->exts->click_by_xdotool($submit_selector);
            sleep(10);
        } else {
            $this->exts->two_factor_attempts = 1;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    } else if ($this->exts->exists('input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])')) {
        // If method is phone code, This site may be ask for confirm phone first, So send 2FA to ask user phone
        $input_selector = 'input[name="ProofConfirmation"]:not([type="hidden"]), .confirmIdentityPageControl [id^="iProof"][style*="display: block"] >  input[type="tel"][name^="iProof"], .confirmIdentityPageControl [id^="iProof"][style*="display: table"] >  input[name^="iProof"][type="tel"]:not([type="hidden"]), #iProofPhoneEntry:not([aria-hidden="true"]) input[name^="iProof"][type="tel"]:not([type="hidden"])';
        $message_selector = '#idDiv_SAOTCS_ProofConfirmationDesc, #iAdditionalProofInfo #iEnterProofDesc, #iAdditionalProofInfo #iEnterProofDesc ~ * #iConfirmProofEmailDomain';
        $remember_selector = '';
        $submit_selector = 'input#idSubmit_SAOTCS_SendCode, input#iSelectProofAction[type="submit"], input[type="submit"]';
        $this->exts->two_factor_attempts = 1;
        if ($this->phone_number != '' && is_numeric(trim(substr($this->phone_number,  -1, 4)))) {
            $last4digit = substr($this->phone_number, -1, 4);
            $this->exts->moveToElementAndType($input_selector, $last4digit);
            sleep(3);
            $this->exts->click_by_xdotool($submit_selector);
            sleep(10);
        } else {
            $this->exts->two_factor_attempts = 1;
            $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
        }
    }

    // STEP 3: input code
    if ($this->exts->exists('input[name="otc"], input[name="iOttText"]')) {
        $input_selector = 'input[name="otc"], input[name="iOttText"]';
        $message_selector = 'div#idDiv_SAOTCC_Description, .OTTLabel, #idDiv_SAOTCC_Description, span#otcDesc';
        $remember_selector = 'label#idLbl_SAOTCC_TD_Cb';
        $submit_selector = 'input#idSubmit_SAOTCC_Continue, input#iVerifyCodeAction, input[type="submit"]';
        $this->exts->two_factor_attempts = 0;
        $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
    }
}

private function fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector)
{
    $this->exts->log(__FUNCTION__);
    $this->exts->log("microsoft Two factor page found.");
    $this->exts->capture("2.1-microsoft-two-factor-page");
    $this->exts->log($message_selector);
    if ($this->exts->querySelector($message_selector) != null) {
        $this->exts->two_factor_notif_msg_en = join("\n", $this->exts->extract($message_selector));
        $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    }
    $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (!empty($two_factor_code) && trim($two_factor_code) != '') {
        if ($this->exts->querySelector($input_selector) != null) {
            $this->exts->log("microsoftfillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(2);
            if ($this->exts->exists($remember_selector)) {
                $this->exts->click_by_xdotool($remember_selector);
            }
            $this->exts->capture("2.2-microsoft-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->exists($submit_selector)) {
                $this->exts->log("microsoftfillTwoFactor: Clicking submit button.");
                $this->exts->click_by_xdotool($submit_selector);
            }
            sleep(15);

            if ($this->exts->querySelector($input_selector) == null) {
                $this->exts->log("microsoftTwo factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->fillMicrosoftTwoFactor($input_selector, $message_selector, $remember_selector, $submit_selector);
            } else {
                $this->exts->log("microsoft Two factor can not solved");
            }
        } else {
            $this->exts->log("Not found microsoft two factor input");
        }
    } else {
        $this->exts->log("Not received microsoft two factor code");
    }
}
//*********** END Microsoft Login

// -------------------- GOOGLE login
public $google_username_selector = 'input[name="identifier"]';
public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
public $google_solved_rejected_browser = false;
private function loginGoogleIfRequired()
{
    if ($this->exts->urlContains('google.')) {
        $this->checkFillGoogleLogin();
        sleep(10);
        $this->check_solve_rejected_browser();

        if ($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null) {
            $this->exts->loginFailure(1);
        }

        if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
            sleep(10);
        }
        // Click next if confirm form showed
        $this->exts->click_by_xdotool('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
        $this->checkGoogleTwoFactorMethod();
        sleep(10);
        if ($this->exts->exists('#smsauth-interstitial-remindbutton')) {
            $this->exts->click_by_xdotool('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if ($this->exts->exists('#tos_form input#accept')) {
            $this->exts->click_by_xdotool('#tos_form input#accept');
            sleep(10);
        }
        if ($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')) {
            $this->exts->click_by_xdotool('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')) {
            // SKIP setup 2FA
            $this->exts->click_by_xdotool('.action-button.signin-button');
            sleep(10);
        }
        if ($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')) {
            $this->exts->click_by_xdotool('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if ($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')) {
            $this->exts->click_by_xdotool('input[name="later"]');
            sleep(7);
        }
        if ($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')) {
            $this->exts->click_by_xdotool('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if ($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')) {
            $this->exts->click_by_xdotool('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }
        if ($this->exts->urlContains('gds.google.com/web/chip')) {
            $this->exts->click_by_xdotool('[role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if ($this->exts->exists('form #approve_button[name="submit_true"]')) {
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->click_by_xdotool('form #approve_button[name="submit_true"]');
            sleep(10);
        }


        $this->exts->log('URL before back to main tab: ' . $this->exts->getUrl());
        $this->exts->capture("google-login-before-back-maintab");
        if (
            $this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[aria-invalid="true"][name="Passwd"]') != null
        ) {
            $this->exts->loginFailure(1);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not required google login.');
        $this->exts->capture("3-no-google-required");
    }
}
private function checkFillGoogleLogin()
{
    if ($this->exts->exists('[data-view-id*="signInChooserView"] li [data-identifier]')) {
        $this->exts->click_by_xdotool('[data-view-id*="signInChooserView"] li [data-identifier]');
        sleep(10);
    } else if ($this->exts->exists('form li [role="link"][data-identifier]')) {
        $this->exts->click_by_xdotool('form li [role="link"][data-identifier]');
        sleep(10);
    }
    if ($this->exts->exists('form [data-profileindex]')) {
        $this->exts->click_by_xdotool('form [data-profileindex]');
        sleep(5);
    }
    $this->exts->capture("2-google-login-page");
    if ($this->exts->querySelector($this->google_username_selector) != null) {
        // $this->fake_user_agent();
        // $this->exts->refresh();
        // sleep(5);

        $this->exts->log("Enter Google Username");
        $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
        sleep(1);
        $this->exts->click_by_xdotool($this->google_submit_username_selector);
        sleep(7);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)) {
            $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
            sleep(1);
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
        }

        // Which account do you want to use?
        if ($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->exts->querySelector($this->google_password_selector) != null) {
        $this->exts->log("Enter Google Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);

        if ($this->exts->exists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
        }

        $this->exts->capture("2-google-password-filled");
        $this->exts->click_by_xdotool($this->google_submit_password_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
            $this->exts->capture("2-login-google-pageandcaptcha-filled");
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(10);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
                $this->exts->capture("2-login-google-pageandcaptcha-filled");
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::google Password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}
private function check_solve_rejected_browser()
{
    $this->exts->log(__FUNCTION__);
    $root_user_agent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:105.0) Gecko/20100101 Firefox/105.0');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:105.0) Gecko/20100101 Firefox/105.0');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12.6; rv:105.0) Gecko/20100101 Firefox/105.0');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/605.1.15');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) {
        $this->overwrite_user_agent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36 OPR/90.0.4480.107');
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->exts->capture("2-login-alternative-page");
        $this->checkFillLogin_undetected_mode();
    }

    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        $this->overwrite_user_agent($root_user_agent);
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        $this->overwrite_user_agent($root_user_agent);
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
    if ($this->exts->urlContains('/deniedsigninrejected') || $this->exts->urlContains('/rejected?')) { // If all above failed, using DN user agent as last solution
        if ($this->exts->urlContains('/v3/')) {
            $this->exts->click_by_xdotool('a[href*="/restart"]');
        } else {
            $this->exts->refresh();
        }
        sleep(7);
        $this->checkFillLogin_undetected_mode($root_user_agent);
    }
}
private function overwrite_user_agent($user_agent_string = 'DN')
{
    $userAgentScript = "
(function() {
if ('userAgentData' in navigator) {
    navigator.userAgentData.getHighEntropyValues({}).then(() => {
        Object.defineProperty(navigator, 'userAgent', { 
            value: '{$user_agent_string}', 
            configurable: true 
        });
    });
} else {
    Object.defineProperty(navigator, 'userAgent', { 
        value: '{$user_agent_string}', 
        configurable: true 
    });
}
})();
";
    $this->exts->execute_javascript($userAgentScript);
}

private function checkFillLogin_undetected_mode($root_user_agent = '')
{
    if ($this->exts->exists('form [data-profileindex]')) {
        $this->exts->click_by_xdotool('form [data-profileindex]');
        sleep(5);
    } else if ($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)) {
        $this->exts->capture("2-google-verify-it-you");
        // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
        $this->exts->click_by_xdotool($this->google_submit_username_selector);
        sleep(5);
    }

    $this->exts->capture("2-google-login-page");
    if ($this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
        if (!empty($root_user_agent)) {
            $this->overwrite_user_agent('DN'); // using DN (DONT KNOW) user agent, last solution
        }
        $this->exts->type_key_by_xdotool("F5");
        sleep(5);
        $current_useragent = $this->exts->evaluate('(function() { return navigator.userAgent; })();');

        $this->exts->log('current_useragent: ' . $current_useragent);
        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->google_username_selector);
        $this->exts->click_by_xdotool($this->google_username_selector);
        $this->exts->type_key_by_xdotool("ctrl+a");
        $this->exts->type_key_by_xdotool("Delete");
        $this->exts->type_text_by_xdotool($this->username);
        sleep(1);
        $this->exts->capture_by_chromedevtool("2-google-username-filled");
        $this->exts->click_by_xdotool($this->google_submit_username_selector);
        sleep(7);
        if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            $this->exts->click_by_xdotool($this->google_submit_username_selector);
            sleep(5);
            if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
            }
            if ($this->exts->check_exist_by_chromedevtool('#captchaimg[src]') && !$this->exts->check_exist_by_chromedevtool($this->google_password_selector) && $this->exts->check_exist_by_chromedevtool($this->google_username_selector)) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->click_by_xdotool($this->google_submit_username_selector);
                sleep(5);
            }
        }

        if (!empty($root_user_agent)) { // If using DN user agent, we must revert back to root user agent before continue
            $this->overwrite_user_agent($root_user_agent);
            if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
                $this->exts->type_key_by_xdotool("F5");
                sleep(3);
                $this->exts->type_key_by_xdotool("F5");
                sleep(3);
                $this->exts->type_key_by_xdotool("F5");
                sleep(6);
                $this->exts->capture_by_chromedevtool("2-google-login-reverted-UA");
            }
        }

        // Which account do you want to use?
        if ($this->exts->check_exist_by_chromedevtool('form[action*="/lookup"] button.account-chooser-button')) {
            $this->exts->click_by_xdotool('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if ($this->exts->check_exist_by_chromedevtool('[data-view-id="prbTle"] form [role="link"][data-profileindex]')) {
            $this->exts->click_by_xdotool('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }

    if ($this->exts->check_exist_by_chromedevtool($this->google_password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);
        if ($this->exts->exists('#captchaimg[src]')) {
            $this->exts->processCaptcha('#captchaimg[src]', '#captchaimg[src] ~ * input[type="text"]');
        }

        $this->exts->capture("2-google-password-filled");
        $this->exts->click_by_xdotool($this->google_submit_password_selector);
        sleep(5);
        if ($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)) {
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if ($this->exts->exists('#captchaimg[src]')) {
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->click_by_xdotool($this->google_submit_password_selector);
            sleep(5);
            if ($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)) {
                $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
                sleep(1);
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->capture("2-lgoogle-ogin-pageandcaptcha-filled");
                $this->exts->click_by_xdotool($this->google_submit_password_selector);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}
private function checkGoogleTwoFactorMethod()
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
    $this->exts->capture("2.0-before-check-two-factor");
    // STEP 0 (updated special case 28-Mar-2020): If we meet a unsolvable, click to back to method choosen list
    if ($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')) {
        $this->exts->click_by_xdotool('#assistActionId');
        sleep(5);
    } else if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
        // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
        if ($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false) {
            $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
        }
    } else if ($this->exts->urlContains('/sk/webauthn') || $this->exts->urlContains('/challenge/pk')) {
        // CURRENTLY THIS CASE CAN NOT BE SOLVED
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get clean'");
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get -y update'");
        exec("sudo docker exec " . $node_name . " bash -c 'sudo apt-get install -y xdotool'");
        exec("sudo docker exec " . $node_name . " bash -c 'xdotool key Return'");
        sleep(3);
        $this->exts->capture("2.0-cancel-security-usb");
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->click_by_xdotool('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
    } else if ($this->exts->exists('input[name="ootpPin"]')) {
        // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->click_by_xdotool('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(7);
    } else if ($this->exts->urlContains('/challenge/') && !$this->exts->urlContains('/challenge/pwd') && !$this->exts->urlContains('/challenge/totp')) { // totp is authenticator app code method
        // if this is not password form AND this is two factor form BUT it is not Authenticator app code method, back to selection list anyway in order to choose Authenticator app method if available
        $supporting_languages = [
            "Try another way",
            "Andere Option w",
            "Essayer une autre m",
            "Probeer het op een andere manier",
            "Probar otra manera",
            "Prova un altro metodo"
        ];
        $back_button_xpath = '//*[contains(text(), "Try another way") or contains(text(), "Andere Option w") or contains(text(), "Essayer une autre m")';
        $back_button_xpath = $back_button_xpath . ' or contains(text(), "Probeer het op een andere manier") or contains(text(), "Probar otra manera") or contains(text(), "Prova un altro metodo")';
        $back_button_xpath = $back_button_xpath . ']/..';
        $back_button = $this->exts->getElement($back_button_xpath, null, 'xpath');
        if ($back_button != null) {
            try {
                $this->exts->log(__FUNCTION__ . ' back to method list to find Authenticator app.');
                $this->exts->execute_javascript("arguments[0].click();", [$back_button]);
            } catch (\Exception $exception) {
                $this->exts->execute_javascript("arguments[0].click()", [$back_button]);
            }
        }
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list");
    }

    // STEP 1: Check if list of two factor methods showed, select first
    if ($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')) {
        $this->exts->capture("2.1-2FA-method-list");
        // Updated 03-2023 since we setup sub-system to get authenticator code without request to end-user. So from now, We priority for code from Authenticator app top 1, sms code or email code 2st, then other methods
        if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND TOP 1 method type = 6 is get code from Google Authenticator
            $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != '') {
            $this->exts->click_by_xdotool('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != '') {
            $this->exts->click_by_xdotool('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="1"]:not([data-challengeunavailable="true"])')) {
            // Select enter your passowrd, if only option is passkey
            $this->exts->click_by_xdotool('li [data-challengetype="1"]:not([data-challengeunavailable="true"])');
            sleep(3);
            $this->checkFillGoogleLogin();
            sleep(3);
            $this->checkGoogleTwoFactorMethod();
        } else if ($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')) {
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->click_by_xdotool('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])') && (isset($this->security_phone_number) && $this->security_phone_number != '')) {
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->click_by_xdotool('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"][data-challengeid="12"]:not([data-challengeunavailable="true"])')) {
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->click_by_xdotool('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')) {
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->click_by_xdotool('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if ($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')) {
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
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
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
            $this->exts->type_key_by_xdotool('Return');
            sleep(5);
        }
        if ($this->exts->exists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
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
            $this->exts->type_key_by_xdotool('Return');
            sleep(7);
        }
        if ($this->exts->exists($input_selector)) {
            $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }

    // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if ($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')) {
        // Sometime user must confirm before google send sms
        $this->exts->click_by_xdotool('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if ($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')) {
        $this->exts->click_by_xdotool('[data-view-id] #authzenNext');
        sleep(10);
    } else if ($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')) {
        $this->exts->click_by_xdotool('#idvpreregisteredemailNext');
        sleep(10);
    } else if (count($this->exts->querySelectorAll('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0) {
        $this->exts->click_by_xdotool('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }


    // STEP 4: input code
    if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId')) {
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin, input#idvPin, input#idvPinId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext';
        $this->exts->two_factor_attempts = 3;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')) {
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if ($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')) {
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'text')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
    } else if ($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')) {
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->fillGoogleTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionIdk
    } else if ($this->exts->exists('input[name="secretQuestionResponse"]')) {
        $input_selector = 'input[name="secretQuestionResponse"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
        $this->exts->two_factor_attempts = 0;
        $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
    }
}
private function fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter = false)
{
    $this->exts->log(__FUNCTION__);
    $this->exts->log("Two factor page found.");
    $this->exts->capture("2.1-two-factor");

    if ($this->exts->querySelector($message_selector) != null) {
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'text'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        if ($this->exts->two_factor_attempts > 1) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
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
            $this->exts->log("fillTwoFactor: Entering two_factor_code: " . $two_factor_code);
            $this->exts->moveToElementAndType($input_selector, '');
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(2);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            if ($this->exts->exists($submit_selector)) {
                $this->exts->log("fillTwoFactor: Clicking submit button.");
                $this->exts->click_by_xdotool($submit_selector);
            } else if ($submit_by_enter) {
                $this->exts->type_key_by_xdotool('Return');
            }
            sleep(10);
            $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
            if ($this->exts->querySelector($input_selector) == null) {
                $this->exts->log("Two factor solved");
            } else {
                if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
                    $this->exts->two_factor_attempts++;
                    if ($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')) {
                        // if(strpos(strtoupper($this->exts->extract('div:last-child[style*="visibility: visible;"] [role="button"]')), 'CODE') !== false){
                        $this->exts->click_by_xdotool('[aria-relevant="additions"] + [style*="visibility: visible;"] [role="button"]');
                        sleep(2);
                        $this->exts->capture("2.2-two-factor-resend-code-" . $this->exts->two_factor_attempts);
                        // }
                    }

                    $this->fillGoogleTwoFactor($input_selector, $message_selector, $submit_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            }
        } else {
            $this->exts->log("Not found two factor input");
        }
    } else {
        $this->exts->log("Not received two factor code");
    }
}
// -------------------- GOOGLE login END