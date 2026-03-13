$(document).ready(function () {

    let receiptTable = null;

    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
        }
    });

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
                    render: function (data) {
                        return Number(data || 0).toFixed(2);
                    }
                },

                {
                    data: "given_amount",
                    render: function (data) {
                        return Number(data || 0).toFixed(2);
                    }
                },

                {
                    data: "final_amount",
                    render: function (data) {
                        return Number(data || 0).toFixed(2);
                    }
                },

                {
                    data: "mode",
                    render: function (data) {
                        return data ? data.toUpperCase() : "-";
                    }
                },

                {
                    data: "manager_status",
                    render: function (data) {
                        return renderStatusBadge(data);
                    }
                },

                {
                    data: "status",
                    render: function (data, type, row) {

                        return `<select class="form-select form-select-sm change-receipt-status" data-id="${row.id}">
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

                        const editBtn = `
                        <a href="${editReceiptUrl.replace(":id", data)}"
                        class="btn btn-sm btn-warning me-1">
                        Edit
                        </a>`;

                        const deleteBtn = `
                        <button class="btn btn-sm btn-danger delete-receipt"
                        data-id="${data}">
                        Delete
                        </button>`;

                        return `${editBtn}${deleteBtn}`;
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

            const url = deleteReceiptUrl.replace(":id", id);

            $.ajax({

                url: url,
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

    if ($("#receiptForm").length) {

        $("#receiptForm").validate({

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

            submitHandler: function (form) {

                const $submitBtn = $(form).find("button[type='submit']");

                $.ajax({

                    url: $(form).attr("action"),
                    type: "POST",
                    data: $(form).serialize(),

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
    LOAD CUSTOMER INVOICES
    =========================
    */

    $("#firm_id").on("change", function () {

        let firm_id = $(this).val();
        let invoiceDropdown = $("#invoice_id");

        invoiceDropdown.html('<option value="">Loading...</option>');

        if (firm_id !== "") {

            $.ajax({

                url: "/get-pending-invoices/" + firm_id,
                type: "GET",

                success: function (data) {

                    invoiceDropdown.html('<option value="">Select Invoice</option>');

                    if (data.length > 0) {

                        $.each(data, function (index, invoice) {

                            let paid = invoice.paid_amount ?? 0;
                            let payable = invoice.payable_amount ?? 0;

                            let remaining = payable - paid;

                            invoiceDropdown.append(`
                                <option value="${invoice.id}" 
                                    data-amount="${invoice.amount}"
                                    data-payable="${payable}"
                                    data-paid="${paid}"
                                    data-sales-person="${invoice.salesperson ? invoice.salesperson.name : ''}">
                                    ${invoice.invoice_no} (Remaining: ${remaining})
                                </option>
                            `);

                        });

                    } else {

                        invoiceDropdown.html('<option value="">No Pending Invoice</option>');

                    }

                }

            });

        } else {

            invoiceDropdown.html('<option value="">Select Invoice</option>');

        }

    });

    /*
    =========================
    INVOICE DETAIL
    =========================
    */

    $("#invoice_id").on("change", function () {

        let selected = $(this).find(":selected");

        let salesPerson = selected.data("sales-person") || "";
        let amount = selected.data("amount") || 0;
        let payable = selected.data("payable") || 0;
        let paid = selected.data("paid") || 0;

        let remaining = payable - paid;

        $("#amount").val(amount);
        $("#remaining_amount").val(remaining.toFixed(2));
        $("#sales_person").val(salesPerson);

    });


    /*
    =========================
    CHANGE RECEIPT STATUS
    =========================
    */

    $(document).on("change", ".change-receipt-status", function () {

        let id = $(this).data("id");
        let status = $(this).val();

        let url = updateReceiptStatusUrl.replace(":id", id);

        $.ajax({

            url: url,
            type: "POST",
            data: {
                status: status
            },

            success: function (response) {

                toastr.success(response.message || "Status updated");

                $("#receiptTable").DataTable().ajax.reload(null, false);

            },

            error: function () {

                toastr.error("Status update failed");

            }

        });

    });

});