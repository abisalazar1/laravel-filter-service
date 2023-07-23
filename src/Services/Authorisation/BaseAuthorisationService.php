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
     * @var string
     */
    protected $mainProperty = null;

    /**
     * Gets a property
     */
    public function getProperty(string $property): mixed
    {
        return $this->properties[$property] ?? null;
    }

    /**
     * Gets current property
     */
    public function getMainProperty(): mixed
    {
        return $this->properties[$this->mainProperty];
    }

    /**
     * Sets current property
     */
    public function setMainProperty(mixed $property): self
    {
        $this->properties[$this->mainProperty] = $property;

        return $this;
    }

    /**
     * Properties
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
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Gets the results as a boolean
     */
    public function skipExceptions(): self
    {
        $this->skipExceptions = true;

        return $this;
    }

    /**
     * Check that if model belongs to user owner
     */
    public function doesItBelongToUser(string $property = null): self
    {
        $property = $property ? $this->getProperty($property) : $this->getMainProperty();

        if (! $this->user) {
            $this->error('Sorry user has not been set.');
        }

        if (! $property?->user_id || $property?->user_id !== $this->user->id) {
            $this->error('Item does not belong to this user.');
        }

        return $this;
    }

    /**
     * Verifies that the authenticatable model has the correct password
     *
     * @param  string  $password
     */
    public function passwordVerification(?string $password): self
    {
        if (! $password || ! $this->user || ! Hash::check($password, $this->user->password)) {
            $this->error('The provided credentials are incorrect.');
        }

        return $this;
    }

    /**
     * Requires a logged in user
     */
    public function requireUser(): self
    {
        if (! auth()->check()) {
            $this->error('Not authenticated.', 401);
        }

        return $this;
    }

    /**
     * Gets all the current errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Checks the validation has passed
     */
    public function isValid(): bool
    {
        return (bool) count($this->errors);
    }

    /**
     * Throws the exception or adds the error to the bag
     *
     *
     * @throws AuthorisationException
     */
    protected function error(string $message, int $code = 403): void
    {
        if ($this->skipExceptions) {
            $this->errors[] = $message;

            return;
        }

        throw new AuthorisationException($message, $code);
    }
}
