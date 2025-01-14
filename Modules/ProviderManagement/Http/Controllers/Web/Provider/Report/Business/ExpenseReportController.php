<?php

namespace Modules\ProviderManagement\Http\Controllers\Web\Provider\Report\Business;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\BookingDetailsAmount;
use Modules\CategoryManagement\Entities\Category;
use Modules\ProviderManagement\Entities\Provider;
use Modules\ServiceManagement\Entities\Service;
use Modules\TransactionModule\Entities\Account;
use Modules\TransactionModule\Entities\Transaction;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Entities\Zone;
use Rap2hpoutre\FastExcel\FastExcel;

class ExpenseReportController extends Controller
{
    protected Zone $zone;
    protected Provider $provider;
    protected Category $categories;
    protected Booking $booking;

    protected Account $account;
    protected Service $service;
    protected User $user;
    protected Transaction $transaction;

    public function __construct(Zone $zone, Provider $provider, Category $categories, Service $service, Booking $booking, Account $account, User $user, Transaction $transaction, BookingDetailsAmount $booking_details_amount)
    {
        $this->zone = $zone;
        $this->provider = $provider;
        $this->categories = $categories;
        $this->booking = $booking;

        $this->service = $service;
        $this->account = $account;
        $this->user = $user;
        $this->transaction = $transaction;
        $this->booking_details_amount = $booking_details_amount;
    }


    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Renderable
     */
    public function getBusinessExpenseReport(Request $request)
    {
        Validator::make($request->all(), [
            'zone_ids' => 'array',
            'zone_ids.*' => 'uuid',
            'category_ids' => 'array',
            'category_ids.*' => 'uuid',
            'sub_category_ids' => 'array',
            'sub_category_ids.*' => 'uuid',
            'date_range' => 'in:all_time, this_week, last_week, this_month, last_month, last_15_days, this_year, last_year, last_6_month, this_year_1st_quarter, this_year_2nd_quarter, this_year_3rd_quarter, this_year_4th_quarter, custom_date',
            'from' => $request['date_range'] == 'custom_date' ? 'required' : '',
            'to' => $request['date_range'] == 'custom_date' ? 'required' : '',
        ]);

        //Dropdown data
        $zones = $this->zone->ofStatus(1)->select('id', 'name')->get();
        $categories = $this->categories->ofType('main')->select('id', 'name')->get();
        $subCategories = $this->categories->ofType('sub')->select('id', 'name')->get();

        //params
        $search = $request['search'];
        $queryParams = ['search' => $search];
        if($request->has('zone_ids')) {
            $queryParams['zone_ids'] = $request['zone_ids'];
        }
        if ($request->has('category_ids')) {
            $queryParams['category_ids'] = $request['category_ids'];
        }
        if ($request->has('sub_category_ids')) {
            $queryParams['sub_category_ids'] = $request['sub_category_ids'];
        }
        if ($request->has('date_range')) {
            $queryParams['date_range'] = $request['date_range'];
        }
        if ($request->has('date_range') && $request['date_range'] == 'custom_date') {
            $queryParams['from'] = $request['from'];
            $queryParams['to'] = $request['to'];
        }

        //deterministic
        $dateRange = $request['date_range'];
        if(is_null($dateRange) || $dateRange == 'all_time') {
            $deterministic = 'year';
        } elseif ($dateRange == 'this_week' || $dateRange == 'last_week') {
            $deterministic = 'week';
        } elseif ($dateRange == 'this_month' || $dateRange == 'last_month' || $dateRange == 'last_15_days') {
            $deterministic = 'day';
        } elseif ($dateRange == 'this_year' || $dateRange == 'last_year' || $dateRange == 'last_6_month' || $dateRange == 'this_year_1st_quarter' || $dateRange == 'this_year_2nd_quarter' || $dateRange == 'this_year_3rd_quarter' || $dateRange == 'this_year_4th_quarter') {
            $deterministic = 'month';
        } elseif($dateRange == 'custom_date') {
            $from = Carbon::parse($request['from'])->startOfDay();
            $to = Carbon::parse($request['to'])->endOfDay();
            $diff = Carbon::parse($from)->diffInDays($to);

            if($diff <= 7) {
                $deterministic = 'week';
            } elseif ($diff <= 30) {
                $deterministic = 'day';
            } elseif ($diff <= 365) {
                $deterministic = 'month';
            } else {
                $deterministic = 'year';
            }
        }
        $groupByDeterministic = $deterministic=='week'?'day':$deterministic;

        //** Table Data **
        $filteredBookingAmounts = $this->booking_details_amount
            ->with(['booking'])
            ->whereHas('booking', function ($query) use ($request) {
                self::filterQuery($query, $request)
                    ->ofBookingStatus('completed')
                    ->when($request->has('search'), function ($query) use ($request) {
                        $keys = explode(' ', $request['search']);
                        return $query->where(function ($query) use ($keys) {
                            foreach ($keys as $key) {
                                $query->where('readable_id', 'LIKE', '%' . $key . '%');
                            }
                        });
                    });
            })->orWhereHas('repeat', function ($subQuery) {
                $subQuery->ofBookingStatus('completed');
            })
            ->latest()->paginate(pagination_limit())->appends($queryParams);

        //** Chart & Card Data **
        $amounts = $this->booking_details_amount
            ->whereHas('booking', function ($query) use ($request) {
                self::filterQuery($query, $request)->ofBookingStatus('completed');
            })
            ->when(isset($groupByDeterministic), function ($query) use ($groupByDeterministic) {
                $query->select(
                    DB::raw('sum(service_unit_cost) as service_unit_cost'),
                    DB::raw('sum(service_quantity) as service_quantity'),
                    DB::raw('sum(service_tax) as service_tax'),
                    DB::raw('sum(discount_by_provider) as discount_by_provider'),
                    DB::raw('sum(coupon_discount_by_provider) as coupon_discount_by_provider'),
                    DB::raw('sum(campaign_discount_by_provider) as campaign_discount_by_provider'),
                    DB::raw('sum(provider_earning) as provider_earning'),

                    DB::raw($groupByDeterministic.'(created_at) '.$groupByDeterministic)
                );
            })
            ->groupby($groupByDeterministic)
            ->get()->toArray();

        $chartData = ['normal_discount'=>array(), 'campaign_discount'=>array(), 'coupon_discount'=>array(), 'expenses'=>array(), 'timeline'=>array()];
        $totalPromotionalCost = ['total_expense' => 0, 'discount' => 0, 'coupon' => 0, 'campaign' => 0];
        //data filter for deterministic
        if($deterministic == 'month') {
            $months = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
            foreach ($months as $month) {
                $found=0;
                $chartData['timeline'][] = $month;
                foreach ($amounts as $item) {
                    if ($item['month'] == $month) {
                        //chart
                        $chartData['normal_discount'][] = with_decimal_point($item['discount_by_provider']);
                        $chartData['campaign_discount'][] = with_decimal_point($item['campaign_discount_by_provider']);
                        $chartData['coupon_discount'][] = with_decimal_point($item['coupon_discount_by_provider']);
                        $chartData['expenses'][] = with_decimal_point($item['discount_by_provider'] + $item['coupon_discount_by_provider'] + $item['campaign_discount_by_provider']);
                        $found=1;

                        //card
                        $totalPromotionalCost['discount'] += $item['discount_by_provider']??0;
                        $totalPromotionalCost['coupon'] += $item['coupon_discount_by_provider']??0;
                        $totalPromotionalCost['campaign'] += $item['campaign_discount_by_provider']??0;
                        $totalPromotionalCost['total_expense'] += $totalPromotionalCost['discount'] + $totalPromotionalCost['coupon'] + $totalPromotionalCost['campaign'];
                    }
                }
                //chart
                if(!$found){
                    $chartData['normal_discount'][] = with_decimal_point(0);
                    $chartData['campaign_discount'][] = with_decimal_point(0);
                    $chartData['coupon_discount'][] = with_decimal_point(0);
                    $chartData['expenses'][] = with_decimal_point(0);
                }
            }

        }
        elseif ($deterministic == 'year') {
            $totalPromotionalCost['total_expense'] = 0;
            foreach ($amounts as $key=>$item) {
                //chart
                $chartData['normal_discount'][] = with_decimal_point($item['discount_by_provider']);
                $chartData['campaign_discount'][] = with_decimal_point($item['campaign_discount_by_provider']);
                $chartData['coupon_discount'][] = with_decimal_point($item['coupon_discount_by_provider']);
                $chartData['expenses'][] = with_decimal_point($item['discount_by_provider'] + $item['coupon_discount_by_provider'] + $item['campaign_discount_by_provider']);
                $chartData['timeline'][] = $item[$deterministic];

                //card
                $totalPromotionalCost['discount'] += $item['discount_by_provider']??0;
                $totalPromotionalCost['coupon'] += $item['coupon_discount_by_provider']??0;
                $totalPromotionalCost['campaign'] += $item['campaign_discount_by_provider']??0;
                $totalPromotionalCost['total_expense'] += $item['discount_by_provider'] + $item['coupon_discount_by_provider'] + $item['campaign_discount_by_provider'];

            }
        }
        elseif ($deterministic == 'day') {
            if ($dateRange == 'this_month') {
                $to = Carbon::now()->lastOfMonth();
            } elseif ($dateRange == 'last_month') {
                $to = Carbon::now()->subMonth()->endOfMonth();
            } elseif ($dateRange == 'last_15_days') {
                $to = Carbon::now();
            }

            $number = date('d',strtotime($to));

            for ($i = 1; $i <= $number; $i++) {
                $found=0;
                $chartData['timeline'][] = $i;
                foreach ($amounts as $item) {
                    if ($item['day'] == $i) {
                        //chart
                        $chartData['normal_discount'][] = with_decimal_point($item['discount_by_provider']);
                        $chartData['campaign_discount'][] = with_decimal_point($item['campaign_discount_by_provider']);
                        $chartData['coupon_discount'][] = with_decimal_point($item['coupon_discount_by_provider']);
                        $chartData['expenses'][] = with_decimal_point($item['discount_by_provider'] + $item['coupon_discount_by_provider'] + $item['campaign_discount_by_provider']);
                        $found=1;

                        //card
                        $totalPromotionalCost['discount'] += $item['discount_by_provider']??0;
                        $totalPromotionalCost['coupon'] += $item['coupon_discount_by_provider']??0;
                        $totalPromotionalCost['campaign'] += $item['campaign_discount_by_provider']??0;
                        $totalPromotionalCost['total_expense'] += $totalPromotionalCost['discount'] + $totalPromotionalCost['coupon'] + $totalPromotionalCost['campaign'];
                    }
                }
                //chart
                if(!$found){
                    $chartData['normal_discount'][] = with_decimal_point(0);
                    $chartData['campaign_discount'][] = with_decimal_point(0);
                    $chartData['coupon_discount'][] = with_decimal_point(0);
                    $chartData['expenses'][] = with_decimal_point(0);
                }
            }
        }
        elseif ($deterministic == 'week') {
            if ($dateRange == 'this_week') {
                $from = Carbon::now()->startOfWeek();
                $to = Carbon::now()->endOfWeek();
            } elseif ($dateRange == 'last_week') {
                $from = Carbon::now()->subWeek()->startOfWeek();
                $to = Carbon::now()->subWeek()->endOfWeek();
            }

            for ($i = (int)$from->format('d'); $i <= (int)$to->format('d'); $i++) {
                $found=0;
                $chartData['timeline'][] = $i;
                foreach ($amounts as $item) {
                    if ($item['day'] == $i) {
                        //chart
                        $chartData['normal_discount'][] = with_decimal_point($item['discount_by_provider']);
                        $chartData['campaign_discount'][] = with_decimal_point($item['campaign_discount_by_provider']);
                        $chartData['coupon_discount'][] = with_decimal_point($item['coupon_discount_by_provider']);
                        $chartData['expenses'][] = with_decimal_point($item['discount_by_provider'] + $item['coupon_discount_by_provider'] + $item['campaign_discount_by_provider']);
                        $found=1;

                        //card
                        $totalPromotionalCost['discount'] += $item['discount_by_provider']??0;
                        $totalPromotionalCost['coupon'] += $item['coupon_discount_by_provider']??0;
                        $totalPromotionalCost['campaign'] += $item['campaign_discount_by_provider']??0;
                        $totalPromotionalCost['total_expense'] += $totalPromotionalCost['discount'] + $totalPromotionalCost['coupon'] + $totalPromotionalCost['campaign'];
                    }
                }
                //chart
                if(!$found){
                    $chartData['normal_discount'][] = with_decimal_point(0);
                    $chartData['campaign_discount'][] = with_decimal_point(0);
                    $chartData['coupon_discount'][] = with_decimal_point(0);
                    $chartData['expenses'][] = with_decimal_point(0);
                }
            }
        }

        return view('providermanagement::provider.report.business.expense', compact('zones', 'categories', 'subCategories', 'filteredBookingAmounts', 'chartData', 'totalPromotionalCost', 'deterministic', 'queryParams'));
    }

