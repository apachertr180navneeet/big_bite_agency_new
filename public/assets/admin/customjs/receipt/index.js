$(document).ready(function () {

    let receiptTable = null;

    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
        }
    });

    /*
    =========================
    STATUS BADGE
    =========================
    */

    function renderStatusBadge(value) {

        if (value === "accpet") {
            return '<span class="badge bg-label-success">Accept</span>';
        }

        if (value === "rejected") {
            return '<span class="badge bg-label-danger">Rejected</span>';
        }

        return '<span class="badge bg-label-warning">Pending</span>';
    }

    /*
    =========================
    DATATABLE
    =========================
    */

    if ($("#receiptTable").length) {

        receiptTable = $("#receiptTable").DataTable({

            processing: true,
            serverSide: true,
            autoWidth: false,
            searching: false,

            ajax: {
                url: getReceiptUrl,
                type: "GET",
                data: function (d) {

                    d.receipt_no = $("#filter_receipt_no").val();
                    d.date_from = $("#filter_date_from").val();
                    d.date_to = $("#filter_date_to").val();
                    d.mode = $("#filter_mode").val();
                    d.manager_status = $("#filter_manager_status").val();
                    d.status = $("#filter_status").val();

                }
            },

            columns: [

                { data: "receipt_no" },
                { data: "date" },
                { data: "firm_name", defaultContent: "-" },
                { data: "invoice_no", defaultContent: "-" },

                {
                    data: "amount",
                    render: data => Number(data || 0).toFixed(2)
                },

                {
                    data: "given_amount",
                    render: data => Number(data || 0).toFixed(2)
                },

                {
                    data: "final_amount",
                    render: data => Number(data || 0).toFixed(2)
                },

                {
                    data: "mode",
                    render: data => data ? data.toUpperCase() : "-"
                },

                {
                    data: "manager_status",
                    render: data => renderStatusBadge(data)
                },

                {
                    data: "status",
                    render: function (data, type, row) {

                        return `
                        <select class="form-select form-select-sm change-receipt-status" data-id="${row.id}">
                            <option value="pending" ${data === "pending" ? "selected" : ""}>Pending</option>
                            <option value="accpet" ${data === "accpet" ? "selected" : ""}>Accept</option>
                            <option value="rejected" ${data === "rejected" ? "selected" : ""}>Rejected</option>
                        </select>`;
                    }
                },

                {
                    data: "id",
                    orderable: false,
                    render: function (data) {

                        return `
                        <a href="${editReceiptUrl.replace(":id", data)}"
                        class="btn btn-sm btn-warning me-1">Edit</a>

                        <button class="btn btn-sm btn-danger delete-receipt"
                        data-id="${data}">Delete</button>`;
                    }
                }

            ]

        });

    }

    /*
    =========================
    DELETE RECEIPT
    =========================
    */

    $(document).on("click", ".delete-receipt", function () {

        const id = $(this).data("id");

        Swal.fire({
            title: "Are you sure?",
            text: "This receipt will be permanently deleted!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({

                url: deleteReceiptUrl.replace(":id", id),
                type: "DELETE",

                success: function (response) {

                    toastr.success(response.message || "Receipt deleted");

                    receiptTable.ajax.reload(null, false);

                },

                error: function () {

                    toastr.error("Something went wrong");

                }

            });

        });

    });

    /*
    =========================
    FORM VALIDATION
    =========================
    */

    if ($("#receiptForm").length || $("#editReceiptForm").length) {

        const form = $("#receiptForm").length ? $("#receiptForm") : $("#editReceiptForm");

        form.validate({

            rules: {
                date: { required: true },
                receipt_no: { required: true },
                firm_id: { required: true },
                invoice_id: { required: true },
                given_amount: { required: true, number: true, min: 0.01 }
            },

            messages: {
                date: { required: "Date is required" },
                receipt_no: { required: "Receipt number required" },
                firm_id: { required: "Select firm" },
                invoice_id: { required: "Select invoice" }
            },

            errorElement: "small",
            errorClass: "text-danger",

            submitHandler: function (formEl) {

                const $submitBtn = $(formEl).find("button[type='submit']");

                $.ajax({

                    url: $(formEl).attr("action"),
                    type: "POST",
                    data: $(formEl).serialize(),

                    beforeSend: function () {
                        $submitBtn.prop("disabled", true);
                    },

                    success: function (response) {

                        toastr.success(response.message || "Receipt saved");

                        window.location.href = indexReceiptUrl;

                    },

                    error: function () {

                        $submitBtn.prop("disabled", false);

                        toastr.error("Something went wrong");

                    }

                });

                return false;
            }

        });

    }

    /*
    =========================
    EDIT MODE
    =========================
    */

    if ($("#editReceiptForm").length) {
        $("#invoice_id").prop("disabled", true);
    }

    /*
    =========================
    LOAD INVOICE DATA
    =========================
    */

    function loadInvoiceData() {

        let selected = $("#invoice_id").find(":selected");

        let amount = parseFloat(selected.data("amount")) || 0;
        let payable = parseFloat(selected.data("payable")) || 0;
        let salesPerson = selected.data("sales-person") || "";

        $("#amount").val(amount.toFixed(2));
        $("#sales_person").val(salesPerson);

        calculateRemaining();
    }

    /*
    =========================
    CALCULATE REMAINING
    =========================
    */

    function calculateRemaining() {

        let payable = parseFloat($("#invoice_id option:selected").data("payable")) || 0;

        let given = parseFloat($("#given_amount").val()) || 0;

        let remaining = payable - given;

        if (remaining < 0) remaining = 0;

        $("#remaining_amount").val(remaining.toFixed(2));
    }

    /*
    =========================
    EVENTS
    =========================
    */

    $("#invoice_id").on("change", loadInvoiceData);

    $("#given_amount").on("input", calculateRemaining);

    /*
    =========================
    PAGE LOAD
    =========================
    */

    if ($("#invoice_id").val()) {
        loadInvoiceData();
    }

});