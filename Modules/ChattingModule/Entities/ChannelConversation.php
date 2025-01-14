<?php

namespace Modules\ChattingModule\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Entities\User;

class ChannelConversation extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $fillable = [];

    //relation
    public function conversationFiles(): HasMany
    {
        return $this->hasMany(ConversationFile::class, 'conversation_id', 'id');
    }
    public function conversationLastFiles(): HasMany
    {
        return $this->hasMany(ConversationFile::class, 'conversation_id', 'id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ChannelList::class);
    }

    public function channel_users(): HasMany
    {
        return $this->hasMany(ChannelUser::class, 'channel_id', 'channel_id');
    }

    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            // ... code here
        });

        self::created(function ($model) {
            $model->channel_users
                ->where('user_id', '!=', $model->user_id)
                ->pluck('user_id')
                ->each(function ($item) use ($model) {
                    $to_user = User::find($item);
                    $user = User::with(['provider'])->find($model->user_id);
                    if (!$to_user || !$user) return;

                    $user_name = null;
                    $user_phone = null;
                    $user_image = null;
                    $user_type = null;
                    $userNotification = false;

                    if ($user->user_type == USER_TYPES[0]['value']) {
                        //if admin
                        $user_name = business_config('business_name', 'business_information')?->live_values;
                        $user_phone = business_config('business_phone', 'business_information')?->live_values;
                        $user_image = asset('storage/app/public/business') . '/' . business_config('business_favicon', 'business_information')?->live_values;
                        $user_type = USER_TYPES[0]['value'];
                        if ($to_user->user_type == USER_TYPES[2]['value']){
                            $servicemanNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'provider');
                            $userNotification = $servicemanNotification;
                        }
                        if ($to_user->user_type == USER_TYPES[3]['value']){
                            $servicemanNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'serviceman');
                            $userNotification = $servicemanNotification;
                        }
                        if ($to_user->user_type == USER_TYPES[4]['value']){
                            $servicemanNotification = isNotificationActive(null, 'chatting', 'notification', 'user');
                            $userNotification = $servicemanNotification;
                        }
                    } else if ($user->user_type == USER_TYPES[1]['value']) {
                        //if admin
                        $user_name = business_config('business_name', 'business_information')?->live_values;
                        $user_phone = business_config('business_phone', 'business_information')?->live_values;
                        $user_image = asset('storage/app/public/business') . '/' . business_config('business_favicon', 'business_information')?->live_values;
                        $user_type = USER_TYPES[1]['value'];
                        if ($to_user->user_type == USER_TYPES[2]['value']){
                            $servicemanNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'provider');
                            $userNotification = $servicemanNotification;
                        }
                        if ($to_user->user_type == USER_TYPES[3]['value']){
                            $servicemanNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'serviceman');
                            $userNotification = $servicemanNotification;
                        }
                        if ($to_user->user_type == USER_TYPES[4]['value']){
                            $servicemanNotification = isNotificationActive(null, 'chatting', 'notification', 'user');
                            $userNotification = $servicemanNotification;
                        }
                    }
                    elseif ($user->user_type == USER_TYPES[2]['value'] && $user->provider) {
                        $providerNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'provider');
                        if ($providerNotification) {
                            //if provider
                            $user_name = $user->provider->company_name;
                            $user_phone = $user->provider->company_phone;
                            $user_image = asset('storage/app/public/provider/logo') . '/' . $user->provider->logo;
                            $user_type = USER_TYPES[2]['value'];
                        }

                        $customerNotification = isNotificationActive(null, 'chatting', 'notification', 'user');
                        $userNotification = $customerNotification;
                        if ($to_user->user_type == USER_TYPES[3]['value']){
                            $servicemanNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'serviceman');
                            $userNotification = $servicemanNotification;
                        }

                    } else if ($user->user_type == USER_TYPES[3]['value']) {
                        $servicemanNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'serviceman');
                        if ($servicemanNotification) {
                            //if serviceman
                            $user_name = $user->first_name . ' ' . $user->last_name;
                            $user_phone = $user->phone;
                            $user_image = asset('storage/app/public/serviceman/profile') . '/' . $user->profile_image;
                            $user_type = USER_TYPES[3]['value'];
                            if ($to_user->user_type == USER_TYPES[2]['value']){
                                $servicemanNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'provider');
                                $userNotification = $servicemanNotification;
                            }
                            if ($to_user->user_type == USER_TYPES[4]['value']){
                                $servicemanNotification = isNotificationActive(null, 'chatting', 'notification', 'user');
                                $userNotification = $servicemanNotification;
                            }
                        }

                    } else if ($user->user_type == USER_TYPES[4]['value']) {
                        $customerNotification = isNotificationActive(null, 'chatting', 'notification', 'user');
                        if ($customerNotification) {
                            //if customer
                            $user_name = $user->first_name . ' ' . $user->last_name;
                            $user_phone = $user->phone;
                            $user_image = asset('storage/app/public/user/profile_image') . '/' . $user->profile_image;
                            $user_type = USER_TYPES[4]['value'];
                            $providerNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'provider');
                            $userNotification = $providerNotification;
                            if ($to_user->user_type == USER_TYPES[3]['value']){
                                $servicemanNotification = isNotificationActive($user?->provider?->id, 'chatting', 'notification', 'serviceman');
                                $userNotification = $servicemanNotification;
                            }
                        }

                    } else {
                        return;
                    }

                    if ($userNotification) {
                        device_notification_for_chatting(
                            $to_user->fcm_token,
                            translate('New message has been arrived'),
                            null,
                            null,
                            $model->channel_id,
                            $user_name,
                            $user_image,
                            $user_phone,
                            $user_type,
                            'chatting'
                        );
                    }
                });
        });

        self::updating(function ($model) {
            // ... code here
        });

        self::updated(function ($model) {
            // ... code here
        });

        self::deleting(function ($model) {
            // ... code here
        });

        self::deleted(function ($model) {
            // ... code here
        });
    }
}
