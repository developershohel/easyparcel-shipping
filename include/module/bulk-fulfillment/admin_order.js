jQuery(document).on("click", ".popupclose", function(){
    closeEasyParcelPopup();
});

jQuery(document).on("click", ".popup_close_icon", function(){
    closeEasyParcelPopup();
});

jQuery(document).on("click", ".ep_fulfillment_notification_popupclose", function(){
    jQuery('#easyparcel_fulfillment_notification_popup').removeClass('show');
    setTimeout(function() {
        jQuery('#easyparcel_fulfillment_notification_popup').remove();
    }, 300);
});

jQuery(document).on("click", ".ep-fulfillment-notification-popup-close-icon", function(){
    let title = jQuery("#easyparcel_fulfillment_notification_popup .popup_title").html();
    if(title == "Success") {
        location.reload(true);
    }
    jQuery('#easyparcel_fulfillment_notification_popup').removeClass('show');
    setTimeout(function() {
        jQuery('#easyparcel_fulfillment_notification_popup').remove();
    }, 300);
});

// Enhanced bulk action handler
jQuery(document).on("click", "#doaction", function(e){
    var selectedAction = document.getElementById('bulk-action-selector-top').value;
    
    if(selectedAction == 'easyparcel_order_fulfillment'){
        e.preventDefault();
        handleBulkFulfillment();
    } else if(selectedAction == 'download_easyparcel_awb') {
        e.preventDefault();
        handleBulkAWBDownload();
    }
});

function handleBulkFulfillment() {
    var orderIds = getSelectedOrderIds();
    
    if (orderIds.length === 0) {
        alert('Please select at least one order.');
        return;
    }
    
    // Store order IDs globally for the popup
    window.easyparcelBulkOrderIds = orderIds;
    
    // Add body class to prevent scrolling
    jQuery('body').addClass('popup-open');
    
    // Open the fulfillment popup
    openBulkFulfillmentPopup();
}

function handleBulkAWBDownload() {
    var orderIds = getSelectedOrderIds();
    
    if (orderIds.length === 0) {
        alert('Please select at least one order.');
        return;
    }
    
    // Show processing notification
    easyparcel_fulfillment_notification_popup('orange_text', 'EasyParcel Download AWBs', 'We are processing your AWBs, please wait for the download file.');

    var ajax_data = {
        action: 'generate_easyparcel_awb_zip',
        order_ids: orderIds,    
        security: easyparcel_orders_params.order_nonce,    
    };
    
    jQuery.ajax({
        url: ajaxurl,        
        data: ajax_data,
        type: 'POST',                        
        success: function(response) {
            console.log(response);
            jQuery('#easyparcel_fulfillment_notification_popup').remove();
            
            if (response.success) {
                // Remove any existing download links
                const existingLink = document.querySelector('.dynamic-download-link');
                if(existingLink) {
                    existingLink.remove();
                }

                // Create a new link element and trigger the download
                var link = document.createElement('a');
                link.href = response.data.download_link;
                link.download = 'AWBs.zip';
                link.classList.add('dynamic-download-link');
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                
                // Clean up
                setTimeout(function() {
                    document.body.removeChild(link);
                }, 1000);
                
                // Show success message
                easyparcel_fulfillment_notification_popup('green_text', 'Success', 'AWB ZIP file download started successfully.');
            } else {
                easyparcel_fulfillment_notification_popup('red_text', 'Download Failed', 'Error: ' + (response.data ? response.data.message : 'Unknown error occurred'));
            }
        },
        error: function(xhr, status, error) {
            jQuery('#easyparcel_fulfillment_notification_popup').remove();
            easyparcel_fulfillment_notification_popup('red_text', 'Download Failed', 'Network error occurred. Please try again.');
        }
    });            
}

function getSelectedOrderIds() {
    var checkboxName = easyparcel_orders_params.is_hpos ? 'id[]' : 'post[]';
    var checkboxes = document.getElementsByName(checkboxName);
    var orderIds = [];
    
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].type == "checkbox" && checkboxes[i].checked) {
            orderIds.push(checkboxes[i].value);
        }
    }
    
    return orderIds;
}

