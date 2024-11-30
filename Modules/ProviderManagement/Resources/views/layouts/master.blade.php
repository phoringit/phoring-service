<!DOCTYPE html>
@php
    $site_direction = session()->get('provider_site_direction');
@endphp
<html lang="en" dir="{{$site_direction}}">

<head>
    <title>@yield('title')</title>

    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="description" content=""/>
    <meta name="keywords" content=""/>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php($favIcon = getBusinessSettingsImageFullPath(key: 'business_favicon', settingType: 'business_information', path: 'business/',  defaultPath : 'public/assets/admin-module/img/media/upload-file.png'))
    <link rel="shortcut icon" href="{{ $favIcon }}"/>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
            href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap"
            rel="stylesheet">

    <link href="{{asset('public/assets/provider-module')}}/css/material-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{asset('public/assets/provider-module')}}/css/bootstrap.min.css"/>
    <link rel="stylesheet"
          href="{{asset('public/assets/provider-module')}}/plugins/perfect-scrollbar/perfect-scrollbar.min.css"/>

    <link rel="stylesheet" href="{{asset('public/assets/provider-module')}}/plugins/apex/apexcharts.css"/>
    <link rel="stylesheet" href="{{asset('public/assets/provider-module')}}/plugins/select2/select2.min.css"/>

    <link rel="stylesheet" href="{{asset('public/assets/provider-module')}}/css/toastr.css">

    <link rel="stylesheet" href="{{asset('public/assets/admin-module/plugins/intl-tel-input/css/intlTelInput.min.css')}}"/>
    <link rel="stylesheet" href="{{asset('public/assets/provider-module')}}/css/style.css"/>
    <link rel="stylesheet" href="{{asset('public/assets/provider-module')}}/css/dev.css"/>
    @stack('css_or_js')
    <style>
        @keyframes progress-animation {
            from {
                --progress: 0;
            }
            to {
                --progress: 70;
            }
        }
    </style>
</head>

<body>
<script>
    localStorage.theme && document.querySelector('body').setAttribute("theme", localStorage.theme);
</script>

<div class="offcanvas-overlay"></div>

<div class="preloader"></div>

@include('providermanagement::layouts.partials._header')

@include('providermanagement::layouts.partials._aside')

@include('providermanagement::layouts.partials._settings-sidebar')

<main class="main-area">
    @yield('content')

    @include('providermanagement::layouts.partials._footer')

    @if(env('APP_ENV') == 'demo')
        <div class="alert alert--message-2 alert-dismissible fade show" id="demo-reset-warning">
            <img width="28" class="align-self-start" src="{{ asset('public/assets/admin-module/img/info-2.png') }}" alt="">
            <div class="w-0 flex-grow-1">
                <h6>{{ translate('warning').'!'}}</h6>
                <span class="warning-message">
            {{translate('though_it_is_a_demo_site').'.'.translate('_our_system_automatically_reset_after_one_hour_&_that_is_why_you_logged_out').'.'}}
        </span>
            </div>
            <button type="button" class="btn-close p-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @include('providermanagement::layouts.partials.subscription-modal')
    {{-- Service Request Modal --}}
    <div class="modal fade" id="serviceRequestModal" tabindex="-1"
         aria-labelledby="serviceRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog" style="--bs-modal-width: 430px">
            <div class="modal-content">
            </div>
        </div>
    </div>
</main>


<script src="{{asset('public/assets/provider-module')}}/js/jquery-3.6.0.min.js"></script>
<script src="{{asset('public/assets/provider-module')}}/js/bootstrap.bundle.min.js"></script>
<script src="{{asset('public/assets/provider-module')}}/plugins/perfect-scrollbar/perfect-scrollbar.min.js"></script>
<script src="{{asset('public/assets/provider-module')}}/js/main.js"></script>


<script src="{{asset('public/assets/provider-module')}}/plugins/select2/select2.min.js"></script>

<script src="{{asset('public/assets/provider-module')}}/js/sweet_alert.js"></script>
<script src="{{asset('public/assets/provider-module')}}/js/toastr.js"></script>
<script src="{{asset('public/assets/provider-module')}}/js/dev.js"></script>
<script src="{{ asset('public/assets/admin-module/plugins/intl-tel-input/js/intlTelInput.js') }}"></script>
<script src="{{ asset('public/assets/admin-module/js/country-picker-init.js') }}"></script>
<script src="{{asset('public/assets/admin-module/js/firebase.min.js')}}"></script>

