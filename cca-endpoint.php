<?php
// Configuration
header("Content-Type: application/json");
require_once($_SERVER['DOCUMENT_ROOT'] . "/inc/INC_UTILITY.php");
require_once(__DIR__ . '/../controllers/CustomerCreditAdjustmentController.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/controllers/NotifiCation/notifyController.php');

// $protocol = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https://" : "http://";
$protocol = "https://";
$url_path = $protocol . $_SERVER["HTTP_HOST"];
// echo $url_path;
// echo '<pre>';print_r($_SERVER);echo '</pre>';
// Included controller
$ccaController = new CustomerCreditAdjustmentController($pdo);
// Dingtalk API configuration
$test_mode = false;
// ====================================================================================================== //

// POST Data submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === '') {
        echo json_encode(["status" => "error", "message" => "Action is required"]);
        exit;
    }
    // API : Action(s)
    try {
        if ($action == 'get_cca_list') {
            // Prepare data for search
            $request_type = ($_POST['select_request'] != '') ? $_POST['select_request'] : 'request';

            $search_data = [
                'com' => $_POST['select_com'],
                'status' => $_POST['search_status'],
                'month' => $_POST['search_month'],
                'year' => $_POST['search_year']
            ];

            // Query CCA document list
            $ccaList = $ccaController->getCCADocumentList($request_type, $search_data);

            // Draw result html table
            $html = ob_start(); // Start output buffering

            foreach ($ccaList as $data) : 
                $cca_id = htmlspecialchars($data['CCA_ID']);
                $cca_land = ($data['CCA_LAND'] == 'INTER') ? '_inter' : '';
                $request_type = htmlspecialchars($data['CCA_REQUEST_TYPE']);
                $url = ($request_type == 'request') ? "cca_ed{$cca_land}.php?cca_id={$cca_id}" : "cci_ed{$cca_land}.php?cca_id={$cca_id}";
                $customer_info = json_decode($data['CCA_CUSTOMER_INFO'], true);
                $workflow_status = $ccaController->displayApprovalStatus($data['CCA_STATUS'], $data['CCA_WORKFLOW_SEQ']);
                ?>
                <tr>
                    <td>
                        <a href="<?=$url?>" target="_blank">
                            <img src="images/iconedit.gif" title="Modify" width="18">
                        </a>
                    </td>
                    <td> <?=htmlspecialchars($data['CCA_NO'])?> </td>
                    <td> <?=htmlspecialchars($workflow_status)?> </td>
                    <td> <?=htmlspecialchars($customer_info['CN_CODE'])?> - <?=htmlspecialchars($customer_info['CUSTOMER_NAME'])?> [<?=htmlspecialchars($data['CCA_LAND'])?>]</td>
                    <?php if ($request_type == 'request') { ?>
                            <td>
                                - วงเงินเครดิตที่ต้องการขอ <strong><?=number_format($data['CCA_CREDIT_LIMIT_AMOUNT'], 2)?></strong> <?=($data['CCA_LAND'] == 'INTER') ? 'USD' : 'บาท'?> <br>
                                - จำนวนวันที่ต้องการขอ <strong><?=htmlspecialchars($data['CCA_CREDIT_TERM_DAYS'])?></strong> วัน
                            </td>
                    <?php } else { ?>
                            <td>
                                - วงเงินเครดิตเดิม <?=number_format($data['CCA_CREDIT_LIMIT_AMOUNT'], 2)?> <?=($data['CCA_LAND'] == 'INTER') ? 'USD' : 'บาท'?> <br>
                                - วงเงินเครดิตที่ขอเพิ่ม <?=number_format($data['CCA_CREDIT_LIMIT_AMOUNT_ADD'], 2)?> <?=($data['CCA_LAND'] == 'INTER') ? 'USD' : 'บาท'?> <br>
                                - วงเงินเครดิตรวม <strong><?=number_format($data['CCA_CREDIT_LIMIT_AMOUNT_TOTAL'], 2)?></strong> <?=($data['CCA_LAND'] == 'INTER') ? 'USD' : 'บาท'?> <br>
                                - จำนวนวันที่ต้องการขอ <strong><?=htmlspecialchars($data['CCA_CREDIT_TERM_DAYS'])?></strong> วัน
                            </td>
                    <?php } ?>
                    <td> <?=htmlspecialchars($data['CREATEBY'])?><br> <?=convertdatedMy(htmlspecialchars($data['CCA_CREATDT']))?> </td>
                    <td>
                        <a href="cca_dt.php?cca_id=<?=htmlspecialchars($data['CCA_ID'])?>">
                            <img src="images/icon_dt.png" width="22" title="Customer credit adjustment <?=htmlspecialchars($data['CCA_ID'])?>" alt="Detail">
                        </a>
                    </td>
                </tr>
                <?
            endforeach;
                
            $html = ob_get_clean(); // Stop output buffering

            echo json_encode([
                "status" => "success",
                "html" => trim($html)
            ]);
        }
        if ($action == 'search_customer') {
            $customer_name = $_POST['CUSTOMER_NAME'];
            $customers = $ccaController->searchCustomer($customer_name);

            echo json_encode(
                [
                    'success' => true,
                    'data' =>  $customers
                ]
            );
        }
        if ($action == 'submit_cca_form') {
            // Prepare data for insertion
            $submit_data = [
                'CCA_REQUEST_TYPE' => $_POST['CCA_REQUEST_TYPE'],
                'CCA_LAND' => $_POST['CCA_LAND'],
                'CCA_COM' => $_POST['CCA_COM'],
                'CCA_COMPANY_SUB_ID' => $_POST['CCA_COMPANY_SUB_ID'],
                'CCA_CUSTOMER_INFO' => $_POST['CCA_CUSTOMER_INFO'],
                'CCA_BANK_INFO' => $_POST['CCA_BANK_INFO'],
                'CCA_MONTHLY_PURCHASE_AMOUNT' => (float)$_POST['CCA_MONTHLY_PURCHASE_AMOUNT'],
                'CCA_CREDIT_LIMIT_AMOUNT' => (float)$_POST['CCA_CREDIT_LIMIT_AMOUNT'],
                'CCA_CREDIT_TERM_DAYS' => (int)$_POST['CCA_CREDIT_TERM_DAYS'],
                'CCA_CREDIT_EXCHANGE_RATE' => (float)$_POST['CCA_CREDIT_EXCHANGE_RATE'],
                'CCA_BILLING_DUE_DATE' => $_POST['CCA_BILLING_DUE_DATE'],
                'CCA_BILLING_CONTACT_NAME' => $_POST['CCA_BILLING_CONTACT_NAME'],
                'CCA_TERM_CALCULATION_METHOD' => $_POST['CCA_TERM_CALCULATION_METHOD'],
                'CCA_OTHER_METHOD' => $_POST['CCA_OTHER_METHOD'],
                'CCA_PAYMENT_METHOD' => $_POST['CCA_PAYMENT_METHOD'],
                'CCA_PAYMENT_DUE_DATE' => $_POST['CCA_PAYMENT_DUE_DATE'],
                'CCA_PAYMENT_CONTACT_PERSON' => $_POST['CCA_PAYMENT_CONTACT_PERSON'],
                'CCA_PAYMENT_CONTACT_TEL' => $_POST['CCA_PAYMENT_CONTACT_TEL'],
                'CCA_PAYMENT_CONTACT_EMAIL' => $_POST['CCA_PAYMENT_CONTACT_EMAIL'],
                'CCA_STATUS' => $_POST['CCA_STATUS'],
                'CCA_CREATEBY' => $_POST['CCA_CREATEBY'],
                'CCA_SALES_REMARK' => $_POST['CCA_SALES_REMARK'],
                'CCA_REQUESTER_ID' => $_POST['CCA_REQUESTER_ID'],
                'CCA_PARTNER_REQUEST_ID' => ($_POST['CCA_PARTNER_REQUEST_ID'] != "") ? $_POST['CCA_PARTNER_REQUEST_ID'] : 0 ,
                'CCA_PARTNER_CODE' => $_POST['CCA_PARTNER_CODE']
            ];

            // Decode attached file metadata from hidden input
            $attached_files_meta = json_decode($_POST['CCA_ATTACHED_FILES'] ?? [], true);

            // Handle File Uploads
            $uploaded_files = [];
            $upload_dir = __DIR__ . '/../../upload/docs/SDB/CCA/';

            // Create upload dir if not exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Process uploaded files from <input type="file" multiple>
            if (isset($_FILES['FILES']) && !empty($_FILES['FILES']['name'][0])) {
                foreach ($_FILES['FILES']['tmp_name'] as $index => $tmp_file) {
                    $file_name = basename($_FILES['FILES']['name'][$index]);
                    $target_file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($tmp_file, $target_file_path)) {
                        $uploaded_files[] = [
                            'file_name' => $file_name,
                            'file_path' => $url_path . "/upload/docs/SDB/CCA/". $file_name,
                            'comment' => ''
                        ];
                    }
                }
            }

            // Merge uploaded and preloaded files (avoid duplicates by file_name)
            $files_map = [];

            // Existing files (preloaded from hidden input)
            foreach ($attached_files_meta as $file) {
                $file_name = $file['file_name'];

                $files_map[$file_name] = [
                    'file_name' => $file_name,
                    'file_path' => $url_path . "/upload/docs/SDB/CCA/". $file_name,
                    'comment' => $file['comment'] ?? ''
                ];
            }

            // New uploads (overwrite if file_name matches)
            foreach ($uploaded_files as $file) {
                $file_name = $file['file_name'];

                $files_map[$file_name] = [
                    'file_name' => $file_name,
                    'file_path' => $file['file_path'],
                    'comment' => $files_map[$file_name]['comment'] ?? ''
                ];
            }

            // Final file array
            $final_files = array_values($files_map);

            // Store into DB
            $submit_data['CCA_ATTACHED_FILES'] = json_encode($final_files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Insert into database
            $result = $ccaController->submitCCAForm($submit_data);

            if ($result['status'] == 'success') {
                // Notification after submitted form.
                $last_insert_id = $result['last_insert_id'];
                $cca_customer_info = json_decode($_POST['CCA_CUSTOMER_INFO'], true);
                $cca_customer = $cca_customer_info['CUSTOMER_NAME'];
                $requester_name = $ccaController->getUserProfile($_POST['CCA_REQUESTER_ID'])['NAME_TH'];
                $cca_doc_no = $ccaController->getCCAFormDataByField(['CCA_NO'], $last_insert_id);
                $cca_type = $ccaController->getCCAFormDataByField(['CCA_REQUEST_TYPE'], $last_insert_id);
                $cca_land = $ccaController->getCCAFormDataByField(['CCA_LAND'], $last_insert_id);

                $url_type = ($cca_type == 'request') ? 'cca' : 'cci';
                $url_land = ($cca_land == 'INTER') ? '_inter' : '';

                $next_approver_id = $_POST['CCA_REQUESTER_ID'];

                if ($test_mode == true) {
                    $next_approver_id = [26, 99, 208];
                }       

                $dataArray = [];
                $dataArray['receive'] = $next_approver_id; // Receiver 
                $dataArray['sender'] = $_POST['CCA_CREATEBY']; // Requester
                $dataArray['url'] = "{$url_path}/admin/{$url_type}_ed{$url_land}.php?cca_id={$last_insert_id}"; // URL
                $dataArray['type_title'] = "Status: [Create Document]" . ' - ' . date('Y-m-d H:i:s'); // Form Status
                $dataArray['message'] = "การอนุมัติเครดิต - ถึง " . $requester_name; // Message
                $dataArray['type_ref'] = "CCA"; // Form Type
                $dataArray['ref_No'] = $cca_doc_no; // Doc no.
                $dataArray['customer'] = $cca_customer; // Customer Company
                SendNotiSubmitForm($dbConnect, $dataArray);

                echo json_encode([
                    "status" => "success",
                    "message" => "CCA form submitted successfully",
                    "uploaded_files" => $final_files
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Failed to insert CCA record",
                    'sql' => $result['sql']
                ]);
            }
        }
        if ($action == 'edit_cca_form') {
            // Prepare data for update
            $update_data = [
                'CCA_ID' => $_POST['CCA_ID'],
                'CCA_STATUS' => $_POST['CCA_STATUS'],
                'CCA_COMPANY_SUB_ID' => $_POST['CCA_COMPANY_SUB_ID'],
                'CCA_CUSTOMER_INFO' => $_POST['CCA_CUSTOMER_INFO'],
                'CCA_BANK_INFO' => $_POST['CCA_BANK_INFO'],
                'CCA_MONTHLY_PURCHASE_AMOUNT' => (float)$_POST['CCA_MONTHLY_PURCHASE_AMOUNT'],
                'CCA_CREDIT_LIMIT_AMOUNT' => (float)$_POST['CCA_CREDIT_LIMIT_AMOUNT'],
                'CCA_CREDIT_TERM_DAYS' => (int)$_POST['CCA_CREDIT_TERM_DAYS'],
                'CCA_CREDIT_EXCHANGE_RATE' => (float)$_POST['CCA_CREDIT_EXCHANGE_RATE'],
                'CCA_CREDIT_TERM_CUSTOM' => $_POST['CCA_CREDIT_TERM_CUSTOM'] ?? [],
                'CCA_BILLING_DUE_DATE' => $_POST['CCA_BILLING_DUE_DATE'],
                'CCA_BILLING_CONTACT_NAME' => $_POST['CCA_BILLING_CONTACT_NAME'],
                'CCA_TERM_CALCULATION_METHOD' => $_POST['CCA_TERM_CALCULATION_METHOD'],
                'CCA_OTHER_METHOD' => $_POST['CCA_OTHER_METHOD'],
                'CCA_PAYMENT_METHOD' => $_POST['CCA_PAYMENT_METHOD'],
                'CCA_PAYMENT_DUE_DATE' => $_POST['CCA_PAYMENT_DUE_DATE'],
                'CCA_PAYMENT_CONTACT_PERSON' => $_POST['CCA_PAYMENT_CONTACT_PERSON'],
                'CCA_PAYMENT_CONTACT_TEL' => $_POST['CCA_PAYMENT_CONTACT_TEL'],
                'CCA_SALES_REMARK' => $_POST['CCA_SALES_REMARK'],
                'CCA_FN_REMARK' => $_POST['CCA_FN_REMARK'],
                'CCA_REQUESTER_ID' => $_POST['CCA_REQUESTER_ID'],
                'CCA_APPROVAL_WORKFLOW' => $_POST['CCA_APPROVAL_WORKFLOW'] ?? [],
                'CCA_REF_QUOTATION' => $_POST['CCA_REF_QUOTATION']
            ];

            $attached_files_meta = json_decode($_POST['CCA_ATTACHED_FILES'], true); // safe decode

            // Directory config
            $upload_dir = __DIR__ . '/../../upload/docs/SDB/CCA/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); // recursive creation
            }

            // Handle uploaded files
            $uploaded_files = [];
            if (isset($_FILES['FILES']) && !empty($_FILES['FILES']['name'][0])) {
                foreach ($_FILES['FILES']['tmp_name'] as $index => $tmp_file) {
                    $file_name = basename($_FILES['FILES']['name'][$index]);
                    $target_file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($tmp_file, $target_file_path)) {
                        $uploaded_files[] = [
                            'file_name' => $file_name,
                            'file_path' => $url_path . "/upload/docs/SDB/CCA/". $file_name,
                            'comment' => ''
                        ];
                    }
                }
            }

            // Merge uploaded files with existing (preloaded) ones
            $files_map = [];

            // Add existing metadata (from hidden input)
            foreach ($attached_files_meta as $file) {
                $file_name = $file['file_name'];
                $files_map[$file_name] = [
                    'file_name' => $file_name,
                    'file_path' => $url_path . "/upload/docs/SDB/CCA/". $file_name,
                    'comment' => $file['comment'] ?? ''
                ];
            }

            // Add uploaded files (overwrite or add)
            foreach ($uploaded_files as $file) {
                $file_name = $file['file_name'];

                // If file exists in map, preserve comment/display_path
                $files_map[$file_name] = [
                    'file_name' => $file_name,
                    'file_path' => $file['file_path'],
                    'comment' => $files_map[$file_name]['comment'] ?? ''
                ];
            }

            // Final merged file list
            $merged_files = array_values($files_map);

            // Save to DB
            $update_data['CCA_ATTACHED_FILES'] = json_encode($merged_files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Update the database
            $result = $ccaController->editCCAForm($update_data);

            if ($result['status'] == 'success') {
                echo json_encode([
                    "status" => "success",
                    "message" => "CCA form updated successfully"
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Failed to update CCA record",
                    'sql' => $result['sql']
                ]);
            }
        }
        if ($action == 'submit_cci_form') {
            // Prepare data for insertion
            $submit_data = [
                'CCA_REQUEST_TYPE' => $_POST['CCA_REQUEST_TYPE'],
                'CCA_COM' => $_POST['CCA_COM'],
                'CCA_LAND' => $_POST['CCA_LAND'],
                'CCA_COMPANY_SUB_ID' => $_POST['CCA_COMPANY_SUB_ID'],
                'CCA_CUSTOMER_INFO' => $_POST['CCA_CUSTOMER_INFO'],
                'CCA_CREDIT_LIMIT_AMOUNT' => (float)$_POST['CCA_CREDIT_LIMIT_AMOUNT'],
                'CCA_CREDIT_LIMIT_AMOUNT_ADD' => (float)$_POST['CCA_CREDIT_LIMIT_AMOUNT_ADD'],
                'CCA_CREDIT_LIMIT_AMOUNT_TOTAL' => (float)$_POST['CCA_CREDIT_LIMIT_AMOUNT_TOTAL'],
                'CCA_CREDIT_TERM_DAYS' => (int)$_POST['CCA_CREDIT_TERM_DAYS'],
                'CCA_CREDIT_EXCHANGE_RATE' => (float)$_POST['CCA_CREDIT_EXCHANGE_RATE'],
                'CCA_BILLING_DUE_DATE' => $_POST['CCA_BILLING_DUE_DATE'],
                'CCA_BILLING_CONTACT_NAME' => $_POST['CCA_BILLING_CONTACT_NAME'],
                'CCA_TERM_CALCULATION_METHOD' => $_POST['CCA_TERM_CALCULATION_METHOD'],
                'CCA_OTHER_METHOD' => $_POST['CCA_OTHER_METHOD'],
                'CCA_PAYMENT_METHOD' => $_POST['CCA_PAYMENT_METHOD'],
                'CCA_PAYMENT_DUE_DATE' => $_POST['CCA_PAYMENT_DUE_DATE'],
                'CCA_PAYMENT_CONTACT_PERSON' => $_POST['CCA_PAYMENT_CONTACT_PERSON'],
                'CCA_PAYMENT_CONTACT_TEL' => $_POST['CCA_PAYMENT_CONTACT_TEL'],
                'CCA_PAYMENT_CONTACT_EMAIL' => $_POST['CCA_PAYMENT_CONTACT_EMAIL'],
                'CCA_STATUS' => $_POST['CCA_STATUS'],
                'CCA_CREATEBY' => $_POST['CCA_CREATEBY'],
                'CCA_SALES_REMARK' => $_POST['CCA_SALES_REMARK'],
                'CCA_REQUESTER_ID' => $_POST['CCA_REQUESTER_ID'],
                'CCA_REF_QUOTATION' => $_POST['CCA_REF_QUOTATION'],
                'CCA_REQUEST_ID' => $_POST['CCA_REQUEST_ID'],
                'CCA_PARTNER_CODE' => $_POST['CCA_PARTNER_CODE']
            ];
            // Decode attached file metadata from hidden input
            $attached_files_meta = json_decode($_POST['CCA_ATTACHED_FILES'] ?? [], true);

            // Handle File Uploads
            $uploaded_files = [];
            $upload_dir = __DIR__ . '/../../upload/docs/SDB/CCA/';

            // Create upload dir if not exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Process uploaded files from <input type="file" multiple>
            if (isset($_FILES['FILES']) && !empty($_FILES['FILES']['name'][0])) {
                foreach ($_FILES['FILES']['tmp_name'] as $index => $tmp_file) {
                    $file_name = basename($_FILES['FILES']['name'][$index]);
                    $target_file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($tmp_file, $target_file_path)) {
                        $uploaded_files[] = [
                            'file_name' => $file_name,
                            'file_path' => $url_path . "/upload/docs/SDB/CCA/". $file_name,
                            'comment' => ''
                        ];
                    }
                }
            }

            // Merge uploaded and preloaded files (avoid duplicates by file_name)
            $files_map = [];

            // Existing files (preloaded from hidden input)
            foreach ($attached_files_meta as $file) {
                $file_name = $file['file_name'];

                $files_map[$file_name] = [
                    'file_name' => $file_name,
                    'file_path' => $url_path . "/upload/docs/SDB/CCA/". $file_name,
                    'comment' => $file['comment'] ?? ''
                ];
            }

            // New uploads (overwrite if file_name matches)
            foreach ($uploaded_files as $file) {
                $file_name = $file['file_name'];

                $files_map[$file_name] = [
                    'file_name' => $file_name,
                    'file_path' => $file['file_path'],
                    'comment' => $files_map[$file_name]['comment'] ?? ''
                ];
            }

            // Final file array
            $final_files = array_values($files_map);

            // Store into DB
            $submit_data['CCA_ATTACHED_FILES'] = json_encode($final_files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Insert into database
            $result = $ccaController->submitCCIForm($submit_data);

            if ($result['status'] == 'success') {
                // Notification after submitted form.
                $last_insert_id = $result['last_insert_id'];
                $cca_customer_info = json_decode($_POST['CCA_CUSTOMER_INFO'], true);
                $cca_customer = $cca_customer_info['CUSTOMER_NAME'];
                $requester_name = $ccaController->getUserProfile($_POST['CCA_REQUESTER_ID'])['NAME_TH'];
                $cca_doc_no = $ccaController->getCCAFormDataByField(['CCA_NO'], $last_insert_id);
                $cca_type = $ccaController->getCCAFormDataByField(['CCA_REQUEST_TYPE'], $last_insert_id);
                $cca_land = $ccaController->getCCAFormDataByField(['CCA_LAND'], $last_insert_id);

                $url_type = ($cca_type == 'request') ? 'cca' : 'cci';
                $url_land = ($cca_land == 'INTER') ? '_inter' : '';

                $next_approver_id = $_POST['CCA_REQUESTER_ID'];

                if ($test_mode == true) {
                    $next_approver_id = [26, 99, 208];
                }       

                $dataArray = [];
                $dataArray['receive'] = $next_approver_id; // Receiver 
                $dataArray['sender'] = $_POST['CCA_CREATEBY']; // Requester
                $dataArray['url'] = "{$url_path}/admin/{$url_type}_ed{$url_land}.php?cca_id={$last_insert_id}"; // URL
                $dataArray['type_title'] = "Status: [Create Document]" . ' - ' . date('Y-m-d H:i:s'); // Form Status
                $dataArray['message'] = "การอนุมัติเครดิต - ถึง " . $requester_name; // Message
                $dataArray['type_ref'] = "CCA"; // Form Type
                $dataArray['ref_No'] = $cca_doc_no; // Doc no.
                $dataArray['customer'] = $cca_customer; // Customer Company
                SendNotiSubmitForm($dbConnect, $dataArray);

                echo json_encode([
                    "status" => "success",
                    "message" => "CCI form submitted successfully",
                    "uploaded_files" => $uploaded_files
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Failed to insert CCI record",
                    'sql' => $result['sql']
                ]);
            }
        } 
        if ($action == 'edit_cci_form') {
            // Prepare data for update
            $update_data = [
                'CCA_ID' => $_POST['CCA_ID'],
                'CCA_STATUS' => $_POST['CCA_STATUS'],
                'CCA_COMPANY_SUB_ID' => $_POST['CCA_COMPANY_SUB_ID'],
                'CCA_CUSTOMER_INFO' => $_POST['CCA_CUSTOMER_INFO'],
                'CCA_MONTHLY_PURCHASE_AMOUNT' => (float)$_POST['CCA_MONTHLY_PURCHASE_AMOUNT'],
                'CCA_CREDIT_LIMIT_AMOUNT' => (float)$_POST['CCA_CREDIT_LIMIT_AMOUNT'],
                'CCA_CREDIT_LIMIT_AMOUNT_ADD' => (float)$_POST['CCA_CREDIT_LIMIT_AMOUNT_ADD'],
                'CCA_CREDIT_LIMIT_AMOUNT_TOTAL' => (float)$_POST['CCA_CREDIT_LIMIT_AMOUNT_TOTAL'],
                'CCA_CREDIT_TERM_DAYS' => (int)$_POST['CCA_CREDIT_TERM_DAYS'],
                'CCA_CREDIT_EXCHANGE_RATE' => (float)$_POST['CCA_CREDIT_EXCHANGE_RATE'],
                'CCA_CREDIT_TERM_CUSTOM' => $_POST['CCA_CREDIT_TERM_CUSTOM'] ?? [],
                'CCA_BILLING_DUE_DATE' => $_POST['CCA_BILLING_DUE_DATE'],
                'CCA_BILLING_CONTACT_NAME' => $_POST['CCA_BILLING_CONTACT_NAME'],
                'CCA_TERM_CALCULATION_METHOD' => $_POST['CCA_TERM_CALCULATION_METHOD'],
                'CCA_OTHER_METHOD' => $_POST['CCA_OTHER_METHOD'],
                'CCA_PAYMENT_METHOD' => $_POST['CCA_PAYMENT_METHOD'],
                'CCA_PAYMENT_DUE_DATE' => $_POST['CCA_PAYMENT_DUE_DATE'],
                'CCA_PAYMENT_CONTACT_PERSON' => $_POST['CCA_PAYMENT_CONTACT_PERSON'],
                'CCA_PAYMENT_CONTACT_TEL' => $_POST['CCA_PAYMENT_CONTACT_TEL'],
                'CCA_PAYMENT_CONTACT_EMAIL' => $_POST['CCA_PAYMENT_CONTACT_EMAIL'],
                'CCA_SALES_REMARK' => $_POST['CCA_SALES_REMARK'],
                'CCA_FN_REMARK' => $_POST['CCA_FN_REMARK'],
                'CCA_REQUESTER_ID' => $_POST['CCA_REQUESTER_ID'],
                'CCA_APPROVAL_WORKFLOW' => $_POST['CCA_APPROVAL_WORKFLOW'] ?? [],
                'CCA_REF_QUOTATION' => $_POST['CCA_REF_QUOTATION']
            ];

            $attached_files_meta = json_decode($_POST['CCA_ATTACHED_FILES'], true); // safe decode

            // Directory config
            $upload_dir = __DIR__ . '/../../upload/docs/SDB/CCA/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); // recursive creation
            }

            // Handle uploaded files
            $uploaded_files = [];
            if (isset($_FILES['FILES']) && !empty($_FILES['FILES']['name'][0])) {
                foreach ($_FILES['FILES']['tmp_name'] as $index => $tmp_file) {
                    $file_name = basename($_FILES['FILES']['name'][$index]);
                    $target_file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($tmp_file, $target_file_path)) {
                        $uploaded_files[] = [
                            'file_name' => $file_name,
                            'file_path' => $url_path . "/upload/docs/SDB/CCA/". $file_name,
                            'comment' => ''
                        ];
                    }
                }
            }

            // Merge uploaded files with existing (preloaded) ones
            $files_map = [];

            // Add existing metadata (from hidden input)
            foreach ($attached_files_meta as $file) {
                $file_name = $file['file_name'];
                $files_map[$file_name] = [
                    'file_name' => $file_name,
                    'file_path' => $url_path . "/upload/docs/SDB/CCA/". $file_name,
                    'comment' => $file['comment'] ?? ''
                ];
            }

            // Add uploaded files (overwrite or add)
            foreach ($uploaded_files as $file) {
                $file_name = $file['file_name'];

                // If file exists in map, preserve comment/display_path
                $files_map[$file_name] = [
                    'file_name' => $file_name,
                    'file_path' => $file['file_path'],
                    'comment' => $files_map[$file_name]['comment'] ?? ''
                ];
            }

            // Final merged file list
            $merged_files = array_values($files_map);

            // Save to DB
            $update_data['CCA_ATTACHED_FILES'] = json_encode($merged_files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Update the database
            $result = $ccaController->editCCIForm($update_data);

            if ($result['status'] == 'success') {
                echo json_encode([
                    "status" => "success",
                    "message" => "CCI form updated successfully"
                ]);
            } else {
                echo json_encode([
                    "status" => "error",
                    "message" => "Failed to update CCI record",
                    'sql' => $result['sql']
                ]);
            }
        }      
        if ($action == 'search_qt') {
            $ref_qt = $_POST['ref_qt'];
            $quotation = $ccaController->searchQuotation($ref_qt);;

            echo json_encode(
                [
                    'success' => true,
                    'data' =>  $quotation
                ]
            );
        }
    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage(),
            "error_details" => $e->getTraceAsString() // Remove in production
        ]);
    }
}
?>