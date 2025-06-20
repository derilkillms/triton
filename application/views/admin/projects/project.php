<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <?= form_open($this->uri->uri_string(), ['id' => 'project_form', 'class' => '_transaction_form']); ?>


        <?php
        $prefix = get_purchase_option('project_prefix');
        $next_number = get_purchase_option('next_prj_number');
        $number = (isset($project) ? $project->number : $next_number);
        echo form_hidden('number', $number); ?>

        <?php $project_number = (isset($project) ? $project->project_number : $prefix . '-' . str_pad($next_number, 5, '0', STR_PAD_LEFT) . '-' . date('Y'));
        // print_r($project);
        ?>

        <div class="tw-mx-auto">
            <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                <?= e($title); ?>
            </h4>
            <div class="panel_s">
                <div class="panel-body">
                    <div class="horizontal-scrollable-tabs panel-full-width-tabs">
                        <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                        <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                        <div class="horizontal-tabs">
                            <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                                <li role="presentation" class="active">
                                    <a href="#tab_project" aria-controls="tab_project" role="tab" data-toggle="tab">
                                        <?= _l('project'); ?>
                                    </a>
                                </li>
                                <li role="presentation">
                                    <a href="#tab_settings" aria-controls="tab_settings" role="tab" data-toggle="tab">
                                        <?= _l('project_settings'); ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="tab-content tw-mt-3">
                        <div role="tabpanel" class="tab-pane active" id="tab_project">


                            <?php
                            $disable_type_edit = '';
                            if (isset($project)) {
                                if ($project->billing_type != 1) {
                                    if (total_rows(db_prefix() . 'tasks', ['rel_id' => $project->id, 'rel_type' => 'project', 'billable' => 1, 'billed' => 1]) > 0) {
                                        $disable_type_edit = 'disabled';
                                    }
                                }
                            }
                            echo render_input('project_number', 'Project Number', $project_number, 'text', array('readonly' => ''));
                            ?>
                            <?php $value = (isset($project) ? $project->name : ''); ?>
                            <?= render_input('name', 'project_name', $value); ?>
                            <div class="form-group select-placeholder">
                                <label for="clientid"
                                    class="control-label"><?= _l('project_customer'); ?></label>
                                <select id="clientid" name="clientid" data-live-search="true" data-width="100%"
                                    class="ajax-search"
                                    data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                    <?php $selected = (isset($project) ? $project->clientid : '');
                                    if ($selected == '') {
                                        $selected = ($customer_id ?? '');
                                    }
                                    if ($selected != '') {
                                        $rel_data = get_relation_data('customer', $selected);
                                        $rel_val  = get_relation_values($rel_data, 'customer');
                                        echo '<option value="' . $rel_val['id'] . '" selected>' . $rel_val['name'] . '</option>';
                                    } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <div class="checkbox">
                                    <input type="checkbox" <?php if ((isset($project) && $project->progress_from_tasks == 1) || ! isset($project)) {
                                                                echo 'checked';
                                                            } ?> name="progress_from_tasks" id="progress_from_tasks">
                                    <label
                                        for="progress_from_tasks"><?= _l('calculate_progress_through_tasks'); ?></label>
                                </div>
                            </div>
                            <?php
                            if (isset($project) && $project->progress_from_tasks == 1) {
                                $value = $this->projects_model->calc_progress_by_tasks($project->id);
                            } elseif (isset($project) && $project->progress_from_tasks == 0) {
                                $value = $project->progress;
                            } else {
                                $value = 0;
                            }
                            ?>
                            <label
                                for=""><?= _l('project_progress'); ?>
                                <span
                                    class="label_progress"><?= e($value); ?>%</span></label>
                            <?= form_hidden('progress', $value); ?>
                            <div class="project_progress_slider project_progress_slider_horizontal mbot15"></div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group select-placeholder">
                                        <label
                                            for="billing_type"><?= _l('project_billing_type'); ?></label>
                                        <div class="clearfix"></div>
                                        <select name="billing_type" class="selectpicker" id="billing_type"
                                            data-width="100%"
                                            <?= $disable_type_edit; ?>
                                            data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                            <option value=""></option>
                                            <option value="1" <?php if (isset($project) && $project->billing_type == 1 || ! isset($project) && $auto_select_billing_type && $auto_select_billing_type->billing_type == 1) {
                                                                    echo 'selected';
                                                                } ?>><?= _l('project_billing_type_fixed_cost'); ?>
                                            </option>
                                            <option value="2" <?php if (isset($project) && $project->billing_type == 2 || ! isset($project) && $auto_select_billing_type && $auto_select_billing_type->billing_type == 2) {
                                                                    echo 'selected';
                                                                } ?>><?= _l('project_billing_type_project_hours'); ?>
                                            </option>
                                            <option value="3"
                                                data-subtext="<?= _l('project_billing_type_project_task_hours_hourly_rate'); ?>"
                                                <?php if (isset($project) && $project->billing_type == 3 || ! isset($project) && $auto_select_billing_type && $auto_select_billing_type->billing_type == 3) {
                                                    echo 'selected';
                                                } ?>><?= _l('project_billing_type_project_task_hours'); ?>
                                            </option>
                                        </select>
                                        <?php if ($disable_type_edit != '') {
                                            echo '<p class="text-danger tw-mt-1">' . _l('cant_change_billing_type_billed_tasks_found') . '</p>';
                                        } ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group select-placeholder">
                                        <label
                                            for="status"><?= _l('project_status'); ?></label>
                                        <div class="clearfix"></div>
                                        <select name="status" id="status" class="selectpicker" data-width="100%"
                                            data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
                                            <?php foreach ($statuses as $status) { ?>
                                                <option
                                                    value="<?= e($status['id']); ?>"
                                                    <?php if (! isset($project) && $status['id'] == 2 || (isset($project) && $project->status == $status['id'])) {
                                                        echo 'selected';
                                                    } ?>><?= e($status['name']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <?php if (isset($project) && project_has_recurring_tasks($project->id)) { ?>
                                <div class="alert alert-warning recurring-tasks-notice hide"></div>
                            <?php } ?>
                            <?php if (is_email_template_active('project-finished-to-customer')) { ?>
                                <div class="form-group project_marked_as_finished hide">
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" name="project_marked_as_finished_email_to_contacts"
                                            id="project_marked_as_finished_email_to_contacts">
                                        <label
                                            for="project_marked_as_finished_email_to_contacts"><?= _l('project_marked_as_finished_to_contacts'); ?></label>
                                    </div>
                                </div>
                            <?php } ?>
                            <?php if (isset($project)) { ?>
                                <div class="form-group mark_all_tasks_as_completed hide">
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" name="mark_all_tasks_as_completed"
                                            id="mark_all_tasks_as_completed">
                                        <label
                                            for="mark_all_tasks_as_completed"><?= _l('project_mark_all_tasks_as_completed'); ?></label>
                                    </div>
                                </div>
                                <div class="notify_project_members_status_change hide">
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" name="notify_project_members_status_change"
                                            id="notify_project_members_status_change">
                                        <label
                                            for="notify_project_members_status_change"><?= _l('notify_project_members_status_change'); ?></label>
                                    </div>
                                    <hr />
                                </div>
                            <?php } ?>
                            <?php
                            $input_field_hide_class_total_cost = '';
                            if (! isset($project)) {
                                if ($auto_select_billing_type && $auto_select_billing_type->billing_type != 1 || ! $auto_select_billing_type) {
                                    $input_field_hide_class_total_cost = 'hide';
                                }
                            } elseif (isset($project) && $project->billing_type != 1) {
                                $input_field_hide_class_total_cost = 'hide';
                            }
                            ?>
                            <!-- select item -->


                            <label for="type"><?php echo _l('type'); ?></label>
                            <select name="type" id="type" class="selectpicker" data-live-search="true" data-width="100%" data-none-selected-text="<?php echo _l('ticket_settings_none_assigned'); ?>">
                                <option value=""></option>
                                <option value="capex" <?php if (isset($project) && $project->type == 'capex') {
                                                            echo 'selected';
                                                        } ?>><?php echo _l('capex'); ?></option>
                                <option value="opex" <?php if (isset($project) && $project->type == 'opex') {
                                                            echo 'selected';
                                                        } ?>><?php echo _l('opex'); ?></option>
                            </select>
                            <br><br>




                            <?php
                            $currency_attr = array('data-show-subtext' => true);

                            $selected = '';
                            foreach ($currencies as $currency) {
                                if (isset($project) && $project->currency != 0) {
                                    if ($currency['id'] == $project->currency) {
                                        $selected = $currency['id'];
                                    }
                                } else {
                                    if ($currency['isdefault'] == 1) {
                                        $selected = $currency['id'];
                                    }
                                }
                            }

                            ?>
                            <?php echo render_select('currency', $currencies, array('id', 'name', 'symbol'), 'invoice_add_edit_currency', $selected, $currency_attr); ?>

                            <div id="project_cost"
                                class="<?= e($input_field_hide_class_total_cost); ?>">
                                <?php $value = (isset($project) ? $project->project_cost : ''); ?>
                                <?= render_input('project_cost', 'project_total_cost', $value, 'number'); ?>
                            </div>
                            <?php
                            $input_field_hide_class_rate_per_hour = '';
                            if (! isset($project)) {
                                if ($auto_select_billing_type && $auto_select_billing_type->billing_type != 2 || ! $auto_select_billing_type) {
                                    $input_field_hide_class_rate_per_hour = 'hide';
                                }
                            } elseif (isset($project) && $project->billing_type != 2) {
                                $input_field_hide_class_rate_per_hour = 'hide';
                            }
                            ?>
                            <div id="project_rate_per_hour"
                                class="<?= e($input_field_hide_class_rate_per_hour); ?>">
                                <?php $value = (isset($project) ? $project->project_rate_per_hour : ''); ?>
                                <?php
                                $input_disable = [];
                                if ($disable_type_edit != '') {
                                    $input_disable['disabled'] = true;
                                }
                                ?>
                                <?= render_input('project_rate_per_hour', 'project_rate_per_hour', $value, 'number', $input_disable); ?>
                            </div>
                            <?php $this->load->view('purchase/item_include/main_item_select'); ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <?= render_input('estimated_hours', 'estimated_hours', isset($project) ? $project->estimated_hours : '', 'number'); ?>
                                </div>
                                <div class="col-md-6">
                                    <?php
                                    $selected = [];
                                    if (isset($project_members)) {
                                        foreach ($project_members as $member) {
                                            array_push($selected, $member['staff_id']);
                                        }
                                    } else {
                                        array_push($selected, get_staff_user_id());
                                    }
                                    echo render_select('project_members[]', $staff, ['staffid', ['firstname', 'lastname']], 'project_members', $selected, ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
                                    ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <?php $value = (isset($project) ? _d($project->start_date) : _d(date('Y-m-d'))); ?>
                                    <?= render_date_input('start_date', 'project_start_date', $value); ?>
                                </div>
                                <div class="col-md-6">
                                    <?php $value = (isset($project) ? _d($project->deadline) : ''); ?>
                                    <?= render_date_input('deadline', 'project_deadline', $value); ?>
                                </div>
                            </div>
                            <?php if (isset($project) && $project->date_finished != null && $project->status == 4) { ?>
                                <?= render_datetime_input('date_finished', 'project_completed_date', _dt($project->date_finished)); ?>
                            <?php } ?>
                            <div class="form-group">
                                <label for="tags" class="control-label"><i class="fa fa-tag" aria-hidden="true"></i>
                                    <?= _l('tags'); ?></label>
                                <input type="text" class="tagsinput" id="tags" name="tags"
                                    value="<?= isset($project) ? prep_tags_input(get_tags_in($project->id, 'project')) : ''; ?>"
                                    data-role="tagsinput">
                            </div>
                            <?php $rel_id_custom_field = (isset($project) ? $project->id : false); ?>
                            <?= render_custom_fields('projects', $rel_id_custom_field); ?>
                            <p class="bold">
                                <?= _l('project_description'); ?>
                            </p>
                            <?php $contents = '';
                            if (isset($project)) {
                                $contents = $project->description;
                            } ?>
                            <?= render_textarea('description', '', $contents, [], [], '', 'tinymce'); ?>

                            <?php if (isset($estimate)) { ?>
                                <hr class="hr-panel-separator" />
                                <h5 class="font-medium">
                                    <?= _l('estimate_items_convert_to_tasks') ?>
                                </h5>
                                <input type="hidden" name="estimate_id"
                                    value="<?= $estimate->id ?>">
                                <div class="row">
                                    <?php foreach ($estimate->items as $item) { ?>
                                        <div class="col-md-8 border-right">
                                            <div class="checkbox mbot15">
                                                <input type="checkbox" name="items[]"
                                                    value="<?= $item['id'] ?>"
                                                    checked
                                                    id="item-<?= $item['id'] ?>">
                                                <label
                                                    for="item-<?= $item['id'] ?>">
                                                    <h5 class="no-mbot no-mtop text-uppercase">
                                                        <?= $item['description'] ?>
                                                    </h5>
                                                    <span
                                                        class="text-muted"><?= $item['long_description'] ?></span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div data-toggle="tooltip"
                                                title="<?= _l('task_single_assignees_select_title'); ?>">
                                                <?= render_select('items_assignee[]', $staff, ['staffid', ['firstname', 'lastname']], '', get_staff_user_id(), ['data-actions-box' => true], [], '', '', false); ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <hr class="hr-panel-separator" />

                            <?php if (is_email_template_active('assigned-to-project')) { ?>
                                <div class="checkbox checkbox-primary tw-mb-0">
                                    <input type="checkbox" name="send_created_email" id="send_created_email">
                                    <label
                                        for="send_created_email"><?= _l('project_send_created_email'); ?></label>
                                </div>
                            <?php } ?>
                        </div>
                        <div role="tabpanel" class="tab-pane" id="tab_settings">
                            <div id="project-settings-area">
                                <div class="form-group select-placeholder">
                                    <label for="contact_notification" class="control-label">
                                        <span class="text-danger">*</span>
                                        <?= _l('projects_send_contact_notification'); ?>
                                    </label>
                                    <select name="contact_notification" id="contact_notification"
                                        class="form-control selectpicker"
                                        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                        required>
                                        <?php
                                        $options = [
                                            ['id' => 1, 'name' => _l('project_send_all_contacts_with_notifications_enabled')],
                                            ['id' => 2, 'name' => _l('project_send_specific_contacts_with_notification')],
                                            ['id' => 0, 'name' => _l('project_do_not_send_contacts_notifications')],
                                        ];

                                        foreach ($options as $option) { ?>
                                            <option
                                                value="<?= e($option['id']); ?>"
                                                <?php if ((isset($project) && $project->contact_notification == $option['id'])) {
                                                    echo ' selected';
                                                } ?>><?= e($option['name']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <!-- hide class -->
                                <div class="form-group select-placeholder <?= (isset($project) && $project->contact_notification == 2) ? '' : 'hide' ?>"
                                    id="notify_contacts_wrapper">
                                    <label for="notify_contacts" class="control-label"><span
                                            class="text-danger">*</span>
                                        <?= _l('project_contacts_to_notify') ?></label>
                                    <select name="notify_contacts[]" data-id="notify_contacts" id="notify_contacts"
                                        class="ajax-search" data-width="100%" data-live-search="true"
                                        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
                                        multiple>
                                        <?php
                                        $notify_contact_ids = isset($project) ? unserialize($project->notify_contacts) : [];

                                        foreach ($notify_contact_ids as $contact_id) {
                                            $rel_data = get_relation_data('contact', $contact_id);
                                            $rel_val  = get_relation_values($rel_data, 'contact');
                                            echo '<option value="' . $rel_val['id'] . '" selected>' . $rel_val['name'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <?php foreach ($settings as $setting) {
                                    $checked = ' checked';
                                    if (isset($project)) {
                                        if ($project->settings->{$setting} == 0) {
                                            $checked = '';
                                        }
                                    } else {
                                        foreach ($last_project_settings as $last_setting) {
                                            if ($setting == $last_setting['name']) {
                                                // hide_tasks_on_main_tasks_table is not applied on most used settings to prevent confusions
                                                if ($last_setting['value'] == 0 || $last_setting['name'] == 'hide_tasks_on_main_tasks_table') {
                                                    $checked = '';
                                                }
                                            }
                                        }
                                        if (count($last_project_settings) == 0 && $setting == 'hide_tasks_on_main_tasks_table') {
                                            $checked = '';
                                        }
                                    } ?>
                                    <?php if ($setting != 'available_features') { ?>
                                        <div class="checkbox">
                                            <input type="checkbox"
                                                name="settings[<?= e($setting); ?>]"
                                                <?= e($checked); ?>
                                                id="<?= e($setting); ?>">
                                            <label for="<?= e($setting); ?>">
                                                <?php if ($setting == 'hide_tasks_on_main_tasks_table') { ?>
                                                    <?= _l('hide_tasks_on_main_tasks_table'); ?>
                                                <?php } else { ?>
                                                    <?= e(_l('project_allow_client_to', _l('project_setting_' . $setting))); ?>
                                                <?php } ?>
                                            </label>
                                        </div>
                                    <?php } else { ?>
                                        <div class="form-group mtop15 select-placeholder project-available-features">
                                            <label
                                                for="available_features"><?= _l('visible_tabs'); ?></label>
                                            <select
                                                name="settings[<?= e($setting); ?>][]"
                                                id="<?= e($setting); ?>"
                                                multiple="true" class="selectpicker" id="available_features" data-width="100%"
                                                data-actions-box="true" data-hide-disabled="true">
                                                <?php foreach (get_project_tabs_admin() as $tab) {
                                                    $selected = '';
                                                    if (isset($tab['collapse'])) { ?>
                                                        <optgroup
                                                            label="<?= e($tab['name']); ?>">
                                                            <?php foreach ($tab['children'] as $tab_dropdown) {
                                                                $selected = '';
                                                                if (isset($project) && (
                                                                    (isset($project->settings->available_features[$tab_dropdown['slug']])
                                                                        && $project->settings->available_features[$tab_dropdown['slug']] == 1)
                                                                    || ! isset($project->settings->available_features[$tab_dropdown['slug']])
                                                                )) {
                                                                    $selected = ' selected';
                                                                } elseif (! isset($project) && count($last_project_settings) > 0) {
                                                                    foreach ($last_project_settings as $last_project_setting) {
                                                                        if ($last_project_setting['name'] == $setting) {
                                                                            if (
                                                                                isset($last_project_setting['value'][$tab_dropdown['slug']])
                                                                                && $last_project_setting['value'][$tab_dropdown['slug']] == 1
                                                                            ) {
                                                                                $selected = ' selected';
                                                                            }
                                                                        }
                                                                    }
                                                                } elseif (! isset($project)) {
                                                                    $selected = ' selected';
                                                                } ?>
                                                                <option
                                                                    value="<?= e($tab_dropdown['slug']); ?>"
                                                                    <?= e($selected); ?><?php if (isset($tab_dropdown['linked_to_customer_option']) && is_array($tab_dropdown['linked_to_customer_option']) && count($tab_dropdown['linked_to_customer_option']) > 0) { ?>
                                                                    data-linked-customer-option="<?= implode(',', $tab_dropdown['linked_to_customer_option']); ?>"
                                                                    <?php } ?>><?= e($tab_dropdown['name']); ?>
                                                                </option>
                                                            <?php
                                                            } ?>
                                                        </optgroup>
                                                    <?php } else {
                                                        if (isset($project) && (
                                                            (isset($project->settings->available_features[$tab['slug']])
                                                                && $project->settings->available_features[$tab['slug']] == 1)
                                                            || ! isset($project->settings->available_features[$tab['slug']])
                                                        )) {
                                                            $selected = ' selected';
                                                        } elseif (! isset($project) && count($last_project_settings) > 0) {
                                                            foreach ($last_project_settings as $last_project_setting) {
                                                                if ($last_project_setting['name'] == $setting) {
                                                                    if (
                                                                        isset($last_project_setting['value'][$tab['slug']])
                                                                        && $last_project_setting['value'][$tab['slug']] == 1
                                                                    ) {
                                                                        $selected = ' selected';
                                                                    }
                                                                }
                                                            }
                                                        } elseif (! isset($project)) {
                                                            $selected = ' selected';
                                                        } ?>
                                                        <option
                                                            value="<?= e($tab['slug']); ?>"
                                                            <?php if ($tab['slug'] == 'project_overview') {
                                                                echo ' disabled selected';
                                                            } ?>
                                                            <?= e($selected); ?>
                                                            <?php if (isset($tab['linked_to_customer_option']) && is_array($tab['linked_to_customer_option']) && count($tab['linked_to_customer_option']) > 0) { ?>
                                                            data-linked-customer-option="<?= implode(',', $tab['linked_to_customer_option']); ?>"
                                                            <?php } ?>>
                                                            <?= e($tab['name']); ?>
                                                        </option>
                                                    <?php } ?>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    <?php } ?>
                                    <hr class="tw-my-3 -tw-mx-8" />
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="btn-bottom-toolbar text-right">
                    <button type="submit" data-form="#project_form" class="btn-tr save_detail btn btn-info mleft10 transaction-submit" autocomplete="off"
                        data-loading-text="<?= _l('wait_text'); ?>">
                        <?= _l('submit'); ?>
                    </button>
                </div>
            </div>
        </div>
        <!-- start rate -->

        <div class="row ">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="mtop10 invoice-item">


                            <div class="row">
                                <div class="col-md-4">

                                </div>

                                <?php
                                $project_currency = $base_currency;
                                if (isset($project) && $project->currency != 0) {
                                    $project_currency = pur_get_currency_by_id($project->currency);
                                }

                                $from_currency = (isset($project) && $project->from_currency != null) ? $project->from_currency : $base_currency->id;
                                echo form_hidden('from_currency', $from_currency);

                                ?>
                                <div class="col-md-8 <?php if ($project_currency->id == $base_currency->id) {
                                                            echo 'hide';
                                                        } ?>" id="currency_rate_div">
                                    <div class="col-md-10 text-right">

                                        <p class="mtop10"><?php echo _l('currency_rate'); ?><span id="convert_str"><?php echo ' (' . $base_currency->name . ' => ' . $project_currency->name . '): ';  ?></span></p>
                                    </div>
                                    <div class="col-md-2 pull-right">
                                        <?php $currency_rate = 1;
                                        if (isset($project) && $project->currency != 0) {
                                            $currency_rate = pur_get_currency_rate($base_currency->name, $project_currency->name);
                                        }
                                        echo render_input('currency_rate', '', $currency_rate, 'number', [], [], '', 'text-right');
                                        ?>
                                    </div>
                                </div>

                            </div>
                            <div class="table-responsive s_table ">
                                <table class="table invoice-items-table items table-main-invoice-edit has-calculations no-mtop">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th width="25%" align="left"><i class="fa fa-exclamation-circle" aria-hidden="true" data-toggle="tooltip" data-title="<?php echo _l('item_description_new_lines_notice'); ?>"></i> <?php echo _l('debit_note_table_item_heading'); ?></th>
                                            <th width="10%" align="right"><?php echo _l('unit_price'); ?><span class="th_currency"><?php echo '(' . $project_currency->name . ')'; ?></span></th>
                                            <th width="10%" align="right" class="qty"><?php echo _l('purchase_quantity'); ?></th>
                                            <th width="10%" align="right"><?php echo _l('subtotal'); ?><span class="th_currency"><?php echo '(' . $project_currency->name . ')'; ?></span></th>
                                            <th width="15%" align="right"><?php echo _l('debit_note_table_tax_heading'); ?></th>
                                            <th width="10%" align="right"><?php echo _l('tax_value'); ?><span class="th_currency"><?php echo '(' . $project_currency->name . ')'; ?></span></th>
                                            <th width="10%" align="right"><?php echo _l('debit_note_total'); ?><span class="th_currency"><?php echo '(' . $project_currency->name . ')'; ?></span></th>
                                            <th align="right"><i class="fa fa-cog"></i></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php echo pur_html_entity_decode($project_row_template); ?>
                                        <?php
                                        if (isset($project_detail)) {
                                            $noitem = 1;
                                            foreach ($project_detail as $prj) {



                                        ?>

                                                <tr class="sortable item">
                                                    <td class="dragger ui-sortable-handle">
                                                        <input type="hidden" class="order" name="items[<?= $noitem++ ?>][order]" /><input
                                                            type="hidden"
                                                            class="ids"
                                                            name="items[<?= $noitem++ ?>][id]"
                                                            value="<?= $prj['prd_id'] ?>" />
                                                    </td>
                                                    <td class="">
                                                        <div class="form-group" app-field-wrapper="items[<?= $noitem++ ?>][item_text]">
                                                            <textarea
                                                                id="items[<?= $noitem++ ?>][item_text]"
                                                                name="items[<?= $noitem++ ?>][item_text]"
                                                                class="form-control"
                                                                rows="2"
                                                                placeholder="Item Name">
<?= $prj['item_text'] ?></textarea>
                                                        </div>
                                                    </td>
                                                    <td class="rate">
                                                        <div class="form-group no-margin" app-field-wrapper="items[<?= $noitem++ ?>][unit_price]">
                                                            <input
                                                                type="number"
                                                                id="items[<?= $noitem++ ?>][unit_price]"
                                                                name="items[<?= $noitem++ ?>][unit_price]"
                                                                class="form-control text-right"
                                                                onblur="pur_calculate_total();"
                                                                onchange="pur_calculate_total();"
                                                                min="0.0"
                                                                step="any"
                                                                data-amount="invoice"
                                                                placeholder="Unit Price"
                                                                value="<?= $prj['unit_price'] ?>" />
                                                        </div>
                                                        <input class="hide" name="og_price" disabled="true" value="<?= $prj['unit_price'] ?>" />
                                                    </td>
                                                    <td class="quantities">
                                                        <div class="form-group no-margin" app-field-wrapper="items[<?= $noitem++ ?>][quantity]">
                                                            <input
                                                                type="number"
                                                                id="items[<?= $noitem++ ?>][quantity]"
                                                                name="items[<?= $noitem++ ?>][quantity]"
                                                                class="form-control text-right"
                                                                onblur="pur_calculate_total();"
                                                                onchange="pur_calculate_total();"
                                                                min="0.0"
                                                                step="any"
                                                                data-quantity="1"
                                                                value="<?= $prj['quantity'] ?>" />
                                                        </div>
                                                        <div class="form-group no-margin" app-field-wrapper="items[<?= $noitem++ ?>][unit_name]">
                                                            <input
                                                                type="text"
                                                                id="items[<?= $noitem++ ?>][unit_name]"
                                                                name="items[<?= $noitem++ ?>][unit_name]"
                                                                class="form-control input-transparent text-right pur_input_none"
                                                                placeholder="Unit"
                                                                readonly="1"
                                                                value="" />
                                                        </div>
                                                    </td>
                                                    <td class="into_money">
                                                        <div class="form-group" app-field-wrapper="items[<?= $noitem++ ?>][into_money]">
                                                            <input
                                                                type="number"
                                                                id="items[<?= $noitem++ ?>][into_money]"
                                                                name="items[<?= $noitem++ ?>][into_money]"
                                                                class="form-control text-right"
                                                                readonly="1"
                                                                value="<?= $prj['into_money'] ?>" />
                                                        </div>
                                                    </td>
                                                    <td class="taxrate">
                                                        <div
                                                            class="dropdown bootstrap-select show-tick display-block taxes bs3"
                                                            style="width: 100%">
                                                            <select
                                                                class="selectpicker display-block taxes"
                                                                data-width="100%"
                                                                name="items[<?= $noitem++ ?>][tax_select][]"
                                                                multiple=""
                                                                data-none-selected-text="No Tax"
                                                                tabindex="-98">
                                                                <option
                                                                    value="PPN|11.00"
                                                                    data-taxrate="11.00"
                                                                    data-taxname="PPN|11.00"
                                                                    data-subtext="PPN|11.00">
                                                                    11.00%
                                                                </option>
                                                            </select>
                                                            <div class="dropdown-menu open">
                                                                <div
                                                                    class="inner open"
                                                                    role="listbox"
                                                                    id="bs-select-11"
                                                                    tabindex="-1"
                                                                    aria-multiselectable="true">
                                                                    <ul class="dropdown-menu inner" role="presentation"></ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="tax_value">
                                                        <div class="form-group" app-field-wrapper="items[<?= $noitem++ ?>][tax_value]">
                                                            <input
                                                                type="number"
                                                                id="items[<?= $noitem++ ?>][tax_value]"
                                                                name="items[<?= $noitem++ ?>][tax_value]"
                                                                class="form-control text-right"
                                                                readonly="1"
                                                                value="<?= $prj['tax_value'] ?>" />
                                                        </div>
                                                    </td>
                                                    <td class="hide item_code">
                                                        <div class="form-group" app-field-wrapper="items[<?= $noitem++ ?>][item_code]">
                                                            <input
                                                                type="text"
                                                                id="items[<?= $noitem++ ?>][item_code]"
                                                                name="items[<?= $noitem++ ?>][item_code]"
                                                                class="form-control"
                                                                placeholder="item_code"
                                                                value="<?= $prj['item_code'] ?>" />
                                                        </div>
                                                    </td>
                                                    <td class="hide unit_id">
                                                        <div class="form-group" app-field-wrapper="items[<?= $noitem++ ?>][unit_id]">
                                                            <input
                                                                type="text"
                                                                id="items[<?= $noitem++ ?>][unit_id]"
                                                                name="items[<?= $noitem++ ?>][unit_id]"
                                                                class="form-control"
                                                                placeholder="Unit"
                                                                value="<?= $prj['unit_id'] ?>" />
                                                        </div>
                                                    </td>
                                                    <td class="_total">
                                                        <div class="form-group" app-field-wrapper="items[<?= $noitem++ ?>][total]">
                                                            <input
                                                                type="number"
                                                                id="items[<?= $noitem++ ?>][total]"
                                                                name="items[<?= $noitem++ ?>][total]"
                                                                class="form-control text-right"
                                                                readonly="1"
                                                                value="<?= $prj['total'] ?>" />
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <a
                                                            href="#"
                                                            class="btn btn-danger pull-right"
                                                            onclick="pur_delete_item(this,<?= $prj['prd_id'] ?>,'.invoice-item'); return false;"><i class="fa fa-trash"></i></a>
                                                    </td>
                                                </tr>

                                        <?php
                                            }
                                        }
                                        ?>

                                    </tbody>
                                </table>
                            </div>




                            <div class="col-md-6 pright0 col-md-offset-6">
                                <table class="table text-right mbot0">
                                    <tbody>
                                        <tr id="subtotal">
                                            <td class="td_style"><span class="bold"><?php echo _l('subtotal'); ?></span>
                                            </td>
                                            <td width="65%" id="total_td">

                                                <div class="input-group" id="discount-total">

                                                    <input type="text" readonly="true" class="form-control text-right" name="subtotal" value="<?php if (isset($project)) {
                                                                                                                                                    echo app_format_money($project->subtotal, '');
                                                                                                                                                } ?>">

                                                    <div class="input-group-addon">
                                                        <div class="dropdown">

                                                            <span class="discount-type-selected currency_span" id="subtotal_currency">
                                                                <?php
                                                                if (!isset($project)) {
                                                                    echo pur_html_entity_decode($base_currency->symbol);
                                                                } else {
                                                                    if ($project->currency != 0) {
                                                                        $_currency_symbol = pur_get_currency_name_symbol($project->currency, 'symbol');
                                                                        echo pur_html_entity_decode($_currency_symbol);
                                                                    } else {
                                                                        echo pur_html_entity_decode($base_currency->symbol);
                                                                    }
                                                                }
                                                                ?>
                                                            </span>


                                                        </div>
                                                    </div>

                                                </div>
                                            </td>
                                        </tr>

                                        <tr id="total">
                                            <td class="td_style"><span class="bold"><?php echo _l('total'); ?></span>
                                            </td>
                                            <td width="65%" id="total_td">
                                                <div class="input-group" id="total">

                                                    <input type="text" readonly="true" class="form-control text-right" name="total_mn" value="<?php if (isset($project)) {
                                                                                                                                                    echo app_format_money($project->total, '');
                                                                                                                                                } ?>">
                                                    <div class="input-group-addon">
                                                        <div class="dropdown">

                                                            <span class="discount-type-selected currency_span">
                                                                <?php
                                                                if (!isset($project)) {
                                                                    echo pur_html_entity_decode($base_currency->symbol);
                                                                } else {
                                                                    if ($project->currency != 0) {
                                                                        $_currency_symbol = pur_get_currency_name_symbol($project->currency, 'symbol');
                                                                        echo pur_html_entity_decode($_currency_symbol);
                                                                    } else {
                                                                        echo pur_html_entity_decode($base_currency->symbol);
                                                                    }
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>


                            </div>

                            <div id="removed-items"></div>
                        </div>

                    </div>

                    <div class="clearfix"></div>

                    <div class="btn-bottom-toolbar text-right">
                        <button type="submit" class="btn-tr save_detail btn btn-info mleft10">
                            <?php echo _l('submit'); ?>
                        </button>

                    </div>
                    <div class="btn-bottom-pusher"></div>


                </div>

            </div>

        </div>
        <!-- end rate -->
        <?= form_close(); ?>
    </div>
</div>
<?php init_tail(); ?>
<script>
    <?php if (isset($project)) { ?>
        var original_project_status = '<?= e($project->status); ?>';
    <?php } ?>

    $(function() {

        $contacts_select = $('#notify_contacts'),
            $contacts_wrapper = $('#notify_contacts_wrapper'),
            $clientSelect = $('#clientid'),
            $contact_notification_select = $('#contact_notification');

        init_ajax_search('contacts', $contacts_select, {
            rel_id: $contacts_select.val(),
            type: 'contacts',
            extra: {
                client_id: function() {
                    return $clientSelect.val();
                }
            }
        });

        if ($clientSelect.val() == '') {
            $contacts_select.prop('disabled', true);
            $contacts_select.selectpicker('refresh');
        } else {
            $contacts_select.siblings().find('input[type="search"]').val(' ').trigger('keyup');
        }

        $clientSelect.on('changed.bs.select', function() {
            if ($clientSelect.selectpicker('val') == '') {
                $contacts_select.prop('disabled', true);
            } else {
                $contacts_select.siblings().find('input[type="search"]').val(' ').trigger('keyup');
                $contacts_select.prop('disabled', false);
            }
            deselect_ajax_search($contacts_select[0]);
            $contacts_select.find('option').remove();
            $contacts_select.selectpicker('refresh');
        });

        $contact_notification_select.on('changed.bs.select', function() {
            if ($contact_notification_select.selectpicker('val') == 2) {
                $contacts_select.siblings().find('input[type="search"]').val(' ').trigger('keyup');
                $contacts_wrapper.removeClass('hide');
            } else {
                $contacts_wrapper.addClass('hide');
                deselect_ajax_search($contacts_select[0]);
            }
        });

        $('select[name="billing_type"]').on('change', function() {
            var type = $(this).val();
            if (type == 1) {
                $('#project_cost').removeClass('hide');
                $('#project_rate_per_hour').addClass('hide');
            } else if (type == 2) {
                $('#project_cost').addClass('hide');
                $('#project_rate_per_hour').removeClass('hide');
            } else {
                $('#project_cost').addClass('hide');
                $('#project_rate_per_hour').addClass('hide');
            }
        });

        appValidateForm($('form'), {
            name: 'required',
            clientid: 'required',
            start_date: 'required',
            billing_type: 'required',
            'notify_contacts[]': {
                required: {
                    depends: function() {
                        return !$contacts_wrapper.hasClass('hide');
                    }
                }
            },
        });

        $('select[name="status"]').on('change', function() {
            var status = $(this).val();
            var mark_all_tasks_completed = $('.mark_all_tasks_as_completed');
            var notify_project_members_status_change = $('.notify_project_members_status_change');
            mark_all_tasks_completed.removeClass('hide');
            if (typeof(original_project_status) != 'undefined') {
                if (original_project_status != status) {

                    mark_all_tasks_completed.removeClass('hide');
                    notify_project_members_status_change.removeClass('hide');

                    if (status == 4 || status == 5 || status == 3) {
                        $('.recurring-tasks-notice').removeClass('hide');
                        var notice =
                            "<?= _l('project_changing_status_recurring_tasks_notice'); ?>";
                        notice = notice.replace('{0}', $(this).find('option[value="' + status + '"]')
                            .text()
                            .trim());
                        $('.recurring-tasks-notice').html(notice);
                        $('.recurring-tasks-notice').append(
                            '<input type="hidden" name="cancel_recurring_tasks" value="true">');
                        mark_all_tasks_completed.find('input').prop('checked', true);
                    } else {
                        $('.recurring-tasks-notice').html('').addClass('hide');
                        mark_all_tasks_completed.find('input').prop('checked', false);
                    }
                } else {
                    mark_all_tasks_completed.addClass('hide');
                    mark_all_tasks_completed.find('input').prop('checked', false);
                    notify_project_members_status_change.addClass('hide');
                    $('.recurring-tasks-notice').html('').addClass('hide');
                }
            }

            if (status == 4) {
                $('.project_marked_as_finished').removeClass('hide');
            } else {
                $('.project_marked_as_finished').addClass('hide');
                $('.project_marked_as_finished').prop('checked', false);
            }
        });

        $('form').on('submit', function() {
            $('select[name="billing_type"]').prop('disabled', false);
            $('#available_features,#available_features option').prop('disabled', false);
            $('input[name="project_rate_per_hour"]').prop('disabled', false);
        });

        var progress_input = $('input[name="progress"]');
        var progress_from_tasks = $('#progress_from_tasks');
        var progress = progress_input.val();

        $('.project_progress_slider').slider({
            min: 0,
            max: 100,
            value: progress,
            disabled: progress_from_tasks.prop('checked'),
            slide: function(event, ui) {
                progress_input.val(ui.value);
                $('.label_progress').html(ui.value + '%');
            }
        });

        progress_from_tasks.on('change', function() {
            var _checked = $(this).prop('checked');
            $('.project_progress_slider').slider({
                disabled: _checked
            });
        });

        $('#project-settings-area input').on('change', function() {
            if ($(this).attr('id') == 'view_tasks' && $(this).prop('checked') == false) {
                $('#create_tasks').prop('checked', false).prop('disabled', true);
                $('#edit_tasks').prop('checked', false).prop('disabled', true);
                $('#view_task_comments').prop('checked', false).prop('disabled', true);
                $('#comment_on_tasks').prop('checked', false).prop('disabled', true);
                $('#view_task_attachments').prop('checked', false).prop('disabled', true);
                $('#view_task_checklist_items').prop('checked', false).prop('disabled', true);
                $('#upload_on_tasks').prop('checked', false).prop('disabled', true);
                $('#view_task_total_logged_time').prop('checked', false).prop('disabled', true);
            } else if ($(this).attr('id') == 'view_tasks' && $(this).prop('checked') == true) {
                $('#create_tasks').prop('disabled', false);
                $('#edit_tasks').prop('disabled', false);
                $('#view_task_comments').prop('disabled', false);
                $('#comment_on_tasks').prop('disabled', false);
                $('#view_task_attachments').prop('disabled', false);
                $('#view_task_checklist_items').prop('disabled', false);
                $('#upload_on_tasks').prop('disabled', false);
                $('#view_task_total_logged_time').prop('disabled', false);
            }
        });

        // Auto adjust customer permissions based on selected project visible tabs
        // Eq Project creator disable TASKS tab, then this function will auto turn off customer project option Allow customer to view tasks

        $('#available_features').on('change', function() {
            $("#available_features option").each(function() {
                if ($(this).data('linked-customer-option') && !$(this).is(':selected')) {
                    var opts = $(this).data('linked-customer-option').split(',');
                    for (var i = 0; i < opts.length; i++) {
                        var project_option = $('#' + opts[i]);
                        project_option.prop('checked', false);
                        if (opts[i] == 'view_tasks') {
                            project_option.trigger('change');
                        }
                    }
                }
            });
        });
        $("#view_tasks").trigger('change');
        <?php if (! isset($project)) { ?>
            $('#available_features').trigger('change');
        <?php } ?>
    });
</script>

</body>

</html>
<?php

require 'project_js.php';
?>