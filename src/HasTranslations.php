<?php

namespace KoenHoeijmakers\LaravelTranslatable;

use Illuminate\Support\Arr;
use KoenHoeijmakers\LaravelTranslatable\Exceptions\MissingTranslationsException;
use KoenHoeijmakers\LaravelTranslatable\Scopes\JoinTranslationScope;
use KoenHoeijmakers\LaravelTranslatable\Services\TranslationSavingService;

/**
 * Trait Translatable
 *
 * @package KoenHoeijmakers\LaravelTranslatable
 * @mixin \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
 */
trait HasTranslations
{
    /**
     * The current locale, used to handle internal states.
     *
     * @var string|null
     */
    protected $currentLocale = null;

    /**
     * Boot the translatable trait.
     *
     * @return void
     */
    public static function bootHasTranslations()
    {
        if (config('translatable.use_saving_service', true)) {
            static::saving(function (self $model) {
                app(TranslationSavingService::class)->rememberTranslationForModel($model);
            });

            static::saved(function (self $model) {
                app(TranslationSavingService::class)->storeTranslationOnModel($model);

                $model->refreshTranslation();
            });
        }

        static::addGlobalScope(new JoinTranslationScope());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations()
    {
        return $this->hasMany($this->getTranslationModel(), $this->getTranslationForeignKey());
    }

    /**
     * Check if the translation by the given locale exists.
     *
     * @param string $locale
     * @return bool
     */
    public function translationExists(string $locale)
    {
        return $this->translations()->where($this->getLocaleKeyName(), $locale)->exists();
    }

    /**
     * Get the translation model.
     *
     * @return string
     */
    public function getTranslationModel()
    {
        if (isset($this->translationModel)) {
            return $this->translationModel;
        }

        return get_class($this) . $this->getTranslationModelSuffix();
    }

    /**
     * Get the translation model suffix.
     *
     * @return string
     */
    protected function getTranslationModelSuffix()
    {
        return 'Translation';
    }

    /**
     * Get the translation table.
     *
     * @return string
     */
    public function getTranslationTable()
    {
        $model = $this->getTranslationModel();

        return (new $model())->getTable();
    }

    /**
     * Get the translation foreign key.
     *
     * @return string
     */
    public function getTranslationForeignKey()
    {
        if (isset($this->translationForeignKey)) {
            return $this->translationForeignKey;
        }

        return $this->getForeignKey();
    }

    /**
     * Get the translatable.
     *
     * @return array
     * @throws \KoenHoeijmakers\LaravelTranslatable\Exceptions\MissingTranslationsException
     */
    public function getTranslatable()
    {
        if (!isset($this->translatable)) {
            throw new MissingTranslationsException('Model "' . static::class . '" is missing translations');
        }

        return $this->translatable;
    }

    /**
     * Get the translatable attributes.
     *
     * @return array
     */
    public function getTranslatableAttributes()
    {
        return Arr::only($this->getAttributes(), $this->translatable);
    }

    /**
     * @param string $locale
     * @param array<string, mixed> $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function storeTranslation(string $locale, array $attributes = [])
    {
        if (!is_null($model = $this->translations()->where($this->getLocaleKeyName(), $locale)->first())) {
            $model->update($attributes);

            return $model;
        }

        $model = $this->translations()->make($attributes);
        $model->setAttribute($this->getLocaleKeyName(), $locale);
        $model->save();

        return $model;
    }

    /**
     * Store many translations at once.
     *
     * @param array<string, array> $translations
     * @return $this
     */
    public function storeTranslations(array $translations)
    {
        foreach ($translations as $locale => $translation) {
            $this->storeTranslation($locale, $translation);
        }

        return $this;
    }

    /**
     * @param string $locale
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function getTranslation(string $locale)
    {
        return $this->translations()->where($this->getLocaleKeyName(), $locale)->first();
    }

    /**
     * The locale key name.
     *
     * @return string
     */
    public function getLocaleKeyName()
    {
        return $this->localeKeyName ?? config('translatable.locale_key_name', 'locale');
    }

    /**
     * Get the locale.
     *
     * @return mixed|string
     */
    public function getLocale()
    {
        return $this->currentLocale ?? app()->getLocale();
    }

    /**
     * Refresh the translation (in the current locale).
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|\KoenHoeijmakers\LaravelTranslatable\HasTranslations|\KoenHoeijmakers\LaravelTranslatable\HasTranslations[]|null
     */
    public function refreshTranslation()
    {
        if (!$this->exists) {
            return null;
        }

        $attributes = Arr::only(
            static::findOrFail($this->getKey())->attributes, $this->getTranslatable()
        );

        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        $this->syncOriginal();

        return $this;
    }

    /**
     * Translate the model to the given locale.
     *
     * @param string $locale
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|\KoenHoeijmakers\LaravelTranslatable\HasTranslations|\KoenHoeijmakers\LaravelTranslatable\HasTranslations[]|null
     */
    public function translate(string $locale)
    {
        if (!$this->exists) {
            return null;
        }

        $this->currentLocale = $locale;

        return $this->refreshTranslation();
    }

    /**
     * Get a new query builder that doesn't have any global scopes (except the JoinTranslationScope).
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newQueryWithoutScopes()
    {
        $builder = $this->newEloquentBuilder($this->newBaseQueryBuilder());

        // Once we have the query builders, we will set the model instances so the
        // builder can easily access any information it may need from the model
        // while it is constructing and executing various queries against it.
        return $builder->setModel($this)
            ->withGlobalScope(JoinTranslationScope::class, new JoinTranslationScope())
            ->with($this->with)
            ->withCount($this->withCount);
    }
}
