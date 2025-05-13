<?php
// Server-Portal-ID: 6387 - Last modified: 22.01.2025 13:57:17 UTC - User: 1

public $baseUrl = "https://www.airbnb.de";
public $homePageUrl = "https://www.airbnb.de/trips/upcoming";
public $loginUrl = "https://www.airbnb.de/trips/upcoming";

public $logout_link_one = 'button#headerNavUserButton, form[action="/logout"], button[data-testid*="headernav-logout"]';

public $submit_button_selector = 'form[action="/authenticate"] button[type="submit"]';

public $username_selector = 'form[action="/authenticate"] input[name="email"], input[name="user[email]"]';
public $password_selector = 'form[action="/authenticate"] input[name="password"], input[name="user[password]"]';
public $isSecondChance = true;
public $isNoInvoice = true;
public $portal_language = '';
public $business_invoice = 0;
public $no_host_invoice = 0;
public $no_booking_invoice = 0;
public $booking_detail = 0;
public $credit_note = 0;

/******selectors for Facebook login*******/
public $facebook_username_selector = "form#login_form input[name=\"email\"]";
public $facebook_password_selector = "form#login_form input[name=\"pass\"]";
public $facebook_submit_button_selector = "form#login_form input[type=submit]";
public $facebook_new_submit_button_selector = "form#login_form button#loginbutton";
public $facebook_alt_username_selector = "form input[name=\"email\"]";
public $facebook_alt_password_selector = "form input[name=\"pass\"]";
public $facebook_alt_submit_button_selector = "form button[name=\"login\"][type=submit]";
public $facebook_continue_button_selector = "#continue";
public $user_birthday = ""; // config variable for facebook
/******selectors for Facebook login ends*******/

public $facebook_init_button_selector = '.auth-merge form[action*="facebook_login"] button[type="submit"]';

/*** Following selectors are specific to airbnb google/facebook logins - End***/

public $login_with_google = 0;
public $login_with_facebook = 0;
public $security_phone_number = '';

