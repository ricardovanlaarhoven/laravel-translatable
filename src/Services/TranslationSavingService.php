<?php

namespace KoenHoeijmakers\LaravelTranslatable\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use KoenHoeijmakers\LaravelTranslatable\Contracts\Services\TranslationSavingServiceContract;

class TranslationSavingService implements TranslationSavingServiceContract
{
    /**
     * The application.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $translations = [];

    /**
     * TranslationSavingService constructor.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Remember the translation for the given model.
     *
     * @param \Illuminate\Database\Eloquent\Model|\KoenHoeijmakers\LaravelTranslatable\HasTranslations $model
     * @return void
     */
    public function rememberTranslationForModel(Model $model)
    {
        $attributes = $model->getTranslatableAttributes();

        $this->rememberTranslation($this->getModelIdentifier($model), $attributes);

        foreach (array_keys($attributes) as $attribute) {
            $model->offsetUnset($attribute);
        }
    }

    /**
     * Store the remembered translation for the given model.
     *
     * @param \Illuminate\Database\Eloquent\Model|\KoenHoeijmakers\LaravelTranslatable\HasTranslations $model
     * @return void
     */
    public function storeTranslationOnModel(Model $model)
    {
        $identifier = $this->getModelIdentifier($model);

        $model->storeTranslation(
            $this->app->getLocale(),
            $this->pullRememberedTranslation($identifier)
        );
    }

    /**
     * Remember the translation on the given key.
     *
     * @param mixed $key
     * @param array $attributes
     * @return \KoenHoeijmakers\LaravelTranslatable\Services\TranslationSavingService
     */
    public function rememberTranslation($key, array $attributes)
    {
        $this->translations[$key] = $attributes;

        return $this;
    }

    /**
     * Pull the translation on the given key.
     *
     * @param mixed $key
     * @return mixed
     */
    public function pullRememberedTranslation($key)
    {
        return $this->translations[$key];
    }

    /**
     * Get an unique identifier for the given model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return string
     */
    protected function getModelIdentifier(Model $model)
    {
        return spl_object_hash($model);
    }
}
