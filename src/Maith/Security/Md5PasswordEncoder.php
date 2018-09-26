<?php

namespace Maith\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class Md5PasswordEncoder extends \Symfony\Component\Security\Core\Encoder\BasePasswordEncoder
{
	
	/**
     * {@inheritdoc}
     */
    public function encodePassword($raw, $salt)
    {
        if ($this->isPasswordTooLong($raw)) {
            throw new BadCredentialsException('Invalid password.');
        }
        return md5($raw);
    }

    /**
     * {@inheritdoc}
     */
    public function isPasswordValid($encoded, $raw, $salt)
    {
        return !$this->isPasswordTooLong($raw) && $this->comparePasswords($encoded, $this->encodePassword($raw, $salt));
    }
}