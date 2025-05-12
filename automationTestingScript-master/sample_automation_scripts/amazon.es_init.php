public $baseUrl = "https://www.amazon.es";
public $orderPageUrl = "https://www.amazon.es/gp/css/order-history/ref=nav_youraccount_orders";
public $messagePageUrl = "https://www.amazon.es/gp/message?e=UTF8&cl=1&ref_=ya_d_l_msg_center#!/inbox";
public $businessPrimeUrl = "https://www.amazon.es/businessprimeversand";
public $loginLinkPrim = "div[id=\"nav-flyout-ya-signin\"] a";
public $loginLinkSec = "div[id=\"nav-signin-tooltip\"] a";
public $loginLinkThr = "div#nav-tools a#nav-link-yourAccount";
public $username_selector = 'input[autocomplete="username"]';
public $password_selector = "#ap_password";
public $submit_button_selector = "#signInSubmit";
public $continue_button_selector = "#continue";
public $logout_link = 'a#nav-item-signout, #nav-main a[href*="/sign-out.html"]';
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
public $invalid_filename_keywords = array('agb', 'terms', 'datenschutz', 'privacy', 'rechnungsbeilage', 'informationsblatt', 'gesetzliche', 'retouren', 'widerruf', 'allgemeine gesch', 'mfb-buchung', 'informationen zu zahlung', 'nachvertragliche', 'retourenschein', 'allgemeine_gesch', 'rcklieferschein');
public $invalid_filename_pattern = '';
public $isNoInvoice = true;
public $check_login_failed_selector = 'div#auth-error-message-box div.a-alert-content';

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
        $this->only_years = isset($this->exts->config_array["only_years"]) ? $this->exts->config_array["only_years"] : '';
        $this->auto_tagging = isset($this->exts->config_array["auto_tagging"]) ? $this->exts->config_array["auto_tagging"] : '';
        $this->marketplace_invoice_tags = isset($this->exts->config_array["marketplace_invoice_tags"]) ? $this->exts->config_array["marketplace_invoice_tags"] : '';
        $this->order_overview_tags = isset($this->exts->config_array["order_overview_tags"]) ? $this->exts->config_array["order_overview_tags"] : '';
        $this->amazon_invoice_tags = isset($this->exts->config_array["amazon_invoice_tags"]) ? $this->exts->config_array["amazon_invoice_tags"] : '';
        $this->start_page = isset($this->exts->config_array["start_page"]) ? $this->exts->config_array["start_page"] : '';
        $this->last_invoice_date = isset($this->exts->config_array["last_invoice_date"]) ? $this->exts->config_array["last_invoice_date"] : '';
        $this->procurment_report = isset($this->exts->config_array["procurment_report"]) ? (int)$this->exts->config_array["procurment_report"] : 0;


        $this->exts->log('amazon_download_overview ' . $this->amazon_download_overview);
        $this->exts->log('download_invoice_from_message ' . $this->download_invoice_from_message);
        $this->exts->log('auto_request_invoice ' . $this->auto_request_invoice);
        $this->exts->log('only_years ' . $this->only_years);
        $this->exts->log('auto_tagging ' . $this->auto_tagging);
        $this->exts->log('marketplace_invoice_tags ' . $this->marketplace_invoice_tags);
        $this->exts->log('order_overview_tags ' . $this->order_overview_tags);
        $this->exts->log('amazon_invoice_tags ' . $this->amazon_invoice_tags);
        $this->exts->log('start_page ' . $this->start_page);
        $this->exts->log('last_invoice_date ' . $this->last_invoice_date);
        $this->exts->log('procurment_report ' . $this->procurment_report);


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
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        sleep(2);

        $this->exts->openUrl($this->baseUrl);
        sleep(5);
        $this->exts->capture("Home-page-with-cookie");

        $this->exts->openUrl($this->orderPageUrl);
        sleep(5);

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        }
    }

    if (!$isCookieLoginSuccess) {
        if ($this->exts->querySelector($this->loginLinkThr) != null) {
            $this->exts->log("Found Third Login Link!!");
            $this->exts->click_element($this->loginLinkThr);
        } else if ($this->exts->querySelector($this->loginLinkSec) != null) {
            $this->exts->log("Found Secondry Login Link!!");
            $this->exts->click_element($this->loginLinkSec);
        } else if ($this->exts->querySelector($this->loginLinkPrim) != null) {
            $this->exts->log("Found Primary Login Link!!");
            $this->exts->click_element($this->loginLinkPrim);
        } else {
            $this->exts->openUrl($this->orderPageUrl);
        }
        sleep(5);

        $this->fillForm(0);
        sleep(20);

        if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
            $this->fillForm(0);
            sleep(20);
        }

        if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
            $this->fillForm(0);
            sleep(20);
        }
    }

    if (!$isCookieLoginSuccess) {
        if ($this->checkLogin()) {
            $this->exts->openUrl($this->orderPageUrl);
            sleep(5);

            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

            $this->exts->success();
        } else {
            // Captcha and Two Factor Check
            if ($this->checkCaptcha() || stripos($this->exts->getUrl(), "/ap/cvf/request") !== false) {
                $this->processImageCaptcha();
            }

            sleep(5);
            if ($this->checkLogin()) {
                $this->exts->openUrl($this->orderPageUrl);
                sleep(5);
                $this->exts->capture("LoginSuccess");

                if (!empty($this->exts->config_array['allow_login_success_request'])) {
                    $this->exts->triggerLoginSuccess();
                }
                $this->exts->success();
            } else {
                $this->exts->log(__FUNCTION__ . '::Use login failed');
                $this->exts->log('::URL login failure:: ' . $this->exts->getUrl());
                $this->exts->capture("LoginFailed");

                $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
                $emailFailed = strtolower($this->exts->extract('div#auth-email-invalid-claim-alert div.a-alert-content'));

                $this->exts->log(__FUNCTION__ . '::Email Failed text: ' . $emailFailed);
                $this->exts->log(__FUNCTION__ . '::error text: ' . $error_text);
                if (
                    stripos($emailFailed, strtolower('La dirección de correo electrónico o el número de teléfono móvil faltan o son inválidos. Corríjalo e inténtelo de nuevo.')) !== false ||
                    stripos($emailFailed, strtolower('The email address or mobile phone number is missing or invalid. Please correct it and try again.')) !== false
                ) {
                    $this->exts->loginFailure(1);
                } elseif (
                    stripos($error_text, strtolower('La contraseña no es correcta')) !== false ||
                    stripos($error_text, strtolower('The password is not correct')) !== false  ||
                    stripos($error_text, strtolower('El código que ha introducido no es válido. Vuelva a intentarlo.')) !== false ||
                    stripos($error_text, strtolower('The code you entered is invalid. Please try again.')) !== false
                )
                    $this->exts->loginFailure(1);
                else {
                    $this->exts->loginFailure();
                }
            }
        }
    } else {
        $this->exts->openUrl($this->orderPageUrl);
        sleep(5);
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
            $this->exts->triggerLoginSuccess();
        }

        $this->exts->success();
    }
}

