public $baseUrl = "https://www.orange.fr/portail";
public $loginUrl = "https://login.orange.fr/?return_url=https://www.orange.fr/portail";
public $homePageUrl = "https://id.orange.fr/auth_user/bin/auth_user.cgi?return_url=http://www.orange.fr";
public $username_selector = "input#login";
public $password_selector = "input#password";
public $submit_button_selector = "button#btnSubmit";
public $login_tryout = 0;
public $twofa_form_selector = "input#smsCode";
public     $month_names_fr = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');

public $captcha_form_selector = 'div[class*="captcha_images"]'; //'[id="captchaRow"] form[name="captcha-form"]';
public $captcha_image_selector = 'div#__next > div > div:nth-child(3) > div:nth-child(2) > ul > li';
public $captcha_submit_btn_selector = 'button.sc-gKseQn'; // 'button.sc-gKsewC';
public $captcha_indications_selector = 'ul.sc-uya65w-0.eatJrZ li';
public $captca_solution_tried = 0;

public $isNoInvoice = true;


/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->exts->openUrl($this->baseUrl);
    sleep(10);

    $user_agent = $this->exts->executeSafeScript('return navigator.userAgent;');
    $this->exts->log('user_agent: ' . $user_agent);

    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    //This is not needed because profile get loaded without calling any function

    if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
        $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
        sleep(10);
    }

    if ($this->checkLogin()) {
        $isCookieLoginSuccess = true;
    } else {
        $this->clearChrome();
        sleep(1);
        $this->exts->openUrl($this->loginUrl);
        sleep(10);

        if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
            sleep(10);
        }

        for ($i = 0; $i < 10; $i++) {
            if ($this->exts->urlContains('/error403.html?status=error') || $this->exts->urlContains('error403.html?ref=idme-ssr&status=error')) {
                $this->clearChrome();
                sleep(1);

                $this->exts->openUrl($this->loginUrl);
                sleep(10);

                if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
                    $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
                    sleep(10);
                }
                sleep(20);
            } else break;
        }

        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists($this->captcha_form_selector)) {
                $this->clearChrome();
                sleep(1);
                $this->exts->openUrl($this->loginUrl);
                sleep(10);
            } else {
                break;
            }
        }

        if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
            sleep(10);
        }
    }

    $this->exts->capture('before-fill-form');

    if (!$isCookieLoginSuccess) {
        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists($this->captcha_form_selector)) {
                $this->clearChrome();
                sleep(1);
                $this->exts->openUrl($this->loginUrl);
                sleep(10);
            } else {
                break;
            }
        }

        if ($this->exts->exists('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]')) {
            $this->exts->moveToElementAndClick('button#didomi-notice-agree-button, button[data-oevent-action="non-merci"]');
            sleep(10);
        }

        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->exists($this->captcha_form_selector)) {
                $this->clearChrome();
                sleep(1);
                $this->exts->openUrl($this->loginUrl);
                sleep(10);
            } else {
                break;
            }
        }

        sleep(20);

        if (!$this->exts->exists($this->username_selector)) {
            $this->exts->openUrl($this->loginUrl);
        }

        $this->fillForm(0);
        sleep(10);
        for ($i = 0; $i < 3 && $this->exts->exists('div#alert-sessionExpired:not([style="display: none;"]) button[data-testid="button-reload"]'); $i++) {
            $this->exts->moveToElementAndClick('div#alert-sessionExpired:not([style="display: none;"]) button[data-testid="button-reload"]');
            $this->clearChrome();
            sleep(1);
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            $this->fillForm(0);
            sleep(10);
        }


        $this->checkFillTwoFactor();

        if ($this->exts->exists('button[data-testid="submit-mc"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="submit-mc"]');
            sleep(3);
            $this->checkFillTwoFactorForMobileAcc();
        }

        if ($this->exts->exists('button[data-testid="link-mc-later"]')) {
            $this->exts->moveToElementAndClick('button[data-testid="link-mc-later"]');
            sleep(20);
        }

        $err_txt1 = "";
        if ($this->exts->getElement("h6#error-msg-box") != null) {
            $err_txt1 = $this->exts->getElement("h6#error-msg-box")->getAttribute('innerText');
        }

        $err_txt2 = "";
        if ($this->exts->getElement("span#default_password_error, label#password-invalid-feedback, label#login-invalid-feedback") != null) {
            $err_txt1 = $this->exts->getElement("span#default_password_error, label#password-invalid-feedback, label#login-invalid-feedback")->getAttribute('innerText');
        }

        if (($err_txt1 != "" && $err_txt1 != null) || ($err_txt2 != "" && $err_txt2 != null)) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        }

        $err_msg1 = $this->exts->extract('div [class="alert-container alert-container-sm alert-danger invalid-feedback"]  label[data-testid="input-password-invalid-feedback"]');
        $lowercase_err_msg = strtolower($err_msg1);
        $substrings = array('vérifiez l’adresse mail et le mot de passe saisis.', 'vérifiez', 'passe');
        foreach ($substrings as $substring) {
            if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                $this->exts->log($err_msg1);
                $this->exts->loginFailure(1);
                break;
            }
        }

        if (strpos($this->exts->getUrl(), '/changePassword') !== false) {
            $this->exts->log('Your current password is not secure enough and needs to be strengthened. ');
            $this->exts->capture('new_password!');
            $this->exts->account_not_ready();
        }

        if ($this->exts->getElement('input#new-password') != null && $this->exts->getElement('input#new-password')->isDisplayed()) {
            $this->exts->log('User must update new password');
            $this->exts->capture('User must update new password');
            $this->exts->account_not_ready();
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if (!empty($this->exts->config_array['allow_login_success_request'])) {
                $this->exts->triggerLoginSuccess();
            }

        } else {
            if ($this->exts->urlContains('recovery/error')) {
                $this->exts->account_not_ready();
            }
            if ($this->exts->exists('p#password-error-title-error')) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->urlContains('/renforcer-mot-de-passe')) {
                $this->exts->account_not_ready();
            } elseif ($this->exts->urlContains('mdp/choice/default')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {

            $this->exts->triggerLoginSuccess();
        }
    }
}

