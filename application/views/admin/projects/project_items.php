<div class="panel_s panel-table-full tw-mt-4">
    <div class="panel-body">
        <div class="col-md-12">
            <p class=" p_style"><?php echo _l('pur_detail'); ?></p>
            <hr class="hr_style" />

            <div class="table-responsive">
                <table class="table items items-preview estimate-items-preview" data-type="estimate">
                    <thead>
                        <tr>

                            <th width="25%" align="left"><?php echo _l('debit_note_table_item_heading'); ?> test</th>
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
                        // echo count($project_detail);
                        if (count($project_detail) > 0) {
                            $count = 1;
                            $t_mn = 0;
                            foreach ($project_detail as $es) {
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
                    <?php if (isset($project)) {
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
    </div>
</div>