/**
    * Method to fill login form
    * @param Integer $count Number of times portal is retried.
    */
function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->log("Begin fillForm URL - " . $this->exts->getUrl());

    try {
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

        if ($this->login_tryout == 0) {
            if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->username_selector) != null) {
                $this->exts->capture("1-pre-login");
                $formType = $this->exts->querySelector($this->password_selector);
                if ($formType == null) {
                    $this->exts->log("Form with Username Only");
                    $this->exts->log("Enter Username");
                    $this->exts->moveToElementAndType($this->username_selector, $this->username);
                    sleep(2);

                    $this->exts->log("Username form button click");
                    $this->exts->moveToElementAndClick($this->continue_button_selector);
                    sleep(5);

                    if ($this->exts->exists('#ap_captcha_guess, #auth-captcha-guess')) {
                        $this->exts->processCaptcha('#auth-captcha-image-container img', '#ap_captcha_guess, #auth-captcha-guess');
                        sleep(15);
                    } else if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
                        $this->exts->processCaptcha('[action="/errors/validateCaptcha"] img', '#captchacharacters');
                        sleep(2);
                        $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                        sleep(15);
                    } else if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
                        $this->exts->processCaptcha('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img', '#ap_captcha_guess, #auth-captcha-guess, #captchacharacters');
                        sleep(2);
                        $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                        sleep(15);
                    } else if ($this->exts->exists('div#auth-error-message-box')) {
                        $this->exts->loginFailure(1);
                    }

                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);

                    if ($this->exts->querySelector($this->remember_me) != null) {
                        $checkboxElements = $this->exts->querySelectorAll($this->remember_me);
                        if (count($checkboxElements) > 0) {
                            $this->exts->log("Check remeber me");
                            $this->exts->click_element($checkboxElements[0]);
                        }
                    }

                    if ($this->exts->exists('#ap_captcha_guess, #auth-captcha-guess')) {
                        $this->exts->processCaptcha('#auth-captcha-image-container img', '#ap_captcha_guess, #auth-captcha-guess');
                        sleep(15);
                    } else if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
                        $this->exts->processCaptcha('[action="/errors/validateCaptcha"] img', '#captchacharacters');
                        sleep(2);
                        $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                        sleep(15);
                    } else if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
                        $this->exts->processCaptcha('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img', '#ap_captcha_guess, #auth-captcha-guess, #captchacharacters');
                        sleep(2);
                        $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                        sleep(15);
                    }

                    $this->exts->capture("1-filled-login");
                    $this->exts->click_by_xdotool($this->submit_button_selector);
                } else {
                    if ($this->exts->querySelector($this->remember_me) != null) {
                        $checkboxElements = $this->exts->querySelectorAll($this->remember_me);
                        if (count($checkboxElements) > 0) {
                            $this->exts->log("Check remeber me");
                            $this->exts->click_element($checkboxElements[0]);
                        }
                    }

                    if ($this->exts->querySelector($this->username_selector) != null && $this->exts->querySelector("input#ap_email[type=\"hidden\"]") == null) {
                        $this->exts->log("Enter Username");
                        $this->exts->querySelector($this->username_selector, $this->username);
                        sleep(2);
                    }

                    if ($this->exts->exists('#ap_captcha_guess, #auth-captcha-guess')) {
                        $this->exts->processCaptcha('#auth-captcha-image-container img', '#ap_captcha_guess, #auth-captcha-guess');
                        sleep(15);
                    } else if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
                        $this->exts->processCaptcha('[action="/errors/validateCaptcha"] img', '#captchacharacters');
                        sleep(2);
                        $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                        sleep(15);
                    } else if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
                        $this->exts->processCaptcha('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img', '#ap_captcha_guess, #auth-captcha-guess, #captchacharacters');
                        sleep(2);
                        $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                        sleep(15);
                    }

                    if ($this->exts->querySelector($this->password_selector) != null) {
                        $this->exts->log("Enter Password");
                        $this->exts->moveToElementAndType($this->password_selector, $this->password);
                        sleep(2);
                    }

                    if ($this->exts->exists('#ap_captcha_guess, #auth-captcha-guess')) {
                        $this->exts->processCaptcha('#auth-captcha-image-container img', '#ap_captcha_guess, #auth-captcha-guess');
                        sleep(15);
                    } else if ($this->exts->exists('[action="/errors/validateCaptcha"] img')) {
                        $this->exts->processCaptcha('[action="/errors/validateCaptcha"] img', '#captchacharacters');
                        sleep(2);
                        $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                        sleep(15);
                    } else if ($this->exts->exists('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img')) {
                        $this->exts->processCaptcha('#auth-captcha-image-container img, [action="/errors/validateCaptcha"] img', '#ap_captcha_guess, #auth-captcha-guess, #captchacharacters');
                        sleep(2);
                        $this->exts->click_by_xdotool('[action="/errors/validateCaptcha"] [type=submit]');
                        sleep(15);
                    }

                    $this->exts->capture("2-filled-login");
                    $this->exts->click_by_xdotool($this->submit_button_selector);
                }
                sleep(6);
            }

            if ($this->exts->exists('form[action="verify"] input#continue')) {
                $this->exts->click_by_xdotool('form[action="verify"] input#continue');
                sleep(15);

                if ($this->exts->exists('input[name="code"]')) {
                    $this->checkFillTwoFactor('input[name="code"]', 'form[action="verify"] span[class*="verify"] [type="submit"]', 'form[action="verify"] div.a-row.a-spacing-none');
                } else if ($this->exts->exists('#auth-mfa-otpcode')) {
                    $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'div.a-row.a-spacing-none');
                }
            }

            $this->exts->log("END fillForm URL - " . $this->exts->getUrl());
        }
        if ($this->exts->exists('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
            $this->exts->click_by_xdotool('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
            sleep(15);
        }

        if ($this->exts->exists('div#auth-error-message-box div.a-alert-content')) {
            $this->exts->loginFailure(1);
        }

        $this->exts->capture('after-click-login');

        if ($this->exts->exists('input[name="verifyToken"]')) {
            $this->exts->click_by_xdotool('input[name="verifyToken"] ~ div input#continue');
            sleep(15);

            if ($this->exts->exists('input[name="code"]')) {
                $this->checkFillTwoFactor('input[name="code"]', 'form[action="verify"] span[class*="verify"] [type="submit"]', 'form[action="verify"] div.a-row.a-spacing-none');
            } else if ($this->exts->exists('#auth-mfa-otpcode')) {
                $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'div.a-row.a-spacing-none');
            } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
                $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
            } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
                $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
            }
        } else if ($this->exts->exists('[action="verify"]') && $this->exts->exists('[name*="dcq_question_date_picker"]')) {
            $this->exts->log('Two factor auth required - security question');
            $this->checkFillAnswerSerQuestion('[name*="dcq_question_date_picker"]', '[value="verify"]', '[action="verify"] .a-form-label');
        } else if ($this->exts->exists('input[name="transactionApprovalStatus"]')) {
            $this->checkFillTwoFactorWithPushNotify('div.a-section.a-spacing-large span.a-text-bold, div#channelDetails');
        } else if ($this->exts->exists('div#tiv-message + form[action="verify"]')) {
            $this->checkFillTwoFactorWithPushNotify('div#tiv-message');
        } else if ($this->exts->exists('input[name="otpCode"]')) {
            $this->checkFillTwoFactor('input[name="otpCode"]', 'input[name="mfaSubmit"]', 'form#auth-mfa-form div.a-box-inner > h1, form#auth-mfa-form div.a-box-inner > p');
        } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]')) {
            $this->exts->click_by_xdotool('div[data-a-input-name="otpDeviceContext"] input[value*="OTP"]:not(:checked)');
            sleep(2);
            $this->exts->click_by_xdotool('input#auth-send-code');
            sleep(15);

            $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
        } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]')) {
            $this->exts->click_by_xdotool('div[data-a-input-name="otpDeviceContext"] input[value*="SMS"]:not(:checked)');
            sleep(2);
            $this->exts->click_by_xdotool('input#auth-send-code');
            sleep(15);

            $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
        } else if ($this->exts->exists('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]')) {
            $this->exts->click_by_xdotool('div[data-a-input-name="otpDeviceContext"] input[value*="VOICE"]:not(:checked)');
            sleep(2);
            $this->exts->click_by_xdotool('input#auth-send-code');
            sleep(15);

            $this->checkFillTwoFactor('#auth-mfa-otpcode', '#auth-signin-button', 'form#auth-mfa-form div.a-box-inner > h1 ~ p');
        }

        if ($this->exts->urlContains('forgotpassword/reverification')) {
            $this->exts->account_not_ready();
        }

        if ($this->exts->exists('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link')) {
            $this->exts->click_by_xdotool('form#auth-account-fixup-phone-form a#ap-account-fixup-phone-skip-link');
            sleep(15);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Catch fillForm URL - " . $this->exts->getUrl());
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector)
{
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $total_2fa = count($this->exts->querySelectorAll($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < $total_2fa; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
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

            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

private function checkFillAnswerSerQuestion($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector)
{
    if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $total_2fa = count($this->exts->querySelectorAll($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < $total_2fa; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
            }
            $this->exts->two_factor_notif_msg_en = 'Please enter answer of below question (MM/YYYY): ' . trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = 'Bitte geben Sie die Antwort der folgenden Frage ein (MM/YYYY): ' . $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $month = trim(explode('/', $two_factor_code)[0]);
            $year = trim(end(explode('/', $two_factor_code)));
            $this->exts->moveToElementAndType('[name="dcq_question_date_picker_1_1"]', $month);
            $this->exts->moveToElementAndType('[name="dcq_question_date_picker_1_2"]', $year);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->click_by_xdotool($two_factor_submit_selector);
            sleep(15);

            if ($this->exts->querySelector($two_factor_selector) == null) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillTwoFactor($two_factor_selector, $two_factor_submit_selector, $two_factor_message_selector);
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

private function checkFillTwoFactorWithPushNotify($two_factor_message_selector)
{
    if ($this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->querySelector($two_factor_message_selector) != null) {
            $total_2fa = count($this->exts->querySelectorAll($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < $total_2fa; $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . 'Please input "OK" after responded email/approve notification!';
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en . 'Please input "OK" after responded email/approve notification!';;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '' && strtolower($two_factor_code) == 'ok') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            sleep(15);

            if ($this->exts->querySelector($two_factor_message_selector) == null && !$this->exts->exists('input[name="transactionApprovalStatus"]')) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = '';
                $this->checkFillTwoFactorWithPushNotify($two_factor_message_selector);
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

/**
    * Method to check captcha form
    * return boolean true/false
    */
public function checkCaptcha()
{
    $this->exts->capture("check-captcha");

    $isCaptchaFound = false;
    if ($this->exts->querySelector("input#ap_captcha_guess") != null || $this->exts->querySelector("input#auth-captcha-guess") != null) {
        $this->login_tryout = (int)$this->login_tryout + 1;
        $isCaptchaFound = true;
    }

    return $isCaptchaFound;
}

/**
    * Method to check Two Factor form
    * return boolean true/false
    */
public function checkMultiFactorAuth()
{
    $this->exts->capture("check-two-factor");

    $isTwoFactorFound = false;
    if ($this->exts->querySelector("form#auth-mfa-form") != null) {
        $isTwoFactorFound = true;
    } else if ($this->exts->querySelector("form.cvf-widget-form[action=\"verify\"]") != null) {
        $isTwoFactorFound = true;
    }

    return $isTwoFactorFound;
}

/**
    * Method to Check where user is logged in or not
    * return boolean true/false
    */
public function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        if ($this->exts->querySelector($this->logout_link) != null) {
            // $this->exts->waitForCssSelectorPresent($this->logout_link, function() {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            // 	$isLoggedIn = true;
            // }, function() {
            // 	$isLoggedIn = false;
            // }, 30);
            return true;
        } else {
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

/**
    * Method to Process Image Catcha and Password field if present
    */
public function processImageCaptcha()
{
    $this->exts->log("Processing Image Captcha");
    if ($this->exts->querySelector($this->password_selector) != null) {
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);
    }
    $this->exts->processCaptcha("form[name=\"signIn\"]", "form[name=\"signIn\"] input[name=\"guess\"]");
    sleep(2);

    $this->exts->capture("filled-captcha");
    $this->exts->click_element($this->submit_button_selector);
    sleep(2);
}