public $recovery_email = '';
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->security_phone_number = isset($this->exts->config_array["security_phone_number"]) ? (int)$this->exts->config_array["security_phone_number"] : '';
    $this->recovery_email = isset($this->exts->config_array["recovery_email"]) ? (int)$this->exts->config_array["recovery_email"] : '';
    $this->user_birthday = isset($this->exts->config_array["birthday"]) ? (int)@$this->exts->config_array["birthday"] : "";
    $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)$this->exts->config_array["login_with_google"] : $this->login_with_google;
    $this->login_with_facebook = isset($this->exts->config_array["login_with_facebook"]) ? (int)$this->exts->config_array["login_with_facebook"] : $this->login_with_facebook;
    $this->portal_language = isset($this->exts->config_array["portal_language"]) ? trim($this->exts->config_array["portal_language"]) : '';
    $this->business_invoice = isset($this->exts->config_array["business_invoice"]) ? (int)@$this->exts->config_array["business_invoice"] : 0;
    $this->credit_note = isset($this->exts->config_array["credit_note"]) ? (int)@$this->exts->config_array["credit_note"] : 0;
    $this->no_host_invoice = isset($this->exts->config_array["no_host_invoice"]) ? (int)@$this->exts->config_array["no_host_invoice"] : 0;
    $this->no_booking_invoice = isset($this->exts->config_array["no_booking_invoice"]) ? (int)@$this->exts->config_array["no_booking_invoice"] : 0;
    $this->booking_detail = isset($this->exts->config_array["booking_detail"]) ? (int)@$this->exts->config_array["booking_detail"] : 0;

    if (trim($this->portal_language) == "en_us") {
        $this->baseUrl = "https://www.airbnb.com";
        $this->homePageUrl = "https://www.airbnb.com/trips/upcoming";
        $this->loginUrl = "https://www.airbnb.com/trips/upcoming";
    }
    switch ($this->portal_language) {
        case 'fr':
            $this->baseUrl = "https://www.airbnb.fr";
            $this->homePageUrl = "https://www.airbnb.fr/trips/upcoming";
            $this->loginUrl = "https://www.airbnb.fr/trips/upcoming";
            break;
        case 'nl':
            $this->baseUrl = "https://www.airbnb.nl";
            $this->homePageUrl = "https://www.airbnb.nl/trips/upcoming";
            $this->loginUrl = "https://www.airbnb.nl/trips/upcoming";
            break;
        case 'es':
            $this->baseUrl = "https://www.airbnb.es";
            $this->homePageUrl = "https://www.airbnb.es/trips/upcoming";
            $this->loginUrl = "https://www.airbnb.es/trips/upcoming";
            break;
        case 'en_us':
            $this->baseUrl = "https://www.airbnb.com";
            $this->homePageUrl = "https://www.airbnb.com/trips/upcoming";
            $this->loginUrl = "https://www.airbnb.com/trips/upcoming";
            break;
        default:
            break;
    }
    $this->exts->openUrl($this->homePageUrl);
    sleep(7);

    $this->exts->capture("Home-page-without-cookie");
    $isCookieLoginSuccess = false;

    // first load cookies and see if already logged in
    if ($this->exts->loadCookiesFromFile()) {
        sleep(2);
        $this->exts->openUrl($this->homePageUrl);
        sleep(5);
        $this->exts->capture("Home-page-with-cookie");
        if ($this->exts->exists('button.tos-agree')) {
            $this->exts->click_by_xdotool('button.tos-agree');
            sleep(10);
        }
        if ($this->exts->exists('[data-testid="main-cookies-banner-container"] div:nth-child(2) > button')) {
            $this->exts->click_by_xdotool('[data-testid="main-cookies-banner-container"] div:nth-child(2) > button');
            sleep(3);
        }
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            // $this->exts->manage()->deleteAllCookies();
        }
    }

    sleep(2);

    // if cookie is not found or cookie login didn't work, proceed to login again
    if (!$isCookieLoginSuccess) {
        $this->exts->openUrl($this->loginUrl);
        $this->exts->log('URL is ' . $this->exts->getUrl());
        sleep(10);
        if ($this->login_with_google == 1) {
            $this->exts->click_by_xdotool('button[data-testid="social-auth-button-google"]');
            sleep(15);
            $google_tab = $this->exts->findTabMatchedUrl(['google']);
            if ($google_tab != null) {
                $this->exts->switchToTab($google_tab);
            }

            $this->loginGoogleIfRequired();
            sleep(5);
            $this->exts->switchToInitTab();
        } else if ($this->login_with_facebook == 1) {
            if (!$this->exts->exists('button[data-testid="social-auth-button-facebook"]')) {
                // Click to Choose login option
                $this->exts->click_by_xdotool('[data-testid="login-pane"] form[action="/authenticate"] + div > button');
                sleep(2);
            }

            $this->exts->click_by_xdotool('button[data-testid="social-auth-button-facebook"]');
            sleep(15);
            $facebook_tab = $this->exts->findTabMatchedUrl(['facebook']);
            if ($facebook_tab != null) {
                $this->exts->switchToTab($facebook_tab);
            }
            $this->processFacebookInit();
            sleep(5);
            $this->exts->switchToInitTab();
        } else {
            if ($this->exts->exists('[role="alertdialog"]:not([style*="display:none"]) button.accept-cookies-button, [data-testid="main-cookies-banner-container"] button[data-testid="accept-btn"]')) {
                $this->exts->click_by_xdotool('[role="alertdialog"]:not([style*="display:none"]) button.accept-cookies-button, [data-testid="main-cookies-banner-container"] button[data-testid="accept-btn"]');
                sleep(3);
            }
            $this->exts->click_by_xdotool('button[data-testid="social-auth-button-email"]');
            sleep(7);
            if ($this->exts->exists('[data-testid="main-cookies-banner-container"] div:nth-child(2) > button')) {
                $this->exts->click_by_xdotool('[data-testid="main-cookies-banner-container"] div:nth-child(2) > button');
                sleep(3);
            }
            $this->fillForm(0);
            $this->checkVerification(1);
        }
    } else {
        $this->exts->capture("LoginSuccess");
        $this->exts->log('LoginSuccess!!!!');
        $this->waitForLogin(0);
    }
}
function fillForm($count)
{
    $this->exts->log(__FUNCTION__ . " :: Begin " . $count);
    // $this->exts->click_by_xdotool('button[data-testid="social-auth-button-email"]');
    // sleep(3);
    if ($this->exts->querySelector($this->password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->username_selector);
        $this->exts->moveToElementAndType($this->username_selector, '');
        sleep(1);
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->log("Enter Password");
        $this->exts->click_by_xdotool($this->password_selector);
        $this->exts->moveToElementAndType($this->password_selector, '');
        sleep(1);
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(1);

        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_button_selector);
        sleep(5);
        if ($this->exts->exists('#airlock-inline-container') && !$this->exts->exists('[aria-labelledby*="user-challenges"], [role="dialog"][aria-labelledby="dls-modal__user-challenges-modal"]')) {
            $this->checkFillRecaptcha();
            $this->exts->click_by_xdotool($this->submit_button_selector);
        }
        sleep(5);
        $this->exts->capture("after-click-submit");
    } else if ($this->exts->exists($this->username_selector)) {
        sleep(3);
        $this->exts->capture("2-username-page");

        $this->exts->log("Enter Username");
        $this->exts->click_by_xdotool($this->username_selector);
        $this->exts->moveToElementAndType($this->username_selector, '');
        sleep(1);
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);
        $this->exts->click_by_xdotool('[data-testid="signup-login-submit-btn"]');
        sleep(5);
        $this->check_and_solve_challenge();
        if ($this->exts->exists("iframe[data-e2e='enforcement-frame'].show.active") && $this->exts->exists($this->username_selector)) {
            $this->switchToFrame("iframe[data-e2e='enforcement-frame'].show.active");
            $this->exts->click_by_xdotool('button[data-e2e="close-button"]');
            sleep(5);
            $this->exts->click_by_xdotool('[data-testid="signup-login-submit-btn"]');
            sleep(5);
            $this->check_and_solve_challenge();
            sleep(7);
        }

        if ($this->exts->exists($this->password_selector)) {
            $this->exts->log("Enter Password");
            $this->exts->click_by_xdotool($this->password_selector);
            $this->exts->moveToElementAndType($this->password_selector, '');
            sleep(1);
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);
            $this->exts->capture("2-password-filled");
            $this->exts->click_by_xdotool('[data-testid="signup-login-submit-btn"]');
            sleep(5);
            if ($this->exts->exists('button[data-testid="signup-login-submit-btn"][disabled]') && $this->exts->exists($this->password_selector) && !$this->exts->exists('form[data-testid="auth-form"] #g-recaptcha.show-inline-block')) {
                // maybe login take time to display recaptcha, wait more
                sleep(12);
            }
            if ($this->exts->exists($this->password_selector) && $this->exts->exists('form[data-testid="auth-form"] #g-recaptcha.show-inline-block')) {
                $this->exts->log("Re-Enter Password");
                $this->exts->click_by_xdotool($this->password_selector);
                $this->exts->moveToElementAndType($this->password_selector, '');
                sleep(1);
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                $this->checkFillRecaptcha();
            }
        } else {
            $this->exts->capture("2-password-page-not-found");
            $authen_form = count($this->exts->getElements('form[data-testid="auth-form"]'));
            if ($authen_form === 1 && $this->exts->exists('.auth-merge form[action*="google_login"] button[type="submit"], form[data-testid="auth-form"][action*="/oauth_connect?from=google_login"] [data-testid="social-auth-button-google"]')) {
                $this->exts->click_by_xdotool('.auth-merge form[action*="google_login"] button[type="submit"], form[data-testid="auth-form"][action*="/oauth_connect?from=google_login"] [data-testid="social-auth-button-google"]');
                sleep(10);
                $google_tab = $this->exts->findTabMatchedUrl(['google']);
                if ($google_tab != null) {
                    $this->exts->switchToTab($google_tab);
                }

                $this->loginGoogleIfRequired();
                sleep(5);
                $this->exts->switchToInitTab();
            } else if ($authen_form === 1 && $this->exts->exists('form[data-testid="auth-form"] button[data-testid="social-auth-button-facebook"]')) {
                $this->exts->click_by_xdotool('form[data-testid="auth-form"] button[data-testid="social-auth-button-facebook"]');
                sleep(15);

                $facebook_tab = $this->exts->findTabMatchedUrl(['facebook']);
                if ($facebook_tab != null) {
                    $this->exts->switchToTab($facebook_tab);
                }
                $this->processFacebookInit();
                sleep(5);
                $this->exts->switchToInitTab();
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
function checkVerification($count)
{
    $this->exts->log(__FUNCTION__ . " :: Begin");
    sleep(5);
    try {
        $two_factor_success = $this->checkFillTwoFactor();
        // After 2FA success, it check one again by asking recaptcha at login page
        if ($two_factor_success && $this->exts->exists($this->password_selector)) {
            // if($this->exts->exists('#airlock-inline-container') && !$this->exts->exists('[aria-labelledby*="user-challenges"], [role="dialog"][aria-labelledby="dls-modal__user-challenges-modal"]')){
            //  $this->checkFillRecaptcha();
            //  $this->exts->click_by_xdotool($this->submit_button_selector);
            // }
        }

        $login_form = $this->exts->querySelector("form.login-form");
        $radio_btn = $this->exts->querySelector('fieldset div[class*="radioButtonContainer_"]');
        $select_friction = $this->exts->querySelector('input[name="select_friction"]');
        $logout_link = $this->exts->querySelector("button#headerNavUserButton");
        $is_airlock_url = stripos($this->exts->getUrl(), "/airlock") !== false;

        $this->exts->log(__FUNCTION__ . " :: login_form - " . ($login_form != null) . " , radio_btn : " . ($radio_btn != null));
        $this->exts->log(__FUNCTION__ . " :: select_friction - " . ($select_friction != null) . " , logout_link : " . ($logout_link != null));
        $this->exts->log(__FUNCTION__ . " :: is_airlock_url : " . $is_airlock_url);
        $this->exts->log(__FUNCTION__ . " :: current URL is " . $this->exts->getUrl());

        if ($login_form == null && $radio_btn == null && $select_friction == null && $logout_link != null && !$is_airlock_url) {

            $this->exts->log(__FUNCTION__ . " No verification");

            $agree_selector = 'div.inlineBlock_36rlri button, form[action*="/users/tos_confirm"] button#submit';
            $this->exts->waitTillPresent($agree_selector, 30);
            if ($this->exts->exists($agree_selector)) {
                $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Found I agree button !!!! ");
                $button_one = $this->exts->querySelector("div.inlineBlock_36rlri button");
                if ($button_one != null) {
                    $this->exts->click_by_xdotool("div.inlineBlock_36rlri button");
                }
                $button_two = $this->exts->querySelector('form[action*="/users/tos_confirm"] button#submit');
                if ($button_two != null) {
                    $this->exts->click_by_xdotool('form[action*="/users/tos_confirm"] button#submit');
                }
            }


            $this->waitForLogin(0);
        } else {
            $count++;
            sleep(10);

            $mergeProfile = null;
            try {
                $mergeProfile = $this->exts->evaluate("(function() { return document.querySelector('.auth-merge__profile').parentElement.parentElement.innerText })();");
            } catch (\Exception $ex2) {
                $this->exts->log(__FUNCTION__ . " :: Exception when executing script for merge__profile" . $ex2);
            }

            if ($mergeProfile != null) {
                $this->exts->log(__FUNCTION__ . ' : Different account login needed!');
                $this->exts->log(__FUNCTION__ . ' : Message from site: ' . $mergeProfile);
            }
            $this->waitForLogin(1);
        }
    } catch (\Exception $ex) {
        $this->exts->log(__FUNCTION__ . " :: Exception " . $ex->getMessage());
    }

    $this->exts->log(__FUNCTION__ . " :: Ends");
}
function checkFillTwoFactor()
{
    $this->exts->capture("2.1-two-factor-checking");

    // updated 20-Nov-2020
    $select_SMS_button = $this->exts->getElement('//*[@role="dialog"]//button/div/div[last()]/div[text()="SMS"]/../../..', null);
    if ($select_SMS_button == null) {
        $select_SMS_button = $this->exts->getElement('//button/div/div[last()]/div[contains(text(), "(SMS)")]', null);
    }
    if ($select_SMS_button != null && !$this->exts->exists('label[for*="aov_select_friction"]')) {
        $this->exts->click_element($select_SMS_button);
        sleep(3);
    }
    // END updated 20-Nov-2020
    // Date: 2023-03-16
    // Author: Eddie
    // Purpose: Locate and click the first phone number button in a reliable and efficient manner

    try {
        // Use an effective XPath selector to find the first phone number button
        $firstPhoneNumberButton = $this->exts->getElement(
            '//button[.//div[contains(text(), \'+\')] and not(preceding::button[.//div[contains(text(), \'+\')]])]',
            null
        );

        // Check if the button was successfully located
        if ($firstPhoneNumberButton !== null) {
            try {
                // Click the button using the robust Selenium WebDriver approach
                $this->exts->click_element($firstPhoneNumberButton);
            } catch (Exception $clickException) {
                // Employ a JavaScript click as a backup strategy if the WebDriver method fails
                $this->exts->execute_javascript('arguments[0].click();', [$firstPhoneNumberButton]);
            }
            // Allow a short 3-second pause to accommodate any page updates following the click
            sleep(3);
        }
    } catch (Exception $e) {
        // Log errors to enable efficient debugging and prompt issue resolution
        error_log('Error: ' . $e->getMessage());
    }



    //Select email if SMS is not there
    $select_email_button = $this->exts->getElement('//*[@role="dialog"]//button/div/div[last()]/div[text()="E-Mail"]/../../..');
    if ($select_email_button == null) {
        $select_email_button = $this->exts->getElement('//button/div/div[last()]/div[contains(text(), "(E-Mail)")]');
    }
    if ($select_email_button != null && !$this->exts->exists('label[for*="aov_select_friction"]')) {
        $this->exts->click_element($select_email_button);
        sleep(3);
    }

    // select 2FA address. (phone number or email address)
    if ($this->exts->exists('label[for*="aov_select_friction"]')) {
        $this->exts->click_by_xdotool('label[for*="aov_select_friction"]');
        sleep(1);
        $this->exts->click_by_xdotool('fieldset ~ div:last-child button[type="button"] span');
        sleep(5);
        $this->exts->capture("2.1-2fa-method-selected");
    }
    // Confirm "Send" if required.
    if ($this->exts->exists('[aria-labelledby*="user-challenges"] section section > div > div >button[type="button"]') && !$this->exts->exists('[aria-labelledby*="user-challenges"] input[name^="codeinput"]')) {
        $this->exts->click_by_xdotool('[aria-labelledby*="user-challenges"] section section > div > div >button[type="button"]');
        sleep(5);
    }

    if ($this->exts->querySelector('[aria-labelledby*="user-challenges"] input[name^="codeinput"], [role="dialog"] input[name^="codeinput"], input[id*="airlock-code-input"]') != null) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor-showed");

        if ($this->exts->exists('div[data-testid="modal-container"] div[role="dialog"] section + div')) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract('div[data-testid="modal-container"] div[role="dialog"] section + div', null, 'innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        } else if ($this->exts->getElement('//div[contains(@aria-labelledby, "user-challenges")]//*[@dir="ltr"]/preceding-sibling::div[1] | //div[@role="dialog"]//*[@dir="ltr"]/preceding-sibling::div[1]') != null) {
            $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement('//div[contains(@aria-labelledby, "user-challenges")]//*[@dir="ltr"]/preceding-sibling::div[1] | //div[@role="dialog"]//*[@dir="ltr"]/preceding-sibling::div[1]')->getAttribute('innerText'));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        } else {
            if ($this->exts->getElement('/html/body/div[12]/section/div/div/div[2]/div/div[2]/div/div/div[1]') != null) {
                $this->exts->two_factor_notif_msg_en = trim($this->exts->getElement('/html/body/div[12]/section/div/div/div[2]/div/div[2]/div/div/div[1]')->getAttribute('innerText'));
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
        }

        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
        $two_factor_code = $this->exts->fetchTwoFactorCode();
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);

            $resultCodes = str_split($two_factor_code);
            $code_inputs = $this->exts->querySelectorAll('[aria-labelledby*="user-challenges"] input[name^="codeinput"], [role="dialog"] input[name^="codeinput"], input[id*="airlock-code-input"]');
            foreach ($code_inputs as $key => $code_input) {
                if (array_key_exists($key, $resultCodes)) {
                    $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #');
                    $this->exts->moveToElementAndType('[aria-labelledby*="user-challenges"] input[name^="codeinput"]:nth-child(' . ($key + 1) . '), [role="dialog"] input[name^="codeinput"]:nth-child(' . ($key + 1) . '), input[id*="airlock-code-input"]:nth-child(' . ($key + 1) . ')', $resultCodes[$key]);
                    // $code_input->sendKeys($resultCodes[$key]);
                } else {
                    $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                }
            }
            // $resultCodes = str_split($two_factor_code);
            // $code_inputs = $this->exts->getElements('[aria-labelledby*="user-challenges"] input[name^="codeinput"], [role="dialog"] input[name^="codeinput"], input[id*="airlock-code-input"]');
            // foreach ($code_inputs as $key => $code_input) {
            //     if (array_key_exists($key, $resultCodes)) {
            //         $this->exts->log('"checkFillTwoFactor: Entering key ' . $resultCodes[$key] . 'to input #' . $code_input->getAttribute('id'));
            //         $code_input->sendKeys($resultCodes[$key]);
            //         sleep(2);
            //         $this->exts->capture("2.2-two-factor-filled-" . $key . "_" . $this->exts->two_factor_attempts);
            //     } else {
            //         $this->exts->log('"checkFillTwoFactor: Have no char for input #' . $code_input->getAttribute('id'));
            //     }
            // }
            if ($this->exts->exists('[aria-labelledby*="user-challenges"] [dir="ltr"] ~ div:last-child div:last-child > button, [role="dialog"] [dir="ltr"] ~ div:last-child div:last-child > button')) {
                $this->exts->click_by_xdotool('[aria-labelledby*="user-challenges"] [dir="ltr"] ~ div:last-child div:last-child > button, [role="dialog"] [dir="ltr"] ~ div:last-child div:last-child > button');
            }
            sleep(10);
            $this->exts->capture("2.2-two-factor-submitted-" . $this->exts->two_factor_attempts);
            if ($this->exts->exists('[aria-labelledby*="user-challenges"] input[name^="codeinput"]') && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFillTwoFactor();
            } else {
                return true;
            }
        } else {
            $this->exts->log("Not received two factor code");
        }
        return false;
    }
    return true;
}
private function checkFillRecaptcha($count = 1)
{
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    $this->exts->waitTillPresent($recaptcha_iframe_selector, 20);
    if ($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);

        $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
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

            $gcallbackFunction = $this->exts->execute_javascript('
                (function() { 
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
                })();
			');
            $this->exts->log('Callback function: ' . $gcallbackFunction);
            $this->exts->log('Callback function: ' . $this->exts->recaptcha_answer);
            if ($gcallbackFunction != null) {
                $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                sleep(10);
            }
        } else {
            // try again if recaptcha expired
            if ($count < 3) {
                $count++;
                $this->checkFillRecaptcha($count);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
    }
}
function check_and_solve_challenge()
{
    // Check and solve Funcaptcha, It can require 5 pics.
    $funcaptcha_displayed = $this->processFunCaptchaByClicking();
    if ($funcaptcha_displayed) {
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
    }

    // if Step above failed, try again
    if ($funcaptcha_displayed) {
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
    }
    if ($funcaptcha_displayed) {
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
    }
    if ($funcaptcha_displayed) {
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
        $funcaptcha_displayed = $this->processFunCaptchaByClicking();
    }
    sleep(5);
}
function processFunCaptchaByClicking()
{
    $this->exts->log("Checking Funcaptcha");
    $language_code = trim($this->portal_language) == "en_us" ? 'en' : 'de';
    // if($this->exts->exists('div.funcaptcha-modal:not([class*="hidden"])')) {
    //  $this->exts->capture("funcaptcha");
    //  // Captcha located in multi layer frame, go inside
    //  if ($this->exts->exists('.funcaptcha-frame')){
    //      $this->switchToFrame('.funcaptcha-frame');
    //  }
    if ($this->exts->exists("iframe[data-e2e='enforcement-frame'].show.active")) {
        $this->switchToFrame("iframe[data-e2e='enforcement-frame'].show.active");

        if ($this->exts->exists('#fc-iframe-wrap')) {
            $this->switchToFrame('#fc-iframe-wrap');
        }

        if ($this->exts->exists('iframe[id*="game-core-frame"]')) {
            $this->switchToFrame('iframe[id*="game-core-frame"]');
            // Click button to show images challenge
            if ($this->exts->exists('button[data-theme="home.verifyButton"]')) {
                $this->exts->click_by_xdotool('button[data-theme="home.verifyButton"]');
                sleep(2);
                $this->exts->switchToDefault();
                $this->switchToFrame("iframe[data-e2e='enforcement-frame'].show.active");
                if ($this->exts->exists('#fc-iframe-wrap')) {
                    $this->switchToFrame('#fc-iframe-wrap');
                }
                if ($this->exts->exists('iframe[id*="game-core-frame"]')) {
                    $this->switchToFrame('iframe[id*="game-core-frame"]');
                }
            } else if ($this->exts->exists('#wrong_children_button')) {
                $this->exts->click_by_xdotool('#wrong_children_button');
                sleep(3);
                // It may add more iframe if click "Try again"
                $this->exts->switchToDefault();
                $this->switchToFrame("iframe[data-e2e='enforcement-frame'].show.active");
                if ($this->exts->exists('#fc-iframe-wrap')) {
                    $this->switchToFrame('#fc-iframe-wrap');
                }
                if ($this->exts->exists('.front > iframe[id*="CaptchaFrame"]')) {
                    $this->switchToFrame('.front > iframe[id*="CaptchaFrame"]');
                }
            }

            $captcha_instruction = $this->exts->extract('div.challenge-instructions-container');
            if (strpos($captcha_instruction, 'richtig herum zu sehen ist') !== false) {
                $captcha_instruction = 'Pick the image that is the correct way up';
            }
            $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
            $this->exts->switchToDefault();
            // if ($this->exts->exists("iframe[data-e2e='enforcement-frame'].show.active")){
            //  $this->switchToFrame("iframe[data-e2e='enforcement-frame'].show.active");
            // }

            $captcha_wraper_selector = 'div#arkose-selector-id';
            $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);
            if ($coordinates == '') {
                $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);
            }
            if ($coordinates != '') {
                $wraper = $this->exts->getElement($captcha_wraper_selector);
                if ($wraper != null) {
                    foreach ($coordinates as $coordinate) {
                        $this->click_recaptcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                    }
                    sleep(5);
                }
            }
        }
        $this->exts->switchToDefault();
        return true;
    }
    $this->exts->switchToDefault();
    return false;
}

