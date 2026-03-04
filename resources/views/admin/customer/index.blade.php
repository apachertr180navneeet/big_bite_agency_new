@extends('admin.layouts.app')

@section('style')
@endsection

@section('content')

<div class="container-fluid flex-grow-1 container-p-y">

    <!-- Page Header -->
    <div class="row">
        <div class="col-md-6 text-start">
            <h5 class="py-2 mb-2">
                <span class="text-primary fw-light">Customer Management</span>
            </h5>
        </div>

        <div class="col-md-6 text-end">
            <a 
                href="{{route('admin.customer.create')}}" 
                class="btn btn-primary"
            >
                Add Customer
            </a>
        </div>
    </div>

    <!-- Customer Table -->
    <div class="row">
        <div class="col-xl-12 col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive text-nowrap">
                        <table class="table table-bordered" id="customerTable" style="width:100%">
                            
                            <colgroup>
                                <col style="width:20%">  <!-- Firm Name -->
                                <col style="width:15%">  <!-- Name -->
                                <col style="width:15%">  <!-- Phone -->
                                <col style="width:15%">  <!-- GST No -->
                                <col style="width:10%">  <!-- Discount -->
                                <col style="width:10%">  <!-- Status -->
                                <col style="width:15%">  <!-- Action -->
                            </colgroup>

                            <thead>
                                <tr>
                                    <th>Firm Name</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>GST No</th>
                                    <th>Discount (%)</th>
                                    <th>Status</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>

                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection


@section('script')
<script>
    const getCustomerUrl = "{{ route('admin.customer.getall') }}";
    const createCustomerUrl = "{{ route('admin.customer.create') }}";
    const deleteCustomerUrl = "{{ route('admin.customer.delete', ':id') }}";
    const changeStatusUrl = "{{ route('admin.customer.status', ':id') }}";
    const editCustomerUrl = "{{ route('admin.customer.edit', ':id') }}";
</script>
<script src="{{asset('assets/admin/customjs/customer/index.js')}}"></script>
@endsection