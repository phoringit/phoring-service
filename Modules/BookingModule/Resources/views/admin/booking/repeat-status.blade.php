@extends('adminmodule::layouts.master')

@section('title', translate('Booking_Status'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="page-title-wrap mb-3">
                <h2 class="page-title">{{ translate('Booking_Details') }} </h2>
            </div>

            <div class="pb-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <h3 class="c1">{{ translate('Booking') }} # {{ $booking['readable_id'] }}</h3>
                        <span class="badge badge-{{
                            $booking->booking_status == 'ongoing' ? 'warning' :
                            ($booking->booking_status == 'completed' ? 'success' :
                            ($booking->booking_status == 'canceled' ? 'danger' : 'info'))
                        }}">
                            {{ ucwords($booking->booking_status) }}
                        </span>
                    </div>
                    <p class="opacity-75 fz-12">{{ translate('Booking_Placed') }}
                        : {{ date('d-M-Y h:ia', strtotime($booking->created_at)) }}</p>
                </div>
                <div class="d-flex flex-wrap flex-xxl-nowrap gap-3">
                    <div class="d-flex flex-wrap gap-3">
                        @if ($booking['payment_method'] == 'offline_payment' && !$booking['is_paid'])
                            <span class="btn btn--primary offline-payment" data-id="{{ $booking->id }}">
                                <span class="material-icons">done</span>{{ translate('Verify Offline Payment') }}
                            </span>
                        @endif
                        @if ($booking['payment_method'] == 'cash_after_service' && $booking->is_verified == '2')
                            <span class="btn btn--primary change-booking-request" data-id="{{ $booking->id }}"
                                data-bs-toggle="modal" data-bs-target="#exampleModal--{{ $booking->id }}">
                                <span class="material-icons">done</span>{{ translate('Change Request Status') }}
                            </span>

                            <div class="modal fade" id="exampleModal--{{ $booking->id }}" tabindex="-1"
                                aria-labelledby="exampleModalLabel--{{ $booking->id }}" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-body p-4 py-5">
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                            <div class="text-center mb-4 pb-3">
                                                <img class="mb-4"
                                                    src="{{ asset('/public/assets/admin-module/img/booking-req-status.png') }}"
                                                    alt="">
                                                <h3>{{ translate('Verify the booking request status?') }}</h3>
                                                <p>{{ translate('Need verification for max booking amount') }}</p>
                                            </div>
                                            <form method="post"
                                                action="{{ route('admin.booking.verification-status', [$booking->id]) }}">
                                                @csrf
                                                <div class="c1-light-bg p-4 rounded">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input approve-request" checked
                                                            type="radio" name="status" id="inlineRadio1" value="approve">
                                                        <label class="form-check-label"
                                                            for="inlineRadio1">{{ translate('Approve the Request') }}</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input deny-request" type="radio"
                                                            name="status" id="inlineRadio2" value="cancel">
                                                        <label class="form-check-label"
                                                            for="inlineRadio2">{{ translate('Cancel Booking') }}</label>
                                                    </div>
                                                </div>
                                                <div class="modal-footer text-center justify-content-center border-0">
                                                    <button type="submit"
                                                        class="btn btn--primary">{{ translate('submit') }}</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if (in_array($booking['booking_status'], ['accepted', 'ongoing']) && $booking['payment_method'] == 'cash_after_service' && !$booking['is_paid'])
                            @can('booking_edit')
                                <button class="btn btn--primary" data-bs-toggle="modal"
                                    data-bs-target="#serviceUpdateModal--{{ $booking['id'] }}" data-toggle="tooltip"
                                    title="{{ translate('Add or remove services') }}">
                                    <span class="material-symbols-outlined">edit</span>{{ translate('Edit Services') }}
                                </button>
                            @endcan
                        @endif
                        <a href="{{ route('admin.booking.single_invoice', [$booking->id]) }}" class="btn btn-primary"
                            target="_blank">
                            <span class="material-icons">description</span>{{ translate('Invoice') }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center flex-xxl-nowrap gap-3 mb-4">
                <ul class="nav nav--tabs nav--tabs__style2">
                    <li class="nav-item">
                        <a class="nav-link {{ $webPage == 'details' ? 'active' : '' }}"
                            href="{{ url()->current() }}?web_page=details">{{ translate('details') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ $webPage == 'status' ? 'active' : '' }}"
                            href="{{ url()->current() }}?web_page=status">{{ translate('status') }}</a>
                    </li>
                </ul>

                @php($max_booking_amount = business_config('max_booking_amount', 'booking_setup')->live_values ?? 0)

                @if (
                    $booking->is_verified == 2 &&
                        $booking->payment_method == 'cash_after_service' &&
                        $max_booking_amount <= $booking->total_booking_amount)
                    <div class="border border-danger-light bg-soft-danger rounded py-3 px-3 text-dark">
                        <span class="text-danger"># {{ translate('Note: ') }}</span>
                        <span>{{ $booking?->bookingDeniedNote?->value }}</span>
                    </div>
                @endif

                @if ($booking->is_paid == 0 && $booking->payment_method == 'offline_payment')
                    <div class="border border-danger-light bg-soft-danger rounded py-3 px-3 text-dark">
                        <span>
                            <span class="text-danger fw-semibold"> # {{ translate('Note: ') }} </span>
                            {{ translate('Please Check & Verify the payment information weather it is correct or not before confirm the booking. ') }}
                        </span>
                    </div>
                @endif

            </div>

            <div class="row gy-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="border-bottom pb-3 mb-3">
                                <div
                                    class="d-flex flex-column flex-sm-row justify-content-sm-between align-items-sm-center gap-3 flex-wrap">
                                    <div>
                                        <h4 class="mb-2">{{ translate('Payment Method') }}</h4>
                                        <h5 class="c1 mb-2"><span
                                                class="text-capitalize">{{ str_replace(['_', '-'], ' ', $booking->payment_method) }}
                                                @if ($booking->payment_method == 'offline_payment' && $booking?->booking_offline_payments?->first()?->method_name)
                                                    ({{ $booking?->booking_offline_payments?->first()?->method_name }})
                                                @endif
                                            </span>
                                        </h5>
                                        <p>
                                            <span>{{ translate('Amount') }} : </span>
                                            {{ with_currency_symbol($booking->total_booking_amount) }}
                                        </p>
                                        @if ($booking->payment_method == 'offline_payment')
                                            <h4 class="mb-2">{{ translate('Payment_Info') }}</h4>
                                            <div class="d-flex gap-1 flex-column">
                                                @foreach ($booking?->booking_offline_payments?->first()?->customer_information ?? [] as $key => $item)
                                                    <div><span>{{ translate($key) }}</span>:
                                                        <span>{{ translate($item) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-start text-sm-end">
                                        <p class="mb-2">
                                            <span>{{ translate('Payment_Status') }} : </span>
                                            <span class="text-{{ $booking->is_paid ? 'success' : 'danger' }}"
                                                id="payment_status__span">{{ $booking->is_paid ? translate('Paid') : translate('Unpaid') }}</span>
                                        </p>

                                        <h5 class="d-flex gap-1 flex-wrap align-items-center">
                                            <div>{{ translate('Schedule_Date') }} :</div>
                                            <div id="service_schedule__span">
                                                <div>{{ date('d-M-Y h:ia', strtotime($booking->service_schedule)) }} <span
                                                        class="text-secondary">{{ $booking?->scheduleHistories->count() > 1 ? '(' . translate('Edited') . ')' : '' }}</span>
                                                </div>

                                                <div class="timeline-container">
                                                    <ul class="timeline-sessions">
                                                        <p class="fs-14">{{ translate('Schedule Change Log') }}</p>
                                                        @foreach ($booking?->scheduleHistories()->orderBy('created_at', 'desc')->get() as $history)
                                                            <li
                                                                class="{{ $booking->service_schedule == $history->schedule ? 'active' : '' }}">
                                                                <div class="timeline-date">
                                                                    {{ \Carbon\Carbon::parse($history->schedule)->format('d-M-Y') }}
                                                                </div>
                                                                <div class="timeline-time">
                                                                    {{ \Carbon\Carbon::parse($history->schedule)->format('h:i A') }}
                                                                </div>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            </div>
                                        </h5>
                                    </div>
                                </div>
                            </div>

                            <div class="timeline-wrapper mt-4 ps-xl-5">
                                <div class="timeline-steps m-0">
                                    <div class="timeline-step completed">
                                        <div class="timeline-number">
                                            <svg viewBox="0 0 512 512" width="100" title="check">
                                                <path
                                                    d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z" />
                                            </svg>
                                        </div>
                                        <div class="timeline-info">
                                            <p class="timeline-title text-capitalize">{{ translate('Booking_Placed') }}
                                            </p>
                                            <p class="timeline-text">
                                                {{ date('d-M-Y h:ia', strtotime($booking->created_at)) }}</p>
                                            <p class="timeline-text">By
                                                -
                                                {{ isset($booking->customer) ? Str::limit($booking->customer->first_name . ' ' . $booking->customer->last_name, 30) : translate('Not_Available') }}
                                            </p>
                                        </div>
                                    </div>
                                    @foreach ($booking->statusHistories as $status_history)
                                        <div class="timeline-step completed">
                                            <div class="timeline-number">
                                                <svg viewBox="0 0 512 512" width="100" title="check">
                                                    <path
                                                        d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z" />
                                                </svg>
                                            </div>
                                            <div class="timeline-info">
                                                <p class="timeline-title text-capitalize">
                                                    {{ $status_history->booking_status }}</p>
                                                <p class="timeline-text">
                                                    {{ date('d-M-Y h:ia', strtotime($status_history->created_at)) }}</p>
                                                <p class="timeline-text">{{ translate('By') }}
                                                    @if (isset($status_history->user->provider))
                                                        -
                                                        {{ Str::limit($status_history?->user?->provider?->company_name, 30) }}
                                                    @else
                                                        -
                                                        {{ isset($status_history->user) ? Str::limit($status_history->user->first_name . ' ' . $status_history->user->last_name, 30) : '' }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="c1">{{ translate('Booking Setup') }}</h3>
                            <hr>
                            @can('booking_can_manage_status')
                                <div class="d-flex justify-content-between align-items-center gap-10 form-control h-45">
                                    <span class="title-color">{{ translate('Payment Status') }}</span>

                                    <div class="on-off-toggle">
                                        <input class="on-off-toggle__input switcher_input"
                                               value="{{ $booking['is_paid'] ? '1' : '0' }}"
                                               {{ $booking['is_paid'] ? 'checked' : '' }} type="checkbox"
                                               id="payment_status" />
                                        <label for="payment_status" class="on-off-toggle__slider">
                                            <span class="on-off-toggle__on">
                                                <span class="on-off-toggle__text">{{ translate('Paid') }}</span>
                                                <span class="on-off-toggle__circle"></span>
                                            </span>
                                            <span class="on-off-toggle__off">
                                                <span class="on-off-toggle__circle"></span>
                                                <span class="on-off-toggle__text">{{ translate('Unpaid') }}</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            @endcan

                            @can('booking_can_manage_status')
                                <div class="mt-3">
                                    @if ($booking->booking_status != 'pending')
                                        <select class="js-select without-search" id="booking_status">
                                            @if($booking['booking_status'] != 'canceled')
                                                <option value="0" disabled
                                                    {{ $booking['booking_status'] == 'accepted' ? 'selected' : '' }}>
                                                    {{ translate('Booking_Status') }}: {{ translate('Accepted') }}</option>
                                                <option value="ongoing"
                                                    {{ $booking['booking_status'] == 'ongoing' ? 'selected' : '' }}>
                                                    {{ translate('Booking_Status') }}: {{ translate('Ongoing') }}</option>
                                                <option value="completed"
                                                    {{ $booking['booking_status'] == 'completed' ? 'selected' : '' }}>
                                                    {{ translate('Booking_Status') }}: {{ translate('Completed') }}</option>
                                            @endif
                                            @if($booking['booking_status'] != 'completed' && !$booking->is_paid)
                                                <option value="canceled"
                                                    {{ $booking['booking_status'] == 'canceled' ? 'selected' : '' }}>
                                                    {{ translate('Booking_Status') }}: {{ translate('Canceled') }}</option>
                                            @endif
                                        </select>
                                    @endif
                                </div>
                            @endcan
                            <div class="mt-3">
                                @if (!in_array($booking->booking_status, ['ongoing', 'completed']))
                                    @can('booking_can_manage_status')
                                        <input type="datetime-local" class="form-control h-45" name="service_schedule"
                                               value="{{ $booking->service_schedule }}" id="service_schedule"
                                               onchange="service_schedule_update()">
                                    @endcan
                                @endif
                            </div>

                            <div class="py-3 d-flex flex-column gap-3 mb-2">
                                @if ($booking->evidence_photos)
                                    <div class="c1-light-bg radius-10 py-3 px-4">
                                        <div class="d-flex justify-content-start gap-2">
                                            <h4 class="mb-2">{{ translate('uploaded_Images') }}</h4>
                                        </div>

                                        <div class="py-3 px-4">
                                            <div class="d-flex flex-wrap gap-3 justify-content-lg-start">
                                                @foreach ($booking->evidence_photos_full_path ?? [] as $key => $img)
                                                    <img width="100" class="max-height-100"
                                                         src="{{ $img }}"
                                                         alt="{{ translate('evidence-photo') }}" @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="c1-light-bg radius-10">
                                    <div
                                        class="border-bottom d-flex align-items-center justify-content-between gap-2 py-3 px-4 mb-2">
                                        <h4 class="d-flex align-items-center gap-2">
                                            <span class="material-icons title-color">person</span>
                                            {{ translate('Customer_Information') }}
                                        </h4>

                                        <div class="btn-group">
                                            @if (in_array($booking->booking_status, ['completed', 'cancelled']))
                                                @if (!$booking?->is_guest)
                                                    <div
                                                        class="d-flex align-items-center gap-2 cursor-pointer customer-chat">
                                                        <span class="material-symbols-outlined">chat</span>
                                                        <form action="{{ route('admin.chat.create-channel') }}"
                                                              method="post" id="chatForm-{{ $booking->booking_id }}">
                                                            @csrf
                                                            <input type="hidden" name="customer_id"
                                                                   value="{{ $booking?->booking?->customer?->id }}">
                                                            <input type="hidden" name="type" value="booking">
                                                            <input type="hidden" name="user_type" value="customer">
                                                        </form>
                                                    </div>
                                                @endif
                                            @else
                                                <div class="cursor-pointer" data-bs-toggle="dropdown"
                                                     aria-expanded="false">
                                                    <span class="material-symbols-outlined">more_vert</span>
                                                </div>
                                                <ul
                                                    class="dropdown-menu dropdown-menu__custom border-none dropdown-menu-end">
                                                    @can('booking_edit')
                                                        <li data-bs-toggle="modal"
                                                            data-bs-target="#serviceAddressModal--{{ $booking['id'] }}"
                                                            data-toggle="tooltip" data-placement="top">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="material-symbols-outlined">edit_square</span>
                                                                {{ translate('Edit_Details') }}
                                                            </div>
                                                        </li>
                                                    @endcan
                                                    @if (!$booking?->is_guest)
                                                        <li>
                                                            <div
                                                                class="d-flex align-items-center gap-2 cursor-pointer customer-chat">
                                                                <span class="material-symbols-outlined">chat</span>
                                                                {{ translate('chat_with_Customer') }}
                                                                <form action="{{ route('admin.chat.create-channel') }}"
                                                                      method="post" id="chatForm-{{ $booking->booking_id }}">
                                                                    @csrf
                                                                    <input type="hidden" name="customer_id"
                                                                           value="{{ $booking?->booking?->customer?->id }}">
                                                                    <input type="hidden" name="type" value="booking">
                                                                    <input type="hidden" name="user_type"
                                                                           value="customer">
                                                                </form>
                                                            </div>
                                                        </li>
                                                    @endif
                                                </ul>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="py-3 px-4">
                                        @php($customer_name = $booking?->booking?->service_address?->contact_person_name)
                                        @php($customer_phone = $booking?->booking?->service_address?->contact_person_number)

                                        <div class="media gap-2 flex-wrap">
                                            @if (!$booking?->booking?->is_guest && $booking?->booking?->customer)
                                                <img width="58" height="58"
                                                     class="rounded-circle border border-white"
                                                     src="{{ $booking?->booking?->customer?->profile_image_full_path }}"
                                                     alt="{{ translate('user_image') }}">
                                            @else
                                                <img width="58" height="58"
                                                     class="rounded-circle border border-white"
                                                     src="{{ asset('public/assets/provider-module/img/user2x.png') }}"
                                                     alt="{{ translate('user_image') }}">
                                            @endif

                                            <div class="media-body">
                                                <h5 class="c1 mb-3">
                                                    @if (!$booking?->booking?->is_guest && $booking?->booking?->customer)
                                                        <a href="{{ route('admin.customer.detail', [$booking?->booking?->customer?->id, 'web_page' => 'overview']) }}"
                                                           class="c1">{{ Str::limit($customer_name, 30) }}</a>
                                                    @else
                                                        <span>{{ Str::limit($customer_name ?? '', 30) }}</span>
                                                    @endif
                                                </h5>
                                                <ul class="list-info">
                                                    @if ($customer_phone)
                                                        <li>
                                                            <span class="material-icons">phone_iphone</span>
                                                            <a
                                                                href="tel:{{ $customer_phone }}">{{ $customer_phone }}</a>
                                                        </li>
                                                    @endif
                                                    <li>
                                                        <span class="material-icons">map</span>
                                                        <p>{{ Str::limit($booking?->booking?->service_address?->address ?? translate('not_available'), 100) }}
                                                        </p>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="c1-light-bg radius-10 provider-information">
                                    <div
                                        class="border-bottom d-flex align-items-center justify-content-between gap-2 py-3 px-4 mb-2">
                                        <h4 class="d-flex align-items-center gap-2">
                                            <span class="material-icons title-color">person</span>
                                            {{ translate('Provider_Information') }}
                                        </h4>
                                        @if (isset($booking->provider))
                                            <div class="btn-group">
                                                <div class="cursor-pointer" data-bs-toggle="dropdown"
                                                     aria-expanded="false">
                                                    <span class="material-symbols-outlined">more_vert</span>
                                                </div>
                                                <ul
                                                    class="dropdown-menu dropdown-menu__custom border-none dropdown-menu-end">
                                                    <li>
                                                        <div
                                                            class="d-flex align-items-center gap-2 cursor-pointer provider-chat">
                                                            <span class="material-symbols-outlined">chat</span>
                                                            {{ translate('chat_with_Provider') }}
                                                            <form action="{{ route('admin.chat.create-channel') }}"
                                                                  method="post" id="chatForm-{{ $booking->id }}">
                                                                @csrf
                                                                <input type="hidden" name="provider_id"
                                                                       value="{{ $booking?->provider?->owner?->id }}">
                                                                <input type="hidden" name="type" value="booking">
                                                                <input type="hidden" name="user_type"
                                                                       value="provider-admin">
                                                            </form>
                                                        </div>
                                                    </li>
                                                    @if (in_array($booking->booking_status, ['ongoing', 'accepted']))
                                                        @can('booking_can_manage_status')
                                                            <li>
                                                                <div class="d-flex align-items-center gap-2"
                                                                     data-bs-target="#providerModal" data-bs-toggle="modal">
                                                                    <span
                                                                        class="material-symbols-outlined">manage_history</span>
                                                                    {{ translate('change_Provider') }}
                                                                </div>
                                                            </li>
                                                        @endcan
                                                    @endif
                                                    <li>
                                                        <a class="d-flex align-items-center gap-2 cursor-pointer p-0"
                                                           href="{{ route('admin.provider.details', [$booking?->provider?->id, 'web_page' => 'overview']) }}">
                                                            <span class="material-icons">person</span>
                                                            {{ translate('View_Details') }}
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                    @if (isset($booking->provider))
                                        <div class="py-3 px-4">
                                            <div class="media gap-2 flex-wrap">
                                                <img width="58" height="58"
                                                     class="rounded-circle border border-white"
                                                     src="{{ $booking?->provider?->logo_full_path }}"
                                                     alt="{{ translate('provider') }}">
                                                <div class="media-body">
                                                    <a
                                                        href="{{ route('admin.provider.details', [$booking?->provider?->id, 'web_page' => 'overview']) }}">
                                                        <h5 class="c1 mb-3">
                                                            {{ Str::limit($booking->provider->company_name ?? '', 30) }}
                                                        </h5>
                                                    </a>
                                                    <ul class="list-info">
                                                        <li>
                                                            <span class="material-icons">phone_iphone</span>
                                                            <a
                                                                href="tel:{{ $booking->provider->contact_person_phone ?? '' }}">{{ $booking->provider->contact_person_phone ?? '' }}</a>
                                                        </li>
                                                        <li>
                                                            <span class="material-icons">map</span>
                                                            <p>{{ Str::limit($booking->provider->company_address ?? '', 100) }}
                                                            </p>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="d-flex flex-column gap-2 mt-30 align-items-center">
                                            <span class="material-icons text-muted fs-2">account_circle</span>
                                            <p class="text-muted text-center fw-medium mb-3">
                                                {{ translate('No Provider Information') }}</p>
                                        </div>
                                        <div class="text-center pb-4">
                                            <button class="btn btn--primary" data-bs-target="#providerModal" data-bs-toggle="modal" @if($booking['booking_status'] == 'canceled') disabled @endif>{{ translate('assign provider') }}</button>
                                        </div>
                                    @endif
                                </div>

                                <div class="c1-light-bg radius-10 serviceman-information">
                                    <div
                                        class="border-bottom d-flex align-items-center justify-content-between gap-2 py-3 px-4 mb-2">
                                        <h4 class="d-flex align-items-center gap-2">
                                            <span class="material-icons title-color">person</span>
                                            {{ translate('Serviceman_Information') }}
                                        </h4>
                                        @if (isset($booking->serviceman) && in_array($booking->booking_status, ['ongoing', 'accepted']))
                                            <div class="btn-group">
                                                <div class="cursor-pointer" data-bs-toggle="dropdown"
                                                     aria-expanded="false">
                                                    <span class="material-symbols-outlined">more_vert</span>
                                                </div>
                                                <ul class="dropdown-menu dropdown-menu__custom border-none dropdown-menu-end">
                                                    @can('booking_can_manage_status')
                                                        <li>
                                                            <div class="d-flex align-items-center gap-2"
                                                                 data-bs-target="#servicemanModal" data-bs-toggle="modal">
                                                                <span
                                                                    class="material-symbols-outlined">manage_history</span>
                                                                {{ translate('change serviceman') }}
                                                            </div>
                                                        </li>
                                                    @endcan
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                    @if (isset($booking->serviceman))
                                        <div class="py-3 px-4">
                                            <div class="media gap-2 flex-wrap">
                                                <img width="58" height="58"
                                                     class="rounded-circle border border-white"
                                                     src="{{ $booking?->serviceman?->user?->profile_image_full_path }}"
                                                     alt="{{ translate('serviceman') }}">
                                                <div class="media-body">
                                                    <h5 class="c1 mb-3">
                                                        {{ Str::limit($booking->serviceman && $booking->serviceman->user ? $booking->serviceman->user->first_name . ' ' . $booking->serviceman->user->last_name : '', 30) }}
                                                    </h5>
                                                    <ul class="list-info">
                                                        <li>
                                                            <span class="material-icons">phone_iphone</span>
                                                            <a
                                                                href="tel:{{ $booking->serviceman && $booking->serviceman->user ? $booking->serviceman->user->phone : '' }}">
                                                                {{ $booking->serviceman && $booking->serviceman->user ? $booking->serviceman->user->phone : '' }}
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="d-flex flex-column gap-2 mt-30 align-items-center">
                                            <span class="material-icons text-muted fs-2">account_circle</span>
                                            <p class="text-muted text-center fw-medium mb-3">
                                                {{ translate('No Serviceman Information') }}</p>
                                        </div>
                                        <div class="text-center pb-4">
                                            <button class="btn btn--primary" data-bs-target="#servicemanModal" data-bs-toggle="modal"
                                                {{ ($booking['booking_status'] == 'completed' || $booking['booking_status'] == 'canceled') ? 'disabled' : '' }}>
                                                {{ translate('assign Serviceman') }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="providerModal" tabindex="-1" aria-labelledby="providerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content modal-content-data" id="modal-data-info">
                @include('bookingmodule::admin.booking.partials.details.provider-info-modal-data')
            </div>
        </div>
    </div>

    <div class="modal fade" id="servicemanModal" tabindex="-1" aria-labelledby="servicemanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content modal-content-data1" id="modal-data-info1">
                @include('bookingmodule::admin.booking.partials.details.serviceman-info-modal-data')
            </div>
        </div>
    </div>

    <div class="modal fade" id="serviceUpdateModal--{{ $booking['id'] }}" tabindex="-1"
         aria-labelledby="serviceUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header px-4 pt-4 border-0 pb-1">
                    <h3 class="text-capitalize">{{ translate('update_booking_list') }}</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4">
                    <div class="d-flex align-items-end justify-content-between gap-3 flex-wrap mb-4">
                        <div>
                            <h5 class="mb-2">
                                {{ translate('Booking') }} # {{ $booking['readable_id'] }}
                            </h5>
                            <h3 class="c1 fw-bold mb-2">{{ translate('Sub-Booking') }} # {{ $booking['readable_id']}}
                            </h3>
                        </div>
                        <h5 class="d-flex gap-1 flex-wrap align-items-center justify-content-end fw-normal mb-2">
                            <div>{{ translate('Schedule_Date') }} :</div>
                            <div id="service_schedule__span">
                                <div class="fw-semibold">{{ date('d-M-Y h:ia', strtotime($booking->created_at)) }}</div>
                            </div>
                        </h5>
                    </div>

                    <div class="bg-F8F8F8 p-3 mb-3">
                        <h4 class="mb-3"> {{ translate('Service') }} : {{ translate('AC_Repairing') }}
                        </h4>
                        <div class="d-flex flex-wrap gap-3">
                            <h4> {{ translate('Category') }} : {{ $booking->booking->category->name }}</h4>
                            <h4> {{ translate('SubCategory') }} : {{ $booking->booking->subCategory->name }}</h4>
                        </div>
                    </div>

                    <div class="mb-30">
                        <span class="c1 fw-semibold"> # {{ translate('Note') }}:</span>
                        <span class="title-color">
                        {{ translate('Please provide extra layer in the packaging') }}</span>
                    </div>

                    <form action="{{ route('admin.booking.service.update_repeat_booking_service') }}" method="POST"
                          id="booking-edit-table" class="mb-30">
                        <div class="table-responsive">
                            <table class="table text-nowrap align-middle mb-0" id="service-edit-table">
                                @csrf
                                @method('put')
                                <thead>
                                <tr>
                                    <th class="ps-lg-3 fw-bold">{{ translate('Service') }}</th>
                                    <th class="fw-bold text--end">{{ translate('Price') }}</th>
                                    <th class="fw-bold text-center">{{ translate('Qty') }}</th>
                                    <th class="fw-bold text--end">{{ translate('Discount') }}</th>
                                    <th class="fw-bold text--end">{{ translate('Total') }}</th>
                                </tr>
                                </thead>

                                <tbody id="service-edit-tbody">
                                @php($sub_total = 0)
                                @foreach ($booking->detail as $key => $detail)
                                    <tr id="service-row--{{ $detail?->variant_key }}">
                                        <td class="text-wrap ps-lg-3">
                                            @if (isset($detail->service))
                                                <div class="d-flex flex-column">
                                                    <a href="{{ route('admin.service.detail', [$detail->service->id]) }}"
                                                       class="fw-bold">{{ Str::limit($detail->service->name, 30) }}</a>
                                                    <div>{{ Str::limit($detail ? $detail->variant_key : '', 50) }}
                                                    </div>
                                                </div>
                                            @else
                                                <span
                                                    class="badge badge-pill badge-danger">{{ translate('Service_unavailable') }}</span>
                                            @endif
                                        </td>
                                        <td class="text--end" id="service-cost-{{ $detail?->variant_key }}">
                                            {{ currency_symbol() . ' ' . $detail->service_cost }}</td>
                                        <td>
                                            <input type="number" min="1" name="qty[]"
                                                   class="form-control qty-width dark-color-bo m-auto"
                                                   id="qty-{{ $detail?->variant_key }}"
                                                   value="{{ $detail->quantity }}"
                                                   oninput="this.value = this.value.replace(/[^0-9]/g, '');">
                                        </td>
                                        <td class="text--end" id="discount-amount-{{ $detail?->variant_key }}">
                                            {{ currency_symbol() . ' ' . $detail->discount_amount }}</td>
                                        <td class="text--end" id="total-cost-{{ $detail?->variant_key }}">
                                            {{ currency_symbol() . ' ' . $detail->total_cost }}
                                        </td>
                                        <input type="hidden" name="service_ids[]"
                                               value="{{ $detail->service->id }}">
                                        <input type="hidden" name="variant_keys[]"
                                               value="{{ $detail->variant_key }}">
                                    </tr>
                                    @php($sub_total += $detail->service_cost * $detail->quantity)
                                @endforeach
                                <input type="hidden" name="zone_id" value="{{ $booking->booking->zone_id }}">
                                <input type="hidden" name="booking_id" value="{{ $booking->booking_id }}">
                                <input type="hidden" name="booking_repeat_id" value="{{ $booking->id }}">
                                </tbody>
                            </table>
                        </div>
                    </form>

                </div>
                <div class="modal-footer d-flex justify-content-end gap-3 border-0 pt-0 pb-4">
                    <button type="button" class="btn btn--secondary" data-bs-dismiss="modal"
                            aria-label="Close">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn--primary"
                            form="booking-edit-table">{{ translate('update_cart') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict";

        $('.switcher_input').on('click', function() {
            let paymentStatus = $(this).is(':checked') === true ? 1 : 0;
            payment_status_change(paymentStatus)
        })

        $('.reassign-provider').on('click', function() {
            let id = $(this).data('provider-reassign');
            updateProvider(id)
        })

        $('.reassign-serviceman').on('click', function() {
            let id = $(this).data('serviceman-reassign');
            updateServiceman(id)
        })

        $('.offline-payment').on('click', function() {
            let route = '{{ route('admin.booking.offline-payment.verify', ['booking_id' => $booking->id]) }}';
            route_alert_reload(route, '{{ translate('Want to verify the payment') }}', true);
        })

        @if ($booking->booking_status == 'pending')
        $(document).ready(function() {
            selectElementVisibility('serviceman_assign', false);
            selectElementVisibility('payment_status', false);
        });
        @endif

        $("#booking_status").change(function() {
            var booking_status = $("#booking_status option:selected").val();
            if (parseInt(booking_status) !== 0) {
                var route = '{{ route('admin.booking.status_update', [$booking->id]) }}' + '?booking_status=' +
                    booking_status;
                update_booking_details(route, '{{ translate('want_to_update_status') }}', 'booking_status',
                    booking_status);
            } else {
                toastr.error('{{ translate('choose_proper_status') }}');
            }
        });

        $("#serviceman_assign").change(function() {
            var serviceman_id = $("#serviceman_assign option:selected").val();
            if (serviceman_id !== 'no_serviceman') {
                var route = '{{ route('admin.booking.serviceman_update', [$booking->id]) }}' + '?serviceman_id=' +
                    serviceman_id;

                update_booking_details(route, '{{ translate('want_to_assign_the_serviceman') }}?',
                    'serviceman_assign', serviceman_id);
            } else {
                toastr.error('{{ translate('choose_proper_serviceman') }}');
            }
        });

        function payment_status_change(payment_status) {
            var route = '{{ route('admin.booking.payment_update', [$booking->id]) }}' + '?payment_status=' +
                payment_status;
            update_booking_details(route, '{{ translate('want_to_update_status') }}', 'payment_status', payment_status);
        }

        function service_schedule_update() {
            var service_schedule = $("#service_schedule").val();
            var route = '{{ route('admin.booking.schedule_update', [$booking->id]) }}' + '?service_schedule=' +
                service_schedule;

            update_booking_details(route, '{{ translate('want_to_update_the_booking_schedule') }}', 'service_schedule',
                service_schedule);
        }

        function update_booking_details(route, message, componentId, updatedValue) {
            Swal.fire({
                title: "{{ translate('are_you_sure') }}?",
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'var(--c2)',
                confirmButtonColor: 'var(--c1)',
                cancelButtonText: '{{ translate('Cancel') }}',
                confirmButtonText: '{{ translate('Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.get({
                        url: route,
                        dataType: 'json',
                        data: {},
                        beforeSend: function() {},
                        success: function(data) {
                            update_component(componentId, updatedValue);
                            toastr.success(data.message, {
                                CloseButton: true,
                                ProgressBar: true
                            });

                            if (componentId === 'booking_status' || componentId === 'payment_status' ||
                                componentId === 'service_schedule' || componentId ===
                                'serviceman_assign') {
                                location.reload();
                            }
                        },
                        complete: function() {},
                    });
                }
            })
        }

        function update_component(componentId, updatedValue) {

            if (componentId === 'booking_status') {
                $("#booking_status__span").html(updatedValue);

                selectElementVisibility('serviceman_assign', true);
                selectElementVisibility('payment_status', true);

            } else if (componentId === 'payment_status') {
                $("#payment_status__span").html(updatedValue);
                if (updatedValue === 'paid') {
                    $("#payment_status__span").addClass('text-success').removeClass('text-danger');
                } else if (updatedValue === 'unpaid') {
                    $("#payment_status__span").addClass('text-danger').removeClass('text-success');
                }

            }
        }

        function selectElementVisibility(componentId, visibility) {
            if (visibility === true) {
                $('#' + componentId).next(".select2-container").show();
            } else if (visibility === false) {
                $('#' + componentId).next(".select2-container").hide();
            } else {}
        }
    </script>

    <script>
        $(document).ready(function() {
            $('#category_selector__select').select2({
                dropdownParent: "#serviceUpdateModal--{{ $booking['id'] }}"
            });
            $('#sub_category_selector__select').select2({
                dropdownParent: "#serviceUpdateModal--{{ $booking['id'] }}"
            });
            $('#service_selector__select').select2({
                dropdownParent: "#serviceUpdateModal--{{ $booking['id'] }}"
            });
            $('#service_variation_selector__select').select2({
                dropdownParent: "#serviceUpdateModal--{{ $booking['id'] }}"
            });
        });

    </script>


    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ business_config('google_map', 'third_party')?->live_values['map_api_key_client'] }}&libraries=places&v=3.45.8">
    </script>
    <script>
        function readURL(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function(e) {
                    $('#viewer').attr('src', e.target.result);
                }

                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function() {
            readURL(this);
        });

        $(document).ready(function() {
            function initAutocomplete() {
                let myLatLng = {
                    lat: {{ $customerAddress->lat ?? 23.811842872190343 }},
                    lng: {{ $customerAddress->lon ?? 90.356331 }}
                };
                const map = new google.maps.Map(document.getElementById("location_map_canvas"), {
                    center: myLatLng,
                    zoom: 13,
                    mapTypeId: "roadmap",
                });

                let marker = new google.maps.Marker({
                    position: myLatLng,
                    map: map,
                });

                marker.setMap(map);
                var geocoder = geocoder = new google.maps.Geocoder();
                google.maps.event.addListener(map, 'click', function(mapsMouseEvent) {
                    var coordinates = JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2);
                    var coordinates = JSON.parse(coordinates);
                    var latlng = new google.maps.LatLng(coordinates['lat'], coordinates['lng']);
                    marker.setPosition(latlng);
                    map.panTo(latlng);

                    document.getElementById('latitude').value = coordinates['lat'];
                    document.getElementById('longitude').value = coordinates['lng'];


                    geocoder.geocode({
                        'latLng': latlng
                    }, function(results, status) {
                        if (status == google.maps.GeocoderStatus.OK) {
                            if (results[1]) {
                                document.getElementById('address').value = results[1]
                                    .formatted_address;
                            }
                        }
                    });
                });

                const input = document.getElementById("pac-input");
                const searchBox = new google.maps.places.SearchBox(input);
                map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);

                map.addListener("bounds_changed", () => {
                    searchBox.setBounds(map.getBounds());
                });
                let markers = [];

                searchBox.addListener("places_changed", () => {
                    const places = searchBox.getPlaces();

                    if (places.length == 0) {
                        return;
                    }

                    markers.forEach((marker) => {
                        marker.setMap(null);
                    });
                    markers = [];

                    const bounds = new google.maps.LatLngBounds();
                    places.forEach((place) => {
                        if (!place.geometry || !place.geometry.location) {
                            console.log("Returned place contains no geometry");
                            return;
                        }
                        var mrkr = new google.maps.Marker({
                            map,
                            title: place.name,
                            position: place.geometry.location,
                        });
                        google.maps.event.addListener(mrkr, "click", function(event) {
                            document.getElementById('latitude').value = this.position.lat();
                            document.getElementById('longitude').value = this.position
                                .lng();
                        });

                        markers.push(mrkr);

                        if (place.geometry.viewport) {
                            bounds.union(place.geometry.viewport);
                        } else {
                            bounds.extend(place.geometry.location);
                        }
                    });
                    map.fitBounds(bounds);
                });
            };
            initAutocomplete();
        });


        $('.__right-eye').on('click', function() {
            if ($(this).hasClass('active')) {
                $(this).removeClass('active')
                $(this).find('i').removeClass('tio-invisible')
                $(this).find('i').addClass('tio-hidden-outlined')
                $(this).siblings('input').attr('type', 'password')
            } else {
                $(this).addClass('active')
                $(this).siblings('input').attr('type', 'text')


                $(this).find('i').addClass('tio-invisible')
                $(this).find('i').removeClass('tio-hidden-outlined')
            }
        })
    </script>

    <script>
        $(document).ready(function() {

            $(document).on('click', '.sort-by-class', function() {
                console.log('hi')
                const route = '{{ url('admin/provider/available-provider') }}'
                var sortOption = document.querySelector('input[name="sort"]:checked').value;
                var bookingId = "{{ $booking->id }}"

                $.get({
                    url: route,
                    dataType: 'json',
                    data: {
                        sort_by: sortOption,
                        booking_id: bookingId
                    },
                    beforeSend: function() {

                    },
                    success: function(response) {
                        $('.modal-content-data').html(response.view);
                    },
                    complete: function() {},
                    error: function() {
                        toastr.error('{{ translate('Failed to load') }}')
                    }
                });
            })
        });

        $(document).ready(function() {
            $(document).on('keyup', '.search-form-input', function() {
                const route = '{{ url('admin/provider/available-provider') }}';
                let sortOption = document.querySelector('input[name="sort"]:checked').value;
                let bookingId = "{{ $booking->id }}";
                let searchTerm = $('.search-form-input').val();

                $.get({
                    url: route,
                    dataType: 'json',
                    data: {
                        sort_by: sortOption,
                        booking_id: bookingId,
                        search: searchTerm,
                    },
                    beforeSend: function() {},
                    success: function(response) {
                        $('.modal-content-data').html(response.view);


                        var cursorPosition = searchTerm.lastIndexOf(searchTerm.charAt(searchTerm
                            .length - 1)) + 1;
                        $('.search-form-input').focus().get(0).setSelectionRange(cursorPosition,
                            cursorPosition);
                    },
                    complete: function() {},
                    error: function() {
                        toastr.error('{{ translate('Failed to load') }}');
                    }
                });
            });
        });

        function updateProvider(providerId) {
            const bookingId = "{{ $booking->id }}";
            const route = '{{ url('admin/provider/reassign-provider') }}' + '/' + bookingId;
            const sortOption = document.querySelector('input[name="sort"]:checked').value;
            const searchTerm = $('.search-form-input').val();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.ajax({
                url: route,
                type: 'PUT',
                dataType: 'json',
                data: {
                    sort_by: sortOption,
                    booking_id: bookingId,
                    search: searchTerm,
                    provider_id: providerId
                },
                beforeSend: function() {
                    toastr.info('{{ translate('Processing request...') }}');
                },
                success: function(response) {
                    $('.modal-content-data').html(response.view);
                    toastr.success('{{ translate('Successfully reassign provider') }}');
                    setTimeout(function() {
                        location.reload()
                    }, 600);
                },
                complete: function() {},
                error: function() {
                    toastr.error('{{ translate('Failed to load') }}');
                }
            });
        }



        $(document).ready(function() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $(document).on('keyup', '.search-form-input1', function() {
                const route = '{{ url('admin/booking/serviceman-update', $booking->id) }}';
                let searchTerm = $('.search-form-input1').val();

                $.ajax({
                    url: route,
                    type: 'PUT',
                    dataType: 'json',
                    data: {
                        booking_id: "{{ $booking->id }}",
                        search: searchTerm,
                    },
                    beforeSend: function() {},
                    success: function(response) {
                        $('.modal-content-data1').html(response.view);
                    },
                    complete: function() {},
                    error: function(xhr) {
                        if (xhr.status === 419) {
                            toastr.error('{{ translate('Session expired, please refresh the page.') }}');
                        } else {
                            toastr.error('{{ translate('Failed to load') }}');
                        }
                    }
                });
            });
        });


        function updateServiceman(servicemanId) {
            const bookingId = "{{ $booking->id }}";
            const route = '{{ url('admin/booking/serviceman-update') }}' + '/' + bookingId;
            const searchTerm = $('.search-form-input1').val();

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.ajax({
                url: route,
                type: 'PUT',
                dataType: 'json',
                data: {
                    booking_id: bookingId,
                    search: searchTerm,
                    serviceman_id: servicemanId
                },
                beforeSend: function() {
                    toastr.info('{{ translate('Processing request...') }}');
                },
                success: function(response) {
                    $('.modal-content-data').html(response.view);
                    toastr.success('{{ translate('Successfully reassign provider') }}');
                    setTimeout(function() {
                        location.reload()
                    }, 600);
                },
                complete: function() {},
                error: function() {
                    toastr.error('{{ translate('Failed to load') }}');
                }
            });
        }

        $(document).ready(function() {
            $('.your-button-selector').on('click', function() {
                updateSearchResults();
            });

            $('.cancellation-note').hide();

            $('.deny-request').click(function() {
                $('.cancellation-note').show();
            });

            $('.approve-request').click(function() {
                $('.cancellation-note').hide();
            });
        });

        $('.customer-chat').on('click', function() {
            $(this).find('form').submit();
        });

        $('.provider-chat').on('click', function() {
            $(this).find('form').submit();
        });

        document.addEventListener('DOMContentLoaded', function() {
            const denyRequestRadio = document.querySelector('.deny-request');
            const cancellationNote = document.querySelector('.cancellation-note');

            denyRequestRadio.addEventListener('change', function() {
                if (this.checked) {
                    cancellationNote.style.display = 'block';
                    document.querySelector('textarea[name="booking_deny_note"]').required = true;
                } else {
                    cancellationNote.style.display = 'none';
                    document.querySelector('textarea[name="booking_deny_note"]').required = false;
                }
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            $('.without-search').select2({
                minimumResultsForSearch: Infinity
            });
        });
    </script>
@endpush
