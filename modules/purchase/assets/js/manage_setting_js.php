<script>
    (function($) {
        "use strict";
        var addMoreVendorsInputKey = $('.list_approve select[name*="approver"]').length;
        $("body").on('click', '.new_vendor_requests', function() {
            if ($(this).hasClass('disabled')) {
                return false;
            }

            addMoreVendorsInputKey = $('.list_approve select[name*="approver"]').length;
            var newattachment = $('.list_approve').find('#item_approve').eq(0).clone().appendTo('.list_approve');
            newattachment.find('button[data-toggle="dropdown"]').remove();
            newattachment.find('select').selectpicker('refresh');

            newattachment.find('button[data-id="approver[0]"]').attr('data-id', 'approver[' + addMoreVendorsInputKey + ']');
            newattachment.find('label[for="approver[0]"]').attr('for', 'approver[' + addMoreVendorsInputKey + ']');
            newattachment.find('select[name="approver[0]"]').attr('name', 'approver[' + addMoreVendorsInputKey + ']');
            newattachment.find('select[id="approver[0]"]').attr('id', 'approver[' + addMoreVendorsInputKey + ']').selectpicker('refresh');
            newattachment.find('select[data-id="0"]').attr('data-id', addMoreVendorsInputKey);

            newattachment.find('button[data-id="staff[0]"]').attr('data-id', 'staff[' + addMoreVendorsInputKey + ']');
            newattachment.find('label[for="staff[0]"]').attr('for', 'staff[' + addMoreVendorsInputKey + ']');
            newattachment.find('select[name="staff[0]"]').attr('name', 'staff[' + addMoreVendorsInputKey + ']');
            newattachment.find('select[id="staff[0]"]').attr('id', 'staff[' + addMoreVendorsInputKey + ']').selectpicker('refresh');

            newattachment.find('button[data-id="department[0]"]').attr('data-id', 'department[' + addMoreVendorsInputKey + ']');
            newattachment.find('label[for="department[0]"]').attr('for', 'department[' + addMoreVendorsInputKey + ']');
            newattachment.find('select[name="department[0]"]').attr('name', 'department[' + addMoreVendorsInputKey + ']');
            newattachment.find('select[id="department[0]"]').attr('id', 'department[' + addMoreVendorsInputKey + ']').selectpicker('refresh');

            newattachment.find('button[data-id="action[0]"]').attr('data-id', 'action[' + addMoreVendorsInputKey + ']');
            newattachment.find('label[for="action[0]"]').attr('for', 'action[' + addMoreVendorsInputKey + ']');
            newattachment.find('select[name="action[0]"]').attr('name', 'action[' + addMoreVendorsInputKey + ']');
            newattachment.find('select[id="action[0]"]').attr('id', 'action[' + addMoreVendorsInputKey + ']').selectpicker('refresh');

            newattachment.find('#is_staff_0').attr('id', 'is_staff_' + addMoreVendorsInputKey);
            newattachment.find('button[name="add"] i').removeClass('fa-plus').addClass('fa-minus');
            newattachment.find('button[name="add"]').removeClass('new_vendor_requests').addClass('remove_vendor_requests').removeClass('btn-success').addClass('btn-danger');

            $('select[name="approver[' + addMoreVendorsInputKey + ']"]').on('change', function() {
                let index = $(this).attr('data-id');
                if ($(this).val() == 'staff') {
                    $('#is_staff_' + index).removeClass('hide');
                    $('select[name="staff[' + index + ']"').attr('required', 'required');
                } else {
                    $('#is_staff_' + index).addClass('hide');
                    $('select[name="staff[' + index + ']"').removeAttr('required');
                }
            });

            addMoreVendorsInputKey++;

        });
        $("body").on('click', '.remove_vendor_requests', function() {
            $(this).parents('#item_approve').remove();
        });

        $('.account-template-form-submiter').on('click', function() {
            $('input[name="account_template"]').val(account_template.getData());
        });


        $('body').on('change', '.approver_class', function() {
            addMoreVendorsInputKey = $('.list_approve select[name*="approver"]').length;

            for (let i = 0; i < addMoreVendorsInputKey; i++) {
                if ($('select[name="approver[' + i + ']"]').val() == 'staff') {
                    $('#is_staff_' + i).removeClass('hide');
                    $('select[name="staff[' + i + ']"').attr('required', 'required');
                } else {
                    $('#is_staff_' + i).addClass('hide');
                    $('select[name="staff[' + i + ']"').removeAttr('required');
                }
            }
        });


    })(jQuery);



    function edit_approval_setting(invoker, id) {
        "use strict";
        appValidateForm($('#approval-setting-form'), {
            name: 'required',
            related: 'required'
        });

        var name = $(invoker).data('name');
        var related = $(invoker).data('related');
        var setting = $(invoker).data('setting');

        $('input[name="approval_setting_id"]').val(id);

        $('#approval_setting_modal input[name="name"]').val(name);
        $('select[name="related"]').val(related).change();

        $.post(admin_url + 'purchase/get_html_approval_setting/' + id).done(function(response) {
            response = JSON.parse(response);

            $('.list_approve').html('');
            $('.list_approve').append(response);
            init_selectpicker();

        });

        $('#approval_setting_modal').modal('show');
        $('#approval_setting_modal .add-title').addClass('hide');
        $('#approval_setting_modal .edit-title').removeClass('hide');
    }

    function new_approval_setting() {
        "use strict";
        appValidateForm($('#approval-setting-form'), {
            name: 'required',
            related: 'required'
        });

        $('#approval_setting_modal input[name="name"]').val('');
        $('select[name="related"]').val('').change();

        $.post(admin_url + 'purchase/get_html_approval_setting').done(function(response) {
            response = JSON.parse(response);

            $('.list_approve').html('');
            $('.list_approve').append(response);
            init_selectpicker();
        });

        $('#approval_setting_modal').modal('show');
        $('#approval_setting_modal .add-title').removeClass('hide');
        $('#approval_setting_modal .edit-title').addClass('hide');
    }

    function purchase_order_setting(invoker) {
        "use strict";
        var input_name = invoker.value;
        var input_name_status = $('input[id="' + invoker.value + '"]').is(":checked");

        var data = {};
        data.input_name = input_name;
        data.input_name_status = input_name_status;
        $.post(admin_url + 'purchase/purchase_order_setting', data).done(function(response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
            } else {
                alert_float('warning', response.message);

            }
        });

    }

    function item_by_vendor(invoker) {
        "use strict";
        var input_name = invoker.value;
        var input_name_status = $('input[id="' + invoker.value + '"]').is(":checked");

        var data = {};
        data.input_name = input_name;
        data.input_name_status = input_name_status;
        $.post(admin_url + 'purchase/item_by_vendor', data).done(function(response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
            } else {
                alert_float('warning', response.message);

            }
        });
    }

    function show_tax_column(invoker) {
        "use strict";
        var input_name = invoker.value;
        var input_name_status = $('input[id="' + invoker.value + '"]').is(":checked");

        var data = {};
        data.input_name = input_name;
        data.input_name_status = input_name_status;
        $.post(admin_url + 'purchase/show_tax_column', data).done(function(response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
            } else {
                alert_float('warning', response.message);

            }
        });
    }

    function send_email_welcome_for_new_contact(invoker) {
        "use strict";
        var input_name = invoker.value;
        var input_name_status = $('input[id="' + invoker.value + '"]').is(":checked");

        var data = {};
        data.input_name = input_name;
        data.input_name_status = input_name_status;
        $.post(admin_url + 'purchase/send_email_welcome_for_new_contact', data).done(function(response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
            } else {
                alert_float('warning', response.message);

            }
        });
    }

    function reset_purchase_order_number_every_month(invoker) {
        "use strict";
        var input_name = invoker.value;
        var input_name_status = $('input[id="' + invoker.value + '"]').is(":checked");

        var data = {};
        data.input_name = input_name;
        data.input_name_status = input_name_status;
        $.post(admin_url + 'purchase/reset_purchase_order_number_every_month', data).done(function(response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
            } else {
                alert_float('warning', response.message);

            }
        });
    }

    function po_only_prefix_and_number(invoker) {
        "use strict";
        var input_name = invoker.value;
        var input_name_status = $('input[id="' + invoker.value + '"]').is(":checked");

        var data = {};
        data.input_name = input_name;
        data.input_name_status = input_name_status;
        $.post(admin_url + 'purchase/po_only_prefix_and_number', data).done(function(response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
            } else {
                alert_float('warning', response.message);

            }
        });
    }

    function show_item_cf_on_pdf(invoker) {
        "use strict";
        var input_name = invoker.value;
        var input_name_status = $('input[id="' + invoker.value + '"]').is(":checked");

        var data = {};
        data.input_name = input_name;
        data.input_name_status = input_name_status;
        $.post(admin_url + 'purchase/show_item_cf_on_pdf', data).done(function(response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
            } else {
                alert_float('warning', response.message);

            }
        });
    }

    function allow_vendors_to_register(invoker) {
        "use strict";
        var input_name = invoker.value;
        var input_name_status = $('input[id="' + invoker.value + '"]').is(":checked");

        var data = {};
        data.input_name = input_name;
        data.input_name_status = input_name_status;
        $.post(admin_url + 'purchase/allow_vendors_to_register', data).done(function(response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
            } else {
                alert_float('warning', response.message);

            }
        });
    }

    function new_vendor_cate() {
        "use strict";
        $('.edit-title').addClass('hide');
        $('.add-title').removeClass('hide');
        $('#vendor_cate').modal('show');
        $('#additional_vendor_cate').html('');
    }

    function edit_vendor_cate(invoker, id) {
        "use strict";
        $('.edit-title').removeClass('hide');
        $('.add-title').addClass('hide');

        $('#additional_vendor_cate').html('');
        $('#additional_vendor_cate').append(hidden_input('id', id));

        $('#vendor_cate input[name="category_name"]').val($(invoker).data('name'));
        $('#vendor_cate textarea[name="description"]').val($(invoker).data('description'));

        $('#vendor_cate').modal('show');

    }


    function reset_data(event) {
        "use strict";
        if (confirm_delete()) {
            $(event).attr("disabled", "disabled");
            $('#reset_data').submit();
        }

    }
</script>