function closeEasyParcelPopup() {
    jQuery('#easyparcel_fulfillment_popout').removeClass('show');
    jQuery('#easyparcel_result_popup').removeClass('show');
    jQuery('body').removeClass('popup-open');
    
    setTimeout(function() {
        jQuery('#easyparcel_fulfillment_popout').remove();
        jQuery('#easyparcel_result_popup').remove();
    }, 300);
}

// Enhanced fulfillment form handler
// Form submission and shipping provider changes are handled in the main script

// Utility functions
function showerror(element){
    element.css("border-color", "red");
}

function hideerror(element){
    element.css("border-color", "");
}

// Enhanced notification popup function
function easyparcel_fulfillment_notification_popup(message_title_color, message_title, message_content) {
    message_title_color = message_title_color || '';
    message_title = message_title || '';
    message_content = message_content || '';
    
    // Remove any existing notification popup
    jQuery('#easyparcel_fulfillment_notification_popup').remove();
    
    var html = '<div id="easyparcel_fulfillment_notification_popup" class="add_fulfillment_popup" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 999999; display: none;">';
    html += '<div class="fulfillment_popup_row" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; border-radius: 5px; min-width: 500px; max-width: 90%; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);">';
    html += '<div class="popup_header" style="position: relative; padding: 15px 50px 15px 20px; background: #0073aa; color: #fff; border-radius: 5px 5px 0 0; border-bottom: 1px solid #005a87;">';
    html += '<h3 class="popup_title ' + message_title_color + '" style="margin: 0; font-size: 16px; font-weight: 600; color: #fff; padding-right: 30px;">' + message_title + '</h3>';                
    html += '<span class="dashicons dashicons-no-alt ep-fulfillment-notification-popup-close-icon" style="position: absolute; top: 15px; right: 15px; width: 20px; height: 20px; color: #fff; cursor: pointer; font-size: 18px; line-height: 1; z-index: 10;"></span>';
    html += '</div>';
    html += '<div class="popup_body" style="padding: 20px; background: #fff; text-align: center;">';
    html += message_content;
    html += '</div>';                        
    html += '</div>';
    html += '<div class="ep_fulfillment_notification_popupclose" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; cursor: pointer;"></div>';
    html += '</div>';
    
    jQuery("body").append(html);
    
    // Show the popup with fade-in effect
    jQuery('#easyparcel_fulfillment_notification_popup').fadeIn(300);
    
    // Auto-hide success messages after 5 seconds
    if (message_title_color === 'green_text' || message_title.toLowerCase().includes('success')) {
        setTimeout(function() {
            if (jQuery('#easyparcel_fulfillment_notification_popup').length) {
                jQuery('#easyparcel_fulfillment_notification_popup').fadeOut(300, function() {
                    jQuery(this).remove();
                });
            }
        }, 5000);
    }
}

// Select2 matcher function for dropoff points
function modelMatcher(params, data) {                
    data.parentText = data.parentText || "";
    
    // Always return the object if there is nothing to compare
    if (jQuery.trim(params.term) === '') {
        return data;
    }
    
    // Do a recursive check for options with children
    if (data.children && data.children.length > 0) {
        // Clone the data object if there are children
        var match = jQuery.extend(true, {}, data);
    
        // Check each child of the option
        for (var c = data.children.length - 1; c >= 0; c--) {
            var child = data.children[c];
            child.parentText += data.parentText + " " + data.text;
    
            var matches = modelMatcher(params, child);
    
            // If there wasn't a match, remove the object in the array
            if (matches == null) {
                match.children.splice(c, 1);
            }
        }
    
        // If any children matched, return the new object
        if (match.children.length > 0) {
            return match;
        }
    
        // If there were no matching children, check just the plain object
        return modelMatcher(params, match);
    }
    
    // If the typed-in term matches the text of this term, or the text from any
    // parent term, then it's a match.
    var original = (data.parentText + ' ' + data.text).toUpperCase();
    var term = params.term.toUpperCase();
    
    // Check if the text contains the term
    if (original.indexOf(term) > -1) {
        return data;
    }
    
    // If it doesn't contain the term, don't return anything
    return null;
}