<audio id="audio-element">
    <source src="{{asset('public/assets/provider-module')}}/sound/notification.mp3" type="audio/mpeg">
</audio>

<script>
    "use strict";

    $(document).ready(function () {
        $('.js-select').select2();
    });

    function checkDemoResetTime() {
        let currentMinute = new Date().getMinutes();
        if (currentMinute > 55 && currentMinute <= 60) {
            $('#demo-reset-warning').addClass('active');
        } else {
            $('#demo-reset-warning').removeClass('active');
        }
    }
    checkDemoResetTime();
    setInterval(checkDemoResetTime, 60000);

    $("#search-form__input").on("keyup", function () {
        var value = this.value.toLowerCase().trim();
        $(".show-search-result a").show().filter(function () {
            return $(this).text().toLowerCase().trim().indexOf(value) == -1;
        }).hide();
    });

    function checkBooking(count) {
        // console.log(count)
        sessionStorage.setItem("booking_count", parseInt(count));
    }

    function update_notification() {
        let count = $('#notification_count').text();
        let notification_count = sessionStorage.getItem("notification_count");

        if (parseInt(count) > 0) {
            sessionStorage.setItem("notification_count", parseInt(notification_count) + parseInt(count));
        }
    }


    @if(!request()->is('provider/profile-update') && is_null(auth()->user()->provider->coordinates))
    Swal.fire({
        title: "{{translate('Update Location')}}",
        text: "{{translate('You must update your location first')}}",
        type: 'warning',
        showCloseButton: false,
        showCancelButton: false,
        confirmButtonColor: 'var(--c1)',
        confirmButtonText: "{{translate('Update from profile')}}",
        reverseButtons: true
    }).then((result) => {
        if (result.value) {
            location.href = "{{route('provider.profile_update')}}";
        }
    })
    @endif

    $('.form-alert').on('click', function () {
        let id = $(this).data('id');
        let message = $(this).data('message');
        let title = $(this).data('title');
        form_alert(id, message, title)
    });

    function form_alert(id, message, title = 'are_you_sure?') {
        Swal.fire({
            title: title,
            text: message,
            type: 'warning',
            showCloseButton: true,
            showCancelButton: true,
            cancelButtonColor: 'var(--c2)',
            confirmButtonColor: 'var(--c1)',
            cancelButtonText: 'No',
            confirmButtonText: 'Yes',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $('#' + id).submit()
            }
        })
    }

    $('.route-alert').on('click', function () {
        let route = $(this).data('route');
        let message = $(this).data('message');
        route_alert(route, message)
    });

    function route_alert(route, message) {
        Swal.fire({
            title: "{{translate('are_you_sure')}}?",
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'var(--c2)',
            confirmButtonColor: 'var(--c1)',
            cancelButtonText: '{{translate('Cancel')}}',
            confirmButtonText: '{{translate('Yes')}}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $.get({
                    url: route,
                    dataType: 'json',
                    data: {},
                    beforeSend: function () {
                    },
                    success: function (data) {
                        console.log(data)
                        toastr.success(data.message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    },
                    complete: function () {
                    },
                });
            }
        })
    }


    $('.update-notification').on('click', function () {
        update_notification()
    });

    $('.provider-logout').on('click', function (event) {
        Swal.fire({
            title: "{{translate('are_you_sure')}}?",
            text: "{{translate('want_to_logout')}}",
            type: 'warning',
            showCloseButton: true,
            showCancelButton: true,
            cancelButtonColor: 'var(--c2)',
            confirmButtonColor: 'var(--c1)',
            cancelButtonText: 'Cancel',
            confirmButtonText: 'Yes',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                location.href = "{{route('provider.auth.logout')}}"
            }
        })
    });


    $(document).ready(function () {
        $('#searchForm input[name="search"]').keyup(function () {
            var searchKeyword = $(this).val().trim();

            if (searchKeyword.length >= 2) {
                $.ajax({
                    type: 'POST',
                    url: $('#searchForm').attr('action'),
                    data: {search: searchKeyword, _token: $('input[name="_token"]').val()},
                    success: function (response) {
                        if (response.length === 0) {
                            $('#searchResults').html('<div class="text-center text-muted py-5">{{translate('No results found')}}</div>');
                        } else {
                            var resultHtml = '';
                            response.forEach(function (route) {
                                resultHtml += '<a href="' + route.fullRoute + '" class="search-list-item d-flex flex-column" aria-current="true">';
                                resultHtml += '<h5>' + route.routeName + '</h5>';
                                resultHtml += '<p class="text-muted fs-12">' + route.URI + '</p>';
                                resultHtml += '</a>';
                            });
                            $('#searchResults').html('<div class="search-list d-flex flex-column">' + resultHtml + '</div>');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                    }
                });
            } else {
                $('#searchResults').html('<div class="text-center text-muted py-5">{{translate('Write a minimum of two characters.')}}.</div>');
            }
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.ctrlKey && event.key === 'k') {
            event.preventDefault();
            document.getElementById('modalOpener').click();
        }
    });

    // Search Modal Open Input Focus
    $(document).ready(function () {
        $("#staticBackdrop").on("shown.bs.modal", function () {
            $(this).find("#searchForm input[type=search]").val('');
            $('#searchResults').html('<div class="text-center text-muted py-5">It appears that you have not yet searched.</div>');
            $(this).find("#searchForm input[type=search]").focus();
        });
    });

    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('search', function() {
        if (!this.value.trim()) {
            $('#searchResults').html('<div class="text-center text-muted py-5">It appears that you have not yet searched.</div>');
        }
    });

    $('#searchForm').submit(function (event) {
        event.preventDefault();
    });


    $(document).ready(function(){
        $('.renew-package').on('click', function() {
            var packageId = $(this).data('id');

            $.ajax({
                url: '{{ route("provider.subscription-package.renew.ajax") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    id: packageId
                },
                success: function(response) {
                    $('.append-renew').html(response);
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        });
    });

    $(document).ready(function(){
        $('.shift-package').on('click', function() {
            var packageId = $(this).data('id');

            $.ajax({
                url: '{{ route("provider.subscription-package.shift.ajax") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    id: packageId
                },
                success: function(response) {
                    $('.append-shift').html(response);
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        });
    });

    $(document).ready(function(){
        $('.purchase-package').on('click', function() {
            var packageId = $(this).data('id');

            $.ajax({
                url: '{{ route("provider.subscription-package.purchase.ajax") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    id: packageId
                },
                success: function(response) {
                    $('.append-purchase').html(response);
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        const trialNotificationCloseButtons = document.querySelectorAll('.trial-notification-close');

        trialNotificationCloseButtons.forEach(button => {
            button.addEventListener('click', function () {
                fetch('{{ route('provider.set.modal.closed') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({modalClosed: true})
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Modal closed:', data);
                    });
            });
        });
    });

    $(document).ready(function () {
        @if(session('showSubscriptionModal'))
        $('#subscriptionPlanModal').modal('show');
        @endif
        @if(session('paySubscriptionModal'))
        $('#paySubscriptionModal').modal('show');
        @endif
    });
</script>

<script>
    @php($admin_order_notification = (int) business_config('booking_notification', 'business_information')->live_values)
    @php($admin_order_notification_type = business_config('booking_notification_type', 'business_information')->live_values)
    @if($admin_order_notification)

        var audio = document.getElementById("audio-element");

        function playAudio(status) {
            status ? audio.play() : audio.pause();
        }

        @if($admin_order_notification_type == 'manual')

            @if(!auth()->user()?->provider?->is_suspended || !business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values)
                setInterval(function () {
                    $.get({
                        url: '{{ route('provider.get_updated_data') }}',
                        dataType: 'json',
                        success: function (response) {
                            let data = response.data;

                            let notification_count = sessionStorage.getItem("notification_count");
                            if (notification_count == null || isNaN(notification_count)) {
                                notification_count = 0;
                                sessionStorage.setItem("notification_count", notification_count);
                            }

                            let booking_count = sessionStorage.getItem("booking_count");
                            if (booking_count == null || isNaN(parseInt(booking_count)) || data.booking === 0) {
                                booking_count = 0;
                                sessionStorage.setItem("booking_count", parseInt(booking_count));
                            }

                            let count = data.notification_count;

                            document.getElementById("message_count").innerHTML = data.message;
                            document.getElementById("notification_count").innerHTML = parseInt(count) < 0 ? 0 : parseInt(count);
                            document.getElementById("show-notification-list").innerHTML = data.notification_template;
                            var availableStatus = "{{auth()->user()?->provider?->service_availability}}";

                            if (data.booking !== parseInt(booking_count) && data.booking > 0 && availableStatus == 1) {
                                playAudio(true);
                                Swal.fire({
                                    title: '{{translate('New_Notification')}}',
                                    text: "{{translate('You_have_new_booking_arrived')}}",
                                    icon: 'info',
                                    showCloseButton: true,
                                    showCancelButton: false,
                                    focusConfirm: false,
                                    confirmButtonText: '{{translate('Show_Bookings')}}',
                                }).then((result) => {
                                    if (result.value) {
                                        playAudio(false);
                                        checkBooking();
                                        location.href = "{{ route('provider.booking.list') }}?booking_status=pending&service_type=all";
                                    } else if (result.dismiss === 'close') {
                                        playAudio(false);
                                        checkBooking();
                                        location.href = "{{ route('provider.booking.list') }}?booking_status=pending&service_type=all";
                                    }
                                })
                            }

                            if (data.unchecked_posts > 0 && availableStatus == 1) {
                                playAudio(true);
                                if (data.post_content !== null) {
                                    $('#serviceRequestModal .modal-content').html(data.post_content);
                                }
                                $('#serviceRequestModal').modal('show');
                            }
                        },
                    });
                }, 10000);
             @endif

        @endif

        @if($admin_order_notification_type == 'firebase')
            @php($fcm_credentials = business_config('firebase_message_config', 'third_party')->live_values ?? [])
                @if(!empty($fcm_credentials))
                    var firebaseConfig = {
                        apiKey: "{{isset($fcm_credentials['apiKey']) ? $fcm_credentials['apiKey'] : ''}}",
                        authDomain: "{{isset($fcm_credentials['authDomain']) ? $fcm_credentials['authDomain'] : ''}}",
                        projectId: "{{isset($fcm_credentials['projectId']) ? $fcm_credentials['projectId'] : ''}}",
                        storageBucket: "{{isset($fcm_credentials['storageBucket']) ? $fcm_credentials['storageBucket'] : ''}}",
                        messagingSenderId: "{{isset($fcm_credentials['messagingSenderId']) ? $fcm_credentials['messagingSenderId'] : ''}}",
                        appId: "{{isset($fcm_credentials['appId']) ? $fcm_credentials['appId'] : ''}}",
                        measurementId: "{{isset($fcm_credentials['measurementId']) ? $fcm_credentials['measurementId'] : ''}}"
                    };
                    firebase.initializeApp(firebaseConfig);
                    const messaging = firebase.messaging();
                    function startFCM() {
                        messaging
                            .requestPermission()
                            .then(function() {
                                return messaging.getToken();
                            })
                            .then(function(token) {
                                subscribeTokenToBackend(token, 'demandium_provider_{{auth()->user()->provider->zone_id}}_booking_message');
                            }).catch(function(error) {
                            console.error('Error getting permission or token:', error);
                        });
                    }
                    function subscribeTokenToBackend(token, topic) {
                        fetch('{{url('provider/subscribeToTopic')}}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ token: token, topic: topic })
                        }).then(response => {
                            if (response.status < 200 || response.status >= 400) {
                                return response.text().then(text => {
                                    throw new Error(`Error subscribing to topic: ${response.status} - ${text}`);
                                });
                            }
                            console.log(`Subscribed to "${topic}"`);
                        }).catch(error => {
                            console.error('Subscription error:', error);
                        });
                    }
                    messaging.onMessage(function(payload) {
                        console.log(payload.data);
                        playAudio();
                        Swal.fire({
                            title: '{{translate('New_Notification')}}',
                            text: "{{translate('You_have_new_booking_arrived')}}",
                            icon: 'info',
                            showCloseButton: true,
                            showCancelButton: false,
                            focusConfirm: false,
                            confirmButtonText: '{{translate('Show_Bookings')}}',
                        }).then((result) => {
                            if (result.value) {
                                playAudio(false);
                                checkBooking();
                                location.href = "{{ route('provider.booking.list') }}?booking_status=pending&service_type=all";
                            } else if (result.dismiss === 'close') {
                                playAudio(false);
                                checkBooking();
                                location.href = "{{ route('provider.booking.list') }}?booking_status=pending&service_type=all";
                            }
                        })

                    });
                    startFCM();
                @else
                    console.error('Firebase credentials are not available. Notifications will not work.');
                @endif
        @endif
    @endif
</script>

{!! Toastr::message() !!}

@if (session('warning'))
    <script>
        toastr.warning('{{ session('warning') }}');
    </script>
@endif

@if ($errors->any())
    <script>
        @foreach($errors->all() as $error)
        toastr.error('{{$error}}', Error, {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif


@stack('script')

</body>

</html>