private function click_recaptcha_point($selector = '', $x_on_element = 0, $y_on_element = 0)
{
    $this->exts->log(__FUNCTION__ . " $selector $x_on_element $y_on_element");
    $selector = base64_encode($selector);
    $element_coo = $this->exts->execute_javascript('
		var x_on_element = ' . $x_on_element . '; 
		var y_on_element = ' . $y_on_element . ';
		var coo = document.querySelector(atob("' . $selector . '")).getBoundingClientRect();
		// Default get center point in element, if offset inputted, out put them
		if(x_on_element > 0 || y_on_element > 0) {
			Math.round(coo.x + x_on_element) + "|" + Math.round(coo.y + y_on_element);
		} else {
			Math.round(coo.x + coo.width/2) + "|" + Math.round(coo.y + coo.height/2);
		}
		
	');
    // sleep(1);
    $this->exts->log("Browser clicking position: $element_coo");
    $element_coo = explode('|', $element_coo);

    $root_position = $this->exts->get_brower_root_position();
    $this->exts->log("Browser root position");
    $this->exts->log(print_r($root_position, true));

    $clicking_x = (int)$element_coo[0] + (int)$root_position['root_x'];
    $clicking_y = (int)$element_coo[1] + (int)$root_position['root_y'];
    $this->exts->log("Screen clicking position: $clicking_x $clicking_y");
    $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-" . $this->exts->process_uid;
    // move randomly
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 60, $clicking_x + 60) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 50, $clicking_x + 50) . " " . rand($clicking_y - 50, $clicking_y + 50) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 40, $clicking_x + 40) . " " . rand($clicking_y - 41, $clicking_y + 40) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 30, $clicking_x + 30) . " " . rand($clicking_y - 35, $clicking_y + 30) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 20, $clicking_x + 20) . " " . rand($clicking_y - 25, $clicking_y + 25) . "'");
    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . rand($clicking_x - 10, $clicking_x + 10) . " " . rand($clicking_y - 10, $clicking_y + 10) . "'");

    exec("sudo docker exec " . $node_name . " bash -c 'xdotool mousemove " . $clicking_x . " " . $clicking_y . " click 1;'");
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

