<?php

namespace Abix\DataFiltering\Services\Authorisation;

use Abix\DataFiltering\Exceptions\AuthorisationException;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;

class BaseAuthorisationService
{
    /**
     * Gets the value as boolean
     * If this is set to true, the service will not throw the exception
     *
     * @var bool
     */
    protected $skipExceptions = false;

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
    protected $user = null;

    /**
     * Array of properties
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Sets current property
     *
     * @var Model
     */
    protected $currentProperty = null;

    /**
     * Properties
     *
     * @param  array  $properties
     * @return self
     */
    public function setProperties(array $properties = []): self
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * Sets the model to authenticate
     *
     * @param  User  $user
     * @return self
     */
    public function setUser(?User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Gets the results as a boolean
     *
     * @return self
     */
    public function skipExceptions(): self
    {
        $this->skipExceptions = true;

        return $this;
    }

    /**
     * Verifies that the authenticatable model has the correct password
     *
     * @param  string  $password
     * @return self
     */
    public function passwordVerification(?string $password): self
    {
        if (! $password || ! $this->user || ! Hash::check($password, $this->user->password)) {
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
     * Gets a property
     *
     * @param  string  $property
     * @return mixed
     */
    public function getProperty(string $property): mixed
    {
        return $this->properties[$property] ?? null;
    }

    /**
     * Checks the validation has passed
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return (bool) count($this->errors);
    }

    /**
     * Throws the exception or adds the error to the bag
     *
     * @param  string  $message
     * @return void
     *
     * @throws AuthorisationException
     */
    protected function error(string $message): void
    {
        if ($this->skipExceptions) {
            $this->errors[] = $message;

            return;
        }

        throw new AuthorisationException($message);
    }
}