public $isLoginByCookie = false;
function fillForm($count)
{
    $this->exts->capture("1-pre-login");
    $this->exts->log("Begin fillForm " . $count);
    try {
        sleep(5);
        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;

            if ($this->exts->exists($this->username_selector)) {
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
            }

            $this->exts->moveToElementAndClick($this->submit_button_selector);
            sleep(10);

            if ($this->exts->getElementByText('div#login-error', ['cette adresse e-mail', 'Cette adresse mail ou ce numéro de mobile n’est pas valide. Vérifiez votre saisie'], null, false) != null) {
                $this->exts->loginFailure(1);
            }

            if ($this->exts->exists('a[data-testid="footerlink-authent-pwd"], button[data-testid="footerlink-authent-pwd"]')) {
                $this->exts->moveToElementAndClick('a[data-testid="footerlink-authent-pwd"], button[data-testid="footerlink-authent-pwd"]');
                sleep(16);
            }

            $this->exts->moveToElementAndClick('div#choice-form a[href*="otc"]');
            sleep(10);

            $this->checkFillTwoFactor();

            if ($this->exts->getElement('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password') != null) {
                $this->exts->moveToElementAndClick('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password');
                sleep(15);
            }

            if ($this->exts->getElement($this->password_selector) != null) {
                if ($this->exts->getElement($this->password_selector) != null) {
                    $this->exts->log("Enter Password");
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(5);
                }

                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);

                if ($this->exts->exists($this->password_selector)) {
                    $this->exts->moveToElementAndType($this->password_selector, $this->password);
                    sleep(2);

                    $this->exts->moveToElementAndClick($this->submit_button_selector);

                    sleep(10);
                }
            } else if ($this->exts->getElement("button#btnSubmit") && strpos($this->exts->getUrl(), "/keep-connected") !== false) {
                $this->isLoginByCookie = true;
                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);
            } else {
                $temp = "";
                if ($this->exts->getElement("h6#error-msg-box-login") != null) {
                    $temp = $this->exts->getElement("h6#error-msg-box-login")->getAttribute('innerText');
                }

                if ($temp != "" && $temp != null) {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure(1);
                }
            }
        } else if ($this->exts->exists($this->password_selector)) {
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->moveToElementAndClick($this->submit_button_selector);

            sleep(10);

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);
            }
        } else if ($this->exts->getElement("button#btnSubmit") && strpos($this->exts->getUrl(), "/keep-connected") !== false) {
            $this->isLoginByCookie = true;
            $this->exts->moveToElementAndClick($this->submit_button_selector);

            sleep(10);
        } else if ($this->exts->exists('a[data-testid="footerlink-authent-pwd"]')) {
            $this->exts->moveToElementAndClick('a[data-testid="footerlink-authent-pwd"]');
            sleep(15);

            $this->exts->moveToElementAndClick('div#choice-form a[href*="otc"]');
            sleep(10);

            $this->checkFillTwoFactor();

            if ($this->exts->getElement('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password') != null) {
                $this->exts->moveToElementAndClick('div#authWithoutMCDiv:not([style*="display: none"]) > a.link-action-password');
                sleep(15);
            }

            $this->exts->log('enter password');
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->moveToElementAndClick($this->submit_button_selector);

            sleep(10);

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->moveToElementAndClick($this->submit_button_selector);

                sleep(10);
            }
        } else {
            $temp = $this->exts->extract('h6#error-msg-box-login');
            if ($temp != "" && $temp != null) {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure(1);
            }
        }

        sleep(10);

        if ($this->exts->exists('div.promoteMC-container a#btnLater')) {
            $this->exts->moveToElementAndClick('div.promoteMC-container a#btnLater');
            sleep(15);
        }

        if ($this->exts->exists('a[data-oevent-action="clic_lien_plus_tard"]')) {
            $this->exts->moveToElementAndClick('a[data-oevent-action="clic_lien_plus_tard"]');
            sleep(14);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
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

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}
function getInnerTextByJS($selector_or_object, $parent = null)
{
    if ($selector_or_object == null) {
        $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
        return;
    }
    $element = $selector_or_object;
    if (is_string($selector_or_object)) {
        $element = $this->exts->getElement($selector_or_object, $parent);
        if ($element == null) {
            $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
        }
        if ($element == null) {
            $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
        }
    }
    if ($element != null) {
        return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
    }
}
function solveClickCaptcha()
{
    $this->exts->log("Start solving click captcha:");
    if ($this->exts->exists($this->captcha_form_selector)) {
        $this->exts->capture("solveClickCaptcha");
        $retry_count = 0;
        while ($retry_count < 5) {
            // $indications = str_replace("?", " ", $this->exts->extract($this->captcha_indications_selector, null, 'innerText'));
            $indicationsArray = array();
            $indications_sel = $this->exts->getElements('ol[class*="timeline-captcha"] li', null, 'css');
            foreach ($indications_sel as $key => $indication_sel) {
                $temp = $this->getInnerTextByJS($indication_sel);
                $temp = trim($temp);
                $this->exts->log($temp);
                array_push($indicationsArray, $temp);
            }
            $hcaptcha_challenger_wraper_selector = 'div[class*="captcha_images"]';
            $translatedIndication = "";
            foreach ($indicationsArray as $key => $indication) {
                $translatedIndication = $translatedIndication . ($key + 1) . '-' . $this->getTranslatedClickCaptchaInstruction($indication) . '.';
            }
            $this->exts->log("translatedIndications " . $translatedIndication);
            $captcha_instruction = "Click on the image in this order." . $translatedIndication;
            $coordinates = $this->exts->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true); // use $language_code and $captcha_instruction if they changed captcha content
            $call_2captcha_retry = 0;
            while (($coordinates == '' || count($coordinates) != 6) && $call_2captcha_retry < 5) {
                $coordinates = $this->exts->processClickCaptcha($hcaptcha_challenger_wraper_selector, $captcha_instruction, '', $json_result = true);
                $call_2captcha_retry++;
            }
            if ($coordinates != '') {
                $challenge_wraper = $this->exts->getElement($hcaptcha_challenger_wraper_selector);
                if ($challenge_wraper != null) {
                    foreach ($coordinates as $coordinate) {
                        $this->exts->log('Clicking X/Y: ' . $coordinate['x'] . '/' . $coordinate['y']);
                        $this->exts->click_by_xdotool($challenge_wraper, intval($coordinate['x']), intval($coordinate['y']));
                        sleep(2);
                    }
                    $this->exts->capture("After captcha clicked.");
                }
            }
            $retry_count++;
            $this->captca_solution_tried++;

            $this->exts->capture('after-click-all-images');

            if ($this->exts->exists('div.justify-content-sm-start button[type="button"]')) {
                $this->exts->moveToElementAndClick('div.justify-content-sm-start button[type="button"]');
                sleep(15);
            }

            $this->exts->capture('after-solve-clickcaptcha');
            if (!$this->exts->exists($this->captcha_form_selector)) {
                $this->exts->log("Captcha solved!!!!!! About to continue process...");
                break;
            } else {
                $this->exts->log("Captcha not solved!!!!!! Refresh to retry...");
                $this->exts->refresh();
                sleep(10);
                if (!$this->exts->exists($this->captcha_form_selector)) {
                    break;
                }
            }
        }
    } else {
        $this->exts->log("Captcha not found!!!!!!");
    }
}

