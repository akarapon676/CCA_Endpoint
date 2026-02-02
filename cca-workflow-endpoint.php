<?php
// Include your class and dependencies
// Server URL path
$protocol = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https://" : "http://";
$url_path = $protocol . $_SERVER["HTTP_HOST"];

// Assuming you have PDO $db connection already setup:
$cca_id = $_POST['cca_id'] ?? null;

// Using Controller
$controller = new CCAWorkflowController($pdo, $cca_id);
// Dingtalk API configuration
$test_mode = true;

// POST Data submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Action from POST
    $action = $_POST['action'] ?? 'draw_workflow_sequence';
    $type = $_POST['type'] ?? 'request';
    $com = $_POST['com'] ?? 'SDB';

    // You might also want to get amount and term for condition checks (adjust as needed)
    $amount = $_POST['amount'];
    $term = $_POST['term'] ?? 999;
    $add = $_POST['add'];
    $exchange_rate = $_POST['exchange_rate'] ?? 1;
    $total = (isset($_POST['total'])) ? $_POST['total'] * $exchange_rate : $amount * $exchange_rate;
    $custom = $_POST['custom'] ?? [];
    $fn_remark = $_POST['fn_remark'] ?? '';
    $approval_workflow = [];

    // Define your conditions similar to old code
    $condition1 = ($total <= 1000000 && ($term >= 0 && $term <= 30));
    $condition2 = ($total <= 5000000 && ($term >= 0 && $term <= 30));
    $condition3 = (($total <= 5000000 && ($term >= 30 && $term <= 90)) || ($total <= 50000000 && ($term > 0 && $term <= 90)));
    $condition4 = ($total > 50000000 && ($term >= 0 && $term <= 90));

    // Retrieve role conditions from the controller
    $fn_director_skip_condition = $controller->checkApproverRoleState('is_skip', 'FnDirector');
    $fn_director_action_condition = $controller->checkApproverRoleState('is_action', 'FnDirector');
    $acc_manager_skip_condition = $controller->checkApproverRoleState('is_skip', 'AccManager');
    $acc_manager_login_condition = $controller->checkApproverRoleState('is_login', 'AccManager');
    $acc_manager_action_condition = $controller->checkApproverRoleState('is_action', 'AccManager');
    $admin_login_condition = $controller->checkApproverRoleState('is_login', 'Admin');
    $admin_action_condition = $controller->checkApproverRoleState('is_action', 'Admin');
    $sale_head_login_condition = $controller->checkApproverRoleState('is_login', 'SaleHead'); 
    $sale_head_action_condition = $controller->checkApproverRoleState('is_action', 'SaleHead'); 

    $admin_id = 109;
    $sale_head_id = 138;
    // Workflow condition for Admin (GA) role
    if ($admin_login_condition) {
        $admin_id = $_SESSION['SDT_ID'];
    }
    if ($admin_action_condition) {
        $admin_list = $controller->getAdminList();
        $admin_id_list = [];
        foreach ($admin_list as $admin) {
            $admin_id_list[] = $admin['SDT_ID'];
        }
        foreach ($controller->workflow_transaction as $data) {
            if (in_array($data['approver_id'], $admin_id_list)) {
                $admin_id = $data['approver_id'];
                break; 
            }
        }
    }
    // Workflow condition for Head of sale role
    if ($sale_head_login_condition) {
        $sale_head_id = $_SESSION['SDT_ID'];
    }
    if ($sale_head_action_condition) {
        $sale_head_id_list = [138, 150];
        foreach ($controller->workflow_transaction as $data) {
            if (in_array($data['approver_id'], $sale_head_id_list)) {
                $sale_head_id = $data['approver_id'];
                break; 
            }
        }
    }
    
    $_approver_id_list = [];
    $_approver_btn_list = [];

    // Create Workflow for Normal credit term conditions
    if ($condition1) { 
        $_approver_id_list = [$sale_head_id, $admin_id, 88];
        $_approver_btn_list = [
            [['Verify', 'Return', 'Reject']],
            [['Review', 'Return']],
            [['Approve', 'Return', 'Skip', 'Reject']]
        ];
    } else 
    if ($condition2) { 
        $_approver_id_list = [$sale_head_id, $admin_id, 88, 9];
        $_approver_btn_list = [
            [['Verify', 'Return', 'Reject']],
            [['Review', 'Return']],
            [['Review', 'Return', 'Skip', 'Reject']],
            [[]]
        ];
    } else 
    if ($condition3) {
        $_approver_id_list = [$sale_head_id, $admin_id, 88, 4];
        $_approver_btn_list = [
            [['Verify', 'Return', 'Reject']],
            [['Review', 'Return']],
            [['Review', 'Return', 'Skip', 'Reject']],
            [[]]
        ];
    } else 
    if ($condition4) {
        $_approver_id_list = [$sale_head_id, $admin_id, 88, [4, 9, 20, 5]];
        $_approver_btn_list = [
            [['Verify', 'Return', 'Reject']],
            [['Review', 'Return']],
            [['Review', 'Return', 'Skip', 'Reject']],
            [[], [], [], []]
        ];
    }
    // Create Workflow for additional override conditions based on role states
    if ($fn_director_skip_condition || $acc_manager_skip_condition) {
        $_approver_id_list = ($acc_manager_skip_condition) ? [$sale_head_id, $admin_id, 100, 4] : [$sale_head_id, $admin_id, 88, 4];
        $_approver_btn_list = [
            [['Verify', 'Return', 'Reject']],
            [['Review', 'Return']],
            [['Review', 'Return', 'Skip', 'Reject']],
            [[]]
        ];
    } else 
    if (($acc_manager_login_condition || $acc_manager_action_condition) && !$fn_director_action_condition) {
        if ($condition1 || $condition2) {
            $_approver_id_list = [$sale_head_id, $admin_id, 100, 9];
            $_approver_btn_list = [
                [['Verify', 'Return', 'Reject']],
                [['Review', 'Return']],
                [['Review', 'Return', 'Skip', 'Reject']],
                [[]]
            ];
        } else if ($condition3) {
            $_approver_id_list = [$sale_head_id, $admin_id, 100, 4];
            $_approver_btn_list = [
                [['Verify', 'Return', 'Reject']],
                [['Review', 'Return']],
                [['Review', 'Return', 'Skip', 'Reject']],
                [[]]
            ];
        } else if ($condition4) {
            $_approver_id_list = [$sale_head_id, $admin_id, 100, [4, 9, 20, 5]];
            $_approver_btn_list = [
                [['Verify', 'Return', 'Reject']],
                [['Review', 'Return']],
                [['Review', 'Return', 'Skip', 'Reject']],
                [[], [], [], []]
            ];
        }
    }
    // Create approval workflow based on various conditions 
    $approval_workflow = $controller->createApprovalWorkflow($_approver_id_list, $_approver_btn_list);
    
    // Use array_map to remove 'btn_list' from each approver
    $btn_removed_workflow = array_map(function ($data) {
        $data['approvers'] = array_map(function ($approver) {
            unset($approver['btn_list']);
            return $approver;
        }, $data['approvers']);
        return $data;
    }, $approval_workflow);

    // print_r ($btn_removed_workflow); // This one has 4 seq
    
    // HTTP Request action(s) : ======================================================================================
    if ($action == 'draw_workflow_sequence') {
        echo json_encode([
            'status' => 'success',
            'workflow' => $approval_workflow
        ]);
        exit;
    }
    if ($action == 'submit_for_approval') {
        $fields = [
            'CCA_STATUS' => 'Approval',
            'CCA_WORKFLOW_SEQ' => 1
        ];
        $controller->editCCAFormByField($fields, $cca_id);
    
        // Notification after submitted for approval.
        $cca_customer_info = json_decode($controller->getCCAFormDataByField(['CCA_CUSTOMER_INFO'], $cca_id), true);
        $cca_customer = $cca_customer_info['CUSTOMER_NAME'];
        $cca_doc_no = $controller->getCCAFormDataByField(['CCA_NO'], $cca_id);
        $cca_type = $controller->getCCAFormDataByField(['CCA_REQUEST_TYPE'], $cca_id);
        $cca_land = $controller->getCCAFormDataByField(['CCA_LAND'], $cca_id);
        $cca_requester = $controller->getCCAFormDataByField(['CCA_REQUESTER_ID'], $cca_id);
        $cca_created_by = $controller->getCCAFormDataByField(['CCA_CREATEBY'], $cca_id);

        $url_type = ($cca_type == 'request') ? 'cca' : 'cci';
        $url_land = ($cca_land == 'INTER') ? '_inter' : '';

        $next_approver_id = [$sale_head_id];
        // Add requester id and creator id
        $next_approver_id[] = $cca_requester;
        if ($cca_requester != $cca_created_by) {
            $next_approver_id[] = $cca_created_by;
        }
        $next_approver_name = $controller->getApproverName($sale_head_id);
        if ($test_mode == true) {
            $next_approver_id = [26, 99, 208];
        }

        $dataArray = [];
        $dataArray['receive'] = $next_approver_id; // Receiver 
        $dataArray['sender'] = $cca_requester; // Requester
        $dataArray['url'] = "{$url_path}/admin/{$url_type}_ed{$url_land}.php?cca_id={$cca_id}"; // URL
        $dataArray['type_title'] = "Status: [Submit for Approval]" . ' - ' . date('Y-m-d H:i:s'); // Form Status
        $dataArray['message'] = "การอนุมัติเครดิต - ถึง " . $next_approver_name; // Message
        $dataArray['type_ref'] = "CCA"; // Form Type
        $dataArray['ref_No'] = $cca_doc_no; // Doc no.
        $dataArray['customer'] = $cca_customer; // Customer Company
        SendNotiSubmitForm($dbConnect, $dataArray);
        
        echo json_encode([
            'status' => 'success',
            'json' => json_encode($btn_removed_workflow, true)
        ]);
        exit;
    }
    if ($action == 'workflow_action') {
        $sequence_action = $_POST['sequence_action'] ?? '';
        $sequence_comment = $_POST['sequence_comment'] ?? '';
        $sequence_approver = (int)($_POST['sequence_approver'] ?? 0);
        $sequence_seq = (int)($_POST['sequence_seq'] ?? 2);

        if ($sequence_action && $sequence_approver) {
            // Transaction update for approver action
            $controller->updateWorkflowTransaction($sequence_action, $sequence_comment, $sequence_approver, $sequence_seq);
     
            if ($sequence_action == 'Skip') {
                $_approver_id_list = [$sale_head_id, $admin_id, $sequence_approver, 4];
                $_approver_btn_list = [
                    [['Verify', 'Return', 'Reject']],
                    [['Review', 'Return']],
                    [['Review', 'Return', 'Skip', 'Reject']],
                    [[]]
                ];
            }

            $approval_workflow = $controller->createApprovalWorkflow($_approver_id_list, $_approver_btn_list);
            // Use array_map to remove 'btn_list' from each approver
            $btn_removed_workflow = array_map(function ($data) {
                $data['approvers'] = array_map(function ($approver) {
                    unset($approver['btn_list']);
                    return $approver;
                }, $data['approvers']);
                return $data;
            }, $approval_workflow); 
            // echo "XXX";

            $fields = [
                'CCA_CREDIT_LIMIT_AMOUNT_FINAL' => $amount,
                'CCA_CREDIT_TERM_DAYS_FINAL' => $term,
                'CCA_APPROVAL_WORKFLOW' => json_encode($btn_removed_workflow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ];
            if ($type == 'increase') {
                $fields['CCA_CREDIT_LIMIT_AMOUNT_ADD_FINAL'] = $add;
                $fields['CCA_CREDIT_LIMIT_AMOUNT_TOTAL_FINAL'] = $_POST['total'];
            }
            if (!empty($custom)) {
                $fields['CCA_CREDIT_TERM_CUSTOM'] = $custom;
            }
            if (!empty($fn_remark)) {
                $fields['CCA_FN_REMARK'] = $fn_remark;
            }
            if (!empty($_POST['credit_counting'])) {
                $fields['CCA_TERM_CALCULATION_METHOD_FINAL'] = $_POST['credit_counting'];
            }
            if (!empty($_POST['counting_other'])) {
                $fields['CCA_OTHER_METHOD_FINAL'] = $_POST['counting_other'];
            }

            $controller->editCCAFormByField($fields, $cca_id);
            
            // Notification after approver action.
            $cca_customer_info = json_decode($controller->getCCAFormDataByField(['CCA_CUSTOMER_INFO'], $cca_id), true);
            $cca_customer = $cca_customer_info['CUSTOMER_NAME'];
            $cca_doc_no = $controller->getCCAFormDataByField(['CCA_NO'], $cca_id);
            $cca_type = $controller->getCCAFormDataByField(['CCA_REQUEST_TYPE'], $cca_id);
            $cca_land = $controller->getCCAFormDataByField(['CCA_LAND'], $cca_id);
            $cca_seq = $controller->getCCAFormDataByField(['CCA_WORKFLOW_SEQ'], $cca_id);
            $cca_requester = $controller->getCCAFormDataByField(['CCA_REQUESTER_ID'], $cca_id);
            $cca_created_by = $controller->getCCAFormDataByField(['CCA_CREATEBY'], $cca_id);
            $cca_partner_request_id = $controller->getCCAFormDataByField(['CCA_PARTNER_REQUEST_ID'], $cca_id);
            $cca_partner_code = $controller->getCCAFormDataByField(['CCA_PARTNER_CODE'], $cca_id);

            $url_type = ($cca_type == 'request') ? 'cca' : 'cci';
            $url_land = ($cca_land == 'INTER') ? '_inter' : '';

            $next_approver_id = [$_approver_id_list[$cca_seq - 1]];
            $next_approver_name = $controller->getApproverName($next_approver_id);

            if ($sequence_action == 'Skip') {
                $next_approver_id = [4];
                $next_approver_name = $controller->getApproverName(4);
            }
            if ($sequence_action == 'Verify') {
                // $next_approver_id = [7, 26, 41, 94, 97, 109, 163, 164, 166, 168];
                // $next_approver_id = [94, 97, 109, 166];
                $next_approver_id = $controller->getAdminList();
                $next_approver_name = 'ทีม GA ที่เกี่ยวข้อง';
            }
            if ($sequence_action == 'Approve' || $sequence_action == 'Reject' || $sequence_action == 'Return') {
                $next_approver_id = $_approver_id_list;
                $next_approver_name = 'ทุกคนที่เกี่ยวข้อง';

                if ($cca_partner_code != '') {
                    // 1. Define the variables
                    $url = 'http://partner.dev.softdebut.com/integrations/debut/v1/credit-decisions';

                    $partner_final_amount = ($cca_type == 'increase') ? $_POST['total'] : $amount;

                    $requestBody = [
                        'partner_code' => $cca_partner_code,
                        'credit_status' => $controller->convertToPastSimple($sequence_action),
                        'credit_limit' => $partner_final_amount,
                        // 'previous_credit_limit' => null, 
                        'credit_term_days' => $term,
                        // 'credit_term_special' => '',
                        'request_id' => $cca_partner_request_id
                    ];

                    // Encode the PHP array into a JSON string for the body
                    $jsonData = json_encode($requestBody);

                    // Your API Key
                    $apiKey = 'spc-342342pfamg3434g34f3555bff';

                    // 2. Initialize cURL
                    $ch = curl_init();

                    // 3. Set cURL options
                    curl_setopt($ch, CURLOPT_URL, $url);
                    // Set the request method to POST
                    curl_setopt($ch, CURLOPT_POST, true);
                    // Attach the JSON data to the request body
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                    // Return the response as a string instead of outputting it
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    // Set the necessary headers
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        // Add the X-API-KEY header
                        "X-API-KEY: $apiKey",
                        // Optional but good practice: set Content-Length
                        'Content-Length: ' . strlen($jsonData)
                    ]);

                    // 4. Execute the request
                    $response = curl_exec($ch);
                }

                
            }
            // Add requester id and creator id
            $next_approver_id[] = $cca_requester;
            if ($cca_requester != $cca_created_by) {
                $next_approver_id[] = $cca_created_by;
            }
            
            if ($test_mode == true) {
                $next_approver_id = [26, 99, 208];
            }            

            $dataArray = [];
            $dataArray['receive'] = $next_approver_id; // Receiver 
            $dataArray['sender'] = $sequence_approver; // Requester
            $dataArray['url'] = "{$url_path}/admin/{$url_type}_ed{$url_land}.php?cca_id={$cca_id}"; // URL
            $dataArray['type_title'] = "Status: [".$controller->convertToPastSimple($sequence_action)."]" . ' - ' . date('Y-m-d H:i:s'); // Form Status
            $dataArray['message'] = "การอนุมัติเครดิต - ถึง {$next_approver_name}"; // Message
            $dataArray['type_ref'] = "CCA"; // Form Type
            $dataArray['ref_No'] = $cca_doc_no; // Doc no.
            $dataArray['customer'] = $cca_customer; // Customer Company
            SendNotiSubmitForm($dbConnect, $dataArray);

            echo json_encode([
                'status' => 'success',
                'action' => $sequence_action,
                'approver_id' => $sequence_approver
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        }
        exit;
    }
    if ($action == 'follow_up') {
        $cc_to = $_POST['cc_to'];
        $cc_from = $_POST['cc_from'];

        // Notification after approver action.
        $cca_customer_info = json_decode($controller->getCCAFormDataByField(['CCA_CUSTOMER_INFO'], $cca_id), true);
        $cca_customer = htmlspecialchars($cca_customer_info['CUSTOMER_NAME']);
        $cca_doc_no = htmlspecialchars($controller->getCCAFormDataByField(['CCA_NO'], $cca_id));
        $cca_type = htmlspecialchars($controller->getCCAFormDataByField(['CCA_REQUEST_TYPE'], $cca_id));
        $cca_land = htmlspecialchars($controller->getCCAFormDataByField(['CCA_LAND'], $cca_id));
        $cca_seq = htmlspecialchars($controller->getCCAFormDataByField(['CCA_WORKFLOW_SEQ'], $cca_id));
        $cca_requester = htmlspecialchars($controller->getCCAFormDataByField(['CCA_REQUESTER_ID'], $cca_id));
        $cca_created_by = htmlspecialchars($controller->getCCAFormDataByField(['CCA_CREATEBY'], $cca_id));

        $url_type = ($cca_type == 'request') ? 'cca' : 'cci';
        $url_land = ($cca_land == 'INTER') ? '_inter' : '';

        $cc_to_name = $controller->getApproverName($cc_to);

        $dataArray = [];
        $dataArray['receive'] = $cc_to; // Receiver 
        $dataArray['sender'] = $cc_from; // Requester
        $dataArray['url'] = "{$url_path}/admin/{$url_type}_ed{$url_land}.php?cca_id={$cca_id}"; // URL
        $dataArray['type_title'] = "Status: [Follow Up]" . ' - ' . date('Y-m-d H:i:s'); // Form Status
        $dataArray['message'] = "การอนุมัติเครดิต - ถึง {$cc_to_name}"; // Message
        $dataArray['type_ref'] = "CCA"; // Form Type
        $dataArray['ref_No'] = $cca_doc_no; // Doc no.
        $dataArray['customer'] = $cca_customer; // Customer Company
        SendNotiSubmitForm($dbConnect, $dataArray);

        echo json_encode([
            'status' => 'success',
            'action' => 'follow up'
        ]);
        exit;
    }
    
    // Fallback for unknown action
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}
?>