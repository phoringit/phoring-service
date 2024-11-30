<?php

namespace Modules\BookingModule\Http\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;
use Modules\BookingModule\Entities\BookingPartialPayment;
use Modules\BookingModule\Entities\BookingRepeat;
use Modules\BookingModule\Entities\BookingRepeatDetails;
use Modules\BookingModule\Entities\SubscriptionBookingType;
use Modules\BusinessSettingsModule\Entities\PackageSubscriber;
use Modules\CartModule\Entities\Cart;
use Modules\PaymentModule\Entities\OfflinePayment;
use Modules\PromotionManagement\Entities\Coupon;
use Modules\PromotionManagement\Entities\PushNotification;
use Modules\PromotionManagement\Entities\PushNotificationUser;
use Modules\UserManagement\Entities\User;
use Modules\BookingModule\Entities\Booking;
use Modules\ServiceManagement\Entities\Service;
use Modules\BookingModule\Entities\BookingDetail;
use Modules\ProviderManagement\Entities\Provider;
use Modules\BookingModule\Events\BookingRequested;
use Modules\BookingModule\Entities\BookingDetailsAmount;
use Modules\BookingModule\Entities\BookingOfflinePayment;
use Modules\BookingModule\Entities\BookingStatusHistory;
use Modules\BookingModule\Entities\BookingScheduleHistory;
use Modules\ProviderManagement\Entities\SubscribedService;
use Modules\BusinessSettingsModule\Entities\BusinessSettings;

trait BookingTrait
{
    //=============== PLACE BOOKING ===============

