<?php
// Server-Portal-ID: 10178 - Last modified: 23.01.2025 14:17:22 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = "https://www.spotify.com/de/account/overview/";
public $isNoInvoice = true;
public $facebook_login = 0;
public $login_with_google = 0;

/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
    $this->exts->log('Begin initPortal '.$count);
    // Set `facebook_login = 1` to enable hardcoded Facebook login for the test engine.
    $this->facebook_login = isset($this->exts->config_array["facebook_login"]) ? (int)$this->exts->config_array["facebook_login"] : 0;
    // $this->facebook_login = 1;
    
    
    $this->login_with_google = isset($this->exts->config_array["login_with_google"]) ? (int)$this->exts->config_array["login_with_google"] : $this->login_with_google;
    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if($this->exts->config_array['lang_code'] == 'en'){
        $this->baseUrl = "https://www.spotify.com/us/account/overview/";
    }

    // Set custom timeout for portal
    $this->exts->two_factor_timeout = 10;
    
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    if(!$this->isLoggedin()){
        $this->exts->loadCookiesFromFile(true);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);
    }
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if(!$this->isLoggedin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->click_if_existed('button[id*="accept-btn"]');
        sleep(3);
        if ((int)$this->login_with_google == 1) {
            $this->exts->moveToElementAndClick('[data-testid="google-login"]');
            sleep(5);

            $this->loginGoogleIfRequired();
        } else if ((int)$this->facebook_login == 1) {
            $this->exts->moveToElementAndClick('[data-testid="facebook-login"]');
            sleep(5);

            $this->loginFacebookIfRequired();
            $this->processAfterFacebookLogin();
        } else {
            $this->checkFillLogin(1);
        }

        $this->checkSolveLoginChallenges();
        sleep(5);
        $this->checkFillTwoFactor();
        sleep(5);
        $this->checkSolveLoginChallenges();
        sleep(5);
    }

    // then check user logged in or not
    $this->processAfterLogin();
}
private function checkFillLogin() {
    if($this->exts->exists('input#login-username')) {
        sleep(2);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType('input#login-username', $this->username);
        sleep(1);
        // $this->exts->checkFillRecaptcha();

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType('input#login-password', $this->password);
        sleep(1);
        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick('#login-button');
        $this->checkSolveLoginChallenges();
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function checkSolveLoginChallenges(){
    $this->exts->capture("2-challenges-checking");
    $captcha_found_solved = false;
    sleep(5);
    if($this->exts->urlContains('challenge.spotify.com')){
        $this->exts->refresh();
        $this->exts->log("Found Captcha Url :");
        sleep(2);
       $is_captcha = $this->solve_captcha_by_clicking(0);
        if ($is_captcha) {
            for ($i = 1; $i < 30; $i++) {
                if ($is_captcha == false) {
                    break;
                }
                $is_captcha = $this->solve_captcha_by_clicking($i);
            }
        }
        if($this->exts->exists('button[name="solve"]')){
            $this->exts->click_element('button[name="solve"]');
        }
        return $captcha_found_solved;
    }
    
}

private function solve_captcha_by_clicking($count = 1)
    {
        $this->exts->log("Checking captcha");
        $language_code = '';
        $unsolved_recaptcha_submit_selector = 'iframe[src*="/recaptcha/enterprise/anchor"]';
        $recaptcha_challenger_wraper_selector = 'div[style*="visible"] iframe[src*="/recaptcha/enterprise/bframe"]';
        $this->exts->waitTillAnyPresent([$unsolved_recaptcha_submit_selector, $recaptcha_challenger_wraper_selector], 20);
        if ($this->exts->check_exist_by_chromedevtool($unsolved_recaptcha_submit_selector) || $this->exts->exists($recaptcha_challenger_wraper_selector)) {
            // Check if challenge images hasn't showed yet, Click checkbox to show images challenge
            if (!$this->exts->check_exist_by_chromedevtool($recaptcha_challenger_wraper_selector)) {
                $this->exts->click_by_xdotool($unsolved_recaptcha_submit_selector);
                $this->exts->waitTillPresent($recaptcha_challenger_wraper_selector, 30);
            }
            $this->exts->capture("spotify-captcha");


            //$captcha_instruction = $this->exts->extract($iframeElement_instartion,null, 'innerText');
            sleep(5);
            $captcha_wraper_selector = 'div[style*="visible"] iframe[src*="/recaptcha/enterprise/bframe"]';
            
            if ($this->exts->exists($captcha_wraper_selector)) {
                $captcha_instruction = $this->exts->makeFrameExecutable('div[style*="visible"] iframe[src*="/recaptcha/enterprise/bframe"]')->extract('div.rc-imageselect-desc-wrapper');
                
                $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
                $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);


                // if($coordinates == '' || count($coordinates) < 2){
                //  $coordinates = $this->exts->processClickCaptcha($captcha_wraper_selector, $captcha_instruction, '', $json_result=false);
                // }
                if ($coordinates != '') {
                    // $challenge_wraper = $this->exts->querySelector($captcha_wraper_selector);

                    foreach ($coordinates as $coordinate) {
                        $this->click_recaptcha_point($captcha_wraper_selector, (int)$coordinate['x'], (int)$coordinate['y']);
                    }

                    $this->exts->capture("spotify-captcha-selected " . $count);
                    $this->exts->makeFrameExecutable('div[style*="visible"] iframe[src*="/recaptcha/enterprise/bframe"]')->click_element('div button');
                    sleep(5);
                    return true;
                }
            }

            return false;
        }
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
private function checkFillTwoFactor(){
    $this->exts->capture("2-checking-two-factor");
    $two_factor_selector = 'input[data-testid="twofa-digit-input"], input[name="code"]';
    $two_factor_message_selector = 'h1[class*="MfaBodyTitle"]';
    $two_factor_submit_selector = 'form[class*="CodeForm"] button[type="submit"]';

    if ($this->exts->exists($two_factor_selector) && $this->exts->two_factor_attempts < 3) {
        $this->exts->log("Two factor page found.");
        $this->exts->capture("2.1-two-factor");

        if ($this->exts->exists($two_factor_message_selector)) {
            $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
        }
        if ($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
        }
        $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

        $two_factor_code = trim($this->exts->fetchTwoFactorCode());
        if (!empty($two_factor_code) && trim($two_factor_code) != '') {
            $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
            $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
            sleep(1);
            $this->exts->moveToElementAndClick('input[name="trusted"]:not(:checked) + span');
            $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

            $this->exts->log("checkFillTwoFactor: Clicking submit button.");
            $this->exts->moveToElementAndClick($two_factor_submit_selector);
            sleep(10);
        } else {
            $this->exts->log("Not received two factor code");
        }
    }
}
private function isLoggedin() {
    return $this->exts->exists('button[aria-controls="profileMenu"], button[data-testid="user-widget-link"]');
}

/**================================== FACEBOOK LOGIN =================================================**/
public $facebook_loginUrl = 'https://www.facebook.com';
public $facebook_username_selector = 'form#login_form input#email';
public $facebook_password_selector = 'form#login_form input#pass';
public $facebook_remember_me_selector = '';
public $facebook_submit_login_selector = 'form#login_form input[type="submit"], #logginbutton, #loginbutton';
public $check_login_failed_selector = 'div.uiContextualLayerPositioner[data-ownerid="email"], div#error_box, div.uiContextualLayerPositioner[data-ownerid="pass"], input.fileInputUpload';
public $facebook_check_login_success_selector = '#logoutMenu, a[href*="bookmarks"], a[href*="/logout/"], [ng-click="logout()"], a[href*="/account/overview/"], [ng-click="logout()';

/**
 * Entry Method thats identify and click element by element text
 * Because many website use generated html, It did not have good selector structure, indentify element by text is more reliable
 * This function support seaching element by multi language text or regular expression
 * @param String $selector Selector string of element.
 * @param String $multi_language_texts the text label of element that want to click, can input single label, or multi language array or regular expression. Exam: 'invoice', ['invoice', 'rechung'], '/invoice|rechung/i'
 * @param Element $parent_element parent element when we search element inside.
 * @param Bool $is_absolutely_matched tru if want seaching absolutely, false if want to seaching relatively.
 */

private function loginFacebookIfRequired(){
    if($this->exts->urlContains('facebook.')){
        $this->exts->log('Start login with facebook');
        // $this->exts->openUrl($this->facebook_loginUrl);
        sleep(5);

        if($this->exts->exists('div[role="button"][aria-label="Allow"]')){
            $this->exts->log('Click On allow button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label*="Als"] > div"]');
             sleep(10);
        }

       if($this->exts->exists('div[role="button"][aria-label*="Als"] > div, div[role="button"][aria-label*="Continue"] > div')){
            $this->exts->log('Click On Continue button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label*="Als"] > div, div[role="button"][aria-label*="Continue"] > div');
             sleep(5);
        }

        

        $this->checkFillFacebookLogin();
        sleep(10);
        // choose 2FA verification method
        if ($this->exts->exists('button#checkpointSubmitButton')){
            // confirm "choose a verification method"
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(1);
            
            //Approve your login on another computer - 14
            //Log in with your Google account - 35
            //Get a code sent to your email = Receive code by email - 37
            //Get code on the phone - 34
            // Choose send code to phone, if not available, choose send code to email, else choose first option.
            $facebook_verification_method = $this->exts->getElementByText('.uiInputLabelLabel', ['phone', 'telefon', 'telefoon'], null, false);
            if ($facebook_verification_method == null){
                $facebook_verification_method = $this->exts->getElementByText('.uiInputLabelLabel', ['email', 'e-mail', 'e-mailadres'], null, false);
            }
            if($facebook_verification_method != null ) {
                $facebook_verification_method->click();
            }
            else {
                // choose first option.
                $this->exts->moveToElementAndClick('.uiInputLabelLabel');
            }
            
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(2);
            // choose number/email to send code
            $this->exts->moveToElementAndClick('.uiInputLabelInput');
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(2);
            // fill code and continue: captcha_response
            $this->checkFillFacebookTwoFactor();
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(2);
            // fill code and continue: approvals_code
            $this->checkFillFacebookTwoFactor();
            $this->exts->moveToElementAndClick('button#checkpointSubmitButton');
            sleep(2);
        }

        //Click "Continue" if required
        $continue_btn = $this->exts->getElementByText('div[role="button"]', ['Continue', 'Fortsetzen', 'Continuer'], null, true);
        if ($continue_btn != null) {
            $continue_btn->click();
            sleep(3);
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Not required facebook login.');
        $this->exts->capture("3-no-facebook-required");
    }
}

private function checkFillFacebookLogin() {
    if($this->exts->querySelector($this->facebook_password_selector) != null) {
        sleep(3);
        $this->exts->capture("2-facebook-login-page");
        
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->facebook_username_selector, $this->username);
        sleep(1);
        
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->facebook_password_selector, $this->password);
        sleep(1);
        
        if($this->facebook_remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->facebook_remember_me_selector);
        sleep(2);
        
        $this->exts->capture("2-facebook-login-page-filled");
        $this->exts->moveToElementAndClick($this->facebook_submit_login_selector);
        sleep(10);

        if($this->exts->exists('div[role="button"][aria-label="Allow"]')){
             $this->exts->log('Click On allow button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label="Allow"]');
             sleep(10);
        } 

        if($this->exts->exists('div[role="button"][aria-label*="Als"], div[role="button"][aria-label*="Continue"]')){
            $this->exts->log('Click On Continue button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label*="Als"], div[role="button"][aria-label*="Continue"]');
             sleep(10);
        }

        if($this->exts->exists('div[role="button"][aria-label*="Als"] > div, div[role="button"][aria-label*="Continue"] > div')){
            $this->exts->log('Click On Continue button');
            $this->exts->click_by_xdotool('div[role="button"][aria-label*="Als"] > div, div[role="button"][aria-label*="Continue"] > div');
             sleep(10);
        }

    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-facebook-login-page-not-found");
    }
}

private function checkFillFacebookTwoFactor() {
    $facebook_two_factor_selector = 'form.checkpoint[action*="/checkpoint"] input[name*="captcha_response"], form.checkpoint[action*="/checkpoint"] input[name*="approvals_code"], input#recovery_code_entry';
    $facebook_two_factor_message_selector = 'form.checkpoint[action*="/checkpoint"] div strong, form[action*="/recover/code"] h2.uiHeaderTitle"';
    $facebook_two_factor_submit_selector = 'button#checkpointSubmitButton, form[action*="/recover/code"] div.uiInterstitialBar button';
    
    if($this->exts->querySelector($facebook_two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
        $this->exts->log("Facebook two factor page found.");
        $this->exts->capture("2.1-facebook-two-factor");
        
        if($this->exts->querySelector($facebook_two_factor_message_selector) != null){
            $this->exts->two_factor_notif_msg_en = "";
            for ($i=0; $i < count($this->exts->querySelectorAll($facebook_two_factor_message_selector)); $i++) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->querySelectorAll($facebook_two_factor_message_selector)[$i]->getText()."\n";
            }
            $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Facebook Message:\n".$this->exts->two_factor_notif_msg_en);
        }
        if($this->exts->two_factor_attempts == 2) {
            $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
        }
        
        $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
        for ($i=0; $i < 2 && empty($facebook_two_factor_code); $i++) {
            $this->exts->capture('Facebook-2FA-not-received'.$i);
            $this->exts->log("Facebook Not received two factor code");
            $this->exts->log("Facebook Requesting re-send two factor code.");
            $this->exts->capture('Facebook-Request-2FA-Again'.$i);
            // Request portal to resend two factor code
            if ($this->exts->exists('#checkpointBottomBar a[role="button"]')){
                $this->exts->moveToElementAndClick('#checkpointBottomBar a[role="button"]');
                sleep(2);
                // click "Didn't get a code href"
                if ($this->exts->getElement('//span[@id="fbLoginApprovalsThrobber"]/preceding-sibling::a', null, 'xpath') != null){
                    $this->exts->getElement('//span[@id="fbLoginApprovalsThrobber"]/preceding-sibling::a', null, 'xpath')->click();
                    sleep(2);
                    // close modal
                    $this->exts->moveToElementAndClick('a.layerCancel');
                }
            } elseif ($this->exts->exists('input.uiLinkButtonInput[name*="send_code"]')){
                $this->exts->moveToElementAndClick('input.uiLinkButtonInput[name*="send_code"]');
                sleep(2);
            } elseif ($this->exts->exists('a[href*="/recover/initiate"]')) {
                $this->exts->moveToElementAndClick('a[href*="/recover/initiate"]');
                sleep(2);
                // choose first email address to send code
                $this->exts->moveToElementAndClick('form[action*="/ajax/recover/initiate/"] table input');
                // click next/further
                $this->exts->moveToElementAndClick('div.uiInterstitialBar button');
                sleep(2);
            }
            // call API to get code
            $facebook_two_factor_code = trim($this->exts->fetchTwoFactorCode());
        }
        
        if(!empty($facebook_two_factor_code) && trim($facebook_two_factor_code) != '') {
            $this->exts->log("FacebookCheckFillTwoFactor: Entering facebook_two_factor_code.".$facebook_two_factor_code);
            $this->exts->moveToElementAndType($facebook_two_factor_selector, $facebook_two_factor_code);
            
            $this->exts->log("FacebookCheckFillTwoFactor: Clicking submit button.");
            sleep(3);
            $this->exts->capture("2.2-facebook-two-factor-filled-".$this->exts->two_factor_attempts);
            
            $this->exts->moveToElementAndClick($facebook_two_factor_submit_selector);
            sleep(3);
            $this->exts->capture('2.2-facebook-two-factor-submitted-'.$this->exts->two_factor_attempts);
            
            if($this->exts->querySelector($facebook_two_factor_selector) == null){
                $this->exts->log("Facebook two factor solved");
                // Save device
                if ($this->exts->exists('input[value*="save_device"]')){
                    $this->exts->moveToElementAndClick('input[value*="save_device"]');
                    $this->exts->moveToElementAndClick($facebook_two_factor_submit_selector);
                }
            } else if ($this->exts->two_factor_attempts < 3) {
                $this->exts->two_factor_attempts++;
                $this->checkFillFacebookTwoFactor();
                $this->exts->moveToElementAndClick($facebook_two_factor_submit_selector);
            } else {
                $this->exts->log("Facebook two factor can not solved.");
            }
        } else{
            $this->exts->log("Facebook failed to fetch two factor code!!!");
        }
        sleep(3);
    }
}

private function isFacebookLoggedin() {
    return $this->exts->exists($this->facebook_check_login_success_selector);
}

private function processAfterFacebookLogin() {
    // then check user logged in or not
    if($this->isFacebookLoggedin()) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User facebook logged in');
        $this->exts->capture("3-facebook-login-success");
        
        // if(!empty($this->exts->config_array['allow_login_success_request'])) {
        // 	$this->exts->triggerLoginSuccess();
        // }
        // Do the rest of work below (e.g: download invoices...)
        $this->exts->openUrl($this->homePageUrl);
        sleep(10);
        if($this->exts->exists('button[id*="accept-btn"]')) {
            $this->exts->moveToElementAndClick('button[id*="accept-btn"]');
            sleep(2);
        }
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        
        if($this->exts->exists('.mh-message-bar button.mh-close')) {
            $this->exts->moveToElementAndClick('.mh-message-bar button.mh-close');
        }
        
        $this->processAfterLogin();
        
        if($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        
        $this->exts->success();
        
    } else {
        $this->exts->log(__FUNCTION__.'::Use facebook login failed');
        $mesg = strtolower($this->exts->extract('form.checkpoint > div', null, 'innerText'));
        if (strpos($mesg, 'dein konto') !== false && strpos($mesg, 'gesperrt') !== false) {
            $this->exts->account_not_ready();
        }
        if (strpos($mesg, 'account has been temporarily blocked') !== false) {
            $this->exts->account_not_ready();
        }
        if (strpos($this->exts->get_page_content(), 'Dein Konto wurde gesperrt') !== false) {
            $this->exts->account_not_ready();
        }
        if($this->exts->querySelector($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } elseif ($this->exts->urlContains('login/device-based/regular/login')){
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
/**==================================END FACEBOOK LOGIN =================================================**/

// -------------------- GOOGLE login
public $google_username_selector = 'input[name="identifier"]:not([aria-hidden="true"])';
public $google_submit_username_selector = '#identifierNext, input#submit, input#next';
public $google_password_selector = 'input[name="password"], input[name="Passwd"]';
public $google_submit_password_selector = '#passwordNext, #gaia_loginform input#signIn, #passwordNext button, input#submit';
private function loginGoogleIfRequired() {
    if($this->exts->urlContains('google.')){
        if($this->exts->urlContains('/webreauth')){
            $this->exts->moveToElementAndClick('#identifierNext');
            sleep(6);
        }
        $this->googleCheckFillLogin();
        sleep(5);
        if($this->exts->querySelector('input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }
        if($this->exts->querySelector('form[action*="/signin/v2/challenge/password/"] input[name="password"][aria-invalid="true"], input[name="identifier"][aria-invalid="true"], span#passwordError, input[name="Passwd"][aria-invalid="true"]') != null) {
            $this->exts->loginFailure(1);
        }

        // Click next if confirm form showed
        $this->exts->moveToElementAndClick('div[data-ownership-changed-phone-number] div:nth-child(2) > [role="button"]');
        $this->googleCheckTwoFactorMethod();

        if($this->exts->exists('#smsauth-interstitial-remindbutton')){
            $this->exts->moveToElementAndClick('#smsauth-interstitial-remindbutton');
            sleep(10);
        }
        if($this->exts->exists('#tos_form input#accept')){
            $this->exts->moveToElementAndClick('#tos_form input#accept');
            sleep(10);
        }
        if($this->exts->exists('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]')){
            $this->exts->moveToElementAndClick('[wizard-step-uid="RecoveryOptionsCollectionWizard:starter"] div:last-child > [role="button"]');
            sleep(10);
        }
        if($this->exts->exists('.action-button.signin-button + a.setup-button[href*="/two-step-verification/"]')){
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('.action-button.signin-button');
            sleep(10);
        }
        if($this->exts->exists('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button')){
            // SKIP setup 2FA
            $this->exts->moveToElementAndClick('a.setup-button[href*="two-step-verification/enroll"] + button.signin-button');
            sleep(10);
        }
        if($this->exts->exists('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]')){
            $this->exts->moveToElementAndClick('[action="/signin/newfeatures/save"] #optionsButton ~ [role="button"]');
            sleep(10);
        }
        if($this->exts->exists('input[name="later"]') && $this->exts->urlContains('/AddressNoLongerAvailable')){
            $this->exts->moveToElementAndClick('input[name="later"]');
            sleep(7);
        }
        if($this->exts->exists('#editLanguageAndContactForm a[href*="/adsense/app"]')){
            $this->exts->moveToElementAndClick('#editLanguageAndContactForm a[href*="/adsense/app"]');
            sleep(7);
        }
        if($this->exts->exists('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]')){
            $this->exts->moveToElementAndClick('[data-view-instance-id="/web/chip-V0"] [role="button"]:first-child [jsslot]');
            sleep(10);
        }

        if($this->exts->exists('#submit_approve_access')){
            $this->exts->moveToElementAndClick('#submit_approve_access');
            sleep(10);
        } else if($this->exts->exists('form #approve_button[name="submit_true"]')){
            // An application is requesting permission to access your Google Account.
            // Click allow
            $this->exts->moveToElementAndClick('form #approve_button[name="submit_true"]');
            sleep(10);
        }
        $this->exts->capture("3-google-before-back-to-main-tab");
    } else {
        $this->exts->log(__FUNCTION__.'::Not required google login.');
        $this->exts->capture("3-no-google-required");
    }
}
private function googleCheckFillLogin() {
    if($this->exts->exists('form ul li [role="link"][data-identifier]')){
        $this->exts->moveToElementAndClick('form ul li [role="link"][data-identifier]');
        sleep(5);
    }

    if($this->exts->urlContainsAny(['/ServiceLogin/identifier', '/ServiceLogin/webreauth']) && $this->exts->exists($this->google_submit_username_selector) && !$this->exts->exists($this->google_username_selector)){
        $this->exts->capture("google-verify-it-you");
        // To help keep your account secure, Google needs to verify it’s you. Please sign in again to continue to Google Ads
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
    }
    
    $this->exts->capture("2-google-login-page");
    if($this->exts->exists($this->google_username_selector)) {
        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->google_username_selector, $this->username);
        sleep(1);
        $this->exts->moveToElementAndClick($this->google_submit_username_selector);
        sleep(5);
        if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)){
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            $this->exts->moveToElementAndClick($this->google_submit_username_selector);
            sleep(5);
            if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)){
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
            if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists($this->google_password_selector) && $this->exts->exists($this->google_username_selector)){
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
                $this->exts->moveToElementAndClick($this->google_submit_username_selector);
                sleep(5);
            }
        } else if($this->exts->urlContains('/challenge/recaptcha')){
            $this->googlecheckFillRecaptcha();
            $this->exts->moveToElementAndClick('[data-primary-action-label] > div > div:first-child button');
            sleep(5);
        }

        // Which account do you want to use?
        if($this->exts->exists('form[action*="/lookup"] button.account-chooser-button')){
            $this->exts->moveToElementAndClick('form[action*="/lookup"] button.account-chooser-button');
            sleep(5);
        }
        if($this->exts->exists('[data-view-id="prbTle"] form [role="link"][data-profileindex]')){
            $this->exts->moveToElementAndClick('[data-view-id="prbTle"] form [role="link"][data-profileindex]');
            sleep(5);
        }
    }
    
    if($this->exts->exists($this->google_password_selector)) {
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
        sleep(1);
        if($this->exts->exists('#captchaimg[src]')){
            $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
        }
        
        $this->exts->capture("2-google-login-page-filled");
        $this->exts->moveToElementAndClick($this->google_submit_password_selector);
        sleep(5);
        if($this->exts->exists('#captchaimg[src]') && !$this->exts->exists('input[name="password"][aria-invalid="true"]') && $this->exts->exists($this->google_password_selector)){
            $this->exts->moveToElementAndType($this->google_password_selector, $this->password);
            sleep(1);
            if($this->exts->exists('#captchaimg[src]')){
                $this->exts->processCaptcha('#captchaimg[src]', 'input[name="ca"]');
            }
            $this->exts->moveToElementAndClick($this->google_submit_password_selector);
            sleep(5);
            if($this->exts->exists('#captchaimg[src]') && $this->exts->exists($this->google_password_selector)){
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
        $this->exts->log(__FUNCTION__.'::Google password page not found');
        $this->exts->capture("2-google-password-page-not-found");
    }
}
private function googleCheckTwoFactorMethod() {
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
    if($this->exts->exists('#assistActionId') && $this->exts->exists('[data-illustration="securityKeyLaptopAnim"]')){
        $this->exts->moveToElementAndClick('#assistActionId');
        sleep(5);
    } else if($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false){
        // (updated special case 28-Mar-2020): If we meet QR-Code, click 'Choose another option' to back to method choosen list
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
        if($this->exts->urlContains('/challenge/wa') && strpos($this->exts->extract('form header h2'), 'QR-Code') !== false){
            $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
            sleep(5);
        }
    } else if($this->exts->urlContains('/sk/webauthn')){
        $node_name = !empty($this->exts->config_array['node_name']) ? $this->exts->config_array['node_name'] : "selenium-node-".$this->exts->process_uid;
        exec("sudo docker exec ".$node_name." bash -c 'xdotool key Return'");
        sleep(3);
        $this->exts->capture("2.0-cancel-security-usb-google");
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(5);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')){
        // (updated special case 09-May-2020): If Notification method showed immediately, This method often make user confused
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] > div > div:nth-child(2)  div:nth-child(2) > [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button, button#assistiveActionOutOfQuota');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    } else if($this->exts->exists('input[name="ootpPin"]')){
        // (updated special case 11-Jun-2020): If "Verify by offline device" immediately, This method often make user confused and maybe they don't have device on hand
        // So, We try to click 'Choose another option' in order to select easier method
        $this->exts->moveToElementAndClick('[data-view-id] [data-secondary-action-label] > div > div:nth-child(2) [role="button"], [data-view-id] [data-secondary-action-label] > div > div:nth-child(2) button');
        sleep(7);
        $this->exts->capture("2.0-backed-methods-list-google");
    }
    
    // STEP 1: Check if list of two factor methods showed, select first
    if($this->exts->exists('li [data-challengetype]:not([data-challengeunavailable="true"])')){
        // We most RECOMMEND confirm security phone or email, then other method
        if($this->exts->exists('li [data-challengetype="13"]:not([data-challengeunavailable="true"])') && isset($this->security_phone_number) && $this->security_phone_number != ''){
            $this->exts->moveToElementAndClick('li [data-challengetype="13"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="12"]:not([data-challengeunavailable="true"])') && isset($this->recovery_email) && $this->recovery_email != ''){
            $this->exts->moveToElementAndClick('li [data-challengetype="12"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="6"]:not([data-challengeunavailable="true"])')){
            // We RECOMMEND method type = 6 is get code from Google Authenticator
            $this->exts->moveToElementAndClick('li [data-challengetype="6"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])')){
            // We second RECOMMEND method type = 9 is get code from SMS
            $this->exts->moveToElementAndClick('li [data-challengetype][data-sendmethod="SMS"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])')){
            // We recommend method type = 4 and [data-sendauthzenprompt="true"] is  Tap YES on your smartphone or tablet
            $this->exts->moveToElementAndClick('li [data-challengetype="4"][data-sendauthzenprompt="true"]:not([data-challengeunavailable="true"]), li [data-challengetype="39"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype="5"]:not([data-challengeunavailable="true"])')){
            // Use a smartphone or tablet to receive a security code (even when offline)
            $this->exts->moveToElementAndClick('li [data-challengetype="5"]:not([data-challengeunavailable="true"])');
        } else if($this->exts->exists('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])')){
            // We DONT recommend method is QR code OR is Security USB, we can not solve this type of 2FA
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengetype="4"]):not([data-challengetype="2"]):not([data-challengeunavailable="true"])');
        } else {
            $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"])');
        }
        sleep(10);
    } else if($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')){
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')){
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')){
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
        sleep(10);
    }
    
    // STEP 2: (Optional)
    if($this->exts->exists('input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]')){
        // If methos is recovery email, send 2FA to ask for email
        $this->exts->two_factor_attempts = 2;
        $input_selector = 'input#knowledge-preregistered-email-response, input[name="knowledgePreregisteredEmailResponse"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if(isset($this->recovery_email) && $this->recovery_email != ''){
            $this->exts->moveToElementAndType($input_selector, $this->recovery_email);
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if($this->exts->exists($input_selector)){
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if($this->exts->exists('[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]')){
        // If methos confirm recovery phone number, send 2FA to ask
        $this->exts->two_factor_attempts = 3;
        $input_selector = '[data-view-id*="knowledgePreregisteredPhoneView"] input[type="tel"]';
        $message_selector = '[data-view-id] form section div > div[jsslot] > div:first-child';
        $submit_selector = '';
        if(isset($this->security_phone_number) && $this->security_phone_number != ''){
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool("Return");
            sleep(5);
        }
        if($this->exts->exists($input_selector)){
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
            sleep(5);
        }
    } else if($this->exts->exists('input#phoneNumberId')){
        // Enter a phone number to receive an SMS with a confirmation code.
        $this->exts->two_factor_attempts = 3;
        $input_selector = 'input#phoneNumberId';
        $message_selector = '[data-view-id] form section > div > div > div:first-child';
        $submit_selector = '';
        if(isset($this->security_phone_number) && $this->security_phone_number != ''){
            $this->exts->moveToElementAndType($input_selector, $this->security_phone_number);
            $this->exts->type_key_by_xdotool("Return");
            sleep(7);
        }
        if($this->exts->exists($input_selector)){
            $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
        }
    } else if($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')){
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if($this->exts->exists('[data-view-id*="securityKeyWebAuthnView"], [data-view-id*="securityKeyView"]')){
        // Method: insert your security key and touch it
        $this->exts->two_factor_attempts = 3;
        $this->exts->two_factor_notif_msg_en = 'Use chrome, login then insert your security key and touch it' . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = '[Chrome] Stecken Sie den Sicherheitsschlussel in den USB-Anschluss Ihres Computers ein. Wenn er eine Taste hat, tippen Sie darauf.' . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
        // choose another option: #assistActionId
    }
    
    // STEP 3: (Optional)  After choose method and confirm email or phone or.., google may asked confirm one more time before send code
    if($this->exts->exists('#smsButton, [data-illustration="accountRecoverySmsPin"]')){
        // Sometime user must confirm before google send sms
        $this->exts->moveToElementAndClick('#smsButton, div:first-child > [role="button"], [data-secondary-action-label] > div > div:nth-child(1) button');
        sleep(10);
    } else if($this->exts->exists('#authzenNext') && $this->exts->exists('[data-view-id*="authzenView"], [data-illustration*="authzen"]')){
        $this->exts->moveToElementAndClick('[data-view-id] #authzenNext');
        sleep(10);
    } else if($this->exts->exists('#idvpreregisteredemailNext') && !$this->exts->exists('form input:not([type="hidden"])')){
        $this->exts->moveToElementAndClick('#idvpreregisteredemailNext');
        sleep(10);
    } else if(count($this->exts->getElements('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])')) > 0){
        $this->exts->moveToElementAndClick('li [data-challengetype]:not([data-challengeunavailable="true"]):not([data-challengetype="undefined"])');
        sleep(7);
    }
    
    
    // STEP 4: input code
    if($this->exts->exists('form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin')){
        $input_selector = 'form input[name="idvPin"], form input[name="totpPin"], input[name="code"], input#backupCodePin';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '#idvPreregisteredPhoneNext, #idvpreregisteredemailNext, #totpNext, #idvanyphoneverifyNext, #backupCodeNext, #view_container div.pwWryf.bxPAYd div.zQJV3 div.qhFLie > div > div > button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    } else if($this->exts->exists('input[name="ootpPin"], input#securityKeyOtpInputId')){
        $input_selector = 'input[name="ootpPin"], input#securityKeyOtpInputId';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if($this->exts->exists('input[name="Pin"]')){
        $input_selector = 'input[name="Pin"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector, true);
    } else if($this->exts->exists('[data-view-id*="authzenView"] form, form [data-illustration*="authzen"]') || $this->exts->urlContains('/challenge/dp?')){
        // Check your smartphone. Google has sent a notification to your smartphone. Tap Yes in the notification, then tap 91 on your smartphone to continue
        $this->exts->two_factor_attempts = 3;
        $message_selector = '[data-view-id*="authzenView"] form, [data-view-id] form[method="post"]';
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Enter \"OK\" after confirmation on device";
        $this->exts->two_factor_notif_msg_de = trim($this->exts->extract($message_selector, null, 'innerText')) . "\n>>>Geben Sie danach hier unten \"OK\" ein.";
        $this->googleFillTwoFactor(null, null, '');
        sleep(5);
    } else if($this->exts->exists('input[name="secretQuestionResponse"]')){
        $input_selector = 'input[name="secretQuestionResponse"]';
        $message_selector = 'form > span > section > div > div > div:first-child';
        $submit_selector = '[data-secondary-action-label] > div > div:nth-child(1) button';
        $this->exts->two_factor_attempts = 0;
        $this->googleFillTwoFactor($input_selector, $message_selector, $submit_selector);
    }
}
private function googleFillTwoFactor($input_selector, $message_selector, $submit_selector, $submit_by_enter=false) {
    $this->exts->log(__FUNCTION__);
    $this->exts->log("Google two factor page found.");
    $this->exts->capture("2.1-two-factor-google");
    
    if($this->exts->querySelector($message_selector) != null){
        $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($message_selector, null, 'innerText'));
        $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
    }
    
    $this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
    $this->exts->notification_uid = "";
    $two_factor_code = trim($this->exts->fetchTwoFactorCode());
    if(!empty($two_factor_code) && trim($two_factor_code) != '') {
        if($this->exts->querySelector($input_selector) != null){
            if(substr(trim($two_factor_code), 0, 2) === 'G-'){
                $two_factor_code = end(explode('G-', $two_factor_code));
            }
            if(substr(trim($two_factor_code), 0, 2) === 'g-'){
                $two_factor_code = end(explode('g-', $two_factor_code));
            }
            $this->exts->log(__FUNCTION__.": Entering two_factor_code: ".$two_factor_code);
            $this->exts->moveToElementAndType($input_selector, '');
            $this->exts->moveToElementAndType($input_selector, $two_factor_code);
            sleep(1);
            if($this->exts->allExists(['input[type="checkbox"]:not(:checked) + div', 'input[name="Pin"]'])){
                $this->exts->moveToElementAndClick('input[type="checkbox"]:not(:checked) + div');
                sleep(1);
            }
            $this->exts->capture("2.2-google-two-factor-filled-".$this->exts->two_factor_attempts);
            
            if($this->exts->exists($submit_selector)){
                $this->exts->log(__FUNCTION__.": Clicking submit button.");
                $this->exts->moveToElementAndClick($submit_selector);
            } else if($submit_by_enter){
                $this->exts->type_key_by_xdotool("Return");
            }
            sleep(10);
            $this->exts->capture("2.2-google-two-factor-submitted-".$this->exts->two_factor_attempts);
            if($this->exts->querySelector($input_selector) == null){
                $this->exts->log("Google two factor solved");
            } else {
                if($this->exts->two_factor_attempts < 3){
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
private function googlecheckFillRecaptcha() {
    $this->exts->log(__FUNCTION__);
    $recaptcha_iframe_selector = 'form iframe[src*="/recaptcha/"]';
    $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
    if($this->exts->exists($recaptcha_iframe_selector)) {
        $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
        $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
        $this->exts->log("iframe url  - " . $iframeUrl);
        $this->exts->log("SiteKey - " . $data_siteKey);
        $url = reset(explode('?', $this->exts->getUrl()));
        $isCaptchaSolved = $this->exts->processRecaptcha($url, $data_siteKey, false);
        $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
        
        if($isCaptchaSolved) {
            // Step 1 fill answer to textarea
            $this->exts->log(__FUNCTION__."::filling reCaptcha response..");
            $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
            for ($i=0; $i < count($recaptcha_textareas); $i++) {
                $this->exts->execute_javascript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
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
            $this->exts->log('Callback function: '.$gcallbackFunction);
            if($gcallbackFunction != null){
                $this->exts->execute_javascript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
                sleep(10);
            }
        }
    } else {
        $this->exts->log(__FUNCTION__.'::Not found reCaptcha');
    }
}
// End GOOGLE login

public function processAfterLogin() {
    if($this->isLoggedin()){
        $this->exts->capture("LoginSuccess");
        if ($this->exts->exists('.mh-message-bar button.mh-close')) {
            $this->exts->moveToElementAndClick('.mh-message-bar button.mh-close');
        }

        if($this->exts->config_array['lang_code'] == 'en'){
            $this->exts->openUrl('https://www.spotify.com/us/account/order-history/');
        } else {
            $this->exts->openUrl('https://www.spotify.com/de/account/order-history/');
        }
        sleep(15);
        $this->downloadInvoice();

        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log("LoginFailed " . $this->exts->getUrl());
        if (stripos($this->exts->extract('[data-testid="login-container"] [role="alert"], div[data-encore-id="banner"]'), ' Passwor') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}
public function downloadInvoice()
{
    $this->exts->capture("receipt-page");
    if($this->exts->exists('button[id*="accept-btn"]')) {
        $this->exts->moveToElementAndClick('button[id*="accept-btn"]');
        sleep(2);
    }

    $invoices = [];
    $rows = $this->exts->getElements('[data-testid="invoice-table"] tr');
    foreach($rows as $row) {
        $order_link = $this->exts->getElement('a[href*="/order-history/subscription/"]', $row);
        if($order_link != null) {
            $this->isNoInvoice = false;
            $invoiceUrl = $order_link->getAttribute("href");
            $temp_array = explode('/subscription/', $invoiceUrl);
            $invoiceName = end($temp_array);
            $temp_array = explode('?', $invoiceName);
            $invoiceName = reset($temp_array);
            
            array_push($invoices, array(
                'invoiceName'=>$invoiceName,
                'invoiceDate'=>'',
                'invoiceAmount'=>'',
                'invoiceUrl'=>$invoiceUrl
            ));
        }
    }
    
    // Download all invoices
    $this->exts->log('Invoices found: '.count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: '.$invoice['invoiceName']);
        $this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
        
        $this->exts->openUrl($invoice['invoiceUrl']);
        sleep(2);
        $this->exts->waitTillPresent('[data-testid="table-receipt"] tr');
        sleep(1);

        if($this->exts->exists('.mh-message-bar button.mh-close, #onetrust-banner-sdk .onetrust-close-btn-handler')) {
            $this->exts->moveToElementAndClick('.mh-message-bar button.mh-close, #onetrust-banner-sdk .onetrust-close-btn-handler');
            sleep(1);
        }
        
        $invoiceFileName = $invoice['invoiceName'].'.pdf';
        $downloaded_file = $this->exts->download_current($invoiceFileName, 1);
        if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
            $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
        } else {
            $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
        }
    }
}