function processFacebookInit()
{

    $this->exts->log(__FUNCTION__ . " Begin ");

    $this->exts->click_by_xdotool($this->facebook_init_button_selector);


    /*** begin google login code****/
    sleep(10);
    $this->fillFormFacebook(0);
    sleep(5);
    if ($this->exts->exists('html#facebook')) {
        $this->checkTwoFactorAuthFacebook();
    }
    if ($this->exts->exists('form[action*="/oauth/confirm"] button[name="__CONFIRM__"]')) {
        $this->exts->click_by_xdotool('form[action*="/oauth/confirm"] button[name="__CONFIRM__"]');
        sleep(1);
    }

    if ($this->exts->exists('form#login_form div[class*="login_error_box"]')) {
        $this->exts->loginFailure(1);
    }
    $this->exts->switchToInitTab();
    sleep(2);
    $this->exts->closeAllTabsButThis();
    $this->checkFillTwoFactor();
    $this->waitForLogin(0);

    /*** end google login code****/

    $this->exts->log(__FUNCTION__ . " End ");
}
/***********************************************************************************/
/*****************************Facebook login begins*********************************/
/***********************************************************************************/

function checkTwoFactorAuthFacebook()
{

    $this->exts->log(__FUNCTION__ . " :: Begin");
    $this->checkConsent();
    if ($this->checkMultiFactorAuthFacebook()) {
        $app_code = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"approvals_code\"]");
        $captcha_response = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"captcha_response\"]");
        $birthday_captcha_day = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"birthday_captcha_day\"]");
        $contact_index = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"contact_index\"]");


        $checkpoint_url = $this->exts->urlContains("/checkpoint/");
        if ($app_code != null || $captcha_response != null || $birthday_captcha_day != null) {
            $this->processTwoFactorAuthFacebook();
        } else if ($checkpoint_url) {

            if ($this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointFooterButton[type=\"submit\"]") != null) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointFooterButton[type=\"submit\"]");
            }

            if ($this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]") != null) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
            }

            sleep(10);
            $ele_one = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] div._5orv:nth-child(1) input[name=\"c\"][value=\"2\"]");
            $ele_two = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] div._5orv:nth-child(2) input[name=\"c\"][value=\"2\"]");
            if ($ele_one != null) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] div._5orv:nth-child(1)");
                sleep(5);
            } else if ($ele_two != null) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] div._5orv:nth-child(2)");
                sleep(5);
            }
            $this->exts->log(__FUNCTION__ . " :: checkpoint_url found : " . $this->exts->getUrl());
            $this->exts->capture("CaptchaCheckAfterClick");
            $ele_one = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"4\"]");
            $temp_one = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"14\"]");
            $temp_two = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"2\"]");
            $temp_three = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"captcha_response\"]");
            $temp_four = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"birthday_captcha_day\"]");
            if ($ele_one != null) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"4\"]");
                // TODO add click with sendeys spacebar if above fails
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
                sleep(5);
                $ele_two = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"send_code\"]");
                $ele_three = $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/block/\"] input[name=\"send_code\"]");
                if ($ele_two != null) {
                    $this->clickLabelAndSubmit("input[name=\"contact_index\"]", "form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
                } else if ($ele_three != null) {
                    $this->clickLabelAndSubmit("input[name=\"contact_index\"]", "form.checkpoint[action*=\"/checkpoint/block/\"] button#checkpointSubmitButton[type=\"submit\"]");
                }
                $this->processTwoFactorAuthFacebook();
            } else if ($temp_one != null) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"14\"]");
                // TODO add click with sendeys spacebar if above fails
                sleep(1);
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
                sleep(5);
                $this->processTwoFactorAuthFacebook();
            } else if ($temp_two != null) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"verification_method\"][value=\"2\"]");
                // TODO add click with sendeys spacebar if above fails
                sleep(1);
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
                sleep(5);
            } else if ($temp_three != null || $temp_four != null) {
                $this->processTwoFactorAuthFacebook();
            } else {
                $this->exts->log(__FUNCTION__ . " :: Login Failed 1 " . $this->exts->getUrl());
                $this->exts->capture("final-else-block-1");
                $this->exts->loginFailure();
            }
        } else if ($contact_index != null) {
            $this->clickLabelAndSubmit("input[name=\"contact_index\"]", "form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
            $this->processTwoFactorAuthFacebook();
        } else {
            $this->exts->log(__FUNCTION__ . " :: Login Failed 2 " . $this->exts->getUrl());
            $this->exts->capture("final-else-block-2");
            $this->exts->loginFailure();
        }
    } else {
        $this->exts->log(__FUNCTION__ . " :: loginFailed 3 " . $this->exts->getUrl());
        $this->exts->capture("final-else-block-3");
        if (strpos(strtolower($this->exts->extract('.login_error_box')), 'entered is incorrect') !== false) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('.login_error_box')), 'your password was changed') !== false) {
            $this->exts->loginFailure(1);
        }
        // $this->exts->loginFailure();
    }

    sleep(5);
    $temp_one = $this->exts->querySelector("form button#checkpointSubmitButton[type=\"submit\"]");
    if ($temp_one != null) {
        $this->exts->log("--Confirm-Location-page--");
        $this->exts->capture("confirm-location");
        if ($this->exts->exists('form button#checkpointSubmitButton[type=\"submit\"]')) {
            $this->exts->click_by_xdotool("form button#checkpointSubmitButton[type=\"submit\"]");
            sleep(5);
        }
        $temp_one = $this->exts->querySelector("form button#checkpointSubmitButton[type=\"submit\"]");
        if ($this->exts->exists('form button#checkpointSubmitButton[type=\"submit\"]')) {
            $this->exts->click_by_xdotool("form button#checkpointSubmitButton[type=\"submit\"]");
            sleep(5);
        }
    }

    $this->exts->capture("Confirm-Page");

    $this->checkConsent();

    if ($this->exts->querySelector("div[data-testid=\"return_to_feed_button\"] button._271k._271m._1qjd") != null) {
        $this->exts->click_by_xdotool("div[data-testid=\"return_to_feed_button\"] button._271k._271m._1qjd");
        sleep(5);
    }
    sleep(3);

    $this->exts->log(__FUNCTION__ . " :: End");
}
function clickLabelAndSubmit($selector_radio, $selector_button)
{

    $this->exts->log(__FUNCTION__ . " :: Begin");
    $radio_btns = $this->exts->querySelectorAll($selector_radio);
    if (count($radio_btns) > 0) {
        $this->exts->click_element(radio_btns[0]);
        sleep(1);
    }
    $this->exts->click_by_xdotool($selector_button);
    sleep(5);
    $this->exts->log(__FUNCTION__ . " :: End");
}
function checkMultiFactorAuthFacebook()
{
    return $this->exts->exists("form.checkpoint[action*=\"/checkpoint/?next\"]");
}
function checkCaptchaFacebook()
{

    $isCaptcha = $this->exts->querySelector("input#ap_captcha_guess");
    if ($isCaptcha == null) {
        $isCaptcha = $this->exts->querySelector("input#auth-captcha-guess");
    }
    if ($isCaptcha != null) {
        return true;
    }
    return false;
}
function checkConsent()
{
    $this->exts->log(__FUNCTION__ . " :: Begin");
    $consent_btn = $this->exts->querySelector("div[data-testid=\"parent_approve_consent_button\"] button._271k._271m._1qjd");
    if ($this->exts->urlContains("/consent/?") || $consent_btn != null) {
        $this->exts->click_by_xdotool("div[data-testid=\"parent_approve_consent_button\"] button._271k._271m._1qjd");
        sleep(5);
    }
    $this->exts->log(__FUNCTION__ . " :: End");
}
function fillBirthdate($birthdate)
{
    $this->exts->log(__FUNCTION__ . " Begin ");
    $arr = explode("-", trim($birthdate));
    if (stripos($birthdate, ".") !== false && count($arr) < 3) {
        $arr = explode(".", $birthdate);
    }
    if (count($arr) == 3) {
        $bDay = "document.getElementsByName('birthday_captcha_day')[0].value='" . trim($arr[0]) . "'";
        $this->exts->execute_javascript($bDay, array());

        $bMon = "document.getElementsByName('birthday_captcha_month')[0].value='" . trim($arr[1]) . "'";
        $this->exts->execute_javascript($bMon, array());

        $bYear = "document.getElementsByName('birthday_captcha_year')[0].value='" . trim($arr[2]) . "'";
        $$this->exts->execute_javascript($bYear, array());

        sleep(2);
        $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
        sleep(10);
        return true;
    } else {
        $this->exts->log("Birthday length : " . count($arr));
        return false;
    }
}
function processTwoFactorAuthFacebook()
{

    $this->exts->log(__FUNCTION__ . " :: Begin");
    $this->exts->capture("TwoFactorAuth");

    $this->exts->two_factor_notif_title_en = "Facebook - Code";
    $this->exts->two_factor_notif_title_de = "Facebook - Code";
    if ($this->exts->two_factor_attempts == 2) {
        $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->two_factor_notif_msg_retry_en;
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . $this->exts->two_factor_notif_msg_retry_de;
    }

    if ($this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"approvals_code\"]") != null) {
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        $this->exts->log(__FUNCTION__ . ":: 1 Received two_factor_code. " . $two_factor_code);
        if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
            $this->exts->moveToElementAndType("input[name=\"approvals_code\"]", $two_factor_code);
            $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
            sleep(10);
            $save_device_selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"name_action_selected\"][value=\"save_device\"]";
            $selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"approvals_code\"]";
            if ($this->exts->querySelector($selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";
                $this->processTwoFactorAuthFacebook();
            } else if ($this->exts->querySelector($save_device_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
                sleep(5);
            }
        }
    } else if ($this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"captcha_response\"]") != null) {
        $cpText = $this->exts->querySelectorAll("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j strong");
        if (count($cpText) > 0) {
            $msgTitle = trim($cpText[0]->getText());
            $this->exts->two_factor_notif_title_en = $msgTitle;
            $this->exts->two_factor_notif_title_de = $msgTitle;
        }
        $cpTextDesc = $this->exts->querySelectorAll("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j div");
        if (count($cpTextDesc) > 0) {
            $msgDesc = $cpTextDesc[0]->getText();
            $this->exts->two_factor_notif_msg_en = $msgDesc;
            $this->exts->two_factor_notif_msg_de = $msgDesc;
        }
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        $this->exts->log(__FUNCTION__ . ":: 2 Received two_factor_code : . " . $two_factor_code);
        if (trim($two_factor_code) != "" && !empty($two_factor_code)) {
            $this->exts->moveToElementAndType("input[name=\"captcha_response\"]", $two_factor_code);
            if ($this->exts->querySelector("input[name=\"captcha_response\"]") != null) {
                $this->exts->type_key_by_xdotool('Return');
                sleep(10);
            }

            $send_code_selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"send_code\"]";
            $selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"captcha_response\"]";
            if ($this->exts->querySelector($selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";
                $this->processTwoFactorAuthFacebook();
            } else if ($this->exts->querySelector($send_code_selector) != null && $this->exts->two_factor_attempts < 3) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
                sleep(5);
                $this->exts->two_factor_attempts++;
                $this->exts->notification_uid = "";
                $this->processTwoFactorAuthFacebook();
            } else if ($this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]") != null) {
                $this->exts->click_by_xdotool("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointSubmitButton[type=\"submit\"]");
            }
        }
    } else if ($this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"birthday_captcha_day\"]") != null) {
        $cpText = $this->exts->querySelectorAll("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j strong");
        if (count($cpText) > 0) {
            $msgTitle = "Facebook - " . trim($cpText[0]->getText());
            $this->exts->two_factor_notif_title_en = $msgTitle;
            $this->exts->two_factor_notif_title_de = $msgTitle;
        }
        $cpTextDesc = $this->exts->querySelectorAll("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j div");
        if (count($cpTextDesc) > 0) {
            $msgDesc = $cpTextDesc[0]->getText();
            $this->exts->two_factor_notif_msg_en = $msgDesc  . "#br#" . "Date should be in format date-month-year (Example: 1-1-2004)";
            $this->exts->two_factor_notif_msg_de = $msgDesc . "#br#" . "Datum muss im Format Tag-Monat-Jahr sein (zB. 1-1-2004)";
        } else {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "#br#" . "Date should be in format date-month-year (Example: 1-1-2004)";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . "#br#" . "Datum muss im Format Tag-Monat-Jahr sein (zB. 1-1-2004)";
        }

        $isRequestRequired = false;
        if (strlen(trim($this->user_birthday)) > 0) {
            if (!$this->fillBirthdate($this->user_birthday)) {
                $arr = explode("", trim($this->user_birthday));
                $this->exts->log("Birthday length : " . count($arr));
                $isRequestRequired = true;
            }
        }

        if ($isRequestRequired) {
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            $this->exts->log(__FUNCTION__ . ":: 3 Received two_factor_code : " . $two_factor_code);
            if (strlen(trim($two_factor_code)) > 0 && $this->fillBirthdate($two_factor_code)) {
                $selector = "form.checkpoint[action*=\"/checkpoint/?next\"] input[name=\"birthday_captcha_day\"]";
                if ($this->exts->querySelector($selector) != null && $this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = "";
                    $this->processTwoFactorAuthFacebook();
                }
            } else {
                $this->exts->log(__FUNCTION__ . " : Invalid birthdate received in 2FA, try again");
                $this->exts->capture("invalid-2fa");
            }
        }
    } else if ($this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] div._2ph_ ul li._3-8x span") != null && $this->exts->querySelector("form.checkpoint[action*=\"/checkpoint/?next\"] button#checkpointFooterButton[type=\"submit\"]") != null) {
        $cpText = $this->exts->querySelectorAll("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j strong");
        if (count($cpText) > 0) {
            $msgTitle = "Facebook - " . trim($cpText[0]->getText());
            $this->exts->two_factor_notif_title_en = $msgTitle;
            $this->exts->two_factor_notif_title_de = $msgTitle;
        }
        $cpTextDesc = $this->exts->querySelectorAll("form.checkpoint[action*=\"/checkpoint/?next\"] div._5p3j div");
        if (count($cpTextDesc) > 0) {
            $msgDesc = $cpTextDesc[0]->getText();
            $this->exts->two_factor_notif_msg_en = $msgDesc  . "#br#" . "Please enter \"OK\" here below afterwards.";
            $this->exts->two_factor_notif_msg_de = $msgDesc . "#br#" . "Gebe danach hier unten \"OK\" ein";
        } else {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . "#br#" . "Please enter \"OK\" here below afterwards.";
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . "#br#" . "Gebe danach hier unten \"OK\" ein)";
        }
        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        $this->exts->log(__FUNCTION__ . ":: 4 Received two_factor_code : . " . $two_factor_code);
        if (!$this->checkLogin() && $this->exts->two_factor_attempts < 3) {
            $this->exts->two_factor_attempts++;
            $this->exts->notification_uid = "";
            $this->processTwoFactorAuthFacebook();
        }
    } else {
        $this->exts->log(__FUNCTION__ . " :: Need --TWO_FACTOR_REQUIRED-- handling here ");
        $this->exts->capture("need_two_factor_handling");
    }

    $this->exts->log(__FUNCTION__ . " :: End");
}
/**
 * Method to fill login form for facebook
 * @param Integer $count Number of times portal is retried.
 */
