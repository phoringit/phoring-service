@extends('providermanagement::layouts.master')

@section('title',translate('Review'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="page-title-wrap mb-3">
                <h2 class="page-title">{{translate('Account_Information')}}</h2>
            </div>

            <div class="mb-3">
                <ul class="nav nav--tabs nav--tabs__style2">
                    <li class="nav-item">
                        <a class="nav-link {{$pageType=='overview'?'active':''}}"
                           href="{{url()->current()}}?page_type=overview">{{translate('Overview')}}</a>
                    </li>
                    @if(!$packageSubscriber)
                        <li class="nav-item">
                            <a class="nav-link {{$pageType=='commission-info'?'active':''}}"
                               href="{{url()->current()}}?page_type=commission-info">{{translate('Commission_Info')}}</a>
                        </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link {{$pageType=='review'?'active':''}}"
                           href="{{url()->current()}}?page_type=review">{{translate('Reviews')}}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{$pageType=='promotional_cost'?'active':''}}"
                           href="{{url()->current()}}?page_type=promotional_cost">{{translate('Promotional_Cost')}}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{$pageType=='withdraw_transaction'?'active':''}}"
                           href="{{route('provider.withdraw.list', ['page_type'=>'withdraw_transaction'])}}">{{translate('withdraw_list')}}</a>
                    </li>
                </ul>
            </div>

            <div class="card mb-30">
                <div class="card-body p-30">
                    <div class="row gx-5">
                        <div class="col-lg-5 mb-30 mb-lg-0 d-flex justify-content-center border-lg-end">
                            <div class="rating-review">
                                <h2 class="rating-review__title">
                                    <span class="rating-review__out-of">{{$provider->avg_rating}}</span>/5
                                </h2>
                                <div class="rating">
                                    <span
                                        class="{{$provider->avg_rating>=1?'material-icons':'material-symbols-outlined'}}">{{$provider->avg_rating>=1?'star':'grade'}}</span>
                                    <span
                                        class="{{$provider->avg_rating>=2?'material-icons':'material-symbols-outlined'}}">{{$provider->avg_rating>=2?'star':'grade'}}</span>
                                    <span
                                        class="{{$provider->avg_rating>=3?'material-icons':'material-symbols-outlined'}}">{{$provider->avg_rating>=3?'star':'grade'}}</span>
                                    <span
                                        class="{{$provider->avg_rating>=4?'material-icons':'material-symbols-outlined'}}">{{$provider->avg_rating>=4?'star':'grade'}}</span>
                                    <span
                                        class="{{$provider->avg_rating>=5?'material-icons':'material-symbols-outlined'}}">{{$provider->avg_rating>=5?'star':'grade'}}</span>
                                </div>
                                <div class="rating-review__info d-flex flex-wrap gap-3 mt-2">
                                    @php($total_review_count = $provider->reviews->where('is_active', 1)->whereNotNull('review_rating')->whereNotNull('review_comment')->count())
                                    @php($totalReviews = $provider->reviews->where('is_active', 1)->whereNotNull('review_rating')->count())
                                        <span>{{$totalReviews}} {{translate('ratings')}}</span>
                                        <span>{{$total_review_count}} {{translate('reviews')}}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <ul class="common-list common-list__style2 after-none gap-10">
                                <li>
                                    <span class="review-name">{{translate('excellent')}}</span>
                                    @php($excellent_count=$provider->reviews->where('is_active', 1)->where('review_rating',5)->count())
                                    @php($excellent=(divnum($excellent_count,$total_review_count))*100)
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"
                                                style="width: {{$excellent}}%"
                                                aria-valuenow="{{$excellent}}" aria-valuemin="0"
                                                aria-valuemax="100">
                                        </div>
                                    </div>
                                    <span class="review-count">{{$excellent_count}}</span>
                                </li>
                                <li>
                                    <span class="review-name">{{translate('good')}}</span>
                                    @php($good_count=$provider->reviews->where('is_active', 1)->where('review_rating',4)->count())
                                    @php($good=(divnum($good_count,$total_review_count))*100)
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: {{$good}}%"
                                                aria-valuenow="{{$good}}" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <span class="review-count">{{$good_count}}</span>
                                </li>
                                <li>
                                    <span class="review-name">{{translate('avarage')}}</span>
                                    @php($average_count=$provider->reviews->where('is_active', 1)->where('review_rating',3)->count())
                                    @php($average=(divnum($average_count,$total_review_count))*100)
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"
                                                style="width: {{$average}}%"
                                                aria-valuenow="{{$average}}" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <span class="review-count">{{$average_count}}</span>
                                </li>
                                <li>
                                    <span class="review-name">{{translate('below_avarage')}}</span>
                                    @php($below_average_count=$provider->reviews->where('is_active', 1)->where('review_rating',2)->count())
                                    @php($below_average=(divnum($below_average_count,$total_review_count))*100)
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar"
                                                style="width: {{$below_average}}%"
                                                aria-valuenow="{{$below_average}}" aria-valuemin="0"
                                                aria-valuemax="100">
                                        </div>
                                    </div>
                                    <span class="review-count">{{$below_average_count}}</span>
                                </li>
                                <li>
                                    <span class="review-name">{{translate('poor')}}</span>
                                    @php($poor_count=$provider->reviews->where('is_active', 1)->where('review_rating',1)->count())
                                    @php($poor=(divnum($poor_count,$total_review_count))*100)
                                    <div class="progress">
                                        <div class="progress-bar" role="progressbar" style="width: {{$poor}}%"
                                                aria-valuenow="{{$poor}}" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                    <span class="review-count">{{$poor_count}}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-title-wrap d-flex justify-content-between flex-wrap align-items-center">
                <h4 class="page-title">{{translate('My Reviews')}}</h4>
                <div>
                    <div class="d-flex justify-content-end border-bottom pb-2 mb-10">
                        <div class="d-flex gap-2 fw-medium">
                            <span class="opacity-75">{{translate('total_reviews')}}:</span>
                            <span class="title-color">{{$reviews->total()}}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="data-table-top d-flex flex-wrap gap-10 justify-content-between">
                        <form action="{{url()->current()}}?page_type={{$pageType}}"
                                class="search-form search-form_style-two"
                                method="POST">
                            @csrf
                            <div class="input-group search-form__input_group">
                                    <span class="search-form__icon">
                                        <span class="material-icons">search</span>
                                    </span>
                                <input type="search" class="theme-input-style search-form__input"
                                        value="{{$search??''}}" name="search"
                                        placeholder="{{translate('search_here')}}">
                            </div>
                            <button type="submit"
                                    class="btn btn--primary">{{translate('search')}}</button>
                        </form>

                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="dropdown">
                                <button type="button"
                                        class="btn btn--secondary text-capitalize dropdown-toggle"
                                        data-bs-toggle="dropdown">
                                    <span class="material-icons">file_download</span> {{translate('download')}}
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                    <li><a class="dropdown-item"
                                            href="{{route('provider.reviews.download',['search'=>$search??''])}}">{{translate('excel')}}</a>
                                    </li>
                                </ul>
                            </div>

                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="example" class="table align-middle">
                            <thead>
                            <tr>
                                <th>{{translate('SL')}}</th>
                                <th>{{translate('Review ID')}}</th>
                                <th>{{translate('reviewer')}}</th>
                                <th>{{translate('date')}}</th>
                                <th>{{translate('ratings')}}</th>
                                <th>{{translate('reviews')}}</th>
                                <th>{{translate('reply')}}</th>
                                <th class="text-center">{{translate('action')}}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($reviews as $bookingId => $review)
                                @if($review->reviews->count() > 1)
                                    @php($getReviewInfo = $review->reviews->first())
                                    <tr class="clickable-row" data-toggle="collapse"
                                        data-target="#group-{{$bookingId}}" aria-expanded="false">
                                        <td>{{$bookingId+$reviews?->firstItem()}}</td>
                                        <td>
                                            {{ Str::limit($review->reviews->pluck('readable_id')->implode(', '), 18) }}
                                        </td>
                                        <td>
                                            @if(isset($getReviewInfo->customer))
                                                <span>{{$getReviewInfo->customer->first_name . ' ' .$getReviewInfo->customer->last_name}}</span>
                                                <br>
                                                <span>{{ translate('Booking ID #') . $review->readable_id ?? 'N/A' }}</span>
                                            @else
                                                <span
                                                    class="opacity-50">{{translate('Customer_not_available')}}</span>
                                            @endif
                                        </td>
                                        <td>{{$getReviewInfo->created_at}}</td>
                                        <td>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="15"
                                                 viewBox="0 0 14 15" fill="none">
                                                <path
                                                    d="M7 1.81445L8.854 5.76398L13 6.4012L10 9.47376L10.708 13.8145L7 11.764L3.292 13.8145L4 9.47376L1 6.4012L5.146 5.76398L7 1.81445Z"
                                                    fill="#FFB900" stroke="#FFB900" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span>{{ number_format($review->reviews->pluck('review_rating')->avg(),1) }}</span>

                                        </td>
                                        <td><a href="">{{translate('see_all')}}</a></td>
                                        <td><a href="">{{translate('see_all')}}</a></td>
                                        <td><a href="">{{translate('see_all')}}</a></td>
                                    </tr>
                                    <tr id="group-{{$bookingId}}" class="collapse">
                                        <td colspan="9">
                                            <table class="table align-middle">
                                                @foreach($review->reviews->where('is_active', 1) as $key => $providerReview)
                                                    <tr>
                                                        <td></td>
                                                        <td>{{ $providerReview->readable_id == 0 ? 'N/A' : $providerReview->readable_id }}</td>
                                                        <td width="21%" class="test-center">
                                                            @if(isset($providerReview->service))
                                                                <img class="img-fluid"
                                                                     src="{{$providerReview->service->cover_image_full_path}}"
                                                                     alt="" width="25%" height="25%">
                                                                <span>{{ Str::limit($providerReview->service->name, 15) }}</span>
                                                            @endif
                                                        </td>
                                                        <td>{{$providerReview->created_at}}</td>
                                                        <td>
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="14"
                                                                 height="15" viewBox="0 0 14 15" fill="none">
                                                                <path
                                                                    d="M7 1.81445L8.854 5.76398L13 6.4012L10 9.47376L10.708 13.8145L7 11.764L3.292 13.8145L4 9.47376L1 6.4012L5.146 5.76398L7 1.81445Z"
                                                                    fill="#FFB900" stroke="#FFB900"
                                                                    stroke-width="2" stroke-linecap="round"
                                                                    stroke-linejoin="round"/>
                                                            </svg>
                                                            {{$providerReview->review_rating}}
                                                        </td>
                                                        <td data-bs-custom-class="review-tooltip" data-bs-toggle="tooltip" title="{{$providerReview->review_comment}}">{{ Str::limit($providerReview->review_comment, 100) ?? translate('No review yet') }}</td>
                                                        <td data-bs-custom-class="review-tooltip" data-bs-toggle="tooltip" title="{{$providerReview->reviewReply?->reply}}">{{ Str::limit($providerReview->reviewReply?->reply, 100) ?? translate('No reply yet') }}</td>
                                                        <td>
                                                            @if(!empty($providerReview->review_comment))
                                                                <div
                                                                    class="d-flex gap-2 justify-content-center">
                                                                    <button class="action-btn btn--light-primary fw-medium text-capitalize fz-14" data-bs-toggle="modal" id="replyModalBtn"
                                                                            data-bs-target="#replyModal"
                                                                            data-booking_id ="{{$providerReview->booking->readable_id}}"
                                                                            data-readable_id ="{{$providerReview->readable_id}}"
                                                                            data-review_id ="{{$providerReview->id}}"
                                                                            data-service_name="{{$providerReview->service->name}}"
                                                                            data-service_img="{{$providerReview->service->cover_image_full_path}}"
                                                                            data-review="{{$providerReview->review_comment ?? translate('No review yet')}}"
                                                                            data-review_reply="{{$providerReview->reviewReply?->reply ?? translate('No reply yet')}}"
                                                                            data-variant_key="{{ $providerReview->service?->bookings[0]?->variant_key }}"
                                                                            data-action="{{ route('provider.service.review.reply') }}"
                                                                    >
                                                                            <span
                                                                                class="material-icons">visibility</span>
                                                                    </button>
                                                                </div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </table>
                                        </td>
                                    </tr>
                                @else
                                    @php($getReview = $review->reviews->first())
                                    <tr>
                                        <td>{{$bookingId+$reviews?->firstItem()}}</td>
                                        <td>{{ $getReview->readable_id == 0 ? 'N/A' : $getReview->readable_id }}</td>
                                        <td>
                                            @if(isset($review->customer))
                                                <span>{{$review->customer->first_name . ' ' .$review->customer->last_name}}</span>
                                                <br>
                                                <span>{{ translate('Booking ID #') . $review->readable_id ?? 'N/A' }}</span>
                                            @else
                                                <span
                                                    class="opacity-50">{{translate('Customer_not_available')}}</span>
                                            @endif
                                        </td>
                                        <td>{{$getReview->created_at}}</td>
                                        <td>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="15"
                                                 viewBox="0 0 14 15" fill="none">
                                                <path
                                                    d="M7 1.81445L8.854 5.76398L13 6.4012L10 9.47376L10.708 13.8145L7 11.764L3.292 13.8145L4 9.47376L1 6.4012L5.146 5.76398L7 1.81445Z"
                                                    fill="#FFB900" stroke="#FFB900" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            {{$getReview->review_rating}}
                                        </td>
                                        <td data-bs-toggle="tooltip"
                                            title="{{$getReview->review_comment}}">{{ Str::limit($getReview->review_comment, 100) ?? translate('No review yet') }}</td>
                                        <td data-bs-toggle="tooltip"
                                            title="{{$getReview->reviewReply?->reply}}">{{ Str::limit($getReview->reviewReply?->reply, 100) ?? translate('No reply yet') }}</td>
                                        <td>
                                            @if(!empty($getReview->review_comment))
                                                <div class="d-flex gap-2 justify-content-center">
                                                    <button
                                                        class="action-btn btn--light-primary fw-medium text-capitalize fz-14"
                                                        data-bs-toggle="modal" id="replyModalBtn"
                                                        data-bs-target="#replyModal"
                                                        data-booking_id="{{$getReview?->booking?->readable_id}}"
                                                        data-readable_id="{{$getReview->readable_id}}"
                                                        data-review_id ="{{$getReview->id}}"
                                                        data-service_name="{{$getReview->service->name}}"
                                                        data-service_img="{{$getReview->service->cover_image_full_path}}"
                                                        data-review="{{$getReview->review_comment ?? translate('No review yet')}}"
                                                        data-review_reply="{{$getReview->reviewReply?->reply ?? translate('No reply yet')}}"
                                                        data-variant_key="{{ $getReview->booking?->detail[0]?->variant_key }}"
                                                        data-action="{{ route('provider.service.review.reply') }}"
                                                    >
                                                        <span class="material-icons">visibility</span>
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td class="text-center" colspan="8">
                                        {{ translate('You don’t have any reviews yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                    {!! $reviews->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>


    <?php
    $reviewPermission = business_config('provider_can_reply_review', 'provider_config')->live_values;
    ?>
    <div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-0">
                    <div class="p-3 pt-0">
                        <div class="d-flex gap-3">
                            <img src="" class="rounded aspect-square object-fit-cover" width="80" alt="Service Image">
                            <div class="w-0 flex-grow-1">
                                <div class="mb-2">
                                    <span>{{translate('Booking ID #')}}</span> <label class="booking_id"></label>
                                </div>
                                <h5 class="service_name"></h5>
                                <div class="mt-2">
                                    <span class="variant_key"></span>
                                </div>
                            </div>
                        </div>
                        <div class="review_section mb-3 mt-3">
                            <h4 class="mb-2">{{translate('Review')}}</h4>
                            <div class="p-3 rounded bg--secondary">
                                <p class="review_content"></p>
                            </div>
                        </div>
                        <form action="" method="post">
                            @csrf
                            <div class="reply_section">
                                <div>
                                    <h4 class="mb-3">{{translate('Reply')}}</h4>
                                    <div class="form-group">
                                        <textarea id="reply_content" class="form-control" name="reply_content" rows="4" {{$reviewPermission ? '' : 'readonly disabled'}}></textarea>
                                        <input type="hidden" class="form-control" name="review_id" value="">
                                    </div>
                                </div>
                            </div>
                            @if($reviewPermission)
                                <div class="text-end mt-3">
                                    <button class="btn btn--primary" type="submit">{{ translate('submit') }}</button>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        "use strict"

        document.addEventListener('DOMContentLoaded', function () {
            var clickableRows = document.querySelectorAll('.clickable-row');
            clickableRows.forEach(function (row) {
                row.addEventListener('click', function () {
                    var target = row.getAttribute('data-target');
                    var collapseElement = document.querySelector(target);
                    collapseElement.classList.toggle('show');
                });
            });
        });

        $('#replyModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const modal = $(this);
            const serviceImg = button.data('service_img');
            const serviceName = button.data('service_name');
            const bookingID = button.data('booking_id');
            const readableID = button.data('readable_id');
            const reviewID = button.data('review_id');
            const review = button.data('review');
            const variantKey = button.data('variant_key');
            const reviewReply = button.data('review_reply');
            const action = button.data('action');
            console.log(serviceImg)

            modal.find('.service_name').text(serviceName);
            modal.find('.booking_id').text(bookingID);
            modal.find('.review_content').text(review);
            modal.find('.variant_key').text(variantKey);
            modal.find('img').attr('src', serviceImg);

            modal.find('textarea[name=reply_content]').val(reviewReply);
            modal.find('input[name=review_id]').val(reviewID);
            modal.find('form').attr('action',action);
        });
    </script>
@endpush
