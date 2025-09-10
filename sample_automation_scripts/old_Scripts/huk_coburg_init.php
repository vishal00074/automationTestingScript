public $baseUrl = 'https://www.huk.de/login.do';
public $loginUrl = 'https://www.huk.de/login.do';
public $invoicePageUrl = '';

public $username_selector = 's-text-field input[type="email"],form#formZentralDataLogin input[name="TXT_B_KENNUNG"], input[name*="username-input"],input[name*="username"]';
public $password_selector = 'form#formZentralDataLogin input[name="TXT_PIN"], input[name*="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"][class="s-button__button"]';

public $check_login_failed_selector = '.message--error div.message__message';
public $check_login_success_selector = 'div.s-menu__menu [name="logout"]';

public $restrictPages = 3;
public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	
	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(30);
	if($this->exts->getElement('button.cookie-consent__button--primary') != null) {
		$this->exts->moveToElementAndClick('button.cookie-consent__button--primary');
		sleep(5);
	}
	$this->exts->capture('1-init-page');
	
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->openUrl($this->loginUrl);
		sleep(15);
		if($this->exts->exists('div[class*="login"] a[href*="start_kundenbereich"]')){
			$this->exts->log("Choose login button");
			$this->exts->moveToElementAndClick('div[class*="login"] a[href*="start_kundenbereich"]');
			sleep(25);
		}

		if($this->exts->exists('.s-huk-brand-header__right ul[slot="metaNavigationDesktop"] li a[href*="anmelden"]')){

			$this->exts->log("Choose login button new");
			$this->exts->moveToElementAndClick('.s-huk-brand-header__right ul[slot="metaNavigationDesktop"] li a[href*="anmelden"]');
			sleep(25);
		}

		$this->checkFillLogin(0);
		sleep(20);
		if ($this->exts->exists('huk-button[modifier="inverted"]')) {
			$this->exts->moveToElementAndClick('huk-button[modifier="inverted"]');
			sleep(20);
			$this->checkFillLogin(1);
			sleep(20);
		}
		$this->checkFillTwoFactor();
	}
	if($this->exts->urlContains('begruessung/uwg')){
		$button = $this->exts->getElementByText('s-button button', 'Ablehnen', null, false);
		if($button != null) {
			try {
				$button->click();
			} catch (Exception $e) {
				$this->exts->executeSafeScript('arguments[0].click()', [$button]);
			}
			sleep(5);
			$this->checkFillLogin(2);
			sleep(20);
			$this->checkFillTwoFactor();
		}
		// $this->exts->moveToElementAndClick('button[title=Ablehnen]');
	}
	sleep(10);
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->refresh();
		sleep(20);
	}
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		if (!empty($this->exts->config_array['allow_login_success_request'])) {

			$this->exts->triggerLoginSuccess();
		}

	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if(strpos(strtolower($this->getInnerTextByJS($this->check_login_failed_selector)), 'passwor') !== false) {
			$this->exts->loginFailure(1);
		} elseif(stripos(strtolower($this->exts->extract('div.s-banner__column--content  div.s-banner__supporting-text')), 'Das eingegebene Passwort ist falsch.') !== false){
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } elseif (stripos(strtolower($this->exts->extract('div.s-banner__column--content  div.s-banner__supporting-text')), 'Sie haben Ihr Passwort mehrfach falsch eingegeben. Ihr Konto haben wir deshalb aus SicherheitsgrÃ¼nden gesperrt. Bitte erstellen Sie sich unter folgendem Link neue Zugangsdaten:') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } elseif (stripos(strtolower($this->exts->extract('div[class="s-banner__wrapper"] div.s-banner__supporting-text')), 'Ihr Zugang ist gesperrt. Bitte erstellen Sie sich unter folgendem Link neue Zugangsdaten:') !== false) {
            $this->exts->log("Wrong credential !!!!");
            $this->exts->loginFailure(1);
        } 
		else {
			$this->exts->loginFailure();
		}
	}
}

private function checkFillLogin($count = 0) {

	$this->exts->log("checkFillLogin". $count);

	if($this->exts->getElement($this->username_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");
		
		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);
		$this->processWaitCaptcha();

		$this->exts->moveToElementAndClick('form s-button[variant="filled"] button');
		sleep(10);
		$this->processWaitCaptcha();

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);

		$this->processWaitCaptcha();

		if($this->remember_me_selector != '')
			$this->exts->moveToElementAndClick($this->remember_me_selector);
		sleep(2);
		
		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(3);
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false || strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'e-mail-adresse') !== false) {
			$this->exts->loginFailure(1);
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function processWaitCaptcha()
{
    $this->exts->waitTillPresent('button[class="frc-button"]', 10);
    for ($i = 0; $i < 30; $i++) {
        if ($this->exts->exists('div.frc-success')) {
            break;
        }
        if ($this->exts->exists('button[class="frc-button"]')) {
            $this->exts->moveToElementAndClick('button[class="frc-button"]');
        }
        $this->exts->waitTillPresent('div.frc-success', 30);
    }
}

private function checkFillTwoFactor() {
	$two_factor_selector = '[name="pin"] input#pin-input';
	$two_factor_message_selector = 'form > div > p.text--default';
	$two_factor_submit_selector = 'form [variant="cta"] button.button__button';

	if($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");

		if($this->exts->getElement($two_factor_message_selector) != null){
			$this->exts->two_factor_notif_msg_en = "";
			for ($i=0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) { 
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElements($two_factor_message_selector)[$i]->getText()."\n";
			}
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
			$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}

		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			$this->exts->getElement($two_factor_selector)->sendKeys($two_factor_code);
			
			$this->exts->log("checkFillTwoFactor: Clicking submit button.");
			sleep(3);
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(15);

			if($this->exts->getElement($two_factor_selector) == null){
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



function getInnerTextByJS($selector_or_object, $parent = null){
	if($selector_or_object == null){
		$this->exts->log(__FUNCTION__.' Can not get innerText of null');
		return;
	}
	$element = $selector_or_object;
	if(is_string($selector_or_object)){
		$element = $this->exts->getElement($selector_or_object, $parent);
		if($element == null){
			$element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
		}
		if($element == null){
			$this->exts->log(__FUNCTION__.':: Can not found element with selector/xpath: '. $selector_or_object);
		}
	}
	if ($element != null) {
		return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
	}
}