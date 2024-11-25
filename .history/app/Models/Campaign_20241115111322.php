<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Campaign extends Model
{
    use HasFactory;
    protected $fillable = [
        'campaign_id',
        'advertiser_id',
        'advertiser_code',
        'device_type',
        'device_os',
        'campaign_name',
        'campaign_type',
        'ad_type',
        'ad_title',
        'ad_description',
        'target_url',
        'conversion_url',
        'website_category',
        'daily_budget',
        'country_ids',
        'country_name',
        'priority',
        'status',
        'trash',
        'updated_at',
        'countries',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'advertiser_code', 'uid');
    }
    public function category()
    {
        return $this->belongsTo(Category::class, 'website_category');
    }
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $original = $model->exists ? $model->getOriginal() : [];
            $changedData = [];
            $changedData1 = [];
            $actionType = $model->exists ? 2 : 1;
            $message = '';
            $userType = 1;
            $statusDescriptions = [
                0 => 'Incomplete',
                1 => 'InReview',
                2 => 'Active',
                3 => 'InActive',
                4 => 'Paused',
                5 => 'OnHold',
                6 => 'Suspended'
            ];

            $dirtyFields = $model->getDirty();
            if (empty($dirtyFields)) {
                return;
            }

            if ($model->exists && array_key_exists('status', $dirtyFields) && count($dirtyFields) === 1) {
                $message = 'Admin has changed the status!';
                $changedData['status'] = [
                    'previous' => $statusDescriptions[$original['status']],
                    'updated' => $statusDescriptions[$model->status],
                ];
                $userType = 2;
            }
            if ($model->exists && array_key_exists('updated_at', $dirtyFields) && array_key_exists('cpc_amt', $dirtyFields)) {
                $message = 'Admin has changed the Bidding Price!';
                $changedData['cpc_amt'] = [
                    'previous' => $original['cpc_amt'],
                    'updated' => $model->cpc_amt,
                ];
                $userType = 2;
            }

            if ($model->exists && array_key_exists('trash', $dirtyFields)) {
                $message = 'Admin has removed the campaign!';
                $changedData['trash'] = [
                    'previous' => $original['trash'],
                    'updated' => $model->trash,
                ];
                $userType = 2;  
            }

            if (empty($message)) {
                if ($model->exists) {
                    foreach ($dirtyFields as $key => $value) {
                        if (array_key_exists($key, $original) && $original[$key] !== $value) {
                            if ($key === 'website_category') {
                                $category = Category::find($value);
                                $categoryOld = Category::find($original[$key]);
                                $changedData[$key] = [
                                    'previous' => $categoryOld ? $categoryOld->cat_name : 'Unknown',
                                    'updated' => $category ? $category->cat_name : 'Unknown',
                                ];
                            } else if ($key === 'status') {
                                $changedData[$key] = [
                                    'previous' => $statusDescriptions[$original['status']],
                                    'updated' => $statusDescriptions[$model->status],
                                ];
                            } else if ($key === 'countries') {
                                $previousCountries = json_decode($original['countries'], true);
                                $updatedCountries = json_decode($model->countries, true);

                                if ($previousCountries && is_array($previousCountries)) {
                                    $prevCountriesString = implode(',', array_column($previousCountries, 'label'));
                                } else {
                                    $prevCountriesString = 'All';
                                }

                                if ($updatedCountries && is_array($updatedCountries)) {
                                    $updatedCountriesString = implode(',', array_column($updatedCountries, 'label'));
                                } else {
                                    $updatedCountriesString = $model->countries === 'All' ? 'All' : 'Unknown';  // Handle the case where there are no updated countries
                                }

                                $changedData['countries'] = [
                                    'previous' => $prevCountriesString,
                                    'updated' => $updatedCountriesString,
                                ];
                            } else {
                                $changedData[$key] = [
                                    'previous' => $original[$key],
                                    'updated' => $value,
                                ];
                            }
                        }
                    }
                    $message = 'User has updated the campaign!';
                    $changedData1['status'] = [
                        'previous' => $statusDescriptions[$original['status']],
                        'updated' => $statusDescriptions[1], 
                    ];
                } else {
                    $changedData['camp_created'] = [
                        'previous' => '----',
                        'updated' => '----',
                    ];
                    $message = 'User has created the campaign!';
                }
            }


          if (!empty($changedData)) {
                $filteredChangedData = array_filter($changedData, function ($change) {
                    return !empty($change['updated']);
                });
    
                if (!empty($filteredChangedData)) {
                    CampaignLogs::create([
                        'campaign_type' => $model->campaign_type,
                        'campaign_id' => $model->campaign_id,
                        'campaign_data' => json_encode(array_merge($filteredChangedData, ['message' => $message])),
                        'action' => $actionType,
                        'user_type' => $userType,
                    ]);
                }
            }
        });
    }
}