    /**
     * @param $userId
     * @param $request
     * @param $transactionId
     * @param int $isGuest
     * @return array|string[]
     */
    public function placeBookingRequest($userId, $request, $transactionId, int $isGuest = 0): array
    {
        $cartData = Cart::where(['customer_id' => $userId])->get();

        if ($cartData->count() == 0) {
            return ['flag' => 'failed', 'message' => 'no data found'];
        }

        $isPartials = $request['is_partial'] ? 1 : 0;
        $customerWalletBalance = User::find($userId)?->wallet_balance;
        if ($isPartials && $isGuest && ($customerWalletBalance <= 0 || $customerWalletBalance >= $cartData->sum('total_cost'))) {
            return ['flag' => 'failed', 'message' => 'Invalid data'];
        }

        $bookingIds = [];
        foreach ($cartData->pluck('sub_category_id')->unique() as $subCategory) {

            $booking = new Booking();

            DB::transaction(function () use ($subCategory, $booking, $transactionId, $request, $cartData, $userId, $isGuest, $isPartials, $customerWalletBalance) {
                $cartData = $cartData->where('sub_category_id', $subCategory);

                if ($request->has('payment_method') && $request['payment_method'] == 'cash_after_service') {
                    $transactionId = 'cash-payment';

                } else if ($request->has('payment_method') && $request['payment_method'] == 'wallet_payment') {
                    $transactionId = 'wallet-payment';
                }

                $totalBookingAmount = $cartData->sum('total_cost');

                $referralDiscount = 0;
                $zoneId = config('zone_id') == null ? $request['zone_id'] : config('zone_id');
                $referralDiscount += $this->referralEarningCalculationForFirstBooking($userId, $totalBookingAmount - $cartData->sum('tax_amount'), $zoneId);
                $totalBookingAmount -= $referralDiscount;

                $bookingAdditionalChargeStatus = business_config('booking_additional_charge', 'booking_setup')->live_values ?? 0;
                $extraFee = 0;
                if ($bookingAdditionalChargeStatus) {
                    $extraFee = business_config('additional_charge_fee_amount', 'booking_setup')->live_values ?? 0;
                }
                $totalBookingAmount += $extraFee;

                $booking->customer_id = $userId;
                $booking->provider_id = $cartData->first()->provider_id;
                $booking->category_id = $cartData->first()->category_id;
                $booking->sub_category_id = $subCategory;
                $booking->zone_id = $zoneId;
                $booking->booking_status = 'pending';
                $booking->is_paid = $request['payment_method'] == 'cash_after_service' || $request['payment_method'] == 'offline_payment' ? 0 : 1;
                $booking->payment_method = $request['payment_method'];
                $booking->transaction_id = $transactionId;
                $booking->total_booking_amount = $totalBookingAmount;
                $booking->total_tax_amount = $cartData->sum('tax_amount');
                $booking->total_discount_amount = $cartData->sum('discount_amount');
                $booking->total_campaign_discount_amount = $cartData->sum('campaign_discount');
                $booking->total_coupon_discount_amount = $cartData->sum('coupon_discount');
                $booking->coupon_code = $cartData->first()->coupon_code;
                $booking->service_schedule = date('Y-m-d H:i:s', strtotime($request['service_schedule'])) ?? now()->addHours(5);
                $booking->service_address_id = $request['service_address_id'] ?? '';
                $booking->booking_otp = rand(100000, 999999);
                $booking->is_guest = $isGuest;
                $booking->extra_fee = $extraFee;
                $booking->total_referral_discount_amount = $referralDiscount;
                $booking->save();


                if ($isPartials) {
                    $paidAmount = $customerWalletBalance;
                    $due_amount = $totalBookingAmount - $paidAmount;

                    $bookingPartialPayment = new BookingPartialPayment;
                    $bookingPartialPayment->booking_id = $booking->id;
                    $bookingPartialPayment->paid_with = 'wallet';
                    $bookingPartialPayment->paid_amount = $paidAmount;
                    $bookingPartialPayment->due_amount = $due_amount;
                    $bookingPartialPayment->save();

                    if ($request['payment_method'] != 'cash_after_service') {
                        $bookingPartialPayment = new BookingPartialPayment;
                        $bookingPartialPayment->booking_id = $booking->id;
                        $bookingPartialPayment->paid_with = $request['payment_method'];
                        $bookingPartialPayment->paid_amount = $due_amount;
                        $bookingPartialPayment->due_amount = 0;
                        $bookingPartialPayment->save();
                    }
                }

                foreach ($cartData->all() as $datum) {
                    $detail = new BookingDetail();
                    $detail->booking_id = $booking->id;
                    $detail->service_id = $datum['service_id'];
                    $detail->service_name = Service::find($datum['service_id'])->name ?? 'service-not-found';
                    $detail->variant_key = $datum['variant_key'];
                    $detail->quantity = $datum['quantity'];
                    $detail->service_cost = $datum['service_cost'];
                    $detail->discount_amount = $datum['discount_amount'];
                    $detail->campaign_discount_amount = $datum['campaign_discount'];
                    $detail->overall_coupon_discount_amount = $datum['coupon_discount'];
                    $detail->tax_amount = $datum['tax_amount'];
                    $detail->total_cost = $datum['total_cost'];
                    $detail->save();

                    $bookingDetailsAmount = new BookingDetailsAmount();
                    $bookingDetailsAmount->booking_details_id = $detail->id;
                    $bookingDetailsAmount->booking_id = $booking->id;
                    $bookingDetailsAmount->service_unit_cost = $datum['service_cost'];
                    $bookingDetailsAmount->service_quantity = $datum['quantity'];
                    $bookingDetailsAmount->service_tax = $datum['tax_amount'];
                    $bookingDetailsAmount->discount_by_admin = $this->calculate_discount_cost($datum['discount_amount'])['admin'];
                    $bookingDetailsAmount->discount_by_provider = $this->calculate_discount_cost($datum['discount_amount'])['provider'];
                    $bookingDetailsAmount->campaign_discount_by_admin = $this->calculate_campaign_cost($datum['campaign_discount'])['admin'];
                    $bookingDetailsAmount->campaign_discount_by_provider = $this->calculate_campaign_cost($datum['campaign_discount'])['provider'];
                    $bookingDetailsAmount->coupon_discount_by_admin = $this->calculate_coupon_cost($datum['coupon_discount'])['admin'];
                    $bookingDetailsAmount->coupon_discount_by_provider = $this->calculate_coupon_cost($datum['coupon_discount'])['provider'];
                    $bookingDetailsAmount->save();
                }

                $schedule = new BookingScheduleHistory();
                $schedule->booking_id = $booking->id;
                $schedule->changed_by = $userId;
                $schedule->is_guest = $isGuest;
                $schedule->schedule = date('Y-m-d H:i:s', strtotime($request['service_schedule'])) ?? now()->addHours(5);
                $schedule->save();

                $statusHistory = new BookingStatusHistory();
                $statusHistory->changed_by = $booking->id;
                $statusHistory->booking_id = $userId;
                $statusHistory->is_guest = $isGuest;
                $statusHistory->booking_status = isset($booking->provider_id) ? 'accepted' : 'pending';
                $statusHistory->save();



                if ($booking->booking_partial_payments->isNotEmpty()) {
                    if ($booking['payment_method'] == 'cash_after_service') {
                        placeBookingTransactionForPartialCas($booking);  // waller + CAS payment
                    } elseif ($booking['payment_method'] != 'wallet_payment') {
                        placeBookingTransactionForPartialDigital($booking);  //wallet + digital payment
                    }
                } elseif ($booking['payment_method'] == 'offline_payment') {
                    $customerInformation = (array)json_decode(base64_decode($request['customer_information']))[0];
                    $bookingOfflinePayment = new BookingOfflinePayment();
                    $bookingOfflinePayment->booking_id = $booking->id;
                    $bookingOfflinePayment->method_name = OfflinePayment::find($request['offline_payment_id'])?->method_name;
                    $bookingOfflinePayment->customer_information = $customerInformation;
                    $bookingOfflinePayment->save();
                } elseif ($booking['payment_method'] != 'cash_after_service' && $booking['payment_method'] != 'wallet_payment') {
                    placeBookingTransactionForDigitalPayment($booking);  //digital payment
                } elseif ($booking['payment_method'] != 'cash_after_service') {
                    placeBookingTransactionForWalletPayment($booking);   //wallet payment
                }

                //firebaseTopic
                $bookingNotification = (int) (business_config('booking_notification', 'business_information'))?->live_values;
                $bookingNotificationType = (business_config('booking_notification_type', 'business_information'))?->live_values;
                if ($bookingNotification && $bookingNotificationType == 'firebase') {
                    try {
                        $topic = 'demandium_provider_' . $booking->zone_id . '_booking_message';
                        topic_notification($topic, 'new booking', '', 'def.png', null);
                    } catch (Exception $e) {
                    }
                }


                $maximumBookingAmount = (business_config('max_booking_amount', 'booking_setup'))?->live_values;

                $bookingNotificationStatus = business_config('booking', 'notification_settings')->live_values;
                if ($booking->payment_method == 'cash_after_service') {
                    if ($maximumBookingAmount > 0 && $booking->total_booking_amount < $maximumBookingAmount) {
                        if (isset($booking->provider_id) && $booking->booking_status != 'pending') {
                            $provider = Provider::with('owner')->whereId($booking->provider_id)->first();
                            $fcmToken = $provider?->owner->fcm_token ?? null;
                            $languageKey = $provider?->owner?->current_language_key;
                            if (!is_null($fcmToken) && isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking']) {
                                $notification = isNotificationActive($provider?->id, 'booking', 'notification', 'provider');
                                $title = get_push_notification_message('booking_accepted', 'provider_notification', $languageKey);
                                if ($title && sendDeviceNotificationPermission($booking?->provider_id) && $notification) {
                                        device_notification($fcmToken, $title, null, null, $booking->id, 'booking');
                                }
                            }
                        } else {
                            $providerIds = SubscribedService::where('sub_category_id', $subCategory)->ofSubscription(1)->pluck('provider_id')->toArray();
                            if (business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values) {
                                $providers = Provider::with('owner')->whereIn('id', $providerIds)->where('zone_id', $booking?->zone_id)->where('is_suspended', 0)->get();
                            } else {
                                $providers = Provider::with('owner')->whereIn('id', $providerIds)->where('zone_id', $booking?->zone_id)->get();
                            }

                            foreach ($providers as $provider) {
                                $fcmToken = $provider->owner->fcm_token ?? null;
                                $notification = isNotificationActive($provider?->id, 'booking', 'notification', 'provider');
                                $title = get_push_notification_message('new_service_request_arrived', 'provider_notification', optional($provider->owner)->current_language_key);

                                if (!is_null($fcmToken) && $provider->service_availability && $title && $notification && isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking'] && sendDeviceNotificationPermission($provider->id)) {
                                    device_notification($fcmToken, $title, null, null, $booking->id, 'booking');
                                }
                            }

                        }
                    }
                } else {
                    if (isset($booking->provider_id)) {
                        $provider = Provider::with('owner')->whereId($booking->provider_id)->first();
                        $fcmToken = $provider?->owner?->fcm_token ?? null;
                        $languageKey = $provider?->owner?->current_language_key;
                        if (!is_null($fcmToken)) {
                            $title = get_push_notification_message('booking_accepted', 'provider_notification', $languageKey);
                            if ($title && $fcmToken && isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking'] && sendDeviceNotificationPermission($booking?->provider_id)) {
                                device_notification($fcmToken, $title, null, null, $booking->id, 'booking');
                            }
                        }
                    } else {
                        $providerIds = SubscribedService::where('sub_category_id', $subCategory)->ofSubscription(1)->pluck('provider_id')->toArray();
                        if (business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values) {
                            $providers = Provider::with('owner')->whereIn('id', $providerIds)->where('zone_id', $booking->zone_id)->where('is_suspended', 0)->get();
                        } else {
                            $providers = Provider::with('owner')->whereIn('id', $providerIds)->where('zone_id', $booking->zone_id)->get();
                        }
                        foreach ($providers as $provider) {
                            $fcmToken = $provider->owner->fcm_token ?? null;
                            $title = get_push_notification_message('new_service_request_arrived', 'provider_notification', $provider?->owner?->current_language_key);
                            if (!is_null($fcmToken) && $provider?->service_availability && $title && isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking'] && sendDeviceNotificationPermission($booking?->provider_id)) device_notification($fcmToken, $title, null, null, $booking->id, 'booking');
                        }
                    }
                }
            });
            $bookingIds[] = $booking->id;
        }

        cart_clean($userId);
        event(new BookingRequested($booking));

        return [
            'flag' => 'success',
            'booking_id' => $bookingIds,
            'readable_id' => $booking->readable_id
        ];
    }
    public function placeRepeatBookingRequest($userId, $request, $transactionId, int $isGuest = 0): array
    {
        $cartData = Cart::where(['customer_id' => $userId])->get();

        if ($cartData->count() == 0) {
            return ['flag' => 'failed', 'message' => 'no data found'];
        }

        $bookingIds = [];
        foreach ($cartData->pluck('sub_category_id')->unique() as $subCategory) {

            $booking = new Booking();

            DB::transaction(function () use ($subCategory, $booking, $transactionId, $request, $cartData, $userId, $isGuest) {
                $cartData = $cartData->where('sub_category_id', $subCategory);

                if ($request->has('payment_method') && $request['payment_method'] == 'cash_after_service') {
                    $transactionId = 'cash-payment';

                }

                $totalBookingAmount = $cartData->sum('total_cost');

                $referralDiscount = 0;
                $zoneId = config('zone_id') == null ? $request['zone_id'] : config('zone_id');
                $referralDiscount += $this->referralEarningCalculationForFirstBooking($userId, $totalBookingAmount - $cartData->sum('tax_amount'), $zoneId);
                $totalBookingAmount -= $referralDiscount;

                $bookingAdditionalChargeStatus = business_config('booking_additional_charge', 'booking_setup')->live_values ?? 0;
                $extraFee = 0;
                if ($bookingAdditionalChargeStatus) {
                    $extraFee = (int) business_config('additional_charge_fee_amount', 'booking_setup')->live_values ?? 0;
                }

                $repeatBookingSchedule = json_decode($request['dates'], true);
                $totalDate = count($repeatBookingSchedule);
                $suffixes = range('A', 'Z');

                $coupon = Coupon::where('coupon_code', $cartData->first()->coupon_code)->first();
                $repeatCount = $this->booking->where('customer_id', $userId)
                    ->where('coupon_code', $cartData->first()->coupon_code)
                    ->get()
                    ->sum(function ($booking) use ($cartData) {
                        $repeatCount = $booking->repeat()
                            ->where('coupon_code', $cartData->first()->coupon_code)
                            ->count();

                        return $repeatCount > 0 ? $repeatCount : 1;
                    });

                $totalUsedCount = $repeatCount;

                $maxCouponUsagePerUser = max(0, $coupon?->discount?->limit_per_user - $totalUsedCount);
                $totalDiscount = 0;
                if ($maxCouponUsagePerUser >= $totalDate){
                    $totalDiscount = $cartData->sum('coupon_discount') * $totalDate;
                }else{
                    $totalDiscount = $cartData->sum('coupon_discount') * $maxCouponUsagePerUser;
                }

                $booking->customer_id = $userId;
                $booking->provider_id = $cartData->first()->provider_id;
                $booking->category_id = $cartData->first()->category_id;
                $booking->sub_category_id = $subCategory;
                $booking->zone_id = $zoneId;
                $booking->booking_status = 'pending';
                $booking->payment_method = $request['payment_method'];
                $booking->total_booking_amount = ($totalBookingAmount * $totalDate) + $extraFee;
                $booking->total_tax_amount = $cartData->sum('tax_amount') * $totalDate;
                $booking->total_discount_amount = $cartData->sum('discount_amount') * $totalDate;
                $booking->total_campaign_discount_amount = $cartData->sum('campaign_discount') * $totalDate;
                $booking->total_coupon_discount_amount = $totalDiscount;
                $booking->extra_fee = $extraFee;
                $booking->total_referral_discount_amount = $referralDiscount;
                $booking->coupon_code = $cartData->first()->coupon_code;
                $booking->service_address_id = $request['service_address_id'] ?? '';
                $booking->is_guest = $isGuest;
                $booking->assigned_by = $cartData->first()->provider_id ? 'customer' : null;
                $booking->is_repeated = 1;
                $booking->save();

                foreach ($cartData as $data) {
                    $detail = new BookingDetail();
                    $detail->booking_id = $booking->id;
                    $detail->service_id = $data['service_id'];
                    $detail->service_name = Service::find($data['service_id'])->name ?? 'service-not-found';
                    $detail->variant_key = $data['variant_key'];
                    $detail->quantity = $data['quantity'];
                    $detail->service_cost = $data['service_cost'];
                    $detail->discount_amount = $data['discount_amount'];
                    $detail->campaign_discount_amount = $data['campaign_discount'];
                    $detail->overall_coupon_discount_amount = $data['coupon_discount'];
                    $detail->tax_amount = $data['tax_amount'];
                    $detail->total_cost = $data['total_cost'];
                    $detail->save();
                }

                foreach ($repeatBookingSchedule as $index => $repeat) {
                    $suffix = $this->getSuffix($index);

                    $repeatBooking = new BookingRepeat();
                    $repeatBooking->booking_id = $booking->id;
                    $repeatBooking->provider_id = $cartData->first()->provider_id;
                    $repeatBooking->booking_type = $request['booking_type'];
                    $repeatBooking->transaction_id = $transactionId;
                    $repeatBooking->booking_status = 'pending';
                    $repeatBooking->payment_method = $request['payment_method'];
                    $repeatBooking->service_schedule = date('Y-m-d H:i:s', strtotime($repeat['date'])) ?? now()->addHours(5);
                    $repeatBooking->total_booking_amount = $index < 1 ? $totalBookingAmount + $extraFee : $totalBookingAmount;
                    $repeatBooking->total_tax_amount = $cartData->sum('tax_amount');
                    $repeatBooking->total_discount_amount = $cartData->sum('discount_amount');
                    $repeatBooking->total_campaign_discount_amount = $cartData->sum('campaign_discount');
                    if ($index < $maxCouponUsagePerUser && $coupon) {
                        $repeatBooking->total_coupon_discount_amount = $cartData->sum('coupon_discount');
                        $repeatBooking->coupon_code = $cartData->first()['coupon_code'];
                    } else {
                        $repeatBooking->total_coupon_discount_amount = 0;
                        $repeatBooking->coupon_code = null;
                    }
                    $repeatBooking->extra_fee = $index < 1 ? $extraFee : 0;
                    $repeatBooking->total_referral_discount_amount = $index < 1 ? $referralDiscount : 0;
                    $repeatBooking->booking_otp = rand(100000, 999999);
                    $repeatBooking->readable_id = $booking->readable_id . '-' . $suffix;
                    $repeatBooking->save();

                    $schedule = new BookingScheduleHistory();
                    $schedule->booking_id = $booking->id;
                    $schedule->booking_repeat_id = $repeatBooking->id;
                    $schedule->changed_by = $userId;
                    $schedule->is_guest = $isGuest;
                    $schedule->schedule = date('Y-m-d H:i:s', strtotime($repeat['date'])) ?? now()->addHours(5);
                    $schedule->save();

                    foreach ($cartData as $datum) {
                        $repeatBookingDetails = new BookingRepeatDetails();
                        $repeatBookingDetails->booking_repeat_id = $repeatBooking->id;
                        $repeatBookingDetails->booking_id = $booking->id;
                        $repeatBookingDetails->service_id = $datum['service_id'];
                        $repeatBookingDetails->service_name = Service::find($datum['service_id'])->name ?? 'service-not-found';
                        $repeatBookingDetails->variant_key = $datum['variant_key'];
                        $repeatBookingDetails->quantity = $datum['quantity'];
                        $repeatBookingDetails->service_cost = $datum['service_cost'];
                        $repeatBookingDetails->discount_amount = $datum['discount_amount'];
                        $repeatBookingDetails->campaign_discount_amount = $datum['campaign_discount'];
                        if ($index <= $maxCouponUsagePerUser && $coupon) {
                            $repeatBookingDetails->overall_coupon_discount_amount = $datum['coupon_discount'];
                        } else {
                            $repeatBookingDetails->overall_coupon_discount_amount = 0;
                        }
                        $repeatBookingDetails->tax_amount = $datum['tax_amount'];
                        $repeatBookingDetails->total_cost = $datum['total_cost'];
                        $repeatBookingDetails->save();

                        $bookingDetailsAmount = new BookingDetailsAmount();
                        $bookingDetailsAmount->booking_details_id = 0;
                        $bookingDetailsAmount->booking_repeat_details_id = $repeatBookingDetails->id;
                        $bookingDetailsAmount->booking_id = $booking->id;
                        $bookingDetailsAmount->booking_repeat_id = $repeatBooking->id;
                        $bookingDetailsAmount->service_unit_cost = $datum['service_cost'];
                        $bookingDetailsAmount->service_quantity = $datum['quantity'];
                        $bookingDetailsAmount->service_tax = $datum['tax_amount'];
                        $bookingDetailsAmount->discount_by_admin = $this->calculate_discount_cost($datum['discount_amount'])['admin'];
                        $bookingDetailsAmount->discount_by_provider = $this->calculate_discount_cost($datum['discount_amount'])['provider'];
                        $bookingDetailsAmount->campaign_discount_by_admin = $this->calculate_campaign_cost($datum['campaign_discount'])['admin'];
                        $bookingDetailsAmount->campaign_discount_by_provider = $this->calculate_campaign_cost($datum['campaign_discount'])['provider'];
                        if ($index <= $maxCouponUsagePerUser && $coupon) {
                            $bookingDetailsAmount->coupon_discount_by_admin = $this->calculate_coupon_cost($datum['coupon_discount'])['admin'];
                            $bookingDetailsAmount->coupon_discount_by_provider = $this->calculate_coupon_cost($datum['coupon_discount'])['provider'];
                        }else{
                            $bookingDetailsAmount->coupon_discount_by_admin = 0;
                            $bookingDetailsAmount->coupon_discount_by_provider = 0;
                        }
                        $bookingDetailsAmount->save();
                    }
                }

                //firebaseTopic
                $bookingNotification = (int) (business_config('booking_notification', 'business_information'))?->live_values;
                $bookingNotificationType = (business_config('booking_notification_type', 'business_information'))?->live_values;
                if ($bookingNotification && $bookingNotificationType == 'firebase') {
                    try {
                        $topic = 'demandium_provider_' . $booking->zone_id . '_booking_message';
                        topic_notification($topic, 'new booking', '', 'def.png', null);
                    } catch (Exception $e) {
                    }
                }


                $maximumBookingAmount = (business_config('max_booking_amount', 'booking_setup'))?->live_values;

                $bookingNotificationStatus = business_config('booking', 'notification_settings')->live_values;
                if ($booking->payment_method == 'cash_after_service') {
                    if ($maximumBookingAmount > 0 && $booking->total_booking_amount < $maximumBookingAmount) {
                        if (isset($booking->provider_id) && $booking->booking_status != 'pending') {
                            $provider = Provider::with('owner')->whereId($booking->provider_id)->first();
                            $fcmToken = $provider?->owner->fcm_token ?? null;
                            $repeatOrRegular = $booking?->is_repeated ? 'repeat' : 'regular';
                            $languageKey = $provider?->owner?->current_language_key;
                            if (!is_null($fcmToken) && isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking']) {
                                $notification = isNotificationActive($provider?->id, 'booking', 'notification', 'provider');
                                $title = get_push_notification_message('booking_accepted', 'provider_notification', $languageKey);
                                if ($title && sendDeviceNotificationPermission($booking?->provider_id) && $notification) {
                                        device_notification($fcmToken, $title, null, null, $booking->id, 'booking', null, null, null, null, $repeatOrRegular);
                                }
                            }
                        } else {
                            $providerIds = SubscribedService::where('sub_category_id', $subCategory)->ofSubscription(1)->pluck('provider_id')->toArray();
                            if (business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values) {
                                $providers = Provider::with('owner')->whereIn('id', $providerIds)->where('zone_id', $booking?->zone_id)->where('is_suspended', 0)->get();
                            } else {
                                $providers = Provider::with('owner')->whereIn('id', $providerIds)->where('zone_id', $booking?->zone_id)->get();
                            }

                            foreach ($providers as $provider) {
                                $fcmToken = $provider->owner->fcm_token ?? null;
                                $repeatOrRegular = $booking?->is_repeated ? 'repeat' : 'regular';
                                $notification = isNotificationActive($provider?->id, 'booking', 'notification', 'provider');
                                $title = get_push_notification_message('new_service_request_arrived', 'provider_notification', optional($provider->owner)->current_language_key);

                                if (!is_null($fcmToken) && $provider->service_availability && $title && $notification && isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking'] && sendDeviceNotificationPermission($provider->id)) {
                                    device_notification($fcmToken, $title, null, null, $booking->id, 'booking', null, null, null, null, $repeatOrRegular);
                                }
                            }

                        }
                    }
                }
            });
            $bookingIds[] = $booking->id;
        }

        cart_clean($userId);
        event(new BookingRequested($booking));

        return [
            'flag' => 'success',
            'booking_id' => $bookingIds,
            'readable_id' => $booking->readable_id
        ];
    }

    function getSuffix($index): string
    {
        $suffixes = range('A', 'Z');
        $base = count($suffixes);
        $result = '';
        do {
            $result = $suffixes[$index % $base] . $result;
            $index = floor($index / $base) - 1;
        } while ($index >= 0);
        return $result;
    }

    /**
     * @param $customerUserId
     * @param $request
     * @param $transactionId
     * @param $data
     * @return array
     */

    protected function placeBookingRequestForBidding($customerUserId, $request, $transactionId, $data): array
    {
        $booking = new Booking();

        DB::transaction(function () use ($booking, $transactionId, $request, $customerUserId, $data) {

            if ($request->has('payment_method') && $request['payment_method'] == 'cash_after_service') {
                $transactionId = 'cash-payment';

            } else if ($request->has('payment_method') && $request['payment_method'] == 'wallet_payment') {
                $transactionId = 'wallet-payment';
            }

            $totalBookingAmount = $data['price'];

            $referralDiscount = 0;
            $zoneId = $data['zone_id'];
            $referralDiscount += $this->referralEarningCalculationForFirstBooking($customerUserId, $totalBookingAmount, $zoneId);
            $totalBookingAmount -= $referralDiscount;

            $tax = !is_null($data['service_tax']) ? round((($data['price'] * $data['service_tax']) / 100) * 1, 2) : 0; //

            $totalBookingAmount += $tax;
            $isPartials = $data['is_partial'] ? 1 : 0;
            $customerWalletBalance = User::find($customerUserId)?->wallet_balance;
            if ($isPartials && ($customerWalletBalance <= 0 || $customerWalletBalance >= $totalBookingAmount)) {
                return ['flag' => 'failed', 'message' => 'Invalid data'];
            }

            $bookingAdditionalChargeStatus = business_config('booking_additional_charge', 'booking_setup')->live_values ?? 0;
            $extraFee = 0;
            if ($bookingAdditionalChargeStatus) {
                $extraFee = business_config('additional_charge_fee_amount', 'booking_setup')->live_values ?? 0;
            }

            $totalBookingAmount += $extraFee;

            $booking->customer_id = $customerUserId;
            $booking->provider_id = $data['provider_id'];
            $booking->category_id = $data['category_id'];
            $booking->sub_category_id = $data['sub_category_id'];
            $booking->zone_id = $data['zone_id'];
            $booking->booking_status = 'accepted';
            $booking->is_paid = $data['payment_method'] == 'cash_after_service' || $request['payment_method'] == 'offline_payment' ? 0 : 1;;
            $booking->payment_method = $data['payment_method'];
            $booking->transaction_id = $transactionId;
            $booking->total_booking_amount = $totalBookingAmount;
            $booking->total_tax_amount = $tax;
            $booking->total_discount_amount = 0;
            $booking->total_campaign_discount_amount = 0;
            $booking->total_coupon_discount_amount = 0;
            $booking->service_schedule = date('Y-m-d H:i:s', strtotime($data['service_schedule'])) ?? now()->addHours(5);
            $booking->service_address_id = $data['service_address_id'] ?? '';
            $booking->booking_otp = rand(100000, 999999);
            $booking->is_guest = 0;
            $booking->extra_fee = $extraFee;
            $booking->total_referral_discount_amount = $referralDiscount;
            $booking->save();

            if ($isPartials) {
                $paidAmount = $customerWalletBalance;
                $due_amount = $totalBookingAmount - $paidAmount;

                $bookingPartialPayment = new BookingPartialPayment;
                $bookingPartialPayment->booking_id = $booking->id;
                $bookingPartialPayment->paid_with = 'wallet';
                $bookingPartialPayment->paid_amount = $paidAmount;
                $bookingPartialPayment->due_amount = $due_amount;
                $bookingPartialPayment->save();

                if ($request['payment_method'] != 'cash_after_service') {
                    $bookingPartialPayment = new BookingPartialPayment;
                    $bookingPartialPayment->booking_id = $booking->id;
                    $bookingPartialPayment->paid_with = 'digital';
                    $bookingPartialPayment->paid_amount = $due_amount;
                    $bookingPartialPayment->due_amount = 0;
                    $bookingPartialPayment->save();
                }
            }

            $detail = new BookingDetail();
            $detail->booking_id = $booking->id;
            $detail->service_id = $data['service_id'];
            $detail->service_name = Service::find($data['service_id'])->name ?? 'service-not-found';
            $detail->variant_key = null;
            $detail->quantity = 1;
            $detail->service_cost = $data['price'];
            $detail->discount_amount = 0;
            $detail->campaign_discount_amount = 0;
            $detail->overall_coupon_discount_amount = 0;
            $detail->tax_amount = $tax;
            $detail->total_cost = $totalBookingAmount;
            $detail->save();

            $bookingDetailsAmount = new BookingDetailsAmount();
            $bookingDetailsAmount->booking_details_id = $detail->id;
            $bookingDetailsAmount->booking_id = $booking->id;
            $bookingDetailsAmount->service_unit_cost = $data['price'];
            $bookingDetailsAmount->service_quantity = 1;
            $bookingDetailsAmount->service_tax = $tax;
            $bookingDetailsAmount->discount_by_admin = 0;
            $bookingDetailsAmount->discount_by_provider = 0;
            $bookingDetailsAmount->campaign_discount_by_admin = 0;
            $bookingDetailsAmount->campaign_discount_by_provider = 0;
            $bookingDetailsAmount->coupon_discount_by_admin = 0;
            $bookingDetailsAmount->coupon_discount_by_provider = 0;
            $bookingDetailsAmount->admin_commission = 0;
            $bookingDetailsAmount->save();

            $schedule = new BookingScheduleHistory();
            $schedule->booking_id = $booking->id;
            $schedule->changed_by = $customerUserId;
            $schedule->schedule = date('Y-m-d H:i:s', strtotime($data['service_schedule'])) ?? now()->addHours(5);
            $schedule->save();

            $statusHistory = new BookingStatusHistory();
            $statusHistory->changed_by = $booking->id;
            $statusHistory->booking_id = $customerUserId;
            $statusHistory->booking_status = isset($booking->provider_id) ? 'accepted' : 'pending';
            $statusHistory->save();

            if ($booking->booking_partial_payments->isNotEmpty()) {
                if ($booking['payment_method'] == 'cash_after_service') {
                    placeBookingTransactionForPartialCas($booking);  // waller + CAS payment
                } elseif ($booking['payment_method'] != 'wallet_payment') {
                    placeBookingTransactionForPartialDigital($booking);  //wallet + digital payment
                }
            } elseif ($booking['payment_method'] == 'offline_payment') {
                $customerInformation = (array)json_decode(base64_decode($request['customer_information']))[0];
                $bookingOfflinePayment = new BookingOfflinePayment();
                $bookingOfflinePayment->booking_id = $booking->id;
                $bookingOfflinePayment->method_name = OfflinePayment::find($request['offline_payment_id'])?->method_name;
                $bookingOfflinePayment->customer_information = $customerInformation;
                $bookingOfflinePayment->save();
            } elseif ($booking['payment_method'] != 'cash_after_service' && $booking['payment_method'] != 'wallet_payment') {
                placeBookingTransactionForDigitalPayment($booking);  //digital payment
            } elseif ($booking['payment_method'] != 'cash_after_service') {
                placeBookingTransactionForWalletPayment($booking);   //wallet payment
            }

            $provider = Provider::with('owner')->whereId($booking->provider_id)->first();
            $languageKey = $provider->owner?->current_language_key;
            $maxBookingAmount = (business_config('max_booking_amount', 'booking_setup'))->live_values;
           if ($booking->payment_method != 'cash_after_service' || ($booking->payment_method == 'cash_after_service' && $booking->total_booking_amount < $maxBookingAmount)){
               if (!is_null($provider?->owner?->fcm_token) && $provider?->is_suspended == 0) {
                   $title = get_push_notification_message('booking_accepted', 'provider_notification', $languageKey);
                   $bookingNotificationStatus = business_config('booking', 'notification_settings')->live_values;

                   if ($title && isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking']) {
                       device_notification($provider->owner->fcm_token, $title, null, null, $booking->id, 'booking');
                   }
               }
           }
        });

        return [
            'flag' => 'success',
            'booking_id' => $booking->id,
            'readable_id' => $booking->readable_id,
        ];
    }


    //=============== EDIT BOOKING ===============

    /**
     * @param $request
     * @return void
     */
    protected function addNewBookingService($request): void
    {
        DB::transaction(function () use ($request) {
            $service = Service::with('variations')->find($request['service_id']);
            $variation = $service->variations->where('variant_key', $request['variant_key'])->where('zone_id', $request['zone_id'])->first();
            $quantity = $request['quantity'];
            $booking = Booking::with(['detail', 'details_amounts'])->find($request['booking_id']);

            if ($booking->total_coupon_discount_amount > 0) {
                self::remove_coupon_from_booking($booking, $service);
            }


            $basicDiscount = basic_discount_calculation($service, $variation->price * $quantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $quantity);
            $subtotal = round($variation->price * $quantity, 2);

            $applicableDiscount = ($campaignDiscount >= $basicDiscount) ? $campaignDiscount : $basicDiscount;
            $tax = round(((($variation->price * $quantity - $applicableDiscount) * $service['tax']) / 100), 2);

            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;

            $newTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $booking = Booking::find($request['booking_id']);
            $booking->total_booking_amount += $newTotal;
            $booking->total_tax_amount += $tax;
            $booking->total_discount_amount += $basicDiscount;
            $booking->total_campaign_discount_amount += $campaignDiscount;

            $booking->additional_charge += $newTotal;
            $booking->additional_tax_amount += $tax;
            $booking->additional_discount_amount += $basicDiscount;
            $booking->additional_campaign_discount_amount += $campaignDiscount;
            $booking->save();

            $detail = BookingDetail::where('booking_id', $booking->id)->where('variant_key', $request['variant_key'])->first();
            if (!$detail) $detail = new BookingDetail();
            $detail->booking_id = $booking->id;
            $detail->service_id = $request['service_id'];
            $detail->service_name = $service->name ?? 'service-not-found';
            $detail->variant_key = $request['variant_key'];
            $detail->quantity += $quantity;
            $detail->service_cost += $variation->price;
            $detail->discount_amount += $basicDiscount;
            $detail->campaign_discount_amount += $campaignDiscount;
            $detail->overall_coupon_discount_amount = 0;
            $detail->tax_amount += round($tax, 2);
            $detail->total_cost += $newTotal;
            $detail->save();

            $bookingDetailsAmount = BookingDetailsAmount::where('booking_id', $booking->id)->where('booking_details_id', $detail->id)->first();
            if (!$bookingDetailsAmount) $bookingDetailsAmount = new BookingDetailsAmount();
            $bookingDetailsAmount->booking_details_id = $detail->id;
            $bookingDetailsAmount->booking_id = $booking->id;
            $bookingDetailsAmount->service_unit_cost += $detail['service_cost'];
            $bookingDetailsAmount->service_quantity += $quantity;
            $bookingDetailsAmount->service_tax += $detail['tax_amount'];
            $bookingDetailsAmount->discount_by_admin += $this->calculate_discount_cost($detail['discount_amount'])['admin'];
            $bookingDetailsAmount->discount_by_provider += $this->calculate_discount_cost($detail['discount_amount'])['provider'];
            $bookingDetailsAmount->campaign_discount_by_admin += $this->calculate_campaign_cost($detail['campaign_discount_amount'])['admin'];
            $bookingDetailsAmount->campaign_discount_by_provider += $this->calculate_campaign_cost($detail['campaign_discount_amount'])['provider'];
            $bookingDetailsAmount->coupon_discount_by_admin += $this->calculate_coupon_cost($detail['overall_coupon_discount_amount'])['admin'];
            $bookingDetailsAmount->coupon_discount_by_provider += $this->calculate_coupon_cost($detail['overall_coupon_discount_amount'])['provider'];
            $bookingDetailsAmount->save();

            $serviceAdd = isNotificationActive(null, 'booking', 'notification', 'user');
            $providerNotification = isNotificationActive(null, 'booking', 'notification', 'provider');
            $servicemanNotification = isNotificationActive(null, 'booking', 'notification', 'serviceman');
            if ($serviceAdd) {
                $notifications[] = [
                    'key' => 'booking_edit_service_add',
                    'settings_type' => 'customer_notification'
                ];
            }
            if ($providerNotification) {
                $notifications[] = [
                    'key' => 'booking_edit_service_add',
                    'settings_type' => 'provider_notification'
                ];
            }
            if ($servicemanNotification) {
                $notifications[] = [
                    'key' => 'booking_edit_service_add',
                    'settings_type' => 'serviceman_notification'
                ];
            }

            $bookingNotificationStatus = business_config('booking', 'notification_settings')->live_values;

            if (isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking']) {
                foreach ($notifications ?? [] as $notification) {
                    $key = $notification['key'];
                    $settingsType = $notification['settings_type'];

                    if ($settingsType == 'customer_notification') {
                        $user = $booking?->customer;
                        $title = get_push_notification_message($key, $settingsType, $user?->current_language_key);
                        if ($user?->fcm_token && $title) {
                            device_notification($user?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }

                    if ($settingsType == 'provider_notification') {
                        $provider = $booking?->provider?->owner;
                        $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);
                        if ($provider?->fcm_token && $title) {
                            device_notification($provider?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }

                    if ($settingsType == 'serviceman_notification') {
                        $serviceman = $booking?->serviceman?->user;
                        $title = get_push_notification_message($key, $settingsType, $serviceman?->current_language_key);
                        if ($serviceman?->fcm_token && $title) {
                            device_notification($serviceman?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }
                }
            }

        });
    }

    protected function increase_service_quantity_from_booking($request): void
    {
        if (!$request->has('booking_id', 'service_id', 'variant_key', 'zone_id')) return;

        DB::transaction(function () use ($request) {
            $bookingDetails = BookingDetail::whereHas('booking', fn($query) => $query->where('id', $request['booking_id']))->where('variant_key', $request['variant_key'])->first();
            $service = Service::with('variations')->find($request['service_id']);
            $variation = $service->variations->where('variant_key', $request['variant_key'])->where('zone_id', $request['zone_id'])->first();
            $booking = Booking::with(['detail', 'details_amounts'])->find($request['booking_id']);

            $oldQuantity = $request['old_quantity'];
            $newQuantity = $request['new_quantity'];
            $toAddQuantity = abs($request['old_quantity'] - $request['new_quantity']);

            if ($booking->total_coupon_discount_amount > 0) {
                self::remove_coupon_from_booking($booking, $service);
            }

            $basicDiscount = basic_discount_calculation($service, $variation->price * $newQuantity) - basic_discount_calculation($service, $variation->price * $oldQuantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $newQuantity) - campaign_discount_calculation($service, $variation->price * $oldQuantity);
            $subtotal = round($variation->price * $toAddQuantity, 2);

            $applicableDiscount = max($campaignDiscount, $basicDiscount);
            $tax = round(((($variation->price * $toAddQuantity - $applicableDiscount) * $service['tax']) / 100), 2);

            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;

            $subTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $additional_total = $booking->total_booking_amount - $subTotal;

            $booking = Booking::find($request['booking_id']);
            $booking->additional_charge += $subTotal;
            $booking->additional_tax_amount += $tax;
            $booking->additional_discount_amount += $basicDiscount;
            $booking->additional_campaign_discount_amount += $campaignDiscount;
            $booking->total_booking_amount += $subTotal;
            $booking->total_tax_amount += $tax;
            $booking->total_discount_amount += $basicDiscount;
            $booking->total_campaign_discount_amount += $campaignDiscount;
            $booking->save();


            $basicDiscount = basic_discount_calculation($service, $variation->price * $newQuantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $newQuantity);
            $subtotal = round($variation->price * $newQuantity, 2);

            $applicableDiscount = ($campaignDiscount >= $basicDiscount) ? $campaignDiscount : $basicDiscount;
            $tax = round(((($variation->price * $newQuantity - $applicableDiscount) * $service['tax']) / 100), 2);

            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;


            $subTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $bookingDetails->quantity = $newQuantity;
            $bookingDetails->tax_amount = $tax;
            $bookingDetails->total_cost = $subTotal;
            $bookingDetails->discount_amount = $basicDiscount;
            $bookingDetails->campaign_discount_amount = $campaignDiscount;
            $bookingDetails->overall_coupon_discount_amount = 0;
            $bookingDetails->save();

            $bookingDetailsAmount = BookingDetailsAmount::where('booking_id', $request['booking_id'])->where('booking_details_id', $bookingDetails->id)->first();
            $bookingDetailsAmount->service_quantity = $newQuantity;
            $bookingDetailsAmount->service_tax = $tax;
            $bookingDetailsAmount->coupon_discount_by_admin = 0;
            $bookingDetailsAmount->coupon_discount_by_provider = 0;
            $bookingDetailsAmount->discount_by_admin = $this->calculate_discount_cost($bookingDetails->discount_amount)['admin'];
            $bookingDetailsAmount->discount_by_provider = $this->calculate_discount_cost($bookingDetails->discount_amount)['provider'];
            $bookingDetailsAmount->campaign_discount_by_admin = $this->calculate_campaign_cost($bookingDetails->campaign_discount_amount)['admin'];
            $bookingDetailsAmount->campaign_discount_by_provider = $this->calculate_campaign_cost($bookingDetails->campaign_discount_amount)['provider'];
            $bookingDetailsAmount->save();

            $serviceQty = isNotificationActive(null, 'booking', 'notification', 'user');
            $providerQtyNotification = isNotificationActive(null, 'booking', 'notification', 'provider');
            $servicemanQtyNotification = isNotificationActive(null, 'booking', 'notification', 'serviceman');
            if ($serviceQty) {
                $notifications[] =
                    [
                        'key' => 'booking_edit_service_quantity_increase',
                        'settings_type' => 'customer_notification'
                    ];
            }
            if ($providerQtyNotification) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_increase',
                    'settings_type' => 'provider_notification'
                ];
            }
            if ($servicemanQtyNotification) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_increase',
                    'settings_type' => 'serviceman_notification'
                ];
            }

            $bookingNotificationStatus = business_config('booking', 'notification_settings')->live_values;

            if (isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking']) {
                foreach ($notifications ?? [] as $notification) {
                    $key = $notification['key'];
                    $settingsType = $notification['settings_type'];

                    if ($settingsType == 'customer_notification') {
                        $user = $booking?->customer;
                        $title = get_push_notification_message($key, $settingsType, $user?->current_language_key);
                        if ($user?->fcm_token && $title) {
                            device_notification($user?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }

                    if ($settingsType == 'provider_notification') {
                        $provider = $booking?->provider?->owner;
                        $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);
                        if ($provider?->fcm_token && $title) {
                            device_notification($provider?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }

                    if ($settingsType == 'serviceman_notification') {
                        $serviceman = $booking?->serviceman?->user;
                        $title = get_push_notification_message($key, $settingsType, $serviceman?->current_language_key);
                        if ($serviceman?->fcm_token && $title) {
                            device_notification($serviceman?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }
                }
            }
        });
    }
    protected function increase_service_quantity_from_booking_repeat($request): void
    {
        if (!$request->has('booking_repeat_id', 'service_id', 'variant_key', 'zone_id')) return;
        DB::transaction(function () use ($request) {
            $bookingDetails = BookingRepeatDetails::whereHas('repeat', fn($query) => $query->where('id', $request['booking_repeat_id']))->where('variant_key', $request['variant_key'])->first();
            $service = Service::with('variations')->find($request['service_id']);
            $variation = $service->variations->where('variant_key', $request['variant_key'])->where('zone_id', $request['zone_id'])->first();
            $booking = BookingRepeat::with(['detail', 'details_amounts'])->find($request['booking_repeat_id']);

            $oldQuantity = $request['old_quantity'];
            $newQuantity = $request['new_quantity'];
            $toAddQuantity = abs($request['old_quantity'] - $request['new_quantity']);

            if ($booking->total_coupon_discount_amount > 0) {
                self::remove_coupon_from_booking($booking, $service);
            }

            $basicDiscount = basic_discount_calculation($service, $variation->price * $newQuantity) - basic_discount_calculation($service, $variation->price * $oldQuantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $newQuantity) - campaign_discount_calculation($service, $variation->price * $oldQuantity);
            $subtotal = round($variation->price * $toAddQuantity, 2);

            $applicableDiscount = max($campaignDiscount, $basicDiscount);
            $tax = round(((($variation->price * $toAddQuantity - $applicableDiscount) * $service['tax']) / 100), 2);

            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;

            $subTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $additional_total = $booking->total_booking_amount - $subTotal;

            $booking = BookingRepeat::find($request['booking_repeat_id']);
            $booking->additional_charge += $subTotal;
            $booking->additional_tax_amount += $tax;
            $booking->additional_discount_amount += $basicDiscount;
            $booking->additional_campaign_discount_amount += $campaignDiscount;
            $booking->total_booking_amount += $subTotal;
            $booking->total_tax_amount += $tax;
            $booking->total_discount_amount += $basicDiscount;
            $booking->total_campaign_discount_amount += $campaignDiscount;
            $booking->save();


            $basicDiscount = basic_discount_calculation($service, $variation->price * $newQuantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $newQuantity);
            $subtotal = round($variation->price * $newQuantity, 2);

            $applicableDiscount = ($campaignDiscount >= $basicDiscount) ? $campaignDiscount : $basicDiscount;
            $tax = round(((($variation->price * $newQuantity - $applicableDiscount) * $service['tax']) / 100), 2);

            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;


            $subTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $bookingDetails->quantity = $newQuantity;
            $bookingDetails->tax_amount = $tax;
            $bookingDetails->total_cost = $subTotal;
            $bookingDetails->discount_amount = $basicDiscount;
            $bookingDetails->campaign_discount_amount = $campaignDiscount;
            $bookingDetails->overall_coupon_discount_amount = 0;
            $bookingDetails->save();

            $bookingDetailsAmount = BookingDetailsAmount::where('booking_repeat_id', $booking->id)->where('booking_repeat_details_id', $bookingDetails->id)->first();
            $bookingDetailsAmount->service_quantity = $newQuantity;
            $bookingDetailsAmount->service_tax = $tax;
            $bookingDetailsAmount->coupon_discount_by_admin = 0;
            $bookingDetailsAmount->coupon_discount_by_provider = 0;
            $bookingDetailsAmount->discount_by_admin = $this->calculate_discount_cost($bookingDetails->discount_amount)['admin'];
            $bookingDetailsAmount->discount_by_provider = $this->calculate_discount_cost($bookingDetails->discount_amount)['provider'];
            $bookingDetailsAmount->campaign_discount_by_admin = $this->calculate_campaign_cost($bookingDetails->campaign_discount_amount)['admin'];
            $bookingDetailsAmount->campaign_discount_by_provider = $this->calculate_campaign_cost($bookingDetails->campaign_discount_amount)['provider'];
            $bookingDetailsAmount->save();

            $serviceQty = isNotificationActive(null, 'booking', 'notification', 'user');
            $providerQtyNotification = isNotificationActive(null, 'booking', 'notification', 'provider');
            $servicemanQtyNotification = isNotificationActive(null, 'booking', 'notification', 'serviceman');
            if ($serviceQty) {
                $notifications[] =
                    [
                        'key' => 'booking_edit_service_quantity_increase',
                        'settings_type' => 'customer_notification'
                    ];
            }
            if ($providerQtyNotification) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_increase',
                    'settings_type' => 'provider_notification'
                ];
            }
            if ($servicemanQtyNotification) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_increase',
                    'settings_type' => 'serviceman_notification'
                ];
            }

            $bookingNotificationStatus = business_config('booking', 'notification_settings')->live_values;

            if (isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking']) {
                foreach ($notifications ?? [] as $notification) {
                    $key = $notification['key'];
                    $settingsType = $notification['settings_type'];

                    if ($settingsType == 'customer_notification') {
                        $user = $booking?->booking?->customer;
                        $title = get_push_notification_message($key, $settingsType, $user?->current_language_key);
                        if ($user?->fcm_token && $title) {
                            device_notification($user?->fcm_token, $title, null, null, $booking->booking_id, 'booking', null, null, null, null, 'repeat');
                        }
                    }

                    if ($settingsType == 'provider_notification') {
                        $provider = $booking?->provider?->owner;
                        $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);
                        if ($provider?->fcm_token && $title) {
                            device_notification($provider?->fcm_token, $title, null, null, $booking->booking_id, 'booking', null, null, null, null, 'repeat');
                        }
                    }

                    if ($settingsType == 'serviceman_notification') {
                        $serviceman = $booking?->serviceman?->user;
                        $title = get_push_notification_message($key, $settingsType, $serviceman?->current_language_key);
                        if ($serviceman?->fcm_token && $title) {
                            device_notification($serviceman?->fcm_token, $title, null, null, $booking->id, 'booking', null, null, null, null, 'repeat', 'single');
                        }
                    }
                }
            }
        });
    }

    protected function remove_service_from_booking($request): void
    {
        if (!$request->has('booking_id', 'service_id', 'variant_key', 'zone_id')) return;

        DB::transaction(function () use ($request) {
            $bookingDetails = BookingDetail::whereHas('booking', fn($query) => $query->where('id', $request['booking_id']))->where('variant_key', $request['variant_key'])->first();
            $service = Service::with('variations')->find($request['service_id']);
            $variation = $service->variations->where('variant_key', $request['variant_key'])->where('zone_id', $request['zone_id'])->first();
            $quantity = $bookingDetails['quantity'];
            $booking = Booking::with(['detail', 'details_amounts'])->find($request['booking_id']);

            if ($booking->total_coupon_discount_amount > 0) {
                self::remove_coupon_from_booking($booking, $service);
            }

            $basicDiscount = basic_discount_calculation($service, $variation->price * $quantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $quantity);
            $subtotal = round($variation->price * $quantity, 2);

            $applicableDiscount = ($campaignDiscount >= $basicDiscount) ? $campaignDiscount : $basicDiscount;
            $tax = round(((($variation->price * $quantity - $applicableDiscount) * $service['tax']) / 100), 2);

            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;

            $removedTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $refundAmount = 0;
            if ((($booking->payment_method != 'cash_after_service' && $booking->payment_method != 'offline_payment') || ($booking->payment_method == 'offline_payment' && $booking->is_paid)) && $booking->additional_charge == 0) {
                $refundAmount = $removedTotal;
            }

            $booking = Booking::find($request['booking_id']);
            $booking->total_booking_amount -= $removedTotal;
            $booking->total_tax_amount -= $tax;
            $booking->total_discount_amount -= $basicDiscount;
            $booking->total_campaign_discount_amount -= $campaignDiscount;

            $booking->additional_charge -= $removedTotal;
            $booking->additional_tax_amount -= $tax;
            $booking->additional_discount_amount -= $basicDiscount;
            $booking->additional_campaign_discount_amount -= $campaignDiscount;
            $booking->save();

            BookingDetailsAmount::where('booking_id', $request['booking_id'])->where('booking_details_id', $bookingDetails->id)->delete();

            $bookingDetails->delete();

            if ($refundAmount > 0) {
                removeBookingServiceTransactionForDigitalPayment($booking, $refundAmount);
            }
            $serviceDelete = isNotificationActive(null, 'booking', 'notification', 'user');
            $providerNotificationDelete = isNotificationActive(null, 'booking', 'notification', 'provider');
            $servicemanNotificationDelete = isNotificationActive(null, 'booking', 'notification', 'serviceman');
            if ($serviceDelete) {
                $notifications[] = [
                    'key' => 'booking_edit_service_remove',
                    'settings_type' => 'customer_notification'
                ];
            }
            if ($providerNotificationDelete) {
                $notifications[] = [
                    'key' => 'booking_edit_service_remove',
                    'settings_type' => 'provider_notification'
                ];
            }
            if ($servicemanNotificationDelete) {
                $notifications[] = [
                    'key' => 'booking_edit_service_remove',
                    'settings_type' => 'serviceman_notification'
                ];
            }

            $bookingNotificationStatus = business_config('booking', 'notification_settings')->live_values;

            if (isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking']) {

                foreach ($notifications ?? [] as $notification) {
                    $key = $notification['key'];
                    $settingsType = $notification['settings_type'];

                    if ($settingsType == 'customer_notification') {
                        $user = $booking?->customer;
                        $title = get_push_notification_message($key, $settingsType, $user?->current_language_key);
                        if ($user?->fcm_token && $title) {
                            device_notification($user?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }

                    if ($settingsType == 'provider_notification') {
                        $provider = $booking?->provider?->owner;
                        $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);
                        if ($provider?->fcm_token && $title) {
                            device_notification($provider?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }

                    if ($settingsType == 'serviceman_notification') {
                        $serviceman = $booking?->serviceman?->user;
                        $title = get_push_notification_message($key, $settingsType, $serviceman?->current_language_key);
                        if ($serviceman?->fcm_token && $title) {
                            device_notification($serviceman?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }
                }
            }
        });
    }

    protected function decrease_service_quantity_from_booking($request): void
    {
        if (!$request->has('booking_id', 'service_id', 'variant_key', 'zone_id')) return;

        DB::transaction(function () use ($request) {
            $bookingDetails = BookingDetail::whereHas('booking', fn($query) => $query->where('id', $request['booking_id']))->where('variant_key', $request['variant_key'])->first();
            $service = Service::with('variations')->find($request['service_id']);
            $variation = $service->variations->where('variant_key', $request['variant_key'])->where('zone_id', $request['zone_id'])->first();
            $booking = Booking::with(['detail', 'details_amounts'])->find($request['booking_id']);

            $oldQuantity = $request['old_quantity'];
            $newQuantity = $request['new_quantity'];
            $quantity_to_remove = $request['old_quantity'] - $request['new_quantity'];

            if ($booking->total_coupon_discount_amount > 0) {
                self::remove_coupon_from_booking($booking, $service);
            }

            $basicDiscount = basic_discount_calculation($service, $variation->price * $oldQuantity) - basic_discount_calculation($service, $variation->price * $newQuantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $oldQuantity) - campaign_discount_calculation($service, $variation->price * $newQuantity);
            $subtotal = round($variation->price * $quantity_to_remove, 2);

            $applicableDiscount = max($campaignDiscount, $basicDiscount);
            $tax = round(((($variation->price * $quantity_to_remove - $applicableDiscount) * $service['tax']) / 100), 2);

            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;

            $subTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $removedTotal = $booking->total_booking_amount - $subTotal;

            $refundAmount = 0;
            if ((($booking->payment_method != 'cash_after_service' && $booking->payment_method != 'offline_payment') || ($booking->payment_method == 'offline_payment' && $booking->is_paid)) && $booking->additional_charge == 0) {
                $refundAmount = $removedTotal;
            }

            $booking = Booking::find($request['booking_id']);
            $booking->additional_charge -= $subTotal;
            $booking->additional_tax_amount -= $tax;
            $booking->additional_discount_amount -= $basicDiscount;
            $booking->additional_campaign_discount_amount -= $campaignDiscount;

            $booking->total_booking_amount -= $subTotal;
            $booking->total_tax_amount -= $tax;
            $booking->total_discount_amount -= $basicDiscount;
            $booking->total_campaign_discount_amount -= $campaignDiscount;
            $booking->save();

            $basicDiscount = basic_discount_calculation($service, $variation->price * $newQuantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $newQuantity);
            $subtotal = round($variation->price * $newQuantity, 2);

            $applicableDiscount = ($campaignDiscount >= $basicDiscount) ? $campaignDiscount : $basicDiscount;
            $tax = round(((($variation->price * $newQuantity - $applicableDiscount) * $service['tax']) / 100), 2);


            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;

            $subTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $bookingDetails->quantity = $newQuantity;
            $bookingDetails->tax_amount = $tax;
            $bookingDetails->total_cost = $subTotal;
            $bookingDetails->discount_amount = $basicDiscount;
            $bookingDetails->campaign_discount_amount = $campaignDiscount;
            $bookingDetails->overall_coupon_discount_amount = 0;
            $bookingDetails->save();

            $bookingDetailsAmount = BookingDetailsAmount::where('booking_id', $request['booking_id'])->where('booking_details_id', $bookingDetails->id)->first();
            $bookingDetailsAmount->service_quantity = $newQuantity;
            $bookingDetailsAmount->service_tax = $tax;
            $bookingDetailsAmount->coupon_discount_by_admin = 0;
            $bookingDetailsAmount->coupon_discount_by_provider = 0;
            $bookingDetailsAmount->discount_by_admin = $this->calculate_discount_cost($bookingDetails->discount_amount)['admin'];
            $bookingDetailsAmount->discount_by_provider = $this->calculate_discount_cost($bookingDetails->discount_amount)['provider'];
            $bookingDetailsAmount->campaign_discount_by_admin = $this->calculate_campaign_cost($bookingDetails->campaign_discount_amount)['admin'];
            $bookingDetailsAmount->campaign_discount_by_provider = $this->calculate_campaign_cost($bookingDetails->campaign_discount_amount)['provider'];
            $bookingDetailsAmount->save();

            if ($refundAmount > 0) {
                removeBookingServiceTransactionForDigitalPayment($booking, $removedTotal);
            }

            $otyDecrease = isNotificationActive(null, 'booking', 'notification', 'user');
            $providerQtyDecrease = isNotificationActive(null, 'booking', 'notification', 'provider');
            $servicemanQtyDecrease = isNotificationActive(null, 'booking', 'notification', 'serviceman');
            if ($otyDecrease) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_decrease',
                    'settings_type' => 'customer_notification'
                ];
            }
            if ($providerQtyDecrease) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_decrease',
                    'settings_type' => 'provider_notification'
                ];
            }
            if ($servicemanQtyDecrease) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_decrease',
                    'settings_type' => 'serviceman_notification'
                ];
            }

            $bookingNotificationStatus = business_config('booking', 'notification_settings')->live_values;

            if (isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking']) {

                foreach ($notifications ?? [] as $notification) {
                    $key = $notification['key'];
                    $settingsType = $notification['settings_type'];

                    if ($settingsType == 'customer_notification') {
                        $user = $booking?->customer;
                        $title = get_push_notification_message($key, $settingsType, $user?->current_language_key);
                        if ($user?->fcm_token && $title) {
                            device_notification($user?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }

                    if ($settingsType == 'provider_notification') {
                        $provider = $booking?->provider?->owner;
                        $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);
                        if ($provider?->fcm_token && $title) {
                            device_notification($provider?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }

                    if ($settingsType == 'serviceman_notification') {
                        $serviceman = $booking?->serviceman?->user;
                        $title = get_push_notification_message($key, $settingsType, $serviceman?->current_language_key);
                        if ($serviceman?->fcm_token && $title) {
                            device_notification($serviceman?->fcm_token, $title, null, null, $booking->id, 'booking');
                        }
                    }
                }
            }
        });
    }
    protected function decrease_service_quantity_from_booking_repeat($request): void
    {
        if (!$request->has('booking_repeat_id', 'service_id', 'variant_key', 'zone_id')) return;

        DB::transaction(function () use ($request) {
            $bookingDetails = BookingRepeatDetails::whereHas('repeat', fn($query) => $query->where('id', $request['booking_repeat_id']))->where('variant_key', $request['variant_key'])->first();
            $service = Service::with('variations')->find($request['service_id']);
            $variation = $service->variations->where('variant_key', $request['variant_key'])->where('zone_id', $request['zone_id'])->first();
            $booking = BookingRepeat::with(['detail', 'details_amounts'])->find($request['booking_repeat_id']);

            $oldQuantity = $request['old_quantity'];
            $newQuantity = $request['new_quantity'];
            $quantity_to_remove = $request['old_quantity'] - $request['new_quantity'];

            if ($booking->total_coupon_discount_amount > 0) {
                self::remove_coupon_from_booking($booking, $service);
            }

            $basicDiscount = basic_discount_calculation($service, $variation->price * $oldQuantity) - basic_discount_calculation($service, $variation->price * $newQuantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $oldQuantity) - campaign_discount_calculation($service, $variation->price * $newQuantity);
            $subtotal = round($variation->price * $quantity_to_remove, 2);

            $applicableDiscount = max($campaignDiscount, $basicDiscount);
            $tax = round(((($variation->price * $quantity_to_remove - $applicableDiscount) * $service['tax']) / 100), 2);

            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;

            $subTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $removedTotal = $booking->total_booking_amount - $subTotal;

            $refundAmount = 0;
            if ((($booking->payment_method != 'cash_after_service' && $booking->payment_method != 'offline_payment') || ($booking->payment_method == 'offline_payment' && $booking->is_paid)) && $booking->additional_charge == 0) {
                $refundAmount = $removedTotal;
            }

            $booking = BookingRepeat::find($request['booking_repeat_id']);
            $booking->additional_charge -= $subTotal;
            $booking->additional_tax_amount -= $tax;
            $booking->additional_discount_amount -= $basicDiscount;
            $booking->additional_campaign_discount_amount -= $campaignDiscount;

            $booking->total_booking_amount -= $subTotal;
            $booking->total_tax_amount -= $tax;
            $booking->total_discount_amount -= $basicDiscount;
            $booking->total_campaign_discount_amount -= $campaignDiscount;
            $booking->save();

            $basicDiscount = basic_discount_calculation($service, $variation->price * $newQuantity);
            $campaignDiscount = campaign_discount_calculation($service, $variation->price * $newQuantity);
            $subtotal = round($variation->price * $newQuantity, 2);

            $applicableDiscount = ($campaignDiscount >= $basicDiscount) ? $campaignDiscount : $basicDiscount;
            $tax = round(((($variation->price * $newQuantity - $applicableDiscount) * $service['tax']) / 100), 2);


            $basicDiscount = $basicDiscount > $campaignDiscount ? $basicDiscount : 0;
            $campaignDiscount = $campaignDiscount >= $basicDiscount ? $campaignDiscount : 0;

            $subTotal = round($subtotal - $basicDiscount - $campaignDiscount + $tax, 2);

            $bookingDetails->quantity = $newQuantity;
            $bookingDetails->tax_amount = $tax;
            $bookingDetails->total_cost = $subTotal;
            $bookingDetails->discount_amount = $basicDiscount;
            $bookingDetails->campaign_discount_amount = $campaignDiscount;
            $bookingDetails->overall_coupon_discount_amount = 0;
            $bookingDetails->save();

            $bookingDetailsAmount = BookingDetailsAmount::where('booking_repeat_id', $booking->id)->where('booking_repeat_details_id', $bookingDetails->id)->first();
            $bookingDetailsAmount->service_quantity = $newQuantity;
            $bookingDetailsAmount->service_tax = $tax;
            $bookingDetailsAmount->coupon_discount_by_admin = 0;
            $bookingDetailsAmount->coupon_discount_by_provider = 0;
            $bookingDetailsAmount->discount_by_admin = $this->calculate_discount_cost($bookingDetails->discount_amount)['admin'];
            $bookingDetailsAmount->discount_by_provider = $this->calculate_discount_cost($bookingDetails->discount_amount)['provider'];
            $bookingDetailsAmount->campaign_discount_by_admin = $this->calculate_campaign_cost($bookingDetails->campaign_discount_amount)['admin'];
            $bookingDetailsAmount->campaign_discount_by_provider = $this->calculate_campaign_cost($bookingDetails->campaign_discount_amount)['provider'];
            $bookingDetailsAmount->save();

            if ($refundAmount > 0) {
                removeBookingServiceTransactionForDigitalPayment($booking, $removedTotal);
            }

            $otyDecrease = isNotificationActive(null, 'booking', 'notification', 'user');
            $providerQtyDecrease = isNotificationActive(null, 'booking', 'notification', 'provider');
            $servicemanQtyDecrease = isNotificationActive(null, 'booking', 'notification', 'serviceman');
            if ($otyDecrease) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_decrease',
                    'settings_type' => 'customer_notification'
                ];
            }
            if ($providerQtyDecrease) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_decrease',
                    'settings_type' => 'provider_notification'
                ];
            }
            if ($servicemanQtyDecrease) {
                $notifications[] = [
                    'key' => 'booking_edit_service_quantity_decrease',
                    'settings_type' => 'serviceman_notification'
                ];
            }

            $bookingNotificationStatus = business_config('booking', 'notification_settings')->live_values;

            if (isset($bookingNotificationStatus) && $bookingNotificationStatus['push_notification_booking']) {

                foreach ($notifications ?? [] as $notification) {
                    $key = $notification['key'];
                    $settingsType = $notification['settings_type'];

                    if ($settingsType == 'customer_notification') {
                        $user = $booking?->booking?->customer;
                        $title = get_push_notification_message($key, $settingsType, $user?->current_language_key);
                        if ($user?->fcm_token && $title) {
                            device_notification($user?->fcm_token, $title, null, null, $booking->booking_id, 'booking', null, null, null, null, 'repeat');
                        }
                    }

                    if ($settingsType == 'provider_notification') {
                        $provider = $booking?->provider?->owner;
                        $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);
                        if ($provider?->fcm_token && $title) {
                            device_notification($provider?->fcm_token, $title, null, null, $booking->booking_id, 'booking', null, null, null, null, 'repeat');
                        }
                    }

                    if ($settingsType == 'serviceman_notification') {
                        $serviceman = $booking?->serviceman?->user;
                        $title = get_push_notification_message($key, $settingsType, $serviceman?->current_language_key);
                        if ($serviceman?->fcm_token && $title) {
                            device_notification($serviceman?->fcm_token, $title, null, null, $booking->id, 'booking', null, null, null, null, 'repeat', 'single');
                        }
                    }
                }
            }
        });
    }

    /**
     * @param $booking
     * @param $service
     * @return void
     */
    public function remove_coupon_from_booking($booking, $service): void
    {
        DB::transaction(function () use ($booking, $service) {
            $totalCouponAmountRemoved = 0;
            $totalTaxAmount = 0;
            $totalBookingAmount = 0;

            foreach ($booking->detail as $detail) {
                $totalCouponAmountRemoved += $detail['overall_coupon_discount_amount'];

                $serviceCost = $detail['service_cost'];
                $basicDiscount = $detail['discount_amount'];
                $campaignDiscount = $detail['campaign_discount_amount'];
                $quantity = $detail['quantity'];

                $applicableDiscount = max($campaignDiscount, $basicDiscount);
                $taxPercentage = $service['tax'];
                $tax = round(((($serviceCost * $quantity - $applicableDiscount) * $taxPercentage) / 100), 2);

                $detail->tax_amount = $tax;
                $detail->total_cost = round(($serviceCost * $quantity) - $applicableDiscount + $tax, 2);
                $detail->overall_coupon_discount_amount = 0;
                $detail->save();

                $totalTaxAmount += $tax;
                $totalBookingAmount += $detail->total_cost;
            }

            foreach ($booking->details_amounts as $detailsAmount) {
                $detailsAmount->coupon_discount_by_admin = 0;
                $detailsAmount->coupon_discount_by_provider = 0;
                $detailsAmount->save();
            }

            $booking->total_booking_amount = $totalBookingAmount;
            $booking->total_tax_amount = $totalTaxAmount;
            $booking->total_coupon_discount_amount = 0;
            $booking->coupon_code = null;
            $booking->additional_charge += $totalCouponAmountRemoved;
            $booking->removed_coupon_amount += $totalCouponAmountRemoved;
            $booking->save();
        });
    }


    //=============== PROMOTIONAL COST CALCULATION ===============

    /**
     * @param float $discount_amount
     * @return array
     */
    private function calculate_discount_cost(float $discount_amount): array
    {
        $data = BusinessSettings::where('settings_type', 'promotional_setup')->where('key_name', 'discount_cost_bearer')->first();
        if (!isset($data)) return [];
        $data = $data->live_values;

        if ($data['admin_percentage'] == 0) {
            $adminPercentage = 0;
        } else {
            $adminPercentage = ($discount_amount * $data['admin_percentage']) / 100;
        }

        if ($data['provider_percentage'] == 0) {
            $providerPercentage = 0;
        } else {
            $providerPercentage = ($discount_amount * $data['provider_percentage']) / 100;
        }
        return [
            'admin' => $adminPercentage,
            'provider' => $providerPercentage
        ];
    }

    /**
     * @param float $campaignAmount
     * @return array
     */
    private function calculate_campaign_cost(float $campaignAmount): array
    {
        $data = BusinessSettings::where('settings_type', 'promotional_setup')->where('key_name', 'campaign_cost_bearer')->first();
        if (!isset($data)) return [];
        $data = $data->live_values;

        if ($data['admin_percentage'] == 0) {
            $adminPercentage = 0;
        } else {
            $adminPercentage = ($campaignAmount * $data['admin_percentage']) / 100;
        }

        if ($data['provider_percentage'] == 0) {
            $providerPercentage = 0;
        } else {
            $providerPercentage = ($campaignAmount * $data['provider_percentage']) / 100;
        }

        return [
            'admin' => $adminPercentage,
            'provider' => $providerPercentage
        ];
    }

    /**
     * @param float $couponAmount
     * @return array
     */
    private function calculate_coupon_cost(float $couponAmount): array
    {
        $data = BusinessSettings::where('settings_type', 'promotional_setup')->where('key_name', 'coupon_cost_bearer')->first();
        if (!isset($data)) return [];
        $data = $data->live_values;

        if ($data['admin_percentage'] == 0) {
            $adminPercentage = 0;
        } else {
            $adminPercentage = ($couponAmount * $data['admin_percentage']) / 100;
        }

        if ($data['provider_percentage'] == 0) {
            $providerPercentage = 0;
        } else {
            $providerPercentage = ($couponAmount * $data['provider_percentage']) / 100;
        }

        return [
            'admin' => $adminPercentage,
            'provider' => $providerPercentage
        ];
    }

    /**
     * @param $booking
     * @param float $bookingAmount
     * @param $providerId
     * @return void
     */
    private function update_admin_commission($booking, float $bookingAmount, $providerId): void
    {
        $commissionDetails = $this->calculateCommissionDetails($booking);

        $adminCommission = $commissionDetails['adminCommission'];
        $adminCommissionWithoutCost = $commissionDetails['adminCommissionWithoutCost'];

        $bookingAmountWithoutCommission = $booking['total_booking_amount'] - $adminCommissionWithoutCost;

        if (isset($booking->booking_id)){
            $bookingAmountDetailAmount = BookingDetailsAmount::where('booking_repeat_id', $booking->id)->first();
        }else{
            $bookingAmountDetailAmount = BookingDetailsAmount::where('booking_id', $booking->id)->first();
        }

        $bookingAmountDetailAmount->admin_commission = $adminCommission;
        $bookingAmountDetailAmount->provider_earning = $bookingAmountWithoutCommission;
        $bookingAmountDetailAmount->save();
    }


    public function calculateCommissionDetails($booking): array
    {
        if (isset($booking->booking_id)) {
            $bookingId = $booking->booking_id;
            $bookingDetailsAmounts = BookingDetailsAmount::where('booking_repeat_id', $booking->id)->get();
        } else {
            $bookingId = $booking->id;
            $bookingDetailsAmounts = BookingDetailsAmount::where('booking_id', $booking->id)->get();
        }

        $bookingType = SubscriptionBookingType::where('booking_id', $bookingId)->where('type', 'subscription')->first();
        if($bookingType){
            return [
                'adminCommission' => 0,
                'adminCommissionWithoutCost' => 0,
            ];
        }

        $serviceCost = $booking['total_booking_amount'] - $booking['total_tax_amount'] + $booking['total_discount_amount'] + $booking['total_campaign_discount_amount'] + $booking['total_coupon_discount_amount'] - $booking['extra_fee'];

        $promotionalCostByAdmin = 0;
        $promotionalCostByProvider = 0;
        foreach ($bookingDetailsAmounts as $bookingDetailsAmount) {
            $promotionalCostByAdmin += $bookingDetailsAmount['discount_by_admin'] + $bookingDetailsAmount['coupon_discount_by_admin'] + $bookingDetailsAmount['campaign_discount_by_admin'];
            $promotionalCostByProvider += $bookingDetailsAmount['discount_by_provider'] + $bookingDetailsAmount['coupon_discount_by_provider'] + $bookingDetailsAmount['campaign_discount_by_provider'];
        }

        $providerReceivableTotalAmount = $serviceCost - $promotionalCostByProvider;

        $provider = Provider::find($booking['provider_id']);
        $commissionPercentage = $provider->commission_status == 1 ? $provider->commission_percentage : (business_config('default_commission', 'business_information'))->live_values;
        $adminCommission = ($providerReceivableTotalAmount * $commissionPercentage) / 100;

        $adminCommissionWithoutCost = $adminCommission - $promotionalCostByAdmin;

        return [
            'adminCommission' => $adminCommission,
            'adminCommissionWithoutCost' => $adminCommissionWithoutCost,
        ];
    }


    //=============== REFERRAL EARN & LOYALTY POINT ===============

    /**
     * @param $userId
     * @param $zoneId
     * @return false|void
     */
    private function referral_earning_calculation($userId, $zoneId)
    {
        $isFirstBooking = Booking::where('customer_id', $userId)->count('id');
        if ($isFirstBooking > 1) return false;

        $referredByUser = User::find($userId)->referred_by_user ?? null;
        if (is_null($referredByUser)) return false;

        $customerReferralEarning = business_config('customer_referral_earning', 'customer_config')->live_values ?? 0;
        $amount = business_config('referral_value_per_currency_unit', 'customer_config')->live_values ?? 0;

        if ($customerReferralEarning == 1) {
            referralEarningTransactionAfterBookingComplete($referredByUser, $amount);
            $userRefund  = isNotificationActive(null, 'refer_earn', 'notification', 'user');
            $title = with_currency_symbol($amount) . ' ' . get_push_notification_message('referral_earning', 'customer_notification', $referredByUser?->current_language_key);
            if ($title && $referredByUser?->fcm_token && $userRefund) {
                device_notification($referredByUser?->fcm_token, $title, null, null, null, 'general', null, $referredByUser?->id);
            }

            $pushNotification = new PushNotification();
            $pushNotification->title = translate('You have Earned a Referral Reward!');
            $pushNotification->description = translate("Great news! You have earned a reward for referring a new user, who has now completed their first booking using your code...");
            $pushNotification->to_users = ['customer'];
            $pushNotification->zone_ids = [$zoneId];
            $pushNotification->is_active = 1;
            $pushNotification->cover_image = asset('/public/assets/admin/img/referral_1.png');
            $pushNotification->save();

            $pushNotificationUser = new PushNotificationUser();
            $pushNotificationUser->push_notification_id = $pushNotification->id;
            $pushNotificationUser->user_id = $referredByUser->id;
            $pushNotificationUser->save();
        }
    }

    /**
     * @param $userId
     * @param $totalAmount
     * @param $zoneId
     * @return false|void
     */

    private function referralEarningCalculationForFirstBooking($userId, $totalAmount, $zoneId)
    {
        $isFirstBooking = Booking::where('customer_id', $userId)->count('id');
        if ($isFirstBooking > 0) return 0;

        $referredByUser = User::find($userId)->referred_by_user ?? null;
        if (is_null($referredByUser)) return 0;

        $newUserDiscount = business_config('referral_based_new_user_discount', 'customer_config')->live_values ?? 0;
        $discountType = business_config('referral_discount_type', 'customer_config')->live_values ?? 0;
        $discount = business_config('referral_discount_amount', 'customer_config')->live_values ?? 0;
        $validityType = business_config('referral_discount_validity_type', 'customer_config')->live_values ?? 0;
        $validity = business_config('referral_discount_validity', 'customer_config')->live_values ?? 0;
        $customerReferralEarning = business_config('customer_referral_earning', 'customer_config')->live_values ?? 0;

        if ($newUserDiscount && $customerReferralEarning) {

            $todayDate = Carbon::now();
            $user = User::where('id', $userId)->first();

            if ($validityType === 'day') {
                $validityEndDate = $user->created_at->addDays($validity);
            } elseif ($validityType === 'month') {
                $validityEndDate = $user->created_at->addMonths($validity);
            } else {
                return 0;
            }

            if ($todayDate <= $validityEndDate) {
                if ($discountType == 'flat') {
                    $amount = $discount;
                } elseif ($discountType == 'percentage') {
                    $bookingAmount = $totalAmount;
                    $amount = ($discount / 100) * $bookingAmount;
                }

                if ($amount > 0){
                    $userRefund  = isNotificationActive(null, 'refer_earn', 'notification', 'user');
                    $title = with_currency_symbol($amount) . ' ' . get_push_notification_message('referral_earning_first_booking', 'customer_notification', $user?->current_language_key);
                    if ($title && $user->fcm_token && $userRefund) {
                        device_notification($user->fcm_token, $title, null, null, null, 'general', null, $user->id);
                    }

                    $pushNotification = new PushNotification();
                    $pushNotification->title = translate('You have Earned a Reward!');
                    $pushNotification->description = translate("Great news! You have earned a reward for sign up with a referral code and first booking...");
                    $pushNotification->to_users = ['customer'];
                    $pushNotification->zone_ids = [$zoneId];
                    $pushNotification->is_active = 1;
                    $pushNotification->cover_image = asset('/public/assets/admin/img/referral_2.png');
                    $pushNotification->save();

                    $pushNotificationUser = new PushNotificationUser();
                    $pushNotificationUser->push_notification_id = $pushNotification->id;
                    $pushNotificationUser->user_id = $userId;
                    $pushNotificationUser->save();
                }

                return $amount;

            } else {
                return 0;
            }
        }
        return 0;
    }



    /**
     * @param $userId
     * @param $bookingAmount
     * @return false|void
     */
    private function loyaltyPointCalculation($userId, $bookingAmount)
    {

        $customerLoyaltyPoint = business_config('customer_loyalty_point', 'customer_config');
        if (isset($customerLoyaltyPoint) && $customerLoyaltyPoint->live_values != '1') return false;

        $percentagePerBooking = business_config('loyalty_point_percentage_per_booking', 'customer_config');
        $pointAmount = ($percentagePerBooking->live_values * $bookingAmount) / 100;

        $pointPerCurrencyUnit = business_config('loyalty_point_value_per_currency_unit', 'customer_config');

        $point = $pointPerCurrencyUnit->live_values * $pointAmount;

        loyaltyPointTransaction($userId, $point);

        $user = User::where('id', $userId)->first();
        $title = $point . ' ' . get_push_notification_message('loyalty_point', 'customer_notification', $user?->current_language_key);

        $customerNotification = isNotificationActive(null, 'loyality_point', 'notification', 'user');
        $dataInfo = [
            'user_name' => $user?->first_name . ' ' . $user?->last_name,
        ];
        if ($title && $user && $user->is_active && $user->fcm_token && $customerNotification) {
            device_notification($user->fcm_token, $title, null, null, null, 'loyalty_point', null, $user->id, $dataInfo);
        }
    }

    function readableIdToNumber($suffix): float|int|string
    {
        $suffixes = range('A', 'Z');
        $base = count($suffixes);
        $value = 0;

        for ($i = 0, $len = strlen($suffix); $i < $len; $i++) {
            $value = $value * $base + (array_search($suffix[$i], $suffixes) + 1);
        }

        return $value;
    }


}
