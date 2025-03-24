<?php

defined('BASEPATH') or exit('No direct script access allowed');


$aColumns = [
    db_prefix() . 'pur_estimates.number',
    db_prefix() . 'pur_estimates.total',
    db_prefix() . 'pur_estimates.total_tax',
    'YEAR(date) as year',
    db_prefix() . 'pur_estimates.clientid',
    db_prefix() . 'pur_estimates.project_id',
    db_prefix() . 'projects.name',
    'date',
    'expirydate',

    db_prefix() . 'pur_estimates.status',
    ];

$join = [
    'LEFT JOIN ' . db_prefix() . 'currencies ON ' . db_prefix() . 'currencies.id = ' . db_prefix() . 'pur_estimates.currency',
    'LEFT JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'pur_estimates.clientid',
    'LEFT JOIN ' . db_prefix() . 'projects ON ' . db_prefix() . 'projects.id = ' . db_prefix() . 'pur_estimates.project_id',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'pur_estimates';


$where  = [];

$pur_request = $this->ci->input->post('project_id');
if (isset($pur_request)) {
    $where_pur_request = '';
    foreach ($pur_request as $request) {
        if ($request != '') {
            if ($where_pur_request == '') {
                $where_pur_request .= ' AND (project_id = "' . $request . '"';
            } else {
                $where_pur_request .= ' or project_id = "' . $request . '"';
            }
        }
    }
    if ($where_pur_request != '') {
        $where_pur_request .= ')';
        array_push($where, $where_pur_request);
    }
}

$clientid = $this->ci->input->post('clientid');
if (isset($clientid)) {
    $where_client = '';
    foreach ($clientid as $ven) {
        if ($ven != '') {
            if ($where_client == '') {
                $where_client .= ' AND (clientid = ' . $ven . '';
            } else {
                $where_client .= ' or clientid = ' . $ven . '';
            }
        }
    }
    if ($where_client != '') {
        $where_client .= ')';
        array_push($where, $where_client);
    }
}

if(isset($clientid)){
    array_push($where, ' AND '.db_prefix().'pur_estimates.clientid = '.$clientid);
}

// if(!has_permission('purchase_quotations', '', 'view')){
//     array_push($where, 'AND (' . db_prefix() . 'pur_estimates.addedfrom = '.get_staff_user_id().' OR ' . db_prefix() . 'pur_estimates.buyer = '.get_staff_user_id().' OR ' . db_prefix() . 'pur_estimates.clientid IN (SELECT vendor_id FROM ' . db_prefix() . 'pur_vendor_admin WHERE staff_id=' . get_staff_user_id() . ') OR '.get_staff_user_id().' IN (SELECT staffid FROM ' . db_prefix() . 'pur_approval_details WHERE ' . db_prefix() . 'pur_approval_details.rel_type = "pur_quotation" AND ' . db_prefix() . 'pur_approval_details.rel_id = '.db_prefix().'pur_estimates.id))');
// }

$filter = [];


$aColumns = hooks()->apply_filters('estimates_table_sql_columns', $aColumns);


$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    db_prefix() . 'pur_estimates.id',
    db_prefix() . 'pur_estimates.clientid',
    db_prefix() . 'pur_estimates.invoiceid',
    db_prefix() . 'currencies.name as currency_name',
    'project_id',
    'deleted_customer_name',
    db_prefix() . 'pur_estimates.currency',
    'company',
    'tblprojects.name',
    // 'pur_rq_code'
]);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    $base_currency = get_base_currency_pur();

    if($aRow['currency'] != 0){
        $base_currency = pur_get_currency_by_id($aRow['currency']);
    }

    $numberOutput = '';
    // If is from client area table or projects area request
    
    $numberOutput = '<a href="' . admin_url('purchase/quotations/' . $aRow['id']) . '" onclick="init_pur_estimate(' . $aRow['id'] . '); return false;">' . format_pur_estimate_number($aRow['id']) . '</a>';

    

    $numberOutput .= '<div class="row-options">';

    if (has_permission('purchase_quotations', '', 'view') || has_permission('purchase_quotations', '', 'view_own')) {
        $numberOutput .= ' <a href="' . admin_url('purchase/quotations/' . $aRow['id']) . '" onclick="init_pur_estimate(' . $aRow['id'] . '); return false;">' . _l('view') . '</a>';
    }
    if ( (has_permission('purchase_quotations', '', 'edit') || is_admin()) && $aRow[db_prefix() . 'pur_estimates.status'] != 2) {
        $numberOutput .= ' | <a href="' . admin_url('purchase/estimate/' . $aRow['id']) . '">' . _l('edit') . '</a>';
    }
    if (has_permission('purchase_quotations', '', 'delete') || is_admin()) {
        $numberOutput .= ' | <a href="' . admin_url('purchase/delete_estimate/' . $aRow['id']) . '" class="text-danger">' . _l('delete') . '</a>';
    }
    $numberOutput .= '</div>';

    $row[] = $numberOutput;

    $amount = app_format_money($aRow[db_prefix() . 'pur_estimates.total'], $base_currency->symbol);

    if ($aRow['invoiceid']) {
        $amount .= '<br /><span class="hide"> - </span><span class="text-success">' . _l('estimate_invoiced') . '</span>';
    }

    $row[] = $amount;

    $row[] = app_format_money($aRow[db_prefix() . 'pur_estimates.total_tax'], $base_currency->symbol);

    $row[] = $aRow['year'];

    if (empty($aRow['deleted_customer_name'])) {
        $row[] = '<a href="' . admin_url('clients/client/' . $aRow['clientid']) . '" >' .  $aRow['company'] . '</a>';
    } else {
        $row[] = $aRow['deleted_customer_name'];
    }

    $row[] = '<a href="' . admin_url('projects/view/' . $aRow['project_id']) . '" onclick="init_pur_estimate(' . $aRow['id'] . '); return false;">' . $aRow['tblprojects.name'] .'</a>' ;

   

    $row[] = _d($aRow['date']);

    $row[] = _d($aRow['expirydate']);



    $row[] = get_status_approve($aRow[db_prefix() . 'pur_estimates.status']);


    $row['DT_RowClass'] = 'has-row-options';

    $row = hooks()->apply_filters('estimates_table_row_data', $row, $aRow);

    $output['aaData'][] = $row;
}

echo json_encode($output);
die();
