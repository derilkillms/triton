<?php defined('BASEPATH') or exit('No direct script access allowed');

// $setting_staff = json_decode($get_setting,true);
// print_r($get_setting);
// echo $get_setting;
?>
<style>
    .signature-container {
        position: relative;
        display: inline-block; /* Menyesuaikan ukuran dengan gambar */
    }

    .signature-date {
        position: absolute;
        top: 10px; /* Atur posisi teks ke atas */
        opacity: 65%;
        left: 50%;
        width: 100%;
        transform: translateX(-50%); /* Tengah horizontal */
        color: rgba(0, 0, 0, 0.7); /* Warna hitam dengan transparansi */
        font-weight: bold;
        background: rgba(255, 255, 255, 0.5); /* Latar belakang putih semi transparan */
        padding: 5px 10px;
        border-radius: 5px;
        z-index: 10; /* Pastikan berada di atas gambar */
    }

    .img_style {
        display: block;
        width: 100%; /* Sesuaikan ukuran gambar */
        height: auto;
    }
</style>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <?php $csrf = array(
              'name' => $this->security->get_csrf_token_name(),
              'hash' => $this->security->get_csrf_hash(),
            );
            ?>

            <input type="hidden" id="csrf_token_name" name="<?= $csrf['name']; ?>" value="<?= $csrf['hash']; ?>" />
            <?php if ($pur_request->currency != 0) {

              $base_currency = pur_get_currency_by_id($pur_request->currency);
            } else {
              $base_currency = $base_currency;
            } ?>
            <?php if ($pur_request->status == 1) { ?>
              <div class="ribbon info"><span class="fontz9"><?php echo _l('purchase_draft'); ?></span></div>
            <?php } elseif ($pur_request->status == 2) { ?>
              <div class="ribbon success"><span><?php echo _l('purchase_approved'); ?></span></div>
            <?php } elseif ($pur_request->status == 3) { ?>
              <div class="ribbon danger"><span><?php echo _l('purchase_reject'); ?></span></div>
            <?php } elseif ($pur_request->status == 0) { ?>
              <div class="ribbon danger"><span>Void</span></div>
            <?php } ?>
            <h4 class=""><?php echo _l($title); ?>
            </h4>
            <div class="row">
              <div class="horizontal-scrollable-tabs preview-tabs-top">
                <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                <div class="horizontal-tabs">
                  <ul class="nav nav-tabs nav-tabs-horizontal mbot15" role="tablist">
                    <li role="presentation" class="<?php if ($this->input->get('tab') != 'attachment') {
                                                      echo 'active';
                                                    } ?>">
                      <a href="#information" aria-controls="information" role="tab" data-toggle="tab">
                        <?php echo _l('pur_information'); ?>
                      </a>
                    </li>

                    <li role="presentation" class="<?php if ($this->input->get('tab') == 'attachment') {
                                                      echo 'active';
                                                    } ?> ">
                      <a href="#attachment" aria-controls="attachment" role="tab" data-toggle="tab">
                        <?php echo _l('pur_attachment'); ?>
                      </a>
                    </li>

                    <li role="presentation" class="<?php if ($this->input->get('tab') == 'attachment') {
                                                      echo 'active';
                                                    } ?> ">
                      <?php
                      $totalComments = total_rows(db_prefix() . 'pur_comments', ['rel_id' => $pur_request->id, 'rel_type' => 'pur_request']);
                      ?>
                      <a href="#discuss" aria-controls="discuss" role="tab" data-toggle="tab">
                        <?php echo _l('pur_discuss'); ?>
                        <span class="badge comments-indicator<?php echo $totalComments == 0 ? ' hide' : ''; ?>"><?php echo $totalComments; ?></span>
                      </a>
                    </li>

                    <?php
                    //  $quotations = get_quotations_by_pur_request($pur_request->id); 
                     ?>
                    <li role="presentation" class="">
                      <a href="#compare_quotes" aria-controls="compare_quotes" role="tab" data-toggle="tab">
                        <?php 
                        // echo _l('compare_quotes') . '(' . count($quotations) . ')'; ?>
                      </a>
                    </li>

                  </ul>
                </div>
              </div>
              <div class="tab-content">
                <div role="tabpanel" class="tab-pane ptop10 <?php if ($this->input->get('tab') != 'attachment') {
                                                              echo 'active';
                                                            } ?>" id="information">

                  <div class="row">
                    <div class="col-md-12">
                      <p class="bold col-md-9 p_style"><?php echo _l('information'); ?></p>
                      <div class="col-md-3 pull-right">
                        <div class="task-info task-status task-info-status pull-right">
                          <h5>
                            <i class="fa fa-<?php if ($pur_request->status == 2) {
                                              echo 'star';
                                            } else if ($pur_request->status == 1) {
                                              echo 'star-o';
                                            } else {
                                              echo 'star-half-o';
                                            } ?> pull-left task-info-icon fa-fw fa-lg mtop10"></i><?php echo _l('task_status'); ?>:
                            <?php if (has_permission('purchase_request_change_approve_status', '', 'edit')) { ?>
                              <span class="task-single-menu task-menu-status">
                                <span class="<?php if (has_access(get_staff_user_id(), $pur_request->department, $get_setting)) {
                                                if ($pur_request->status != 0) {
                                                  echo 'trigger';
                                                }
                                              } ?> pointer manual-popover text-has-action">
                                  <?php echo pur_format_approve_status($pur_request->status, true); ?>
                                </span>

                                <span class="content-menu hide">
                                  <ul>
                                    <?php

                                    for ($pur_status = 1; $pur_status <= 2; $pur_status++) { ?>
                                      <?php if ($pur_request->status != $pur_status) { ?>
                                        <li>
                                          <a href="#" onclick="purchase_request_mark_as(<?php echo $pur_status; ?>,<?php echo $pur_request->id; ?>); return false;">test
                                            <?php echo _l('purchase_request_mark_as', get_status_approve_str($pur_status)); ?>
                                          </a>
                                        </li>
                                      <?php } ?>
                                    <?php } ?>
                                    <li>
                                      <a href="#" onclick="purchase_request_mark_as(0,<?php echo $pur_request->id; ?>); return false;">reject
                                        <?php echo _l('purchase_request_mark_as', get_status_approve_str(3)); ?>
                                      </a>
                                    </li>

                                  </ul>
                                </span>

                              </span>
                            <?php } else { ?>
                              <?php echo pur_format_approve_status($pur_request->status, true); ?>
                            <?php } ?>
                          </h5>
                        </div>
                      </div>
                      <div class=" col-md-12">
                        <hr class="hr_style" />
                      </div>
                    </div>
                  </div>

                  <div class=" col-md-12">
                    <table class="table border table-striped martop0">
                      <tbody>
                        <tr class="project-overview">
                          <td class="bold" width="30%"><?php echo _l('pur_rq_code'); ?></td>
                          <td><?php echo pur_html_entity_decode($pur_request->pur_rq_code); ?></td>
                        </tr>
                        <tr class="project-overview">
                          <td class="bold"><?php echo _l('pur_rq_name'); ?></td>
                          <td><?php echo _l($pur_request->pur_rq_name); ?></td>
                        </tr>
                        <tr class="project-overview">
                          <td class="bold"><?php echo _l('purchase_requestor'); ?></td>
                          <td><?php $_data = '<a href="' . admin_url('staff/profile/' . $pur_request->requester) . '">' . staff_profile_image($pur_request->requester, [
                                'staff-profile-image-small',
                              ]) . '</a>';
                              $_data .= ' <a href="' . admin_url('staff/profile/' . $pur_request->requester) . '">' . get_staff_full_name($pur_request->requester) . '</a>';
                              echo pur_html_entity_decode($_data);
                              ?></td>
                        </tr>

                        <tr class="project-overview">
                          <td class="bold"><?php echo _l('request_date'); ?></td>
                          <td><?php echo _dt($pur_request->request_date); ?></td>
                        </tr>
                        <tr>
                          <td class="bold"><?php echo _l('pdf'); ?></td>
                          <td>
                            <div class="btn-group">
                              <a href="#" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-file-pdf"></i><?php if (is_mobile()) {
                                                                                                                                                                                    echo ' PDF';
                                                                                                                                                                                  } ?> <span class="caret"></span></a>
                              <ul class="dropdown-menu dropdown-menu-right">
                                <li class="hidden-xs"><a href="<?php echo admin_url('purchase/pur_request_pdf/' . $pur_request->id . '?output_type=I'); ?>"><?php echo _l('view_pdf'); ?></a></li>
                                <li class="hidden-xs"><a href="<?php echo admin_url('purchase/pur_request_pdf/' . $pur_request->id . '?output_type=I'); ?>" target="_blank"><?php echo _l('view_pdf_in_new_window'); ?></a></li>
                                <li><a href="<?php echo admin_url('purchase/pur_request_pdf/' . $pur_request->id); ?>"><?php echo _l('download'); ?></a></li>
                              </ul>
                            </div>


                          </td>
                        </tr>
                        <tr class="project-overview">
                          <td class="bold"><?php echo _l('public_link'); ?></td>
                          <td>
                            <div class="pull-right _buttons mright5">
                              <a href="javascript:void(0)" onclick="copy_public_link(<?php echo pur_html_entity_decode($pur_request->id); ?>); return false;" class="btn btn-warning btn-with-tooltip" data-toggle="tooltip" title="<?php echo _l('copy_public_link'); ?>" data-placement="bottom"><i class="fa fa-clone "></i></a>
                            </div>
                            <div class="col-md-9">
                              <?php if ($pur_request->hash != '' && $pur_request->hash != null) {
                                echo render_input('link_public', '', site_url('purchase/vendors_portal/pur_request/' . $pur_request->id . '/' . $pur_request->hash));
                              } else {
                                echo render_input('link_public', '', '');
                              } ?>
                            </div>
                          </td>
                        </tr>
                        <tr class="project-overview">
                          <td class="bold"><?php echo _l('rq_description'); ?></td>
                          <td><?php echo pur_html_entity_decode($pur_request->rq_description); ?></td>
                        </tr>

                      </tbody>
                    </table>
                  </div>





                  <div class="col-md-12">
                    <p class=" p_style"><?php echo _l('pur_detail'); ?></p>
                    <hr class="hr_style" />

                    <div class="table-responsive">
                      <table class="table items items-preview estimate-items-preview" data-type="estimate">
                        <thead>
                          <tr>

                            <th width="25%" align="left"><?php echo _l('debit_note_table_item_heading'); ?></th>
                            <th width="10%" align="right" class="qty"><?php echo _l('purchase_quantity'); ?></th>
                            <th width="10%" align="right"><?php echo _l('unit_price'); ?></th>

                            <th width="10%" align="right"><?php echo _l('subtotal_before_tax'); ?></th>
                            <th width="15%" align="right"><?php echo _l('debit_note_table_tax_heading'); ?></th>
                            <th width="10%" align="right"><?php echo _l('tax_value'); ?></th>
                            <th width="10%" align="right"><?php echo _l('debit_note_total'); ?></th>
                          </tr>
                        </thead>
                        <tbody class="ui-sortable">

                          <?php $_subtotal = 0;
                          $_total = 0;
                          if (count($pur_request_detail) > 0) {
                            $count = 1;
                            $t_mn = 0;
                            foreach ($pur_request_detail as $es) {
                              $_subtotal += $es['into_money'];
                              $_total += $es['total'];
                          ?>
                              <tr nobr="true" class="sortable">

                                <td class="description" align="left;"><span><strong><?php
                                                                                    $item = get_item_hp($es['item_code']);
                                                                                    if (isset($item) && isset($item->commodity_code) && isset($item->description)) {
                                                                                      echo pur_html_entity_decode($item->commodity_code . ' - ' . $item->description);
                                                                                    } else {
                                                                                      echo pur_html_entity_decode($es['item_text']);
                                                                                    }
                                                                                    ?></strong></td>
                                <?php
                                $unit_name = pur_get_unit_name($es['unit_id']);
                                ?>
                                <td align="right" width="12%"><?php echo pur_html_entity_decode($es['quantity']) . ' ' . $unit_name; ?></td>
                                <td align="right"><?php echo app_format_money($es['unit_price'], $base_currency->symbol); ?></td>
                                <td align="right"><?php echo app_format_money($es['into_money'], $base_currency->symbol); ?></td>
                                <td align="right"><?php
                                                  if ($es['tax_name'] != '') {
                                                    echo pur_html_entity_decode($es['tax_name']);
                                                  } else {
                                                    $this->load->model('purchase/purchase_model');
                                                    if ($es['tax'] != '') {
                                                      $tax_arr =  $es['tax'] != '' ? explode('|', $es['tax'] ?? '') : [];
                                                      $tax_str = '';
                                                      if (count($tax_arr) > 0) {
                                                        foreach ($tax_arr as $key => $tax_id) {
                                                          if (($key + 1) < count($tax_arr)) {
                                                            $tax_str .= $this->purchase_model->get_tax_name($tax_id) . '|';
                                                          } else {
                                                            $tax_str .= $this->purchase_model->get_tax_name($tax_id);
                                                          }
                                                        }
                                                      }

                                                      echo pur_html_entity_decode($tax_str);
                                                    }
                                                  }
                                                  ?></td>
                                <td align="right"><?php echo app_format_money($es['tax_value'], $base_currency->symbol); ?></td>

                                <td class="amount" align="right"><?php echo app_format_money($es['total'], $base_currency->symbol); ?></td>
                              </tr>
                          <?php

                            }
                          } ?>
                        </tbody>
                      </table>
                    </div>


                  </div>
                  <div class="col-md-6 col-md-offset-6">
                    <table class="table text-right mbot0">
                      <tbody>
                        <tr id="subtotal">
                          <td class="td_style"><span class="bold"><?php echo _l('subtotal'); ?></span>
                          </td>
                          <td width="65%" id="total_td">

                            <?php echo app_format_money($_subtotal, $base_currency->symbol); ?>
                          </td>
                        </tr>
                      </tbody>
                    </table>

                    <table class="table text-right">
                      <tbody id="tax_area_body">
                        <?php if (isset($pur_request)) {
                          echo $taxes_data['html'];
                        ?>
                        <?php } ?>
                      </tbody>
                    </table>

                    <table class="table text-right">
                      <tbody id="tax_area_body">
                        <tr id="total">
                          <td class="td_style"><span class="bold"><?php echo _l('total'); ?></span>
                          </td>
                          <td width="65%" id="total_td">
                            <?php echo app_format_money($_total, $base_currency->symbol); ?>
                          </td>
                        </tr>
                      </tbody>
                    </table>

                  </div>
                  <!-- start tanda tangan -->
                  <?php echo form_hidden('request_detail'); ?>

                  <div class=" col-md-12">
                    <?php if (count($list_approve_status) > 0) { ?>
                      <p class=" p_style"><?php echo _l('pur_approval_infor'); ?></p>
                      <hr class="hr_style" />
                      <div class="project-overview-right">


                        <div class="row">
                          <div class="col-md-12 project-overview-expenses-finance">
                            <?php
                            $this->load->model('staff_model');
                            $enter_charge_code = 0;
                            foreach ($list_approve_status as $value) {
                              $value['staffid'] = explode(', ', $value['staffid'] ?? '');
                              if ($value['action'] == 'sign') {
                            ?>
                                <div class="col-md-3 apr_div">
                                  <p class="text-uppercase text-muted no-mtop bold">
                                    <?php
                                    $staff_name = '';
                                    $st = _l('status_0');
                                    $color = 'warning';
                                    foreach ($value['staffid'] as $key => $val) {
                                      if ($staff_name != '') {
                                        $staff_name .= ' or ';
                                      }
                                      $staff_name .= isset($this->staff_model->get($val)->firstname) ? $this->staff_model->get($val)->firstname : '';
                                    }
                                    echo pur_html_entity_decode($staff_name);
                                    ?></p>
                                  <?php if ($value['approve'] == 2) {
                                  ?>
                                    <div class="signature-container">
                                      <p class="signature-date bold text-center text-success"><?php echo _l('signed') . ' ' . _dt($value['date']); ?></p>
                                      <img src="<?php echo site_url(PURCHASE_PATH . 'pur_request/signature/' . $pur_request->id . '/signature_' . $value['id'] . '.png'); ?>" class="img_style">
                                    </div>
                                  <?php } ?>

                                </div>
                              <?php } else { ?>
                                <div class="col-md-3 apr_div">
                                  <p class="text-uppercase text-muted no-mtop bold">
                                    <?php
                                    $staff_name = '';
                                    foreach ($value['staffid'] as $key => $val) {
                                      if ($staff_name != '') {
                                        $staff_name .= ' or ';
                                      }
                                      $staff_name .= isset($this->staff_model->get($val)->firstname) ? $this->staff_model->get($val)->firstname : '';
                                    }
                                    echo pur_html_entity_decode($staff_name);
                                    ?></p>
                                     <div class="signature-container">
                                  <?php if ($value['approve'] == 2) {
                                  ?>
                                 
                                  <p class="signature-date bold text-center text-<?php if ($value['approve'] == 2) {
                                                                    echo 'success';
                                                                  } elseif ($value['approve'] == 3) {
                                                                    echo 'danger';
                                                                  } ?>"><?php echo _dt($value['date']); ?></p>
                                    <img src="<?php echo site_url(PURCHASE_PATH . 'approval/approved.png'); ?>" class="img_style">
                                  <?php } elseif ($value['approve'] == 3) { ?>
                                    <img src="<?php echo site_url(PURCHASE_PATH . 'approval/rejected.png'); ?>" class="img_style">
                                  <?php } ?>
                                     </div>
                                  <br><br>
                                  <p><?php echo pur_html_entity_decode($value['note']) ?></p>
                                  
                                  
                                </div>
                            <?php }
                            } ?>
                          </div>
                        </div>


                      </div>
                    <?php } ?>
                    <div class="pull-right">
                      <?php

                      if (has_access(get_staff_user_id(), $pur_request->department, $get_setting)) {
                        if ($check_appr && $check_appr != false) {
                          if ($pur_request->status == 1 && ($check_approve_status == false || $check_approve_status == 'reject')) { ?>
                            <a data-toggle="tooltip" data-loading-text="<?php echo _l('wait_text'); ?>" class="btn btn-success lead-top-btn lead-view" data-placement="top" href="#" onclick="send_request_approve(<?php echo pur_html_entity_decode($pur_request->id); ?>); return false;"><?php echo _l('send_request_approve_pur'); ?></a>
                        <?php }
                        }
                      }
                      if (isset($check_approve_status['staffid'])) {
                        ?>
                        <?php
                        if (in_array(get_staff_user_id(), $check_approve_status['staffid']) && !in_array(get_staff_user_id(), $get_staff_sign) && $pur_request->status == 1) { ?>
                          <div class="btn-group">
                            <a href="#" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><?php echo _l('approve'); ?><span class="caret"></span></a>
                            <ul class="dropdown-menu dropdown-menu-right ul_style">
                              <li>
                                <div class="col-md-12">
                                  <?php echo render_textarea('reason', 'reason'); ?>
                                </div>
                              </li>
                              <li>
                                <div class="row text-right col-md-12">
                                  <a href="#" data-loading-text="<?php echo _l('wait_text'); ?>" onclick="approve_request(<?php echo pur_html_entity_decode($pur_request->id); ?>); return false;" class="btn btn-success mright15"><?php echo _l('approve'); ?></a>
                                  <a href="#" data-loading-text="<?php echo _l('wait_text'); ?>" onclick="deny_request(<?php echo pur_html_entity_decode($pur_request->id); ?>); return false;" class="btn btn-warning"><?php echo _l('deny'); ?></a>
                                </div>
                              </li>
                            </ul>
                          </div>
                        <?php }
                        ?>

                        <?php
                        if (in_array(get_staff_user_id(), $check_approve_status['staffid']) && in_array(get_staff_user_id(), $get_staff_sign)) { ?>
                          <button onclick="accept_action();" class="btn btn-success pull-right action-button"><?php echo _l('e_signature_sign'); ?></button>
                        <?php }
                        ?>
                      <?php
                      }
                      ?>
                    </div>

                  </div>

                </div>