    public function getBusinessExpenseReportDownload(Request $request)
    {
        Validator::make($request->all(), [
            'zone_ids' => 'array',
            'zone_ids.*' => 'uuid',
            'category_ids' => 'array',
            'category_ids.*' => 'uuid',
            'sub_category_ids' => 'array',
            'sub_category_ids.*' => 'uuid',
            'date_range' => 'in:all_time, this_week, last_week, this_month, last_month, last_15_days, this_year, last_year, last_6_month, this_year_1st_quarter, this_year_2nd_quarter, this_year_3rd_quarter, this_year_4th_quarter, custom_date',
            'from' => $request['date_range'] == 'custom_date' ? 'required' : '',
            'to' => $request['date_range'] == 'custom_date' ? 'required' : '',
        ]);

        //** Table Data **
        $filteredBookingAmounts = $this->booking_details_amount
            ->with(['booking'])
            ->whereHas('booking', function ($query) use ($request) {
                self::filterQuery($query, $request)
                    ->ofBookingStatus('completed')
                    ->when($request->has('search'), function ($query) use ($request) {
                        $keys = explode(' ', $request['search']);
                        return $query->where(function ($query) use ($keys) {
                            foreach ($keys as $key) {
                                $query->where('readable_id', 'LIKE', '%' . $key . '%');
                            }
                        });
                    });
            })
            ->latest()
            ->get();

        return (new FastExcel($filteredBookingAmounts))->download(time().'-business-expense-report.xlsx', function ($item) {
            return [
                'Booking ID' => $item->booking->readable_id??'',
                'Normal Discount ('.currency_symbol().')' => with_decimal_point($item['discount_by_provider']),
                'Coupon Discount ('.currency_symbol().')' => with_decimal_point($item['coupon_discount_by_provider']),
                'Campaign Discount ('.currency_symbol().')' => with_decimal_point($item['campaign_discount_by_provider']),
                'Total Expense ('.currency_symbol().')' => with_decimal_point($item['discount_by_provider'] + $item['coupon_discount_by_provider'] + $item['campaign_discount_by_provider']),
            ];
        });
    }

