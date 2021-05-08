<?php

namespace Abix\DataFiltering\Services\Authorisation;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use Abix\DataFiltering\Exceptions\AuthorisationException;

class BaseAuthorisationService
{
    /**
     * Gets the value as boolean
     * If this is set to true, the service will not throw the exception
     *
     * @var bool
     */
    protected $resultAsBoolean = false;

    /**
     * All Errors
     *
     * @var array
     */
    public $errors = [];

    /**
     * User
     *
     * @var User
     */
    protected $authenticatable = null;

    /**
     * Gets the results as a boolean
     *
     * @return self
     */
    public function resultAsBoolean()
    {
        $this->resultAsBoolean = true;

        return $this;
    }

    /**
     * Sets the model to authenticate
     *
     * @param User $authenticatable
     * @return self
     */
    public function setAuthenticatable(?User $authenticatable)
    {
        $this->authenticatable = $authenticatable;

        return $this;
    }

    /**
     * Verifies that the authenticatable model has the correct password
     *
     * @param string $password
     * @return self
     */
    public function passwordVerification(string $password): self
    {
        if (!Hash::check($password, optional($this->authenticatable)->password)) {
            $this->error('The provided credentials are incorrect.');
        }

        return $this;
    }

    /**
     * Gets all the current errors
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Checks the validation has passed
     *
     * @return boolean
     */
    public function isValid(): bool
    {
        return (bool) count($this->errors);
    }

    /**
     * Throws the exception or adds the error to the bag
     *
     * @param string $message
     * @throws AuthorisationException
     * @return void
     */
    protected function error(string $message): void
    {
        if ($this->resultAsBoolean) {
            $this->errors[] = $message;

            return;
        }

        throw new AuthorisationException($message);
    }
}
