public $baseUrl = 'https://mein.lekker.de/';
public $loginUrl = 'https://mein.lekker.de/';
public $invoicePageUrl = '';

public $username_selector = 'input[id="login_username"]';
public $password_selector = 'input[id="login_password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form button[type="submit"][throttle-clicks]';

public $check_login_failed_selector = 'div.alert-danger p';
public $check_login_success_selector = 'a[href="/logout"]';

public $isNoInvoice = true;

/**
* Entry Method thats called for a portal
* @param Integer $count Number of times portal is retried.
*/
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->exts->openUrl($this->baseUrl);
    sleep(2);
    $this->exts->loadCookiesFromFile();

    sleep(10);
    // accecpt cookies  if exists
    if($this->exts->exists('aside[id="usercentrics-cmp-ui"]')){
        $this->switchToFrame('aside[id="usercentrics-cmp-ui"]');
        sleep(7);
        if($this->exts->exists('button[class="accept uc-accept-button"]')){
            $this->exts->moveToElementAndClick('button[class="accept uc-accept-button"]');
            sleep(7);
            $this->exts->refresh();
            sleep(7);
         }
    }

    // accecpt cookies again  if exists
    if($this->exts->exists('aside[id="usercentrics-cmp-ui"]')){
        $this->switchToFrame('aside[id="usercentrics-cmp-ui"]');
        sleep(7);
        if($this->exts->exists('button[class="accept uc-accept-button"]')){
            $this->exts->moveToElementAndClick('button[class="accept uc-accept-button"]');
            sleep(7);
            $this->exts->refresh();
            sleep(7);
         }
    }

   
    if (!$this->checkLogin()) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(10);
      
        $this->fillForm(0);

    }

    if ($this->checkLogin()) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");

        if (!empty($this->exts->config_array['allow_login_success_request'])) {
 
            $this->exts->triggerLoginSuccess();
        }

    } else {
        if (stripos($this->exts->extract($this->check_login_failed_selector), 'Fehlerhafte Zugangsdaten') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}


function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
    $this->exts->waitTillPresent($this->username_selector);
    try {
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(10);
            }

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
           
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(10);
            }
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

/**
* Method to Check where user is logged in or not
* return boolean true/false
*/
function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    try {

        $this->exts->waitTillPresent('a[class="dropdown-toggle"]');

        if ($this->exts->exists('a[class="dropdown-toggle"]')) {
            $this->exts->moveToElementAndClick('a[class="dropdown-toggle"]');
        }

        
        $this->exts->waitTillPresent($this->check_login_success_selector);
        if ($this->exts->exists($this->check_login_success_selector)) {

            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

            $isLoggedIn = true;
        }
    } catch (Exception $exception) {

        $this->exts->log("Exception checking loggedin " . $exception);
    }
    return $isLoggedIn;
}