    /**
     * @param $instance
     * @param $request
     * @return mixed
     */
    function filterQuery($instance, $request): mixed
    {
        return $instance
            ->where('provider_id', $request->user()->provider->id)
            ->when($request->has('zone_ids'), function ($query) use($request) {
                $query->whereIn('zone_id', $request['zone_ids']);
            })
            ->when($request->has('category_ids'), function ($query) use($request) {
                $query->whereIn('category_id', $request['category_ids']);
            })
            ->when($request->has('sub_category_ids'), function ($query) use($request) {
                $query->whereIn('sub_category_id', $request['sub_category_ids']);
            })
            ->when($request->has('date_range') && $request['date_range'] == 'custom_date', function ($query) use($request) {
                $query->whereBetween('created_at', [Carbon::parse($request['from'])->startOfDay(), Carbon::parse($request['to'])->endOfDay()]);
            })
            ->when($request->has('date_range') && $request['date_range'] != 'custom_date', function ($query) use($request) {
                //DATE RANGE
                if($request['date_range'] == 'this_week') {
                    //this week
                    $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);

                } elseif ($request['date_range'] == 'last_week') {
                    //last week
                    $query->whereBetween('created_at', [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]);

                } elseif ($request['date_range'] == 'this_month') {
                    //this month
                    $query->whereMonth('created_at', Carbon::now()->month);

                } elseif ($request['date_range'] == 'last_month') {
                    //last month
                    $query->whereMonth('created_at', Carbon::now()->subMonth()->month);

                } elseif ($request['date_range'] == 'last_15_days') {
                    //last 15 days
                    $query->whereBetween('created_at', [Carbon::now()->subDay(15), Carbon::now()]);

                } elseif ($request['date_range'] == 'this_year') {
                    //this year
                    $query->whereYear('created_at', Carbon::now()->year);

                } elseif ($request['date_range'] == 'last_year') {
                    //last year
                    $query->whereYear('created_at', Carbon::now()->subYear()->year);

                } elseif ($request['date_range'] == 'last_6_month') {
                    //last 6month
                    $query->whereBetween('created_at', [Carbon::now()->subMonth(6), Carbon::now()]);

                } elseif ($request['date_range'] == 'this_year_1st_quarter') {
                    //this year 1st quarter
                    $query->whereBetween('created_at', [Carbon::now()->month(1)->startOfQuarter(), Carbon::now()->month(1)->endOfQuarter()]);

                } elseif ($request['date_range'] == 'this_year_2nd_quarter') {
                    //this year 2nd quarter
                    $query->whereBetween('created_at', [Carbon::now()->month(4)->startOfQuarter(), Carbon::now()->month(4)->endOfQuarter()]);

                } elseif ($request['date_range'] == 'this_year_3rd_quarter') {
                    //this year 3rd quarter
                    $query->whereBetween('created_at', [Carbon::now()->month(7)->startOfQuarter(), Carbon::now()->month(7)->endOfQuarter()]);

                } elseif ($request['date_range'] == 'this_year_4th_quarter') {
                    //this year 4th quarter
                    $query->whereBetween('created_at', [Carbon::now()->month(10)->startOfQuarter(), Carbon::now()->month(10)->endOfQuarter()]);
                }
            });
    }
}
