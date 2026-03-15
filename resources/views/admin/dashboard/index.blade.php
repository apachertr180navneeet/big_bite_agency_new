@extends('admin.layouts.app')
@section('style')
@endsection  

@section('content')

<!-- Content -->

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-lg-8 mb-4 order-0">
            <div class="card">
                <div class="d-flex align-items-end row">
                    <div class="col-sm-7">
                        <div class="card-body">
                            <h5 class="card-title text-primary">
                                Congratulations {{ Auth::user()->full_name }}! 🎉
                            </h5>
                            <p class="mb-4">
                                Welcome to Admin Panel
                            </p>

                            <a href="javascript:;" class="btn btn-sm btn-outline-primary">View
                                Badges</a>
                        </div>
                    </div>
                    <div class="col-sm-5 text-center text-sm-left">
                        <div class="card-body pb-0 px-0 px-md-4">
                            <img src="{{ asset('assets/admin/img/illustrations/man-with-laptop-light.png') }}"
                                height="140" alt="View Badge User"
                                data-app-dark-img="illustrations/man-with-laptop-dark.png"
                                data-app-light-img="illustrations/man-with-laptop-light.png" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-4 order-1">
            <div class="row">
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div
                                class="card-title d-flex align-items-start justify-content-between">
                                <div class="avatar flex-shrink-0">
                                    <img src="{{ asset('assets/admin/img/icons/unicons/chart-success.png') }}"
                                        alt="chart success" class="rounded" />
                                </div>
                            </div>
                            <span class="fw-medium d-block mb-1">Total Bill Count</span>
                            <h3 class="card-title mb-2">{{ $invoiceCount }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 col-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div
                                class="card-title d-flex align-items-start justify-content-between">
                                <div class="avatar flex-shrink-0">
                                    <img src="{{ asset('assets/admin/img/icons/unicons/wallet-info.png') }}"  alt="Credit Card" class="rounded" />
                                </div>
                            </div>
                            <span>Total Bill Amount</span>
                            <h3 class="card-title text-nowrap mb-1">{{ $totalBillAmount }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Revenue -->
        <div class="col-12 col-lg-8 order-2 order-md-3 order-lg-2 mb-4">
            <div class="card">
                <div class="row row-bordered g-0">
                    <div class="col-md-12">
                        <h5 class="card-header m-0 me-2 pb-3">Old Pending invoice</h5>
                    </div>
                </div>
            </div>
        </div>
        <!--/ Total Revenue -->
        <div class="col-12 col-md-8 col-lg-4 order-3 order-md-2">
            <div class="row">
                <div class="col-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div
                                class="card-title d-flex align-items-start justify-content-between">
                                <div class="avatar flex-shrink-0">
                                    <img src="{{ asset('assets/admin/img/icons/unicons/paypal.png') }}"  alt="Credit Card" class="rounded" />
                                </div>
                            </div>
                            <span class="d-block mb-1">Salesman</span>
                            <h3 class="card-title text-nowrap mb-2">{{ $activeSalesperson }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-6 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div
                                class="card-title d-flex align-items-start justify-content-between">
                                <div class="avatar flex-shrink-0">
                                    <img src="{{ asset('assets/admin/img/icons/unicons/cc-primary.png') }}"  alt="Credit Card" class="rounded" />
                                </div>
                            </div>
                            <span class="fw-medium d-block mb-1">Customer</span>
                            <h3 class="card-title mb-2">{{ $activeSalesperson }}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <div
                                class="card-title d-flex align-items-start justify-content-between">
                                <div class="avatar flex-shrink-0">
                                    <img src="{{ asset('assets/admin/img/icons/unicons/paypal.png') }}"  alt="Credit Card" class="rounded" />
                                </div>
                            </div>
                            <span class="d-block mb-1">OutStanding</span>
                            <h3 class="card-title text-nowrap mb-2">{{ $totalOutstandingAmount }}</h3>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- / Content -->

<!-- Footer -->

<!-- / Footer -->

                   
@endsection

@section('script')
<script>
    var receiptCount = {{ $receiptCount ?? 0 }};
    var monthlyCollection = @json($monthlyData);
</script>
<script src="{{asset('assets/admin/js/dashboards-analytics.js')}}"></script>
@endsection