<!-- end tanda tangan -->
                <div role="tabpanel" class="tab-pane  <?php if ($this->input->get('tab') == 'attachment') {
                                                        echo 'active';
                                                      } ?>" id="attachment">
                  <?php echo form_open_multipart(admin_url('purchase/purchase_request_attachment/' . $pur_request->id), array('id' => 'partograph-attachments-upload')); ?>


                  <div class="col-md-12">
                    <?php echo render_input('file', 'pur_file', '', 'file'); ?>
                  </div>
                  <div class="col-md-12">
                    <button id="obgy_btn2" type="submit" class="btn btn-info pull-right"><?php echo _l('submit'); ?></button>
                  </div>
                  <?php echo form_close(); ?>

                  <div class="col-md-12" id="purrequest_pv_file">
                    <?php
                    $file_html = '';
                    if (count($pur_request_attachments) > 0) {
                      $file_html .= '<hr />';
                      foreach ($pur_request_attachments as $f) {
                        $href_url = site_url(PURCHASE_PATH . 'pur_request/' . $f['rel_id'] . '/' . $f['file_name']) . '" download';
                        if (!empty($f['external'])) {
                          $href_url = $f['external_link'];
                        }
                        $file_html .= '<div class="mbot15 row inline-block full-width" data-attachment-id="' . $f['id'] . '">
                                                  <div class="col-md-8">
                                                     <a name="preview-purorder-btn" onclick="preview_purrequest_btn(this); return false;" rel_id = "' . $f['rel_id'] . '" id = "' . $f['id'] . '" href="Javascript:void(0);" class="mbot10 mright5 btn btn-success pull-left" data-toggle="tooltip" title data-original-title="' . _l('preview_file') . '"><i class="fa fa-eye"></i></a>
                                                     <div class="pull-left"><i class="' . get_mime_class($f['filetype']) . '"></i></div>
                                                     <a href=" ' . $href_url . '" target="_blank" download>' . $f['file_name'] . '</a>
                                                     <br />
                                                     <small class="text-muted">' . $f['filetype'] . '</small>
                                                  </div>
                                                  <div class="col-md-4 text-right">';
                        if ($f['staffid'] == get_staff_user_id() || is_admin()) {
                          $file_html .= '<a href="#" class="text-danger" onclick="delete_purrequest_attachment(' . $f['id'] . '); return false;"><i class="fa fa-times"></i></a>';
                        }
                        $file_html .= '</div></div>';
                      }

                      echo pur_html_entity_decode($file_html);
                    }
                    ?>
                  </div>

                  <div id="purrequest_file_data"></div>
                </div>

                <div role="tabpanel" class="tab-pane" id="discuss">
                  <div class="row contract-comments mtop15">
                    <div class="col-md-12">
                      <div id="contract-comments"></div>
                      <div class="clearfix"></div>
                      <textarea name="content" id="comment" rows="4" class="form-control mtop15 contract-comment"></textarea>
                      <button type="button" id="add_comment_btn" class="btn btn-info mtop10 pull-right">
                        <?php echo _l('proposal_add_comment'); ?>
                      </button>
                    </div>
                  </div>
                </div>


                

              </div>


            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>
<div class="modal fade" id="add_action" tabindex="-1" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-body">
        <p class="bold" id="signatureLabel"><?php echo _l('signature'); ?></p>
        <div class="signature-pad--body">
          <canvas id="signature" height="130" width="550"></canvas>
        </div>
        <input type="text" class="ip_style" tabindex="-1" name="signature" id="signatureInput">
        <div class="dispay-block">
          <button type="button" class="btn btn-default btn-xs clear" tabindex="-1" onclick="signature_clear();"><?php echo _l('clear'); ?></button>

        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('cancel'); ?></button>
        <button onclick="sign_request(<?php echo pur_html_entity_decode($pur_request->id); ?>);" data-loading-text="<?php echo _l('wait_text'); ?>" autocomplete="off" class="btn btn-success"><?php echo _l('e_signature_sign'); ?></button>
      </div>


    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->


<!-- Modal Edit Komentar -->
<div id="editCommentModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="editCommentModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editCommentModalLabel">Edit Komentar</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <textarea id="editCommentText" class="form-control" rows="4"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" id="save_edit_comment" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </div>
  </div>
</div>

<?php init_tail(); ?>
</body>

</html>
<?php require 'modules/purchase/assets/js/view_pur_request_js.php'; ?>