function getTranslatedClickCaptchaInstruction($originalInstruction)
{
    $result = null;
    try {
        $this->exts->open_new_window();
        sleep(1);
        $originalInstruction = preg_replace("/\r\n|\r|\n/", '%0A', $originalInstruction);
        $this->exts->log('originalInstruction: ' . $originalInstruction);
        $this->exts->openUrl('https://translate.google.com/?sl=fr&tl=en&text=' . $originalInstruction . '&op=translate');
        sleep(3);

        $acceptBtn = $this->exts->getElementByText('button', ['Agree to the use of cookies', 'Accept all'], null, false);
        if ($acceptBtn != null) {
            $acceptBtn->click();
            sleep(12);
            $this->exts->close_new_window();
            $this->exts->open_new_window();
            sleep(1);
            $this->exts->openUrl('https://translate.google.com/?sl=fr&tl=en&text=' . $originalInstruction . '&op=translate');
            sleep(3);
        }
        // sleep(10);
        $result = $this->getInnerTextByJS($this->exts->getElement('div c-wiz:nth-child(2) span[lang="en"] > span > span'));
        $result = str_replace('%0A', "\n", $result);
        $this->exts->close_new_window();
    } catch (\Exception $ex) {
        $this->exts->log("Failed to get translated instruction");
    }

    return $result;
}


