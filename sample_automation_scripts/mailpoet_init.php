public $baseUrl = 'https://account.mailpoet.com/login';
public $loginUrl = 'https://account.mailpoet.com/login';
public $invoicePageUrl = '';

public $username_selector = 'form[id="login-form"] input[id="email"]';
public $password_selector = 'form[id="login-form"] input[id="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form[id="login-form"] input[type="submit"]';

public $check_login_failed_selector = 'div[id="errors"], p[class="notification is-danger"]';
public $check_login_success_selector = 'a[href="/de/logout"]';

public $isNoInvoice = true;

/**

    * Entry Method thats called for a portal

    * @param Integer $count Number of times portal is retried.

    */
private function initPortal($count)
{

    $this->exts->log('Begin initPortal ' . $count);
    $this->disable_extensions();
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->loginUrl);
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);

        $this->fillForm(0);
    }

    sleep(5);
    $this->checkFillTwoFactor();

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
			$this->exts->triggerLoginSuccess();
		}

        $this->exts->success();
    } else {
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


private function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector, 5);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(1);
            }

            $this->exts->capture("1-login-page-filled");
            $this->exts->click_element($this->submit_login_selector);
            sleep(10);



            $is_captcha = $this->solve_captcha_by_clicking(0);
            if ($is_captcha) {
                for ($i = 1; $i < 15; $i++) {
                    if ($is_captcha == false || stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                        break;
                    }
                    $is_captcha = $this->solve_captcha_by_clicking($i);
                }
            }
            sleep(5);
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->click_element($this->submit_login_selector);
                sleep(5);
            }


            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            }
        }
    } catch (\Exception $exception) {

        $this->exts->log("Exception filling loginform " . $exception->getMessage());
    }
}

private function checkFillTwoFactor()
{
    $two_factor_selector = 'input[id="token"]';
    $two_factor_message_selector = '.login-verification p[class="content"]';
    $two_factor_submit_selector = 'input[id="token-submit"]';
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

private function solve_captcha_by_clicking($count = 1)
{
    $this->exts->log("Checking captcha");
    $captcha_wraper_selector = 'div[style*="visibility: visible;"] iframe[title="recaptcha challenge expires in two minutes"]';
    $this->exts->waitTillPresent($captcha_wraper_selector, 20);
    $language_code = '';
    if ($this->exts->exists($captcha_wraper_selector)) {
        $this->exts->capture("mailpoet-captcha");

        $captcha_instruction = $this->exts->makeFrameExecutable($captcha_wraper_selector)->extract('.rc-imageselect-desc-no-canonical');
        if (trim($captcha_instruction) == '') {
            $captcha_instruction = $this->exts->makeFrameExecutable($captcha_wraper_selector)->extract('.rc-imageselect-desc');
        }

        $this->exts->log('language_code: ' . $language_code . ' Instruction: ' . $captcha_instruction);
        sleep(5);
        if ($this->exts->exists($captcha_wraper_selector)) {
            $coordinates = $this->getCoordinates($captcha_wraper_selector, $captcha_instruction, '', $json_result = false);
            if ($coordinates != '') {

                foreach ($coordinates as $coordinate) {
                    $this->exts->click_by_xdotool($captcha_wraper_selector, (int) $coordinate['x'], (int) $coordinate['y']);
                }

                $this->exts->capture("mailpoet-captcha-selected " . $count);
                if ($this->exts->exists($captcha_wraper_selector)) {
                    $this->exts->makeFrameExecutable($captcha_wraper_selector)->click_element('button[id="recaptcha-verify-button"]');
                }
                sleep(15);
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

private function disable_extensions()
{
    $this->exts->openUrl('chrome://extensions/');
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

/**

    * Method to Check where user is logged in or not

    * return boolean true/false

    */
private function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }

    return $isLoggedIn;
}