function fillFormFacebook($count)
{
    $this->exts->log("Begin fillForm " . $count);

    try {
        if ($this->exts->querySelector($this->facebook_password_selector) != null || $this->exts->querySelector($this->facebook_username_selector) != null) {
            $this->exts->capture("1-pre-login");

            if ($this->exts->querySelector($this->facebook_username_selector) != null) {
                $this->exts->log("Enter Username");
                $this->exts->querySelector($this->facebook_username_selector)->clear();
                $this->exts->moveToElementAndType($this->facebook_username_selector, $this->username);
            }
            $this->exts->waitTillPresent($this->facebook_continue_button_selector, 30);
            if ($this->exts->exists($this->facebook_continue_button_selector)) {
                $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Found continue button ");
                $this->exts->click_by_xdotool($this->facebook_continue_button_selector);
            } else {
                $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Timed out waiting for continue button");
            }


            if ($this->exts->querySelector($this->facebook_password_selector) != null) {
                $this->exts->log("Enter Password");
                $this->exts->querySelector($this->facebook_password_selector)->clear();
                $this->exts->moveToElementAndType($this->facebook_password_selector, $this->password);
                sleep(10);
            }

            $this->exts->capture("2-filled-login");
            if ($this->exts->querySelector($this->facebook_submit_button_selector) != null) {
                $this->exts->click_by_xdotool($this->facebook_submit_button_selector);
            } else if ($this->exts->querySelector($this->facebook_new_submit_button_selector) != null) {
                $this->exts->click_by_xdotool($this->facebook_new_submit_button_selector);
            }

            sleep(10);
        } else if ($this->exts->querySelector($this->facebook_alt_password_selector) != null || $this->exts->querySelector($this->facebook_alt_username_selector) != null) {
            $this->exts->capture("1-pre-login");

            if ($this->exts->querySelector($this->facebook_alt_username_selector) != null) {
                $this->exts->log("Enter ALT Username");
                $this->exts->querySelector($this->facebook_alt_username_selector)->clear();
                $this->exts->moveToElementAndType($this->facebook_alt_username_selector, $this->username);
                sleep(10);
            }

            if ($this->exts->querySelector($this->facebook_alt_password_selector) != null) {
                $this->exts->log("Enter ALT Password");
                $this->exts->querySelector($this->facebook_alt_password_selector)->clear();
                $this->exts->moveToElementAndType($this->facebook_alt_password_selector, $this->password);
                sleep(10);
            }

            $this->exts->capture("2-filled-login");

            $this->exts->click_element($this->facebook_alt_submit_button_selector);

            sleep(10);
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}


/***********************************************************************************/
/*****************************Facebook login ends*******************************/
/***********************************************************************************/



/********************************************************************************************/
/****************************Google login begins*********************************************/
/********************************************************************************************/

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
        $back_button = $this->exts->getElement($back_button_xpath);
        if ($back_button != null) {
            try {
                $this->exts->log(__FUNCTION__ . ' back to method list to find Authenticator app.');
                $this->exts->execute_javascript("arguments[0].click();", [$back_button]);
            } catch (\Exception $exception) {
                $this->exts->executeSafeScript("arguments[0].click()", [$back_button]);
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
// End GOOGLE login

/********************************************************************************************/
/****************************Google login ends*********************************************/
/********************************************************************************************/


function waitForLogin($count)
{
    $this->exts->log(__FUNCTION__ . " Begin : " . $count);
    if ($this->exts->exists('button.tos-agree')) {
        $this->exts->click_by_xdotool('button.tos-agree');
        sleep(10);
    }
    $login_wairning = $this->exts->getElement('//button[text()="Nein, danke, das war ich"]');
    if ($login_wairning != null) {
        $this->exts->click_element($login_wairning);
        sleep(5);
        $continue = $this->exts->getElement('//button[text()="Fertig"]');
        if ($continue != null) {
            $this->exts->click_element($continue);
            sleep(10);
        }
    }

    if ($this->checkLogin()) {
        $this->processAfterLogin(0);
    } else if ($count < 4) {
        $count++;
        $c1 = stripos($this->exts->getUrl(), '/community-commitment?redirect_params') !== false;
        if ($c1) {
            try {
                $js = "var len = document.querySelectorAll('div.row button[type=button]').length;";
                $js .= "if(len > 1) { document.querySelectorAll('div.row button[type=button]')[len-2].click();}";
                $this->exts->execute_javascript($js);
            } catch (\Exception $ex) {
                $this->exts->log(__FUNCTION__ . " Exception in waitforlogin 1 : " . $ex);
            }
        }
        sleep(5);
        $this->waitForLogin($count);
    } else {
        $this->exts->capture("login-failed");
        $this->exts->log('LAST URL: ' . $this->exts->getUrl());

        if (stripos(strtolower($this->exts->extract('form[action="/authenticate"] [role="alert"]')), 'passwor') !== false) {
            $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Found login failed screen!!!! ");
            $this->exts->loginFailure(1);
        } else if (stripos(strtolower($this->exts->extract('form[action="/authenticate"] [role="status"]')), 'passwor') !== false) {
            $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Found login failed screen!!!! ");
            $this->exts->loginFailure(1);
        } else if (stripos(strtolower($this->exts->extract('form[action="/authenticate"] [role="status"]')), 'mit dieser e-mail-adresse ist kein nutzerkonto') !== false) {
            $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Found login failed screen!!!! ");
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract('[data-testid="auth-form"] h1 + *', null, 'innerText'), 'ltige Log-in-Daten') !== false) {
            $this->exts->loginFailure(1);
        } else if (stripos($this->exts->extract('[data-testid="auth-form"]', null, 'innerText'), 'Invalid login credentials') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('form[action="/authenticate"] input[name="email"][aria-invalid="true"]')) {
            $this->exts->loginFailure(1);
        } else if (stripos(strtolower($this->exts->extract('[data-testid="auth-form"] h1 + *')), 'the password you entered is incorrect') !== false) {
            $this->exts->loginFailure(1);
        } else if ($this->exts->exists('form[action*="/create"] [data-testid="email-signup-password"]')) {
            $this->exts->loginFailure(1);
        }
        $this->exts->loginFailure();
    }

    $this->exts->log(__FUNCTION__ . " Ends : " . $count);
}
function processAfterLogin($count)
{
    $this->exts->log(__FUNCTION__ . ' : Begin ' . $count);
    $this->exts->log(__FUNCTION__ . ' : Login Success ');
    $this->exts->capture("login-success");
    if ($this->exts->exists('[role="alertdialog"]:not([style*="display:none"]) button.accept-cookies-button, [data-testid="main-cookies-banner-container"] button[data-testid="accept-btn"]')) {
        $this->exts->click_by_xdotool('[role="alertdialog"]:not([style*="display:none"]) button.accept-cookies-button, [data-testid="main-cookies-banner-container"] button[data-testid="accept-btn"]');
        sleep(3);
    }

    if ((int)@$this->no_booking_invoice != 1) {
        $this->processBookings();
    }

    if ((int)@$this->no_host_invoice != 1) {
        // close new tab too avoid too much tabs. Sometime download pdf file don;t close the tab automatically
        $this->exts->switchToInitTab();
        sleep(2);
        $this->exts->closeAllTabsButThis();
        $this->processHostInvoice();
    }

    if ((int)@$this->business_invoice == 1) {
        // close new tab too avoid too much tabs. Sometime download pdf file don;t close the tab automatically
        $this->exts->switchToInitTab();
        sleep(2);
        $this->exts->closeAllTabsButThis();
        $this->processBusinessInvoice();
    }

    if ($this->isNoInvoice) {
        $this->exts->no_invoice();
    }
}
function checkLogin()
{
    $this->exts->log(__FUNCTION__ . "::Begin");
    $isLoggedIn = false;
    try {
        sleep(10);
        if ($this->exts->exists($this->logout_link_one)) {
            $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Login successful !!!! ");
            $isLoggedIn = true;
        } else {
            $this->exts->click_by_xdotool('button[data-testid*="-headernav-profile"][aria-expanded="false"]');
            sleep(1);
            if ($this->exts->exists($this->logout_link_one)) {
                $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Login successful !!!! ");
                $isLoggedIn = true;
            } else {
                $this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Timed out waiting for logout link one");
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}

function processBookings($pageCount = 1)
{
    $this->exts->log(__FUNCTION__ . ' : Begin');
    if ($pageCount == 1) {
        if (trim($this->portal_language) == "en_us") {
            $this->exts->openUrl('https://www.airbnb.com/trips/v1');
        } else {
            $this->exts->openUrl('https://www.airbnb.de/trips/v1');
        }
    }
    sleep(25);
    if ($this->exts->exists('[role="dialog"] > div:first-child button')) {
        $this->exts->click_by_xdotool('[role="dialog"] > div:first-child button');
        sleep(1);
    } else if ($this->exts->exists('[data-testid="modal-container"] footer button:last-child')) {
        $this->exts->click_by_xdotool('[data-testid="modal-container"] footer button:last-child');
        sleep(1);
    }
    $this->exts->capture("4-booking-page");
    //$bookings = $this->exts->getElementsAttribute('#past-trips div a[href*="/trips/v1/"]:not([href*="/trips/v1/inactive"])', 'href');
    $bookings = $this->exts->getElementsAttribute('[data-section-id="PAST_TRIPS"] a[href*="/trips/v1/"]:not([href*="/trips/v1/inactive"])', 'href');

    $this->exts->log('Total Bookings - ' . count($bookings));
    $unique_bookings = array();
    foreach ($bookings as $booking_url) {
        if (!in_array($booking_url, $unique_bookings)) {
            $unique_bookings[] = $booking_url;
        }
    }
    $this->exts->log('Total Bookings - ' . count($unique_bookings));
    foreach ($unique_bookings as $booking_url) {
        $this->exts->openUrl($booking_url);
        sleep(7);
        // Huy 12-2022 Seem below logic is no longer correct
        // // Each booking may have many reservation
        // $reservations = $this->exts->getElementsAttribute('div a[href*="/trips/v1/"][href*="/RESERVATION"]', 'href');
        // $this->exts->log('Reservations  in this booking: '.count($reservations));
        // $unique_reservations = array();
        // foreach ($reservations as $reservation) {
        //  if(!in_array($reservation, $unique_reservations)) {
        //      $unique_reservations[] = $reservation;
        //  }
        // }
        // $this->exts->log('Reservations  in this booking: '.count($unique_reservations));
        // foreach ($unique_reservations as $reservation) {
        //  $this->exts->log('--------------------------');
        //  $this->exts->openUrl($reservation);
        //  sleep(6);
        //  if($this->exts->exists('a[href*="/bill/"], a[href*="/reservation/receipt?code="]')){
        //      $this->exts->log('---Download print');
        //      $print_url = $this->exts->extract('a[href*="/bill/"], a[href*="/reservation/receipt?code="]', null, 'href');
        //      $this->exts->log('Found Bill url: '.$print_url);
        //      $this->exts->openUrl($print_url);
        //      sleep(5);
        //      // Currently, some reservation url not work, so must check to make sure this is reservation detal page.
        //      if($this->exts->urlContainsAny(['/bill/', 'code='])){
        //          if(stripos($print_url, 'code=') !== false){
        //              $invoiceName = explode('&', array_pop(explode('code=', $print_url)))[0];
        //          } else {
        //              $invoiceName = explode('?', array_pop(explode('/bill/', $print_url)))[0];
        //          }

        //          $invoiceFileName = $invoiceName.'.pdf';
        //          $invoiceDate = $this->exts->executeSafeScript('try{return BootstrapData.get("payinProductInfos")[0].formatted_end_time_without_day;} catch(ex){return ""}');
        //          $parsed_date = $this->exts->parse_date($invoiceDate, 'd# M# Y','Y-m-d');
        //          $invoiceAmount = trim(preg_replace('/[^\w\.\,\s]/', '', $this->exts->extract('.receipt-panel-body-padding .h4.pull-right')));
        //          if($invoiceAmount == ''){
        //              $invoiceAmount = $this->exts->executeSafeScript('try{return BootstrapData.get("totalPaid").amount + " " + BootstrapData.get("totalPaid").currency;} catch(ex){return ""}');
        //          }

        //          $this->exts->log('invoiceName: '.$invoiceName);
        //          $this->exts->log('invoiceDate: '.$invoiceDate);
        //          $this->exts->log('parsed_date: '.$parsed_date);
        //          $this->exts->log('invoiceAmount: '.$invoiceAmount);

        //          $downloaded_file = $this->exts->download_current($invoiceFileName, 2);
        //          if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
        //              $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
        //              sleep(1);
        //          } else {
        //              $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        //          }

        //          $this->isNoInvoice = false;
        //      } else {
        //          $this->exts->log('Seem this is not reservation detail page '.$this->exts->getUrl());
        //      }
        //  } else if($this->exts->exists('a[href*="/receipt/"]')){
        //      $this->exts->log('---Download PDF');
        //      $receiptEles = $this->exts->querySelectorAll('a[href*="/receipt/"]');
        //      $check_dublicated = [];
        //      $paths = explode('/', $this->exts->getUrl());
        //      $currentDomain = $paths[2];
        //      foreach($receiptEles as $receiptEle) {
        //          $currText = strtolower($receiptEle->getText());
        //          $this->exts->log('Element Text - '.$currText);
        //          if(trim($currText) == '' || empty($currText)) {
        //              $currText = $this->exts->executeSafeScript('arguments[0].innerText;', [$receiptEle]);
        //              $this->exts->log('Element Text from JS - '.$currText);
        //          }
        //          if(stripos(trim($currText), "invoice") !== false || stripos(trim($currText), "rechnung") !== false) {
        //              $download_url = $receiptEle->getAttribute("href");
        //              if(stripos($download_url, '/receipt/') !== false){
        //                  $invoiceName = explode('?', array_pop(explode('/receipt/', $download_url)))[0];
        //                  if(!in_array($invoiceName, $check_dublicated)){
        //                      array_push($check_dublicated, $invoiceName);
        //                      $invoiceFileName = $invoiceName.'.pdf';

        //                      $this->exts->open_new_window();
        //                      // Some conflict between airbnb.de and airbnb.com that make pdf can not download
        //                      // We must replace domain of download url with current domain.
        //                      $this->exts->log('Original download url: '. $download_url);
        //                      $paths = explode('/', $download_url);
        //                      $paths[2] = $currentDomain;
        //                      $download_url = join('/', $paths);
        //                      $this->exts->log('Solve domain download url: '. $download_url);
        //                      $downloaded_file = $this->exts->direct_download($download_url, 'pdf', $invoiceFileName);
        //                      if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
        //                          $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
        //                          sleep(1);
        //                      } else {
        //                          $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        //                      }
        //                      $this->isNoInvoice = false;
        //                      $this->exts->close_new_window();
        //                  }
        //              }
        //          } else {
        //              $this->exts->update_process_lock();
        //          }
        //      }
        //  }
        // }

        // find receipt in each booking
        if ($this->exts->exists('a[href*="/bill/"], a[href*="/reservation/receipt?code="]')) {
            $this->exts->log('---Download print');
            $print_url = $this->exts->extract('a[href*="/bill/"], a[href*="/reservation/receipt?code="]', null, 'href');
            $this->exts->log('Found Bill url: ' . $print_url);

            $this->exts->openNewTab($print_url);
            sleep(5);
            // Currently, some reservation url not work, so must check to make sure this is reservation detal page.
            if ($this->exts->urlContainsAny(['/bill/', 'code='])) {
                if (stripos($print_url, 'code=') !== false) {
                    $invoiceName = explode('&', array_pop(explode('code=', $print_url)))[0];
                } else {
                    $invoiceName = explode('?', array_pop(explode('/bill/', $print_url)))[0];
                }

                $invoiceFileName = $invoiceName . '.pdf';
                $invoiceDate = $this->exts->executeSafeScript('try{return BootstrapData.get("payinProductInfos")[0].formatted_end_time_without_day;} catch(ex){return ""}');
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd# M# Y', 'Y-m-d');
                $invoiceAmount = trim(preg_replace('/[^\w\.\,\s]/', '', $this->exts->extract('.receipt-panel-body-padding .h4.pull-right')));
                if ($invoiceAmount == '') {
                    $invoiceAmount = $this->exts->executeSafeScript('try{return BootstrapData.get("totalPaid").amount + " " + BootstrapData.get("totalPaid").currency;} catch(ex){return ""}');
                }

                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('parsed_date: ' . $parsed_date);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $downloaded_file = $this->exts->download_current($invoiceFileName, 2);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }

                $this->isNoInvoice = false;
            } else {
                $this->exts->log('Seem this is not reservation detail page ' . $this->exts->getUrl());
            }
            $this->exts->switchToInitTab();
            sleep(2);
            $this->exts->closeAllTabsButThis();
        } else if ($this->exts->exists('a[href*="/receipt/"]')) {
            $this->exts->log('---Download PDF');
            $receipt_urls = $this->exts->getElementsAttribute('a[href*="/receipt/"]', 'href');
            $receipt_urls = array_unique($receipt_urls);
            $paths = explode('/', $this->exts->getUrl());
            $currentDomain = $paths[2];

            foreach ($receipt_urls as $receipt_url) {
                $temp_array = explode('/receipt/', $receipt_url);
                $invoiceName = end($temp_array);
                $temp_array = explode('?', $invoiceName);
                $invoiceName = reset($temp_array);
                $invoiceFileName = $invoiceName . '.pdf';

                // Some conflict between airbnb.de and airbnb.com that make pdf can not download
                // We must replace domain of download url with current domain.
                $this->exts->log('Original download url: ' . $receipt_url);
                $paths = explode('/', $receipt_url);
                $paths[2] = $currentDomain;
                $receipt_url = join('/', $paths);
                $this->exts->log('Solve domain download url: ' . $receipt_url);
                $downloaded_file = $this->exts->direct_download($receipt_url, 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                $this->isNoInvoice = false;
            }
        }
    }

    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $pageCount++;
    if (
        $restrictPages == 0 &&
        $pageCount < 50 &&
        $this->exts->querySelector('a[href*="/trips/v1?past_trips_page=' . $pageCount . '"]') != null
    ) {
        $this->exts->click_by_xdotool('a[href*="/trips/v1?past_trips_page=' . $pageCount . '"]');
        sleep(5);
        $this->processBookings($pageCount);
    }
}
function processBusinessInvoice()
{
    $this->exts->log(__FUNCTION__ . ' : Begin');
    if (trim($this->portal_language) == "en_us") {
        $this->exts->openUrl('https://www.airbnb.com/business/company_dashboard/trips');
    } else {
        $this->exts->openUrl('https://www.airbnb.de/business/company_dashboard/trips');
    }
    sleep(25);

    if ($this->exts->exists('#tos_confirm')) {
        $this->exts->click_by_xdotool('#tos_confirm'); // check accept box
        $this->exts->click_by_xdotool('#tos_form [type=submit]');
        sleep(20);
    }
    if ($this->exts->exists('[role="dialog"] > div:first-child button')) {
        $this->exts->click_by_xdotool('[role="dialog"] > div:first-child button');
        sleep(1);
    } else if ($this->exts->exists('[data-testid="modal-container"] footer button:last-child')) {
        $this->exts->click_by_xdotool('[data-testid="modal-container"] footer button:last-child');
        sleep(1);
    }
    // Click Completted trips tab
    // We can not indentify Completted trips button by selector, the only way is by label
    $tab_buttons = $this->exts->getElements('[role="tablist"] button');
    $this->exts->log('Finding Completted trips button...');
    foreach ($tab_buttons as $key => $tab_button) {
        $tab_name = trim($tab_button->getAttribute('innerText'));
        if (stripos($tab_name, 'Abgeschlossene') !== false || stripos($tab_name, 'Complete') !== false) {
            $this->exts->log('Completted trips button found');
            try {
                $this->exts->log('Click trips button');
                $this->exts->click_element($tab_button);
            } catch (\Exception $exception) {
                $this->exts->log('Click trips button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$tab_button]);
            }
            sleep(20);
            break;
        }
    }

    $this->exts->capture("4-business-trips-page");

    if ($this->exts->exists('[role="tabpanel"] tbody tr[role="button"]')) {
        // Loop through all row, click on each row, if it open a new tab with invoice url, check and download print it.
        $rows = $this->exts->querySelectorAll('[role="tabpanel"] tbody tr[role="button"]');
        $last_invoice_url = '';
        foreach ($rows as $row) {
            $this->exts->log('--------------------------');
            $tags = $this->exts->querySelectorAll('td', $row);
            if (count($tags) >= 5) {
                $invoiceDate = trim(end(explode('-', $tags[2]->getText())));
                $parsed_date = $this->exts->parse_date($invoiceDate, 'j# M? Y', 'Y-m-d');
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getText())) . ' EUR';

                // click the row
                try {
                    $this->exts->log('Click the row');
                    $this->exts->click_element($row);
                } catch (\Exception $exception) {
                    $this->exts->log('Click row by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$row]);
                }
                sleep(5);
                // then check if new tab opened and it open invoice
                $this->exts->switchToNewestActiveTab();
                $current_url = $this->exts->getUrl();
                if (stripos($current_url, '/itinerary/') !== false && $this->exts->exists('a[href*="/reservation/receipt?code="]')) {
                    $url = $this->exts->querySelector('a[href*="/reservation/receipt?code="]')->getAttribute('href');
                    $this->exts->openUrl($url);
                    sleep(5);
                    $current_url = $this->exts->getUrl();
                }
                if ($this->exts->urlContains('code=') &&  $current_url !== $last_invoice_url) {
                    // Currently, some invoice open with a blank page, so must check to make sure the content is invoice (not blank)
                    if ($this->exts->exists('#site-content .page-container-responsive > *')) {
                        $this->exts->log('This is invoice with content');
                        $invoiceName = explode(
                            '&',
                            array_pop(explode('code=', $current_url))
                        )[0];

                        $this->exts->log('invoiceName: ' . $invoiceName);
                        $this->exts->log('invoiceDate: ' . $invoiceDate);
                        $this->exts->log('parsed_date: ' . $parsed_date);
                        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                        $this->exts->log('invoiceUrl: ' . $current_url);

                        $invoiceFileName = $invoiceName . '.pdf';
                        $downloaded_file = $this->exts->download_current($invoiceFileName, 2);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                        } else {
                            $this->exts->log('Timeout when download ' . $invoiceFileName);
                        }
                        $this->isNoInvoice = false;
                    }
                    $last_invoice_url = $current_url;
                }

                $this->exts->switchToInitTab();
                sleep(2);
                $this->exts->closeAllTabsButThis();
                sleep(1);
            }
        }
    } else { // UPDATE new layout 24-11-2020 (by HUY)
        $itinerary_urls = $this->exts->getElementsAttribute('[role="tabpanel"]:not([hidden]) [role="cell"]:nth-child(4) a[href*="/itinerary/"]', 'href');


        foreach ($itinerary_urls as $itinerary_url) {
            $this->exts->log('--------------------------');
            $this->exts->openNewTab($itinerary_url);
            sleep(5);
            if ($this->exts->exists('a[href*="/reservation/receipt?code="]')) {
                $url = $this->exts->querySelector('a[href*="/reservation/receipt?code="]')->getAttribute('href');
                $this->exts->openUrl($url);
                sleep(5);
                // Currently, some invoice open with a blank page, so must check to make sure the content is invoice (not blank)
                if ($this->exts->exists('#site-content .page-container-responsive > *')) {
                    $this->exts->log('This is invoice with content');
                    $invoiceName = explode(
                        '&',
                        array_pop(explode('code=', $url))
                    )[0];

                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceUrl: ' . $url);

                    $invoiceFileName = $invoiceName . '.pdf';
                    $downloaded_file = $this->exts->download_current($invoiceFileName, 2);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                    $this->isNoInvoice = false;
                }
            }
            $this->exts->switchToInitTab();
            sleep(2);
            $this->exts->closeAllTabsButThis();
        }
    }
}
function processHostInvoice()
{
    $this->exts->log(__FUNCTION__ . ' : Begin');
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if (trim($this->portal_language) == "en_us") {
        $domain = 'https://www.airbnb.com';
        // $this->exts->openUrl('https://www.airbnb.com/hosting/reservations/completed');
    } else {
        $domain = 'https://www.airbnb.de';
        // $this->exts->openUrl('https://www.airbnb.de/hosting/reservations/completed');
    }

    $this->exts->openUrl($domain . '/hosting/reservations/completed');
    $this->exts->openUrl($domain . '/hosting/reservations/completed');
    sleep(25);

    if ($this->exts->exists('[role="dialog"] > div:first-child button')) {
        $this->exts->click_by_xdotool('[role="dialog"] > div:first-child button');
        sleep(1);
    } else if ($this->exts->exists('[data-testid="modal-container"] footer button:last-child')) {
        $this->exts->click_by_xdotool('[data-testid="modal-container"] footer button:last-child');
        sleep(1);
    }
    $this->exts->update_process_lock();

    $this->exts->capture("4-host-booking-page");
    if ($this->exts->urlContains('/hosting/reservations/')) {
        for ($paging_count = 1; $paging_count < 100; $paging_count++) {
            $this->exts->waitTillPresent('table > tbody > tr button', 30);
            if (!$this->exts->exists('table > tbody > tr button')) {
                $this->exts->update_process_lock();
                $this->exts->waitTillPresent('table > tbody > tr button', 30);
            }

            $row_count = count($this->exts->getElements('table > tbody > tr'));
            for ($i = 0; $i < $row_count; $i++) {
                $row = $this->exts->getElements('table > tbody > tr')[$i];
                $more_option_button = $this->exts->getElement('button[aria-label="More options"], button[aria-label="Weitere Optionen"], button[aria-label="Autres options"], button[aria-label*=" opciones"], button[aria-label*="Meer opties"]', $row); //supporting de, en, fr, es, nl
                // 'a[href*="/reservations/details/"]'
                // 'a[href*="code="]'
                if ($more_option_button != null) {
                    $refer_date = $this->exts->extract('td:nth-child(5)', $row, 'innerText');
                    $this->exts->log('refer_date: ' . $refer_date);
                    $refer_parsed_date = $this->exts->parse_date($refer_date, 'M j, Y', 'Y-m-d');
                    if (empty($refer_parsed_date)) {
                        $refer_parsed_date = $this->exts->parse_date($refer_date, 'j. M. Y', 'Y-m-d');
                    }
                    $this->exts->log('refer_date parsed: ' . $refer_parsed_date);
                    $date_for_compare = strtotime($refer_parsed_date);
                    $this->exts->log('date_for_compare: ' . $date_for_compare);
                    if (!empty($this->start_date) && !empty($date_for_compare) && $date_for_compare <= strtotime($this->start_date)) {
                        $this->exts->log('LIMIT DATE REACHED: ' . date('Y-m-d', $date_for_compare));
                        $this->exts->log('STOP : ' . __FUNCTION__);
                        return;
                    }

                    $this->exts->log('Click more_option_button button');
                    $this->exts->click_element($more_option_button);
                    sleep(2);
                    $this->exts->waitTillPresent('[role="dialog"] #confirmation, [role="dialog"] a[href*="/vat_invoices/"]');
                    sleep(1);
                    if ($this->exts->exists('[role="dialog"] #confirmation, [role="dialog"] a[href*="/vat_invoices/"]')) {
                        $vatInvoiceURL = $this->exts->extract('[role="dialog"] a[href*="/vat_invoices/"]', null, 'href');
                        $invoiceName = $this->exts->extract('[role="dialog"] #confirmation #confirmation-row-title + *', null, 'innerText');
                        $invoiceName = trim($invoiceName);
                        $booking_print_url = $domain . '/hosting/reservations/details/' . $invoiceName . '?print=true';

                        $invoiceDate = '';
                        $invoiceAmount = $this->exts->extract('td:nth-child(7)', $row, 'innerText');
                        $invoiceAmount = preg_replace('/[^\d\.\,]/', '', $invoiceAmount) . ' EUR';

                        $this->exts->log('invoiceName: ' . $invoiceName);
                        $this->exts->log('invoiceDate: ' . $invoiceDate);
                        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                        $this->exts->log('booking_print_url: ' . $booking_print_url);
                        $this->exts->log('vatInvoiceURL: ' . $vatInvoiceURL);

                        $invoiceFileName = $invoiceName . '.pdf';
                        if (!empty($vatInvoiceURL)) {
                            $downloaded_file = $this->exts->download_capture($vatInvoiceURL, $invoiceFileName, 5);
                            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                            } else {
                                $this->exts->log('Timeout when download ' . $invoiceFileName);
                            }
                            $this->exts->switchToInitTab();
                            sleep(2);
                            $this->exts->closeAllTabsButThis();

                            //Some user need booking details also even if VAT invoice is there
                            if ((int)$this->booking_detail == 1 || (int)$this->credit_note == 1) {
                                $invoiceFileName = 'booking_' . $invoiceName . '.pdf';
                                $downloaded_file = $this->exts->download_capture($booking_print_url, $invoiceFileName, 5);
                                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                    $invoiceName = 'booking_' . $invoiceName;
                                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                                } else {
                                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                                }
                                $this->exts->switchToInitTab();
                                sleep(2);
                                $this->exts->closeAllTabsButThis();
                            }
                        } else if (!empty($booking_print_url)) {
                            //Download Booking confirmation from hosting only if user has not activate no booking invoice
                            if ((int)$this->no_booking_invoice != '1' || (int)$this->booking_detail == 1 || (int)$this->credit_note == 1) {
                                $downloaded_file = $this->exts->download_capture($booking_print_url, $invoiceFileName, 5);
                                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                                    $this->exts->new_invoice($invoiceName, '', $invoiceAmount, $invoiceFileName);
                                } else {
                                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                                }
                                $this->exts->switchToInitTab();
                                sleep(2);
                                $this->exts->closeAllTabsButThis();
                                $this->isNoInvoice = false;
                            } else {
                                $this->exts->log('No VAT invoice found and user has not selected for booking - ' . $invoiceFileName);
                            }
                        }
                    } else {
                        $this->exts->capture("4-host-booking-exception");
                    }

                    // // Close popup
                    // $this->exts->log('Close options popup');
                    // $action = new Interactions\WebDriverActions($this->exts);
                    // $action->moveByOffset(1, 1)->click()->perform();
                    // sleep(1);
                }
            }

            // process next page
            if ($this->exts->exists('button[aria-current="page"] + button:not([disabled])') && $restrictPages == 0) {
                $this->exts->click_by_xdotool('button[aria-current="page"] + button:not([disabled])');
                sleep(5);
            } else {
                break;
            }
        }
    } else {
        $this->exts->log('Seem this is not host user');
    }
}