private function checkFillTwoFactorForMobileAcc()
{
    $this->exts->log('start checkFillTwoFactorForMobileAcc');
    $two_factor_selector = '';
    $two_factor_message_selector = 'span.icon-Internet-security-mobile + div';
    $two_factor_submit_selector = '';

    if ($this->exts->getElement($two_factor_message_selector) != null && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->getElement($two_factor_message_selector) != null) {
            $this->exts->two_factor_notif_msg_en = "";
            for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
            }
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
            if ($this->exts->getElement($two_factor_message_selector) == null && !$this->exts->exists('button[data-testid="btn-mc-error"]')) {
                $this->exts->log("Two factor solved");
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                if ($this->exts->exists('button[data-testid="btn-mc-error"]')) {
                    $this->exts->moveToElementAndClick('button[data-testid="btn-mc-error"]');
                    sleep(3);
                }
                $this->checkFillTwoFactorForMobileAcc();
            } else {
                $this->exts->log("Two factor can not solved");
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input#otc-input, input#otc';
    $two_factor_message_selector = '#otc-form #otcLabel, #otc-form #helpCard, form h3 + p';
    $two_factor_submit_selector = '#otc-form #btnSubmit, button[data-testid="submit-otc"]';

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

function processTFA_SMS()
{
    try {
        $this->exts->log("Current URL - " . $this->exts->getUrl());

        if ($this->exts->getElement($this->twofa_form_selector) != null) {
            $this->handleTwoFactorCode($this->twofa_form_selector, "form#otpForm button[type=\"submit\"]");
            sleep(5);
        }
        if ($this->exts->getElement($this->twofa_form_selector) != null) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception process TFA " . $exception->getMessage());
    }
}

function processTFA_NUM($contractId)
{
    try {
        $this->exts->log("Current URL - " . $this->exts->getUrl());

        $two_factor_selector = "div.contratCalcule_" . $contractId . " input[name=\"clientReference\"]";
        $submit_btn_selector = "div[class=\"buttons contratCalcule contratCalcule_" . $contractId . "\"] button[type=\"submit\"]";
        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->handleTwoFactorCode1($two_factor_selector, $submit_btn_selector, $contractId);
            sleep(5);
        }
        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception process TFA " . $exception->getMessage());
    }
}

function handleTwoFactorCode1($two_factor_selector, $submit_btn_selector, $contractId)
{
    if ($this->exts->two_factor_attempts == 2) {
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
    }

    if ($this->exts->two_factor_attempts == 1) {
        if ($this->exts->getElement("p[class=\"ec_description contratCalcule contratCalcule_" . $contractId . "\"]") != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement("p[class=\"ec_description contratCalcule contratCalcule_" . $contractId . "\"]")->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = trim($this->exts->getElement("p[class=\"ec_description contratCalcule contratCalcule_" . $contractId . "\"]")->getAttribute('innerText'));
        }
    }

    $this->exts->log($this->exts->two_factor_notif_msg_en);

    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
        try {
            $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
            $this->exts->click_by_xdotool($two_factor_selector);
            $this->exts->type_text_by_xdotool($two_factor_code);
            sleep(1);
            $this->exts->moveToElementAndClick($submit_btn_selector);
            sleep(10);

            if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";
                $this->handleTwoFactorCode($two_factor_selector, $submit_btn_selector);
            }
        } catch (\Exception $exception) {
            $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
        }
    }
}

