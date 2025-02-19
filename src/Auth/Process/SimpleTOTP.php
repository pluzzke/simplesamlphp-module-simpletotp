<?php

namespace SimpleSAML\Module\simpletotp\Auth\Process;


use Exception;
use SimpleSAML\Auth\ProcessingFilter;
use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Error\Exception;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\Utils\HTTP;
use SimpleSAML\XHTML\Template;
use SimpleSAML\XML\Utils;

/**
 * This authentication processing filter allows you to perform a multi-factor-authentication against the privacyIDEA.
 *
 * @author Cornelius Kölbel <cornelius.koelbel@netknights.it>
 * @author Jean-Pierre Höhmann <jean-pierre.hoehmann@netknights.it>
 * @author Lukas Matusiewicz <lukas.matusiewicz@netknights.it>
 */
class SimpleTOTP extends ProcessingFilter
{/**
 * Attribute that stores the TOTP secret
 */
    private string $secret_attr = 'totp_secret';

    /**
     * Value of the TOTP secret
     */
    private ?string $secret_val = NULL;

    /**
     * Whether or not the user should be forced to use 2fa.
     *  If false, a user that does not have a TOTP secret will be able to continue
     *   authentication
     */
    private bool $enforce_2fa = false;

    /**
     * External URL to redirect user to if $enforce_2fa is true and they do not
     *  have a TOTP attribute set.  If this attribute is NULL, the user will
     *  be redirect to the internal error page.
     */
    private ?string $not_configured_url = NULL;

    /**
     * @param array $config Authproc configuration.
     * @param mixed $reserved
     * @throws ConfigurationError
     * @throws \Exception
     */
    public function __construct(array $config, mixed $reserved)
    {
        parent::__construct($config, $reserved);

        assert('is_array($config)');

        if (array_key_exists('enforce_2fa', $config)) {
            $this->enforce_2fa = $config['enforce_2fa'];
            if (!is_bool($this->enforce_2fa)) {
                throw new Exception('Invalid attribute name given to simpletotp::2fa filter:
 enforce_2fa must be a boolean.');
            }
        }

        if (array_key_exists('secret_attr', $config)) {
            $this->secret_attr = $config['secret_attr'];
            if (!is_string($this->secret_attr)) {
                throw new Exception('Invalid attribute name given to simpletotp::2fa filter:
 secret_attr must be a string');
            }
        }

        if (array_key_exists('not_configured_url', $config)) {
            $this->not_configured_url = $config['not_configured_url'];
            if (!is_string($config['not_configured_url'])) {
                throw new Exception('Invalid attribute value given to simpletotp::2fa filter:
 not_configured_url must be a string');
            }

            //validate URL to ensure it's we will be able to redirect to
            $this->not_configured_url = (new HTTP)->checkURLAllowed($config['not_configured_url']);
        }

    }

    /**
     * Run the filter.
     *
     * @param array $state The request state.
     * @throws Exception if authentication fails.
     * @throws \Exception
     */
    public function process(array &$state): void
    {
        assert('is_array($state)');
        assert('array_key_exists("Attributes", $state)');

        $attributes =& $state['Attributes'];

        // check for secret_attr coming from user store and make sure it is not empty
        if (array_key_exists($this->secret_attr, $attributes) && !empty($attributes[$this->secret_attr])) {
            $this->secret_val = $attributes[$this->secret_attr][0];
        }

        if ($this->secret_val === NULL && $this->enforce_2fa === true) {
            #2f is enforced and user does not have it configured..
            Logger::debug('User with ID xxx does not have 2f configured when it is
            mandatory for xxxSP');

            //send user to custom error page if configured
            if ($this->not_configured_url !== NULL) {
                (new HTTP)->redirectUntrustedURL($this->not_configured_url);
            } else {
                (new HTTP)->redirectTrustedURL(Module::getModuleURL('simpletotp/not_configured'));
            }

        } elseif ($this->secret_val === NULL && $this->enforce_2fa === false) {
            Logger::debug('User with ID xxx does not have 2f configured but SP does not
            require it. Continue.');
            return;
        }

        //as the attribute is configurable, we need to store it in a consistent location
        $state['2fa_secret'] = $this->secret_val;

        //this means we have secret_val configured for this session, time to 2fa
        $stateId  = State::saveState($state, 'simpletotp:request');
        $url = Module::getModuleURL('simpletotp/totp-post', ['stateId' => $stateId]);
        (new HTTP)->redirectTrustedURL($url, array('StateId' => $stateId));
    }
}