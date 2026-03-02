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
                            <h3 class="card-title mb-2">12,628</h3>
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
                            <h3 class="card-title text-nowrap mb-1">4,679</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Total Revenue -->
        <div class="col-12 col-lg-8 order-2 order-md-3 order-lg-2 mb-4">
            <div class="card">
                <div class="row row-bordered g-0">
                    <div class="col-md-8">
                        <h5 class="card-header m-0 me-2 pb-3">Monthly Colection</h5>
                        <div id="totalRevenueChart" class="px-2"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-body">
                            <div class="text-center">
                                <h4>Receipt</h4>
                            </div>
                        </div>
                        <div id="growthChart"></div>
                        <div class="text-center fw-medium pt-3 mb-2">78 Receipt</div>
                        <div
                            class="d-flex px-xxl-4 px-lg-2 p-4 gap-xxl-3 gap-lg-1 gap-3 justify-content-between">
                            <div class="d-flex">
                                <div class="me-2">
                                    <span class="badge bg-label-primary p-2"><i
                                            class="bx bx-dollar text-primary"></i></span>
                                </div>
                                <div class="d-flex flex-column">
                                    <small>Approved Receipt</small>
                                    <h6 class="mb-0">32.5k</h6>
                                </div>
                            </div>
                            <div class="d-flex">
                                <div class="me-2">
                                    <span class="badge bg-label-info p-2"><i
                                            class="bx bx-wallet text-info"></i></span>
                                </div>
                                <div class="d-flex flex-column">
                                    <small>UnApproved Receipt</small>
                                    <h6 class="mb-0">41k</h6>
                                </div>
                            </div>
                        </div>
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
                            <h3 class="card-title text-nowrap mb-2">2,456</h3>
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
                            <span class="fw-medium d-block mb-1">Cooustomer</span>
                            <h3 class="card-title mb-2">14,857</h3>
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
                            <h3 class="card-title text-nowrap mb-2">2,456</h3>
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
@endsection