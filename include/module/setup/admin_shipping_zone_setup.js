jQuery(document).ready(function($) {
function applyLoadedSettings() {
    const zoneSettings = shippingZoneSetupLocalizeScript.zone_setting;
    if (zoneSettings && zoneSettings.settings) {
        
        let settingType = zoneSettings.setting_type;
        
        // Smart detection: if settings is an array, it's courier mode
        if (Array.isArray(zoneSettings.settings)) {
            settingType = 'couriers';
        } else if (settingType === '0' || settingType === 0) {
            settingType = 'all'; // Default fallback for non-array settings
        }

        // Set the setting type dropdown
        jQuery('#setting_type').val(settingType);
        
        // Trigger change event to load the appropriate panel
        jQuery('#setting_type').trigger('change');
        
    }
}

// Apply settings after a small delay to ensure DOM is ready
setTimeout(applyLoadedSettings, 100);

jQuery(window).on("load", function () {
	jQuery("select#setting_type").trigger("change")

    jQuery(window).click(function(event) {
        if (jQuery(event.target).hasClass('easyparcel-modal')) {
            CloseEPModal();
        }
    });
});

jQuery("select#setting_type").change(function (current_select) {
    let settings = shippingZoneSetupLocalizeScript.zone_setting.settings
    let setting_type = jQuery(current_select.target).find(":selected").val();

    // console.log('$setting_type',setting_type);
    // console.log('start shippingZoneSetupLocalizeScript' , shippingZoneSetupLocalizeScript);
    // console.log('settings' , settings);
    // console.log('typeof settings', typeof settings);
    let panel_2 = jQuery("#shipping-zone-setting-panel-2 tbody")
    switch(setting_type){
        case "all" :
        case "cheapest" :
            if(Array.isArray(settings)){
                settings = defaultSetting();
            }

            panel_2.html(jQuery("#ep-single-setting").html());
            if(setting_type == "all"){
                panel_2.find('.label').hide()
                panel_2.find('#label').val('')
            }else{
                panel_2.find('.label').show()
                panel_2.find('#label').val(settings.label)
            }
            panel_2.find('#charges_type').val(settings.charges_type)
            panel_2.find('#charges_addon_type').val(settings.charges_addon_type)
            panel_2.find('#charges_addon_value').val(settings.charges_addon_value)
            panel_2.find('#charges_value').val(settings.charges_value)
            if(settings.is_free_shipping == '1'){
                panel_2.find('#is_free_shipping').attr('checked', 'checked')
            }else{
                panel_2.find('#is_free_shipping').removeAttr('checked')
            }
            panel_2.find('#free_shipping_type').val(settings.free_shipping_type)
            panel_2.find('#free_shipping_value').val(settings.free_shipping_value)

            panel_2.find('.courier').hide()
            changeChargesType(jQuery(`#shipping-zone-setting-panel-2 tbody`).find('#charges_type'));
            changeFreeShipping(jQuery(`#shipping-zone-setting-panel-2 tbody`).find('#is_free_shipping'));
            break;
        case "couriers" :
            if(!Array.isArray(settings)){
                settings = [];
            }

            renderCourierList(settings);
            break;
    }
    // console.log('end shippingZoneSetupLocalizeScript' , shippingZoneSetupLocalizeScript);
})

function compileCourierList(){
    let ep_row_data = jQuery("#shipping-zone-setting-panel-2 tbody").find(".row_content").find('.ep_row_data');
            
    let setting = []
    for(item of ep_row_data){
        if(jQuery(item).val() != ""){
            setting.push(JSON.parse(jQuery(item).val()))
        }
    }
    shippingZoneSetupLocalizeScript.zone_setting.settings = setting;

    renderCourierList(shippingZoneSetupLocalizeScript.zone_setting.settings)
}

function renderCourierList(settings){
    let panel_2 = jQuery("#shipping-zone-setting-panel-2 tbody")
    panel_2.html(jQuery("#ep-courier-setting").html());
    let table_content = panel_2.find(".row_content");
    renderCourierOption();

    if(settings.length > 0){
        for(const i in settings){

            const item = settings[i];
            let tmpl_row = panel_2.find(".tmpl_row").first().clone()

            tmpl_row.removeClass('tmpl_row').removeAttr('style').attr('data-id',i)
            
            // IMPROVED: Display logic - show courier_name in admin, with custom label indicator
            let displayText = item.courier_name || 'Unknown Courier';
            if (item.label && item.label.trim() !== '') {
                displayText += ' (Custom: ' + item.label + ')';
            }
            tmpl_row.find(".courier_display").html(displayText)
            
            tmpl_row.find(".rate_method").html(item.charges_type)
            tmpl_row.find(".ep_row_data").val(JSON.stringify(item))

            table_content.append(tmpl_row);
        }
    }else{
        table_content.append(`<tr><td colspan="4" class="empty_row">No records</td></tr>`)
    }
    setupSortable()
}

function CloseEPModal(){
    jQuery('.easyparcel-modal').fadeOut(300);
    jQuery('body').css('overflow', 'auto');
}

function OpenEPModal(obj){
    const row_id = jQuery(obj).closest('tr').data('id')
    let title = `<h1>Setting</h1>`
    let content = `<table class="form-table" data-id="${row_id}"><tbody>${jQuery(`#ep-single-setting`).html()}</tbody></table>`
    let buttons = `<p align="right"> <a class="button button-large" onclick="CloseEPModal()">Cancel</a> <a class="button button-primary button-large" onclick="SaveModalData('${row_id || 'new'}')">Add</a> </p>`
    
    jQuery(`#easyparcelModal .modal-body`).html(title + content + buttons);
    jQuery(`#easyparcelModal`).fadeIn(300);
    jQuery('body').css('overflow', 'hidden');
    
    if(row_id){
        // Edit Courier Setting - load existing data
        let setting = shippingZoneSetupLocalizeScript.zone_setting.settings[row_id]
        jQuery(`#easyparcelModal .modal-body`).find('#ep_courier').val(setting.courier_id)
        jQuery(`#easyparcelModal .modal-body`).find('#courier_name').val(setting.courier_name)
        jQuery(`#easyparcelModal .modal-body`).find('#label').val(setting.label || '') // Can be empty
        jQuery(`#easyparcelModal .modal-body`).find('#charges_type').val(setting.charges_type)
        jQuery(`#easyparcelModal .modal-body`).find('#charges_addon_type').val(setting.charges_addon_type)
        jQuery(`#easyparcelModal .modal-body`).find('#charges_addon_value').val(setting.charges_addon_value)
        jQuery(`#easyparcelModal .modal-body`).find('#charges_value').val(setting.charges_value)
        if(setting.is_free_shipping == '1'){
            jQuery(`#easyparcelModal .modal-body`).find('#is_free_shipping').attr('checked')
        }else{
            jQuery(`#easyparcelModal .modal-body`).find('#is_free_shipping').removeAttr('checked')
        }
        jQuery(`#easyparcelModal .modal-body`).find('#free_shipping_type').val(setting.free_shipping_type)
        jQuery(`#easyparcelModal .modal-body`).find('#free_shipping_value').val(setting.free_shipping_value)
    }else{
        // Add New Courier Setting - auto-fill courier_name, leave label empty
        let selectedCourierId = jQuery(`#easyparcelModal .modal-body`).find('#ep_courier').val();
        jQuery(`#easyparcelModal .modal-body`).find('#courier_name').val(
            shippingZoneSetupLocalizeScript.couriers[selectedCourierId].short_name
        )
        // Leave #label empty by default
    }

    changeChargesType(jQuery(`#easyparcelModal .modal-body`).find('#charges_type'))
    changeFreeShipping(jQuery(`#easyparcelModal .modal-body`).find('#is_free_shipping'));
}

function SaveModalData(id){
    CloseEPModal();
    let modal_body = jQuery(`#easyparcelModal .modal-body`)
    let data = {
        courier_id: modal_body.find('#ep_courier').val(),
        courier_name: modal_body.find('#courier_name').val(), // NEW: Save courier name
        label: modal_body.find('#label').val(), // Keep custom label (can be empty)
        charges_type: modal_body.find('#charges_type').val(),
        charges_addon_type: modal_body.find('#charges_addon_type').val(),
        charges_addon_value: modal_body.find('#charges_addon_value').val(),
        charges_value: modal_body.find('#charges_value').val(),
        is_free_shipping: modal_body.find('#is_free_shipping').prop('checked') ? 1 : 0,
        free_shipping_type: modal_body.find('#free_shipping_type').val(),
        free_shipping_value: modal_body.find('#free_shipping_value').val(),
    }

    if(id == 'new'){
        if(!Array.isArray(shippingZoneSetupLocalizeScript.zone_setting.settings)){
            shippingZoneSetupLocalizeScript.zone_setting.settings = []
        }
        shippingZoneSetupLocalizeScript.zone_setting.settings.push(data);
    }else{
        shippingZoneSetupLocalizeScript.zone_setting.settings[id] = data
    }

    renderCourierList(shippingZoneSetupLocalizeScript.zone_setting.settings)
    
}

function DeleteCourier(obj){
    jQuery(obj).closest('tr').remove()
    compileCourierList()
}

function renderCourierOption(){
    let options = [];
    for(const cid in shippingZoneSetupLocalizeScript.couriers){
        options.push(`<option value="${cid}">${shippingZoneSetupLocalizeScript.couriers[cid].short_name}</option>`)
    }
    jQuery('select[name=ep_courier]').html(options.join());
}

function changeCourier(obj){
    const id = jQuery(obj).val();
    let body = jQuery(obj).closest('tbody')
    
    // Auto-fill courier_name for admin display (hidden field)
    body.find('#courier_name').val(shippingZoneSetupLocalizeScript.couriers[id].short_name)
    
    // Leave label field empty for user to customize
    // body.find('#label').val('') // Already empty, no need to set
}

function changeChargesType(obj){
    // console.log('changeChargesType',jQuery(obj).val())

    let charges_type = jQuery(obj).val();

    switch(charges_type){
        case "member_rate" :
            jQuery(".charges_addon_type_row").hide();
            jQuery(".charges_addon_value_row").hide();
            jQuery(".charges_value_row").hide();
            break;
        case "member_rate_addon" :
            jQuery(".charges_addon_type_row").show();
            jQuery(".charges_addon_value_row").show();
            jQuery(".charges_value_row").hide();
            break;
        case "flat_rate" :
            jQuery(".charges_addon_type_row").hide();
            jQuery(".charges_addon_value_row").hide();
            jQuery(".charges_value_row").show();
            break;
    }
}

function changeFreeShipping(obj){
    if(jQuery(obj)[0].checked){
        jQuery(".free_shipping_type_row").show();
        jQuery(".free_shipping_value_row").show();
    }else{
        jQuery(".free_shipping_type_row").hide();
        jQuery(".free_shipping_value_row").hide();
    }
}

function onSaveChange(){
    let panel_1 = jQuery("#shipping-zone-setting-panel-1")
    let setting_type = panel_1.find("#setting_type").find(":selected").val();
    
    if(setting_type != 'couriers'){
        let panel_2 = jQuery("#shipping-zone-setting-panel-2")
        if(Array.isArray(shippingZoneSetupLocalizeScript.zone_setting.settings)){
            shippingZoneSetupLocalizeScript.zone_setting.settings = defaultSetting()
        }
        shippingZoneSetupLocalizeScript.zone_setting.settings
        shippingZoneSetupLocalizeScript.zone_setting.settings.label = panel_2.find("#label").val()
        shippingZoneSetupLocalizeScript.zone_setting.settings.is_free_shipping = panel_2.find("#is_free_shipping").prop('checked') ? 1 : 0
        shippingZoneSetupLocalizeScript.zone_setting.settings.free_shipping_type = panel_2.find("#free_shipping_type").val()
        shippingZoneSetupLocalizeScript.zone_setting.settings.free_shipping_value = panel_2.find("#free_shipping_value").val()
        shippingZoneSetupLocalizeScript.zone_setting.settings.charges_type = panel_2.find("#charges_type").val()
        shippingZoneSetupLocalizeScript.zone_setting.settings.charges_addon_type = panel_2.find("#charges_addon_type").val()
        shippingZoneSetupLocalizeScript.zone_setting.settings.charges_addon_value = panel_2.find("#charges_addon_value").val()
        shippingZoneSetupLocalizeScript.zone_setting.settings.charges_value = panel_2.find("#charges_value").val()
    }
    
    shippingZoneSetupLocalizeScript.zone_setting.setting_type = setting_type
    console.log('setting_type', shippingZoneSetupLocalizeScript.zone_setting)

    jQuery.ajax({
        url: ajaxurl + (ajaxurl.indexOf("?") > 0 ? "&" : "?") + "action=ep_admin_shipping_zone_save_changes",		
        data: {
            easyparcel_admin_shipping_zone_setup_nonce: shippingZoneSetupLocalizeScript.easyparcel_admin_shipping_zone_setup_nonce,
            zone_id: shippingZoneSetupLocalizeScript.zone_id,
            instance_id: shippingZoneSetupLocalizeScript.instance_id,
            setting_type: shippingZoneSetupLocalizeScript.zone_setting.setting_type,
            settings: shippingZoneSetupLocalizeScript.zone_setting.settings,
        },
        type: 'POST',						
        success: function(response) {
            console.log('response',response)
            if(response && response.success) {
                window.onbeforeunload = null;
                try {
                    showNotice('Settings saved! Refreshing page...', 'success');
                } catch (error) {
                    // Fallback to alert
                    alert('Settings saved!');
                }

                // Longer delay to see the notice
                setTimeout(function() {
                    location.reload();
                }, 3000); // Increased to 2 seconds
            } else {
                showNotice('Error saving settings. Please try again.', 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotice('Error saving settings. Please try again.', 'error');
        }
    });	
}

function defaultSetting(){
    return {
        is_legacy: false,
        label: "",
        dropoff_point: "",
        is_free_shipping: 0,
        free_shipping_type: "amount",
        free_shipping_value: 0,
        charges_type: "member_rate",
        charges_addon_type: "amount",
        charges_addon_value: 0,
        charges_value: 0,
    }
}

function showNotice(message, type = 'success') {
    // Remove any existing notices
    jQuery('.ep-notice').remove();
    
    // Create notice element
    let noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
    let notice = jQuery(`
        <div class="notice ${noticeClass} ep-notice is-dismissible" style="margin: 10px 0;">
            <p>${message}</p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        </div>
    `);
    
    // Insert notice at the top of the settings panel or after h1
    if (jQuery('.wrap h1').length) {
        jQuery('.wrap h1').after(notice);
    } else {
        jQuery('#shipping-zone-setting-panel-1').prepend(notice);
    }
    
    // Handle dismiss button
    notice.on('click', '.notice-dismiss', function() {
        notice.fadeOut(300, function() {
            notice.remove();
        });
    });
    
    // Auto-dismiss success messages after 3 seconds
    if (type === 'success') {
        setTimeout(function() {
            notice.fadeOut(300, function() {
                notice.remove();
            });
        }, 3000);
    }
}

function setupSortable(){
    jQuery( ".ui-sortable" ).sortable({ 
        handle: '.sortable-handle' ,
        axis: "y",
        cursor: "move",
        items: "tr",
        update: function(event, ui){
            compileCourierList()
        }
    });
}
window.onSaveChange = onSaveChange;
window.CloseEPModal = CloseEPModal;
window.OpenEPModal = OpenEPModal;
window.SaveModalData = SaveModalData;
window.DeleteCourier = DeleteCourier;
window.changeCourier = changeCourier;
window.changeChargesType = changeChargesType;
window.changeFreeShipping = changeFreeShipping;
window.showNotice = showNotice;
});