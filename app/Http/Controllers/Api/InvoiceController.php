<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Salesperson;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Throwable;

class InvoiceController extends Controller
{
    /**
     * Bulk Insert Invoices API
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function insertMulti(Request $request)
    {
        $rawInput = $request->all() ?: $request->json()->all();

        // -----------------------------------------------------------------
        // 1. Normalize Payload (Supports wrapped or direct array)
        // -----------------------------------------------------------------
        $invoices = $rawInput['invoices'] ?? $rawInput['data'] ?? null;

        if (!$invoices) {
            if (is_array($rawInput) && !empty($rawInput)) {
                $invoices = (array_values($rawInput) === $rawInput && isset($rawInput[0]) && is_array($rawInput[0]))
                    ? $rawInput
                    : (isset($rawInput['invoice_no']) || isset($rawInput['amount']) ? [$rawInput] : []);
            }
        }

        if (empty($invoices) || !is_array($invoices)) {
            return response()->json([
                'status' => false,
                'message' => 'No invoice data provided. Please provide an array of invoices.',
                'errors' => ['invoices' => ['The invoices list is required and cannot be empty.']]
            ], 422);
        }

        // -----------------------------------------------------------------
        // 2. Single-Pass Validation & Identifier Extraction
        // -----------------------------------------------------------------
        $errors = [];
        $seenInPayload = [];
        $duplicatesInPayload = [];
        $invoiceNumbers = [];
        $firmNames = [];
        $salespersonNames = [];
        $validatedItems = [];

        foreach ($invoices as $index => $item) {
            $rowNum = $index + 1;
            $rowErrors = [];

            if (!is_array($item)) {
                $errors["item_{$rowNum}"][] = "Invoice at position {$rowNum} must be a valid JSON object.";
                continue;
            }

            // Validate invoice_no
            $invNo = isset($item['invoice_no']) ? trim((string)$item['invoice_no']) : '';
            if ($invNo === '') {
                $rowErrors[] = "invoice_no is required.";
            } else {
                if (isset($seenInPayload[$invNo])) {
                    $duplicatesInPayload[$invNo] = true;
                } else {
                    $seenInPayload[$invNo] = true;
                    $invoiceNumbers[] = $invNo;
                }
            }

            // Validate and parse date (cached to avoid re-parsing later)
            $formattedDate = null;
            if (empty($item['date'])) {
                $rowErrors[] = "date is required.";
            } else {
                try {
                    $formattedDate = Carbon::parse($item['date'])->format('Y-m-d');
                } catch (Throwable $e) {
                    $rowErrors[] = "date '{$item['date']}' is not a valid date format.";
                }
            }

            // Validate amount
            $amount = null;
            if (!isset($item['amount']) || $item['amount'] === '') {
                $rowErrors[] = "amount is required.";
            } elseif (!is_numeric($item['amount']) || (float)$item['amount'] < 0) {
                $rowErrors[] = "amount must be a valid non-negative number.";
            } else {
                $amount = round((float)$item['amount'], 2);
            }

            // Validate firm / customer
            $firm = trim((string)($item['firm_id'] ?? $item['firm_name'] ?? $item['customer'] ?? $item['customer_name'] ?? ''));
            if ($firm === '') {
                $rowErrors[] = "firm_id or customer name is required.";
            } else {
                $firmNames[$firm] = true;
            }

            // Validate salesperson
            $sp = trim((string)($item['salesperson_id'] ?? $item['salesperson_name'] ?? $item['salesperson'] ?? ''));
            if ($sp === '') {
                $rowErrors[] = "salesperson_id or salesperson name is required.";
            } else {
                $salespersonNames[$sp] = true;
            }

            // Validate discount percent
            if (isset($item['discount_percent']) && $item['discount_percent'] !== '') {
                if (!is_numeric($item['discount_percent']) || (float)$item['discount_percent'] < 0 || (float)$item['discount_percent'] > 100) {
                    $rowErrors[] = "discount_percent must be a number between 0 and 100.";
                }
            }

            if (!empty($rowErrors)) {
                $errors["item_{$rowNum}"] = $rowErrors;
            } else {
                $validatedItems[] = [
                    'inv_no' => $invNo,
                    'date' => $formattedDate,
                    'amount' => $amount,
                    'firm' => $firm,
                    'firm_key' => mb_strtolower($firm),
                    'sp' => $sp,
                    'sp_key' => mb_strtolower($sp),
                    'discount_percent' => isset($item['discount_percent']) && $item['discount_percent'] !== '' ? round((float)$item['discount_percent'], 2) : null,
                    'discount_amount' => isset($item['discount_amount']) && $item['discount_amount'] !== '' ? round((float)$item['discount_amount'], 2) : null,
                    'payable_amount' => isset($item['payable_amount']) && $item['payable_amount'] !== '' ? round((float)$item['payable_amount'], 2) : null,
                ];
            }
        }

        // Add payload duplicates if any
        if (!empty($duplicatesInPayload)) {
            $errors['duplicates_in_payload'] = array_map(function ($inv) {
                return "Invoice number '{$inv}' is duplicated in the request payload.";
            }, array_keys($duplicatesInPayload));
        }

        // Return early if any structural or per-item validation errors occurred
        if (!empty($errors)) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed for one or more invoice items.',
                'errors' => $errors
            ], 422);
        }

        // -----------------------------------------------------------------
        // 3. Database Checks (1 query each, selective columns only)
        // -----------------------------------------------------------------
        $uniqueFirmNames = array_keys($firmNames);
        $uniqueSpNames = array_keys($salespersonNames);

        // a. Check duplicate invoice numbers in DB
        $existingInvInDb = Invoice::withTrashed()
            ->whereIn('invoice_no', $invoiceNumbers)
            ->pluck('invoice_no')
            ->toArray();

        // b. Fetch matching customers in 1 query (selective columns)
        $customerMap = [];
        $existingCustomers = Customer::whereIn('firm_name', $uniqueFirmNames)
            ->orWhereIn('name', $uniqueFirmNames)
            ->get(['id', 'firm_name', 'name', 'discount']);

        foreach ($existingCustomers as $c) {
            if ($c->firm_name) {
                $customerMap[mb_strtolower(trim($c->firm_name))] = $c;
            }
            if ($c->name) {
                $customerMap[mb_strtolower(trim($c->name))] = $c;
            }
        }

        // c. Fetch matching salespersons in 1 query (selective columns)
        $salespersonMap = [];
        $existingSalespersons = Salesperson::whereIn('name', $uniqueSpNames)
            ->orWhereIn('salesperson_code', $uniqueSpNames)
            ->get(['id', 'name', 'salesperson_code']);

        foreach ($existingSalespersons as $s) {
            if ($s->name) {
                $salespersonMap[mb_strtolower(trim($s->name))] = $s;
            }
            if ($s->salesperson_code) {
                $salespersonMap[mb_strtolower(trim($s->salesperson_code))] = $s;
            }
        }

        // d. Detect missing customers & salespersons
        $missingCustomers = [];
        foreach ($uniqueFirmNames as $fn) {
            if (!isset($customerMap[mb_strtolower($fn)])) {
                $missingCustomers[] = $fn;
            }
        }

        $missingSalespersons = [];
        foreach ($uniqueSpNames as $sn) {
            if (!isset($salespersonMap[mb_strtolower($sn)])) {
                $missingSalespersons[] = $sn;
            }
        }

        // If duplicate invoice numbers in DB or missing references found, return 422
        if (!empty($existingInvInDb) || !empty($missingCustomers) || !empty($missingSalespersons)) {
            $dbErrors = [];
            if (!empty($existingInvInDb)) {
                $dbErrors['already_exists_in_database'] = array_map(function ($inv) {
                    return "Invoice number '{$inv}' already exists in database.";
                }, $existingInvInDb);
            }

            return response()->json(array_filter([
                'status' => false,
                'message' => (!empty($missingCustomers) || !empty($missingSalespersons))
                    ? 'Some customers or salespersons do not exist in the database. Please add them first.'
                    : 'One or more invoice numbers already exist in the database.',
                'missing_customers' => $missingCustomers ?: null,
                'missing_salespersons' => $missingSalespersons ?: null,
                'summary' => (!empty($missingCustomers) || !empty($missingSalespersons)) ? [
                    'total_missing_customers' => count($missingCustomers),
                    'total_missing_salespersons' => count($missingSalespersons),
                ] : null,
                'errors' => !empty($dbErrors) ? $dbErrors : null,
            ]), 422);
        }

        // -----------------------------------------------------------------
        // 4. Atomic Database Insert & Calculations (Single Transaction Pass)
        // -----------------------------------------------------------------
        $insertedInvoices = [];
        $userId = 1; // Statically 1

        DB::beginTransaction();
        try {
            foreach ($validatedItems as $item) {
                $customer = $customerMap[$item['firm_key']];
                $salesperson = $salespersonMap[$item['sp_key']];

                $amount = $item['amount'];
                $discountPercent = $item['discount_percent'] ?? (float)($customer->discount ?? 0);
                $discountAmount = $item['discount_amount'] ?? round(($amount * $discountPercent) / 100, 2);
                $payableAmount = $item['payable_amount'] ?? round(max(0, $amount - $discountAmount), 2);

                $invoice = Invoice::create([
                    'date' => $item['date'],
                    'invoice_no' => $item['inv_no'],
                    'firm_id' => $customer->id,
                    'salesperson_id' => $salesperson->id,
                    'amount' => $amount,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'payable_amount' => $payableAmount,
                    'entry_type' => 'hisabkitab',
                    'user_id' => $userId,
                ]);

                $insertedInvoices[] = [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'date' => $invoice->date,
                    'firm_id' => $customer->id,
                    'firm_name' => $customer->firm_name ?: $customer->name,
                    'salesperson_id' => $salesperson->id,
                    'salesperson_name' => $salesperson->name,
                    'amount' => (float)$invoice->amount,
                    'discount_percent' => (float)$invoice->discount_percent,
                    'discount_amount' => (float)$invoice->discount_amount,
                    'payable_amount' => (float)$invoice->payable_amount,
                    'entry_type' => $invoice->entry_type,
                    'user_id' => $userId,
                    'created_at' => $invoice->created_at ? $invoice->created_at->toIso8601String() : null,
                ];
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => count($insertedInvoices) . ' invoice(s) inserted successfully.',
                'total' => count($insertedInvoices),
                'data' => $insertedInvoices
            ], 200);

        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Failed to insert invoices: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Multi Invoice Insert for Red Bull
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function insertMultiRedBull(Request $request)
    {
        $rawInput = $request->all() ?: $request->json()->all();

        // -----------------------------------------------------------------
        // 1. Normalize Payload (Supports wrapped or direct array)
        // -----------------------------------------------------------------
        $invoices = $rawInput['invoices'] ?? $rawInput['data'] ?? null;

        if (!$invoices) {
            if (is_array($rawInput) && !empty($rawInput)) {
                $invoices = (array_values($rawInput) === $rawInput && isset($rawInput[0]) && is_array($rawInput[0]))
                    ? $rawInput
                    : (isset($rawInput['invoice_no']) || isset($rawInput['amount']) ? [$rawInput] : []);
            }
        }

        if (empty($invoices) || !is_array($invoices)) {
            return response()->json([
                'status' => false,
                'agency' => 'Red Bull',
                'message' => 'No invoice data provided. Please provide an array of invoices.',
                'errors' => ['invoices' => ['The invoices list is required and cannot be empty.']]
            ], 422);
        }

        // -----------------------------------------------------------------
        // 2. Single-Pass Validation & Identifier Extraction
        // -----------------------------------------------------------------
        $errors = [];
        $seenInPayload = [];
        $duplicatesInPayload = [];
        $invoiceNumbers = [];
        $firmNames = [];
        $salespersonNames = [];
        $validatedItems = [];

        foreach ($invoices as $index => $item) {
            $rowNum = $index + 1;
            $rowErrors = [];

            if (!is_array($item)) {
                $errors["item_{$rowNum}"][] = "Invoice at position {$rowNum} must be a valid JSON object.";
                continue;
            }

            // Validate invoice_no
            $invNo = isset($item['invoice_no']) ? trim((string)$item['invoice_no']) : '';
            if ($invNo === '') {
                $rowErrors[] = "invoice_no is required.";
            } else {
                if (isset($seenInPayload[$invNo])) {
                    $duplicatesInPayload[$invNo] = true;
                } else {
                    $seenInPayload[$invNo] = true;
                    $invoiceNumbers[] = $invNo;
                }
            }

            // Validate and parse date (cached to avoid re-parsing later)
            $formattedDate = null;
            if (empty($item['date'])) {
                $rowErrors[] = "date is required.";
            } else {
                try {
                    $formattedDate = Carbon::parse($item['date'])->format('Y-m-d');
                } catch (Throwable $e) {
                    $rowErrors[] = "date '{$item['date']}' is not a valid date format.";
                }
            }

            // Validate amount
            $amount = null;
            if (!isset($item['amount']) || $item['amount'] === '') {
                $rowErrors[] = "amount is required.";
            } elseif (!is_numeric($item['amount']) || (float)$item['amount'] < 0) {
                $rowErrors[] = "amount must be a valid non-negative number.";
            } else {
                $amount = round((float)$item['amount'], 2);
            }

            // Validate firm / customer
            $firm = trim((string)($item['firm_id'] ?? $item['firm_name'] ?? $item['customer'] ?? $item['customer_name'] ?? ''));
            if ($firm === '') {
                $rowErrors[] = "firm_id or customer name is required.";
            } else {
                $firmNames[$firm] = true;
            }

            // Validate salesperson
            $sp = trim((string)($item['salesperson_id'] ?? $item['salesperson_name'] ?? $item['salesperson'] ?? ''));
            if ($sp === '') {
                $rowErrors[] = "salesperson_id or salesperson name is required.";
            } else {
                $salespersonNames[$sp] = true;
            }

            // Validate discount percent
            if (isset($item['discount_percent']) && $item['discount_percent'] !== '') {
                if (!is_numeric($item['discount_percent']) || (float)$item['discount_percent'] < 0 || (float)$item['discount_percent'] > 100) {
                    $rowErrors[] = "discount_percent must be a number between 0 and 100.";
                }
            }

            if (!empty($rowErrors)) {
                $errors["item_{$rowNum}"] = $rowErrors;
            } else {
                $validatedItems[] = [
                    'inv_no' => $invNo,
                    'date' => $formattedDate,
                    'amount' => $amount,
                    'firm' => $firm,
                    'firm_key' => mb_strtolower($firm),
                    'sp' => $sp,
                    'sp_key' => mb_strtolower($sp),
                    'discount_percent' => isset($item['discount_percent']) && $item['discount_percent'] !== '' ? round((float)$item['discount_percent'], 2) : null,
                    'discount_amount' => isset($item['discount_amount']) && $item['discount_amount'] !== '' ? round((float)$item['discount_amount'], 2) : null,
                    'payable_amount' => isset($item['payable_amount']) && $item['payable_amount'] !== '' ? round((float)$item['payable_amount'], 2) : null,
                ];
            }
        }

        // Add payload duplicates if any
        if (!empty($duplicatesInPayload)) {
            $errors['duplicates_in_payload'] = array_map(function ($inv) {
                return "Invoice number '{$inv}' is duplicated in the request payload.";
            }, array_keys($duplicatesInPayload));
        }

        // Return early if any structural or per-item validation errors occurred
        if (!empty($errors)) {
            return response()->json([
                'status' => false,
                'agency' => 'Red Bull',
                'message' => 'Validation failed for one or more invoice items.',
                'errors' => $errors
            ], 422);
        }

        // -----------------------------------------------------------------
        // 3. Database Checks (1 query each, selective columns only)
        // -----------------------------------------------------------------
        $uniqueFirmNames = array_keys($firmNames);
        $uniqueSpNames = array_keys($salespersonNames);

        // a. Check duplicate invoice numbers in DB
        $existingInvInDb = Invoice::withTrashed()
            ->whereIn('invoice_no', $invoiceNumbers)
            ->pluck('invoice_no')
            ->toArray();

        // b. Fetch matching customers in 1 query (selective columns)
        $customerMap = [];
        $existingCustomers = Customer::whereIn('firm_name', $uniqueFirmNames)
            ->orWhereIn('name', $uniqueFirmNames)
            ->get(['id', 'firm_name', 'name', 'discount']);

        foreach ($existingCustomers as $c) {
            if ($c->firm_name) {
                $customerMap[mb_strtolower(trim($c->firm_name))] = $c;
            }
            if ($c->name) {
                $customerMap[mb_strtolower(trim($c->name))] = $c;
            }
        }

        // c. Fetch matching salespersons in 1 query (selective columns)
        $salespersonMap = [];
        $existingSalespersons = Salesperson::whereIn('name', $uniqueSpNames)
            ->orWhereIn('salesperson_code', $uniqueSpNames)
            ->get(['id', 'name', 'salesperson_code']);

        foreach ($existingSalespersons as $s) {
            if ($s->name) {
                $salespersonMap[mb_strtolower(trim($s->name))] = $s;
            }
            if ($s->salesperson_code) {
                $salespersonMap[mb_strtolower(trim($s->salesperson_code))] = $s;
            }
        }

        // d. Detect missing customers & salespersons
        $missingCustomers = [];
        foreach ($uniqueFirmNames as $fn) {
            if (!isset($customerMap[mb_strtolower($fn)])) {
                $missingCustomers[] = $fn;
            }
        }

        $missingSalespersons = [];
        foreach ($uniqueSpNames as $sn) {
            if (!isset($salespersonMap[mb_strtolower($sn)])) {
                $missingSalespersons[] = $sn;
            }
        }

        // If duplicate invoice numbers in DB or missing references found, return 422
        if (!empty($existingInvInDb) || !empty($missingCustomers) || !empty($missingSalespersons)) {
            $dbErrors = [];
            if (!empty($existingInvInDb)) {
                $dbErrors['already_exists_in_database'] = array_map(function ($inv) {
                    return "Invoice number '{$inv}' already exists in database.";
                }, $existingInvInDb);
            }

            return response()->json(array_filter([
                'status' => false,
                'agency' => 'Red Bull',
                'message' => (!empty($missingCustomers) || !empty($missingSalespersons))
                    ? 'Some customers or salespersons do not exist in the database. Please add them first.'
                    : 'One or more invoice numbers already exist in the database.',
                'missing_customers' => $missingCustomers ?: null,
                'missing_salespersons' => $missingSalespersons ?: null,
                'summary' => (!empty($missingCustomers) || !empty($missingSalespersons)) ? [
                    'total_missing_customers' => count($missingCustomers),
                    'total_missing_salespersons' => count($missingSalespersons),
                ] : null,
                'errors' => !empty($dbErrors) ? $dbErrors : null,
            ]), 422);
        }

        // -----------------------------------------------------------------
        // 4. Atomic Database Insert & Calculations (Single Transaction Pass)
        // -----------------------------------------------------------------
        $insertedInvoices = [];
        $userId = 3; // Statically 1

        DB::beginTransaction();
        try {
            foreach ($validatedItems as $item) {
                $customer = $customerMap[$item['firm_key']];
                $salesperson = $salespersonMap[$item['sp_key']];

                $amount = $item['amount'];
                $discountPercent = $item['discount_percent'] ?? (float)($customer->discount ?? 0);
                $discountAmount = $item['discount_amount'] ?? round(($amount * $discountPercent) / 100, 2);
                $payableAmount = $item['payable_amount'] ?? round(max(0, $amount - $discountAmount), 2);

                $invoice = Invoice::create([
                    'date' => $item['date'],
                    'invoice_no' => $item['inv_no'],
                    'firm_id' => $customer->id,
                    'salesperson_id' => $salesperson->id,
                    'amount' => $amount,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'payable_amount' => $payableAmount,
                    'entry_type' => 'hisabkitab',
                    'user_id' => $userId,
                ]);

                $insertedInvoices[] = [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'date' => $invoice->date,
                    'firm_id' => $customer->id,
                    'firm_name' => $customer->firm_name ?: $customer->name,
                    'salesperson_id' => $salesperson->id,
                    'salesperson_name' => $salesperson->name,
                    'amount' => (float)$invoice->amount,
                    'discount_percent' => (float)$invoice->discount_percent,
                    'discount_amount' => (float)$invoice->discount_amount,
                    'payable_amount' => (float)$invoice->payable_amount,
                    'entry_type' => $invoice->entry_type,
                    'user_id' => $userId,
                    'created_at' => $invoice->created_at ? $invoice->created_at->toIso8601String() : null,
                ];
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'agency' => 'Red Bull',
                'message' => count($insertedInvoices) . ' invoice(s) inserted successfully.',
                'total' => count($insertedInvoices),
                'data' => $insertedInvoices
            ], 200);

        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'agency' => 'Red Bull',
                'message' => 'Failed to insert invoices: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Multi Invoice Insert for Cadbury
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function insertMultiCadbury(Request $request)
    {
        $rawInput = $request->all() ?: $request->json()->all();

        // -----------------------------------------------------------------
        // 1. Normalize Payload (Supports wrapped or direct array)
        // -----------------------------------------------------------------
        $invoices = $rawInput['invoices'] ?? $rawInput['data'] ?? null;

        if (!$invoices) {
            if (is_array($rawInput) && !empty($rawInput)) {
                $invoices = (array_values($rawInput) === $rawInput && isset($rawInput[0]) && is_array($rawInput[0]))
                    ? $rawInput
                    : (isset($rawInput['invoice_no']) || isset($rawInput['amount']) ? [$rawInput] : []);
            }
        }

        if (empty($invoices) || !is_array($invoices)) {
            return response()->json([
                'status' => false,
                'agency' => 'Cadbury',
                'message' => 'No invoice data provided. Please provide an array of invoices.',
                'errors' => ['invoices' => ['The invoices list is required and cannot be empty.']]
            ], 422);
        }

        // -----------------------------------------------------------------
        // 2. Single-Pass Validation & Identifier Extraction
        // -----------------------------------------------------------------
        $errors = [];
        $seenInPayload = [];
        $duplicatesInPayload = [];
        $invoiceNumbers = [];
        $firmNames = [];
        $salespersonNames = [];
        $validatedItems = [];

        foreach ($invoices as $index => $item) {
            $rowNum = $index + 1;
            $rowErrors = [];

            if (!is_array($item)) {
                $errors["item_{$rowNum}"][] = "Invoice at position {$rowNum} must be a valid JSON object.";
                continue;
            }

            // Validate invoice_no
            $invNo = isset($item['invoice_no']) ? trim((string)$item['invoice_no']) : '';
            if ($invNo === '') {
                $rowErrors[] = "invoice_no is required.";
            } else {
                if (isset($seenInPayload[$invNo])) {
                    $duplicatesInPayload[$invNo] = true;
                } else {
                    $seenInPayload[$invNo] = true;
                    $invoiceNumbers[] = $invNo;
                }
            }

            // Validate and parse date (cached to avoid re-parsing later)
            $formattedDate = null;
            if (empty($item['date'])) {
                $rowErrors[] = "date is required.";
            } else {
                try {
                    $formattedDate = Carbon::parse($item['date'])->format('Y-m-d');
                } catch (Throwable $e) {
                    $rowErrors[] = "date '{$item['date']}' is not a valid date format.";
                }
            }

            // Validate amount
            $amount = null;
            if (!isset($item['amount']) || $item['amount'] === '') {
                $rowErrors[] = "amount is required.";
            } elseif (!is_numeric($item['amount']) || (float)$item['amount'] < 0) {
                $rowErrors[] = "amount must be a valid non-negative number.";
            } else {
                $amount = round((float)$item['amount'], 2);
            }

            // Validate firm / customer
            $firm = trim((string)($item['firm_id'] ?? $item['firm_name'] ?? $item['customer'] ?? $item['customer_name'] ?? ''));
            if ($firm === '') {
                $rowErrors[] = "firm_id or customer name is required.";
            } else {
                $firmNames[$firm] = true;
            }

            // Validate salesperson
            $sp = trim((string)($item['salesperson_id'] ?? $item['salesperson_name'] ?? $item['salesperson'] ?? ''));
            if ($sp === '') {
                $rowErrors[] = "salesperson_id or salesperson name is required.";
            } else {
                $salespersonNames[$sp] = true;
            }

            // Validate discount percent
            if (isset($item['discount_percent']) && $item['discount_percent'] !== '') {
                if (!is_numeric($item['discount_percent']) || (float)$item['discount_percent'] < 0 || (float)$item['discount_percent'] > 100) {
                    $rowErrors[] = "discount_percent must be a number between 0 and 100.";
                }
            }

            if (!empty($rowErrors)) {
                $errors["item_{$rowNum}"] = $rowErrors;
            } else {
                $validatedItems[] = [
                    'inv_no' => $invNo,
                    'date' => $formattedDate,
                    'amount' => $amount,
                    'firm' => $firm,
                    'firm_key' => mb_strtolower($firm),
                    'sp' => $sp,
                    'sp_key' => mb_strtolower($sp),
                    'discount_percent' => isset($item['discount_percent']) && $item['discount_percent'] !== '' ? round((float)$item['discount_percent'], 2) : null,
                    'discount_amount' => isset($item['discount_amount']) && $item['discount_amount'] !== '' ? round((float)$item['discount_amount'], 2) : null,
                    'payable_amount' => isset($item['payable_amount']) && $item['payable_amount'] !== '' ? round((float)$item['payable_amount'], 2) : null,
                ];
            }
        }

        // Add payload duplicates if any
        if (!empty($duplicatesInPayload)) {
            $errors['duplicates_in_payload'] = array_map(function ($inv) {
                return "Invoice number '{$inv}' is duplicated in the request payload.";
            }, array_keys($duplicatesInPayload));
        }

        // Return early if any structural or per-item validation errors occurred
        if (!empty($errors)) {
            return response()->json([
                'status' => false,
                'agency' => 'Cadbury',
                'message' => 'Validation failed for one or more invoice items.',
                'errors' => $errors
            ], 422);
        }

        // -----------------------------------------------------------------
        // 3. Database Checks (1 query each, selective columns only)
        // -----------------------------------------------------------------
        $uniqueFirmNames = array_keys($firmNames);
        $uniqueSpNames = array_keys($salespersonNames);

        // a. Check duplicate invoice numbers in DB
        $existingInvInDb = Invoice::withTrashed()
            ->whereIn('invoice_no', $invoiceNumbers)
            ->pluck('invoice_no')
            ->toArray();

        // b. Fetch matching customers in 1 query (selective columns)
        $customerMap = [];
        $existingCustomers = Customer::whereIn('firm_name', $uniqueFirmNames)
            ->orWhereIn('name', $uniqueFirmNames)
            ->get(['id', 'firm_name', 'name', 'discount']);

        foreach ($existingCustomers as $c) {
            if ($c->firm_name) {
                $customerMap[mb_strtolower(trim($c->firm_name))] = $c;
            }
            if ($c->name) {
                $customerMap[mb_strtolower(trim($c->name))] = $c;
            }
        }

        // c. Fetch matching salespersons in 1 query (selective columns)
        $salespersonMap = [];
        $existingSalespersons = Salesperson::whereIn('name', $uniqueSpNames)
            ->orWhereIn('salesperson_code', $uniqueSpNames)
            ->get(['id', 'name', 'salesperson_code']);

        foreach ($existingSalespersons as $s) {
            if ($s->name) {
                $salespersonMap[mb_strtolower(trim($s->name))] = $s;
            }
            if ($s->salesperson_code) {
                $salespersonMap[mb_strtolower(trim($s->salesperson_code))] = $s;
            }
        }

        // d. Detect missing customers & salespersons
        $missingCustomers = [];
        foreach ($uniqueFirmNames as $fn) {
            if (!isset($customerMap[mb_strtolower($fn)])) {
                $missingCustomers[] = $fn;
            }
        }

        $missingSalespersons = [];
        foreach ($uniqueSpNames as $sn) {
            if (!isset($salespersonMap[mb_strtolower($sn)])) {
                $missingSalespersons[] = $sn;
            }
        }

        // If duplicate invoice numbers in DB or missing references found, return 422
        if (!empty($existingInvInDb) || !empty($missingCustomers) || !empty($missingSalespersons)) {
            $dbErrors = [];
            if (!empty($existingInvInDb)) {
                $dbErrors['already_exists_in_database'] = array_map(function ($inv) {
                    return "Invoice number '{$inv}' already exists in database.";
                }, $existingInvInDb);
            }

            return response()->json(array_filter([
                'status' => false,
                'agency' => 'Cadbury',
                'message' => (!empty($missingCustomers) || !empty($missingSalespersons))
                    ? 'Some customers or salespersons do not exist in the database. Please add them first.'
                    : 'One or more invoice numbers already exist in the database.',
                'missing_customers' => $missingCustomers ?: null,
                'missing_salespersons' => $missingSalespersons ?: null,
                'summary' => (!empty($missingCustomers) || !empty($missingSalespersons)) ? [
                    'total_missing_customers' => count($missingCustomers),
                    'total_missing_salespersons' => count($missingSalespersons),
                ] : null,
                'errors' => !empty($dbErrors) ? $dbErrors : null,
            ]), 422);
        }

        // -----------------------------------------------------------------
        // 4. Atomic Database Insert & Calculations (Single Transaction Pass)
        // -----------------------------------------------------------------
        $insertedInvoices = [];
        $userId = 1;

        DB::beginTransaction();
        try {
            foreach ($validatedItems as $item) {
                $customer = $customerMap[$item['firm_key']];
                $salesperson = $salespersonMap[$item['sp_key']];

                $amount = $item['amount'];
                $discountPercent = $item['discount_percent'] ?? (float)($customer->discount ?? 0);
                $discountAmount = $item['discount_amount'] ?? round(($amount * $discountPercent) / 100, 2);
                $payableAmount = $item['payable_amount'] ?? round(max(0, $amount - $discountAmount), 2);

                $invoice = Invoice::create([
                    'date' => $item['date'],
                    'invoice_no' => $item['inv_no'],
                    'firm_id' => $customer->id,
                    'salesperson_id' => $salesperson->id,
                    'amount' => $amount,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'payable_amount' => $payableAmount,
                    'entry_type' => 'hisabkitab',
                    'user_id' => $userId,
                ]);

                $insertedInvoices[] = [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'date' => $invoice->date,
                    'firm_id' => $customer->id,
                    'firm_name' => $customer->firm_name ?: $customer->name,
                    'salesperson_id' => $salesperson->id,
                    'salesperson_name' => $salesperson->name,
                    'amount' => (float)$invoice->amount,
                    'discount_percent' => (float)$invoice->discount_percent,
                    'discount_amount' => (float)$invoice->discount_amount,
                    'payable_amount' => (float)$invoice->payable_amount,
                    'entry_type' => $invoice->entry_type,
                    'user_id' => $userId,
                    'created_at' => $invoice->created_at ? $invoice->created_at->toIso8601String() : null,
                ];
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'agency' => 'Cadbury',
                'message' => count($insertedInvoices) . ' invoice(s) inserted successfully.',
                'total' => count($insertedInvoices),
                'data' => $insertedInvoices
            ], 200);

        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'agency' => 'Cadbury',
                'message' => 'Failed to insert invoices: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Multi Invoice Insert for Parle
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function insertMultiParle(Request $request)
    {
        $rawInput = $request->all() ?: $request->json()->all();

        // -----------------------------------------------------------------
        // 1. Normalize Payload (Supports wrapped or direct array)
        // -----------------------------------------------------------------
        $invoices = $rawInput['invoices'] ?? $rawInput['data'] ?? null;

        if (!$invoices) {
            if (is_array($rawInput) && !empty($rawInput)) {
                $invoices = (array_values($rawInput) === $rawInput && isset($rawInput[0]) && is_array($rawInput[0]))
                    ? $rawInput
                    : (isset($rawInput['invoice_no']) || isset($rawInput['amount']) ? [$rawInput] : []);
            }
        }

        if (empty($invoices) || !is_array($invoices)) {
            return response()->json([
                'status' => false,
                'agency' => 'Parle',
                'message' => 'No invoice data provided. Please provide an array of invoices.',
                'errors' => ['invoices' => ['The invoices list is required and cannot be empty.']]
            ], 422);
        }

        // -----------------------------------------------------------------
        // 2. Single-Pass Validation & Identifier Extraction
        // -----------------------------------------------------------------
        $errors = [];
        $seenInPayload = [];
        $duplicatesInPayload = [];
        $invoiceNumbers = [];
        $firmNames = [];
        $salespersonNames = [];
        $validatedItems = [];

        foreach ($invoices as $index => $item) {
            $rowNum = $index + 1;
            $rowErrors = [];

            if (!is_array($item)) {
                $errors["item_{$rowNum}"][] = "Invoice at position {$rowNum} must be a valid JSON object.";
                continue;
            }

            // Validate invoice_no
            $invNo = isset($item['invoice_no']) ? trim((string)$item['invoice_no']) : '';
            if ($invNo === '') {
                $rowErrors[] = "invoice_no is required.";
            } else {
                if (isset($seenInPayload[$invNo])) {
                    $duplicatesInPayload[$invNo] = true;
                } else {
                    $seenInPayload[$invNo] = true;
                    $invoiceNumbers[] = $invNo;
                }
            }

            // Validate and parse date (cached to avoid re-parsing later)
            $formattedDate = null;
            if (empty($item['date'])) {
                $rowErrors[] = "date is required.";
            } else {
                try {
                    $formattedDate = Carbon::parse($item['date'])->format('Y-m-d');
                } catch (Throwable $e) {
                    $rowErrors[] = "date '{$item['date']}' is not a valid date format.";
                }
            }

            // Validate amount
            $amount = null;
            if (!isset($item['amount']) || $item['amount'] === '') {
                $rowErrors[] = "amount is required.";
            } elseif (!is_numeric($item['amount']) || (float)$item['amount'] < 0) {
                $rowErrors[] = "amount must be a valid non-negative number.";
            } else {
                $amount = round((float)$item['amount'], 2);
            }

            // Validate firm / customer
            $firm = trim((string)($item['firm_id'] ?? $item['firm_name'] ?? $item['customer'] ?? $item['customer_name'] ?? ''));
            if ($firm === '') {
                $rowErrors[] = "firm_id or customer name is required.";
            } else {
                $firmNames[$firm] = true;
            }

            // Validate salesperson
            $sp = trim((string)($item['salesperson_id'] ?? $item['salesperson_name'] ?? $item['salesperson'] ?? ''));
            if ($sp === '') {
                $rowErrors[] = "salesperson_id or salesperson name is required.";
            } else {
                $salespersonNames[$sp] = true;
            }

            // Validate discount percent
            if (isset($item['discount_percent']) && $item['discount_percent'] !== '') {
                if (!is_numeric($item['discount_percent']) || (float)$item['discount_percent'] < 0 || (float)$item['discount_percent'] > 100) {
                    $rowErrors[] = "discount_percent must be a number between 0 and 100.";
                }
            }

            if (!empty($rowErrors)) {
                $errors["item_{$rowNum}"] = $rowErrors;
            } else {
                $validatedItems[] = [
                    'inv_no' => $invNo,
                    'date' => $formattedDate,
                    'amount' => $amount,
                    'firm' => $firm,
                    'firm_key' => mb_strtolower($firm),
                    'sp' => $sp,
                    'sp_key' => mb_strtolower($sp),
                    'discount_percent' => isset($item['discount_percent']) && $item['discount_percent'] !== '' ? round((float)$item['discount_percent'], 2) : null,
                    'discount_amount' => isset($item['discount_amount']) && $item['discount_amount'] !== '' ? round((float)$item['discount_amount'], 2) : null,
                    'payable_amount' => isset($item['payable_amount']) && $item['payable_amount'] !== '' ? round((float)$item['payable_amount'], 2) : null,
                ];
            }
        }

        // Add payload duplicates if any
        if (!empty($duplicatesInPayload)) {
            $errors['duplicates_in_payload'] = array_map(function ($inv) {
                return "Invoice number '{$inv}' is duplicated in the request payload.";
            }, array_keys($duplicatesInPayload));
        }

        // Return early if any structural or per-item validation errors occurred
        if (!empty($errors)) {
            return response()->json([
                'status' => false,
                'agency' => 'Parle',
                'message' => 'Validation failed for one or more invoice items.',
                'errors' => $errors
            ], 422);
        }

        // -----------------------------------------------------------------
        // 3. Database Checks (1 query each, selective columns only)
        // -----------------------------------------------------------------
        $uniqueFirmNames = array_keys($firmNames);
        $uniqueSpNames = array_keys($salespersonNames);

        // a. Check duplicate invoice numbers in DB
        $existingInvInDb = Invoice::withTrashed()
            ->whereIn('invoice_no', $invoiceNumbers)
            ->pluck('invoice_no')
            ->toArray();

        // b. Fetch matching customers in 1 query (selective columns)
        $customerMap = [];
        $existingCustomers = Customer::whereIn('firm_name', $uniqueFirmNames)
            ->orWhereIn('name', $uniqueFirmNames)
            ->get(['id', 'firm_name', 'name', 'discount']);

        foreach ($existingCustomers as $c) {
            if ($c->firm_name) {
                $customerMap[mb_strtolower(trim($c->firm_name))] = $c;
            }
            if ($c->name) {
                $customerMap[mb_strtolower(trim($c->name))] = $c;
            }
        }

        // c. Fetch matching salespersons in 1 query (selective columns)
        $salespersonMap = [];
        $existingSalespersons = Salesperson::whereIn('name', $uniqueSpNames)
            ->orWhereIn('salesperson_code', $uniqueSpNames)
            ->get(['id', 'name', 'salesperson_code']);

        foreach ($existingSalespersons as $s) {
            if ($s->name) {
                $salespersonMap[mb_strtolower(trim($s->name))] = $s;
            }
            if ($s->salesperson_code) {
                $salespersonMap[mb_strtolower(trim($s->salesperson_code))] = $s;
            }
        }

        // d. Detect missing customers & salespersons
        $missingCustomers = [];
        foreach ($uniqueFirmNames as $fn) {
            if (!isset($customerMap[mb_strtolower($fn)])) {
                $missingCustomers[] = $fn;
            }
        }

        $missingSalespersons = [];
        foreach ($uniqueSpNames as $sn) {
            if (!isset($salespersonMap[mb_strtolower($sn)])) {
                $missingSalespersons[] = $sn;
            }
        }

        // If duplicate invoice numbers in DB or missing references found, return 422
        if (!empty($existingInvInDb) || !empty($missingCustomers) || !empty($missingSalespersons)) {
            $dbErrors = [];
            if (!empty($existingInvInDb)) {
                $dbErrors['already_exists_in_database'] = array_map(function ($inv) {
                    return "Invoice number '{$inv}' already exists in database.";
                }, $existingInvInDb);
            }

            return response()->json(array_filter([
                'status' => false,
                'agency' => 'Parle',
                'message' => (!empty($missingCustomers) || !empty($missingSalespersons))
                    ? 'Some customers or salespersons do not exist in the database. Please add them first.'
                    : 'One or more invoice numbers already exist in the database.',
                'missing_customers' => $missingCustomers ?: null,
                'missing_salespersons' => $missingSalespersons ?: null,
                'summary' => (!empty($missingCustomers) || !empty($missingSalespersons)) ? [
                    'total_missing_customers' => count($missingCustomers),
                    'total_missing_salespersons' => count($missingSalespersons),
                ] : null,
                'errors' => !empty($dbErrors) ? $dbErrors : null,
            ]), 422);
        }

        // -----------------------------------------------------------------
        // 4. Atomic Database Insert & Calculations (Single Transaction Pass)
        // -----------------------------------------------------------------
        $insertedInvoices = [];
        $userId = 4;

        DB::beginTransaction();
        try {
            foreach ($validatedItems as $item) {
                $customer = $customerMap[$item['firm_key']];
                $salesperson = $salespersonMap[$item['sp_key']];

                $amount = $item['amount'];
                $discountPercent = $item['discount_percent'] ?? (float)($customer->discount ?? 0);
                $discountAmount = $item['discount_amount'] ?? round(($amount * $discountPercent) / 100, 2);
                $payableAmount = $item['payable_amount'] ?? round(max(0, $amount - $discountAmount), 2);

                $invoice = Invoice::create([
                    'date' => $item['date'],
                    'invoice_no' => $item['inv_no'],
                    'firm_id' => $customer->id,
                    'salesperson_id' => $salesperson->id,
                    'amount' => $amount,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'payable_amount' => $payableAmount,
                    'entry_type' => 'hisabkitab',
                    'user_id' => $userId,
                ]);

                $insertedInvoices[] = [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'date' => $invoice->date,
                    'firm_id' => $customer->id,
                    'firm_name' => $customer->firm_name ?: $customer->name,
                    'salesperson_id' => $salesperson->id,
                    'salesperson_name' => $salesperson->name,
                    'amount' => (float)$invoice->amount,
                    'discount_percent' => (float)$invoice->discount_percent,
                    'discount_amount' => (float)$invoice->discount_amount,
                    'payable_amount' => (float)$invoice->payable_amount,
                    'entry_type' => $invoice->entry_type,
                    'user_id' => $userId,
                    'created_at' => $invoice->created_at ? $invoice->created_at->toIso8601String() : null,
                ];
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'agency' => 'Parle',
                'message' => count($insertedInvoices) . ' invoice(s) inserted successfully.',
                'total' => count($insertedInvoices),
                'data' => $insertedInvoices
            ], 200);

        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'agency' => 'Parle',
                'message' => 'Failed to insert invoices: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

