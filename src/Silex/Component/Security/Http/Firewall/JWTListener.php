<?php

namespace Silex\Component\Security\Http\Firewall;

use HttpEncodingException;
use Silex\Component\Security\Core\Encoder\TokenEncoderInterface;
use Silex\Component\Security\Http\Token\JWTToken;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;

class JWTListener implements ListenerInterface {

    /**
     * @var TokenStorageInterface
     */
    protected $securityContext;

    /**
     * @var AuthenticationManagerInterface
     */
    protected $authenticationManager;

    /**
     * @var TokenEncoderInterface
     */
    protected $encode;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var string
     */
    protected $providerKey;

    public function __construct(TokenStorageInterface $securityContext,
                                AuthenticationManagerInterface $authenticationManager,
                                TokenEncoderInterface $encoder,
                                array $options,
                                $providerKey)
    {
        $this->securityContext = $securityContext;
        $this->authenticationManager = $authenticationManager;
        $this->encode = $encoder;
        $this->options = $options;
        $this->providerKey = $providerKey;
    }

    /**
     * This interface must be implemented by firewall listeners.
     *
     * @param GetResponseEvent $event
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $requestToken = $this->getToken(
            $this->getRequestToken($request)
        );
        if (!empty($requestToken)) {
            try {
                $decoded = $this->encode->decode($requestToken);
                $user = null;
                if (isset($decoded->{$this->options['username_claim']})) {
                    $user = $decoded->{$this->options['username_claim']};
                }

                $token = new JWTToken(
                    $user,
                    $requestToken,
                    $this->providerKey
                );

                $authToken = $this->authenticationManager->authenticate($token);
                $this->securityContext->setToken($authToken);

            } catch (HttpEncodingException $e) {
                //var_dump($e->getMessage());
            } catch (\UnexpectedValueException $e) {
                //var_dump($e->getMessage());
            } catch(\Exception $e){
                //var_dump($e->getMessage());
            }
        }
    }

    /**
     * @param Request $request
     * @return array|mixed|string|null
     */
    private function getRequestToken(Request $request)
    {
        $token = $request->headers->get($this->options['header_name'], null);
        if (empty($token)) {
            $headerName = strtolower($this->options['header_name']);
            $apacheHeaders = apache_request_headers();
            if (isset($apacheHeaders[$headerName])) {
                $token = $apacheHeaders[$headerName];
            }
        }
        return $token;
    }

    /**
     * Convert token with prefix to normal token
     *
     * @param $requestToken
     *
     * @return string
     */
    protected function getToken($requestToken)
    {
        $prefix = $this->options['token_prefix'];
        if (null === $prefix) {
            return $requestToken;
        }

        if (null === $requestToken) {
            return $requestToken;
        }
        $requestToken = trim(str_replace($prefix, "", $requestToken));
        return $requestToken;
    }
}