function handleTwoFactorCode($two_factor_selector, $submit_btn_selector)
{
    if ($this->exts->two_factor_attempts == 2) {
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
    }

    if ($this->exts->two_factor_attempts == 1) {
        if ($this->exts->getElement("div.addContractFormContent p.ec_form_line + div") != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement("div.addContractFormContent p.ec_form_line + div")->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = trim($this->exts->getElement("div.addContractFormContent p.ec_form_line + div")->getAttribute('innerText'));
        }

        if ($this->exts->getElement("div.addContractFormContent p.ec_form_line ~label[for=\"smsCode\"]") != null) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . trim($this->exts->getElement("div.addContractFormContent p.ec_form_line ~label[for=\"smsCode\"]")->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . trim($this->exts->getElement("div.addContractFormContent p.ec_form_line ~label[for=\"smsCode\"]")->getAttribute('innerText'));
        }
    }

    $this->exts->log($this->exts->two_factor_notif_msg_en);

    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
        try {
            $this->exts->log("SIGNIN_PAGE: Entering two_factor_code.");
            $this->exts->click_by_xdotool($two_factor_selector);
            $this->exts->type_text_by_xdotool($two_factor_code);

            $this->exts->log("SIGNIN_PAGE: Clicking the [SIGN_IN] button.");
            if ($this->exts->getElement($submit_btn_selector) != null && $this->exts->getElement($submit_btn_selector)->isEnabled()) {
                $this->exts->getElement($submit_btn_selector)->click();
                sleep(10);
            } else {
                sleep(5);
                $this->exts->getElement($submit_btn_selector)->click();
                sleep(10);
            }

            if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";
                $this->handleTwoFactorCode($two_factor_selector, $submit_btn_selector);
            }
        } catch (\Exception $exception) {
            $this->exts->log('processTwoFactorAuth::ERROR while taking snapshot');
        }
    }
}

/**
 * Method to Check where user is logged in or not
 * return boolean true/false
 */
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    sleep(10);
    try {
        if ($this->exts->execute_javascript("document.querySelector('header#o-header elcos-header').shadowRoot.querySelector('button[data-oevent-action=\"espaceclient\"]') != null") == true || $this->exts->exists('a[href*="deconnect"]')) {
            $isLoggedIn = true;
        }
    } catch (Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

function downloadDirectAgain($invoice, $count)
{
    $this->exts->log("Download again invoice: " . $invoice['receiptName']);

    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
        $this->exts->log("create file");
        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
    } else {
        $count += 1;
        if ($count < 10) {
            $this->downloadDirectAgain($invoice, $count);
        }
    }
}
