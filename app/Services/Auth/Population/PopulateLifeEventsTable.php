<?php

/**
 * This is a single action class, totally inspired by
 * https://medium.com/@remi_collin/keeping-your-laravel-applications-dry-with-single-action-classes-6a950ec54d1d.
 */

namespace App\Services\Auth\Population;

use Illuminate\Support\Facades\DB;
use App\Services\BaseService;
use Illuminate\Support\Facades\App;
use App\Models\Account\Account;
use App\Models\Contact\LifeEventType;
use App\Models\Contact\LifeEventCategory;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Populate life event types and life event categories for a given account.
 * This is typically done when a new account is created.
 */
class PopulateLifeEventsTable extends BaseService
{
    /**
     * The structure that the method expects to receive as parameter.
     *
     * @var array
     */
    private $structure = [
        'account_id',
        'migrate_existing_data'
    ];

    /**
     * The data needed for the query to be executed.
     *
     * @var array
     */
    private $data;

    /**
     * Execute the service.
     *
     * @param array $data
     * @return void
     */
    public function execute(array $givenData)
    {
        $this->data = $givenData;

        if (!$this->validateDataStructure($this->data, $this->structure)) {
            throw new \Exception('Missing parameters');
        }

        $locale = $this->getLocaleOfAccount($this->data['account_id']);

        $this->createEntries($locale);
    }

    /**
     * Get the locale associated with the account.
     *
     * @return string
     */
    private function getLocaleOfAccount($accountId)
    {
        // get the account
        $account = Account::findOrFail($accountId);

        // get the locale
        return $account->getFirstLocale();
    }

    /**
     * Create life event category and life event type entries.
     *
     * @return void
     */
    private function createEntries($locale)
    {
        App::setLocale($locale);

        $defaultLifeEventCategories = $this->getDefaultLifeEventCategories();

        foreach ($defaultLifeEventCategories as $defaultLifeEventCategory) {
            $lifeEventCategory = $this->feedLifeEventCategory($defaultLifeEventCategory);

            $defaultLifeEventTypes = DB::table('default_life_event_types')
                ->where('default_life_event_category_id', $defaultLifeEventCategory->id)
                ->get();

            foreach ($defaultLifeEventTypes as $defaultLifeEventType) {
                $this->feedLifeEventType($defaultLifeEventType, $lifeEventCategory);
            }
        }
    }

    /**
     * Get the default life event categories.
     *
     * @throws QueryException if the query does not run for some reasons.
     * @return Collection
     */
    private function getDefaultLifeEventCategories()
    {
        try {
            if ($this->data['migrate_existing_data'] == 1) {
                $defaultLifeEventCategories = DB::table('default_life_event_categories')
                    ->get();
            } else {
                $defaultLifeEventCategories = DB::table('default_life_event_categories')
                    ->where('migrated', 0)
                    ->get();
            }
        } catch (QueryException $e) {
            throw new QueryException('Can not get default life event categories.');
        }

        return $defaultLifeEventCategories;
    }

    /**
     * Create an entry in the life event category table.
     *
     * @param Object $defaultLifeEventCategory
     * @return void
     */
    private function feedLifeEventCategory($defaultLifeEventCategory): LifeEventCategory
    {
        try {
            $lifeEventCategory = LifeEventCategory::create([
                'account_id' => $this->data['account_id'],
                'name' => trans($defaultLifeEventCategory->translation_key),
                'core_monica_data' => true,
            ]);
        } catch (QueryException $e) {
            throw new QueryException('Can not create a life event category.');
        }

        return $lifeEventCategory;
    }

    /**
     * Create an entry in the life event type table.
     *
     * @param Object $defaultLifeEventType
     * @return void
     */
    private function feedLifeEventType($defaultLifeEventType, $lifeEventCategory)
    {
        try {
            LifeEventType::create([
                'account_id' => $this->data['account_id'],
                'life_event_category_id' => $lifeEventCategory->id,
                'name' => trans($defaultLifeEventType->translation_key),
                'core_monica_data' => true,
            ]);
        } catch (QueryException $e) {
            throw new QueryException('Can not create a life event type.');
        }
    }
}
