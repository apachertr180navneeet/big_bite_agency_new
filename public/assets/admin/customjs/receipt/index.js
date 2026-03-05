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
                { data: "receipt_no", searchable: true },
                { data: "date", searchable: true },
                { data: "firm_name", searchable: true, defaultContent: "-" },
                { data: "invoice_no", searchable: true, defaultContent: "-" },
                {
                    data: "amount",
                    searchable: false,
                    render: function (data) {
                        return Number(data || 0).toFixed(2);
                    }
                },
                {
                    data: "given_amount",
                    searchable: false,
                    render: function (data) {
                        return Number(data || 0).toFixed(2);
                    }
                },
                {
                    data: "discount",
                    searchable: false,
                    render: function (data) {
                        return Number(data || 0).toFixed(2) + "%";
                    }
                },
                {
                    data: "final_amount",
                    searchable: false,
                    render: function (data) {
                        return Number(data || 0).toFixed(2);
                    }
                },
                {
                    data: "mode",
                    searchable: true,
                    render: function (data) {
                        return data ? data.toUpperCase() : "-";
                    }
                },
                {
                    data: "manager_status",
                    searchable: true,
                    render: function (data) {
                        return renderStatusBadge(data);
                    }
                },
                {
                    data: "status",
                    searchable: true,
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
                    searchable: false,
                    orderable: false,
                    render: function (data) {
                        const editBtn = `
                            <a href="${editReceiptUrl.replace(":id", data)}" class="btn btn-sm btn-warning me-1">
                                Edit
                            </a>
                        `;

                        const deleteBtn = `
                            <button class="btn btn-sm btn-danger delete-receipt" data-id="${data}">
                                Delete
                            </button>
                        `;

                        return `${editBtn}${deleteBtn}`;
                    }
                }
            ]
        });
    }

    function getEditAdjustment(selectedInvoiceId) {
        const $form = $("#receiptForm");
        if (!$form.length || $form.data("mode") !== "edit") {
            return 0;
        }

        const currentInvoiceId = String($form.data("current-invoice-id") || "");
        const currentGiven = Number($form.data("current-given") || 0);

        return currentInvoiceId === selectedInvoiceId ? currentGiven : 0;
    }

    function calculatePayableAndFill() {
        const $firmSelected = $("#firm_id option:selected");
        const $invoiceSelected = $("#invoice_id option:selected");

        const discountPercent = Number($firmSelected.data("discount") || 0);
        const invoiceAmount = Number($invoiceSelected.data("amount") || 0);
        const salesPerson = String($invoiceSelected.data("sales-person") || "");

        const selectedInvoiceId = String($invoiceSelected.val() || "");
        const paidAmount = Number($invoiceSelected.data("paid") || 0);
        const editAdjustment = getEditAdjustment(selectedInvoiceId);

        $("#sales_person").val(salesPerson);

        if (!invoiceAmount) {
            $("#amount").val("");
            $("#discount").val(discountPercent.toFixed(2));
            $("#final_amount").val("");
            $("#remaining_amount").val("");
            $("#given_amount").removeAttr("max");
            return;
        }

        const netPayable = invoiceAmount - (invoiceAmount * discountPercent / 100);
        const remainingAmount = Math.max(netPayable - Math.max(paidAmount - editAdjustment, 0), 0);

        $("#amount").val(invoiceAmount.toFixed(2));
        $("#discount").val(discountPercent.toFixed(2));
        $("#final_amount").val(netPayable.toFixed(2));
        $("#remaining_amount").val(remainingAmount.toFixed(2));
        $("#given_amount").attr("max", remainingAmount.toFixed(2));
    }

    function filterInvoicesByFirm() {
        const selectedFirmId = String($("#firm_id").val() || "");
        const $invoiceSelect = $("#invoice_id");

        if (!$invoiceSelect.length) {
            return;
        }

        const currentInvoice = String($invoiceSelect.val() || "");

        $invoiceSelect.find("option").each(function () {
            const value = String($(this).val() || "");
            const firmId = String($(this).data("firm") || "");

            if (!value) {
                $(this).prop("hidden", false).prop("disabled", false);
                return;
            }

            const show = selectedFirmId !== "" && firmId === selectedFirmId;
            $(this).prop("hidden", !show).prop("disabled", !show);
        });

        if (currentInvoice) {
            const $selected = $invoiceSelect.find(`option[value="${currentInvoice}"]`);
            if (!$selected.length || $selected.prop("disabled")) {
                $invoiceSelect.val("");
            }
        }

        if (selectedFirmId === "") {
            $invoiceSelect.val("");
            $invoiceSelect.prop("disabled", true);
            $("#amount").val("");
            $("#discount").val("");
            $("#final_amount").val("");
            $("#remaining_amount").val("");
            $("#sales_person").val("");
            $("#given_amount").removeAttr("max");
        } else {
            $invoiceSelect.prop("disabled", false);
        }

        calculatePayableAndFill();
    }

    $(document).on("change", "#firm_id", function () {
        filterInvoicesByFirm();
    });

    $(document).on("change", "#invoice_id", function () {
        calculatePayableAndFill();
    });

    $(document).on("change", ".change-receipt-status", function () {
        const id = $(this).data("id");
        const status = $(this).val();
        const url = updateReceiptStatusUrl.replace(":id", id);

        $.ajax({
            url: url,
            type: "POST",
            data: { status: status },
            success: function (response) {
                toastr.success(response.message || "Status updated successfully.");

                if ($.fn.DataTable.isDataTable("#receiptTable")) {
                    $("#receiptTable").DataTable().ajax.reload(null, false);
                }
            },
            error: function (xhr) {
                if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                    $.each(xhr.responseJSON.errors, function (key, value) {
                        toastr.error(value[0]);
                    });
                } else {
                    toastr.error("Unable to update status.");
                }
            }
        });
    });

    $(document).on("click", "#applyReceiptFilters", function () {
        if (receiptTable) {
            receiptTable.ajax.reload();
        }
    });

    $(document).on("click", "#resetReceiptFilters", function () {
        $("#filter_receipt_no").val("");
        $("#filter_date_from").val("");
        $("#filter_date_to").val("");
        $("#filter_mode").val("");
        $("#filter_manager_status").val("");
        $("#filter_status").val("");

        if (receiptTable) {
            receiptTable.ajax.reload();
        }
    });

    $(document).on("keydown", function (e) {
        if ($(e.target).is("input, textarea, select")) {
            return;
        }

        if (e.key === "F2" && typeof createReceiptUrl !== "undefined") {
            e.preventDefault();
            window.location.href = createReceiptUrl;
        }

        if (e.key === "F1" && typeof indexReceiptUrl !== "undefined") {
            e.preventDefault();
            window.location.href = indexReceiptUrl;
        }
    });

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
            if (!result.isConfirmed) {
                return;
            }

            const url = deleteReceiptUrl.replace(":id", id);

            $.ajax({
                url: url,
                type: "DELETE",
                success: function (response) {
                    toastr.success(response.message || "Receipt deleted successfully.");

                    if ($.fn.DataTable.isDataTable("#receiptTable")) {
                        $("#receiptTable").DataTable().ajax.reload(null, false);
                    }
                },
                error: function () {
                    toastr.error("Something went wrong. Please try again.");
                }
            });
        });
    });

    if ($("#receiptForm").length) {
        filterInvoicesByFirm();

        $("#receiptForm").validate({
            rules: {
                date: { required: true },
                receipt_no: { required: true },
                firm_id: { required: true },
                invoice_id: { required: true },
                given_amount: { required: true, number: true, min: 0.01 },
                manager_status: { required: true },
                status: { required: true }
            },
            messages: {
                date: { required: "Date is required" },
                receipt_no: { required: "Receipt number is required" },
                firm_id: { required: "Please select a firm" },
                invoice_id: { required: "Please select an invoice" },
                given_amount: {
                    required: "Given amount is required",
                    number: "Given amount must be numeric",
                    min: "Given amount must be greater than 0"
                },
                manager_status: { required: "Please select manager status" },
                status: { required: "Please select status" }
            },
            errorElement: "small",
            errorClass: "text-danger",
            submitHandler: function (form) {
                const $submitBtn = $(form).find("button[type='submit']");
                const isEditMode = $(form).data("mode") === "edit";

                $.ajax({
                    url: $(form).attr("action"),
                    type: "POST",
                    data: $(form).serialize(),
                    beforeSend: function () {
                        $submitBtn.prop("disabled", true);
                    },
                    success: function (response) {
                        toastr.success(response.message || "Receipt saved successfully!");

                        if (!isEditMode) {
                            form.reset();
                        }

                        setTimeout(function () {
                            if (typeof indexReceiptUrl !== "undefined") {
                                window.location.href = indexReceiptUrl;
                            }
                        }, 1000);
                    },
                    error: function (xhr) {
                        $submitBtn.prop("disabled", false);

                        if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            $.each(xhr.responseJSON.errors, function (key, value) {
                                toastr.error(value[0]);
                            });
                        } else {
                            toastr.error("Something went wrong! Please try again.");
                        }
                    }
                });

                return false;
            }
        });
    }
});