// Alternative Select2 initialization function for better popup integration
function initializeSelectDropdowns() {
    if (jQuery.fn.select2) {
        // Initialize shipping provider dropdown
        if (jQuery('#shipping_provider').length && !jQuery('#shipping_provider').hasClass('select2-hidden-accessible')) {
            jQuery('#shipping_provider').select2({
                width: '100%',
                dropdownParent: jQuery('.fulfillment_popup_row'),
                placeholder: 'Select Preferred Courier Service',
                allowClear: true,
                containerCssClass: 'ep-select-container',
                dropdownCssClass: 'ep-select-dropdown'
            });
        }
        
        // Initialize drop off dropdown if it exists
        if (jQuery('#drop_off').length && !jQuery('#drop_off').hasClass('select2-hidden-accessible')) {
            jQuery('#drop_off').select2({
                width: '100%',
                dropdownParent: jQuery('.fulfillment_popup_row'),
                placeholder: '[Optional] Select Drop Off Point',
                allowClear: true,
                containerCssClass: 'ep-select-container',
                dropdownCssClass: 'ep-select-dropdown',
                matcher: modelMatcher
            });
        }
    }
}

// Initialize everything when document is ready
jQuery(document).ready(function($) {
    // Ensure datepicker is available
    if ($.fn.datepicker) {
        // Initialize datepickers for any existing elements
        $('.easyparcel-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            minDate: 0,
            maxDate: '+1Y',
            showOtherMonths: true,
            selectOtherMonths: false,
            changeMonth: true,
            changeYear: true,
            showButtonPanel: true
        });
    }
    
    // Handle escape key globally
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // Escape key
            closeEasyParcelPopup();
        }
    });
    
    // Prevent popup close when clicking inside the popup content
    $(document).on('click', '.fulfillment_popup_row, .result_popup_row', function(e) {
        e.stopPropagation();
    });
    
    // Close popup when clicking outside
    $(document).on('click', '#easyparcel_fulfillment_popout, #easyparcel_result_popup', function(e) {
        if (e.target === this) {
            closeEasyParcelPopup();
        }
    });
    
    // Handle shipping provider change with enhanced Select2 support
    $(document).on('change', '#shipping_provider', function() {
        var shipping_provider = $(this).val();
        var easyparcel_dropoff = $('#easyparcel_dropoff').val();
        $('.drop_off_field').html('');

        if (!easyparcel_dropoff) {
            return;
        }

        try {
            var easyparcel_dropoff_list = JSON.parse(easyparcel_dropoff);

            for (var i = 0; i < easyparcel_dropoff_list.length; i++) {
                if (easyparcel_dropoff_list[i][shipping_provider]) {
                    if (easyparcel_dropoff_list[i][shipping_provider].length > 0) {
                        var label = '<label for="drop_off" style="display: block; margin-bottom: 5px; font-weight: 600;">Drop Off Point:</label>';
                        var dropoff_select = '<select id="drop_off" name="drop_off" class="chosen_select drop_off_dropdown" style="width:100%;">';
                        dropoff_select += '<option value="">[Optional] Select Drop Off Point</option>';
                        
                        for (var j = 0; j < easyparcel_dropoff_list[i][shipping_provider].length; j++) {
                            dropoff_select += '<option value="' + easyparcel_dropoff_list[i][shipping_provider][j]['point_id'] + '">' + 
                                            easyparcel_dropoff_list[i][shipping_provider][j]['point_name'] + '</option>';
                        }
                        dropoff_select += '</select>';

                        $('.drop_off_field').html(label + dropoff_select);
                        
                        // Initialize Select2 for the new dropdown
                        setTimeout(function() {
                            initializeSelectDropdowns();
                        }, 100);
                    }
                }
            }
        } catch (e) {
            console.error('Error parsing dropoff data:', e);
        }
    });
    
    // Enhanced form validation
    $(document).on('blur', '#shipping_provider, #date_shipped', function() {
        var $this = $(this);
        if ($this.val() === '') {
            if ($this.attr('id') === 'shipping_provider') {
                $this.next('.select2-container').find('.select2-selection').css('border-color', 'red');
            } else {
                $this.css('border-color', 'red');
            }
        } else {
            if ($this.attr('id') === 'shipping_provider') {
                $this.next('.select2-container').find('.select2-selection').css('border-color', '#ddd');
            } else {
                $this.css('border-color', '#ddd');
            }
        }
    });
});