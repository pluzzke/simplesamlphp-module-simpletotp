<?php

declare(strict_types=1);

namespace SimpleSAML\Module\simpletotp\Controller;

use PragmaRX\Google2FA\Google2FA;
use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Error\BadRequest;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\core\Auth\UserPassOrgBase;
use SimpleSAML\Session;
use SimpleSAML\Utils\HTTP;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class for the core module.
 *
 * This class serves the different views available in the module.
 *
 * @package SimpleSAML\Module\core
 */
class TotpProcessor
{

    /**
     * @return \SimpleSAML\XHTML\Template
     */
    public function process(Request $request): Template
    {
        if (!$request->query->has('stateId')) {
            throw new BadRequest('Missing AuthState parameter.');
        }

        $stateId = $request->query->get('stateId');
        State::validateStateId($stateId);
        $state = State::loadState($stateId, 'simpletotp:request', true);

        $parsedStateId = State::parseStateID($stateId);
        if (!is_null($parsedStateId['url'])) {
            (new HTTP)->checkURLAllowed($parsedStateId['url']);
        }

        $totpState = State::loadState($stateId, 'simpletotp:request', true);

        $session = Session::getSessionFromRequest();
        $authority = isset($state['\SimpleSAML\Module\core\Auth\UserPassBase.AuthId'])?$state['\SimpleSAML\Module\core\Auth\UserPassBase.AuthId']:$state['Authority'];
        $displayed_error = NULL;

        $sessionData = $session->getAuthState($authority);
        if (isset($sessionData['MFA_APPROVED']) && $sessionData['MFA_APPROVED'] === 1) {
            ProcessingChain::resumeProcessing($state);
        }

        if ($request->query->has('code')) {
            $inputCode = $request->query->get('code');
            if (!ctype_digit($_REQUEST['code'])) {
                $displayed_error = "A valid TOTP token consists of only numeric values.";
            } else {
                $code = (new Google2FA())->getCurrentOtp($state['2fa_secret']);
                Logger::debug("secret: " . $state['2fa_secret'] . " code entered: " . $inputCode . " actual code: $code");
                if ($code === $inputCode) {
                    $state['MFA_APPROVED'] = 1;
                    $session = Session::getSessionFromRequest();
                    Logger::debug("before");

                    $sessionData = $session->getAuthState($authority);
                    Logger::debug("after");
                    $sessionData['MFA_APPROVED'] = 1;
                    $session->doLogin($authority, $sessionData);
                    ProcessingChain::resumeProcessing($state);
                } else {
                    $displayed_error = "You have entered the incorrect TOTP token.";
                }
            }
        }

        $globalConfig = Configuration::getInstance();
        $template = new Template($globalConfig, 'simpletotp:authenticate');
        $template->data['formData'] = ['stateId' => $stateId];
        $template->data['formURL'] = Module::getModuleURL('simpletotp/totp-post', ['AuthState' => $stateId]);
        $template->data['userError'] = $displayed_error;
        $template->send();
        exit();
    }
}
