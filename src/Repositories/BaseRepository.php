<?php

namespace Abix\DataFiltering\Repositories;

use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    /**
     * Model
     *
     * @var Model
     */
    protected $model;

    /**
     * Sets the model
     */
    public function __construct()
    {
        $this->model = $this->guessModel();
    }

    /**
     * Index
     *
     * @param array $data
     * @param User|null $user
     * @param Builder $query
     * @return Paginate
     */
    public function index(
        array $data,
        ?User $user = null,
        $query = null
    ) {
        return $this->model->filter($data, $user, $query)
            ->paginate(request()->per_page);
    }

    /**
     * Gets a single item
     *
     * @param mix $id
     * @return Model
     */
    public function get($id)
    {
        return $this->model->find($id);
    }

    /**
     * Creates a record
     *
     * @param array $attributes
     * @return Model
     */
    public function create(array $attributes)
    {
        return $this->model->create($attributes);
    }

    /**
     * Updates a model
     *
     * @param mix $id
     * @param array $attributes
     * @return Model
     */
    public function update($id, array $attributes)
    {
        if (!$id instanceof Model) {
            $id = $this->get($id);
        }
        return tap($id, function ($model) use ($attributes) {
            $model->update($attributes);
        });
    }

    /**
     * Deletes a record
     *
     * @param mix $id
     * @return bool
     */
    public function delete($id)
    {
        if (!$id instanceof Model) {
            $id = $this->get($id);
        }

        return $id->delete();
    }

    /**
     * Guesses the model
     *
     * @return Model
     */
    protected function guessModel(): Model
    {
        if ($this->model) {
            return new $this->model;
        }

        $model = (string) Str::of(class_basename($this))
            ->prepend('App\Models\\')
            ->replace('Repository', '');

        return new $model;
    }
}
