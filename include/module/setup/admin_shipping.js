jQuery(window).on("load", function () {
	addFeedbackBanner();
	updateCurrencyField();
	init();
});
jQuery(document).ready(function () {
	jQuery("select#woocommerce_easyparcel_sender_country").change(function (e, trigger = true) {
		init();
	});

	jQuery("#woocommerce_easyparcel_enabled").change(function () {
		init();
	});

	jQuery("#woocommerce_easyparcel_integration_id").blur(function () {
		let value = jQuery("#woocommerce_easyparcel_integration_id").val()
		let country = jQuery("select#woocommerce_easyparcel_sender_country").val();
		
		if(value !== ""){
			let runConfirm = true;
			
			if(jQuery("#woocommerce_easyparcel_sender_name").val() !== "") runConfirm = false;
			if(jQuery("#woocommerce_easyparcel_sender_contact_number").val() !== "") runConfirm = false;
			if(jQuery("#woocommerce_easyparcel_sender_address_1").val() !== "") runConfirm = false;
			if(jQuery("#woocommerce_easyparcel_sender_address_2").val() !== "") runConfirm = false;
			if(jQuery("#woocommerce_easyparcel_sender_city").val() !== "") runConfirm = false;
			if(jQuery("#woocommerce_easyparcel_sender_postcode").val() !== "") runConfirm = false;
			
			if(runConfirm){
				if(confirm("Do you wish to fill in with your Easyparcel default address?")){
					jQuery("#woocommerce_easyparcel_integration_id").after('<div class="loading_bar"><div class="loader"></div></div>');
					var ajax_data = {
						action: 'get_easyparcel_default_address',
						api: value,	
						country: country,	
					};
					jQuery.ajax({
						url: ajaxurl,		
						data: ajax_data,
						type: 'POST',						
						success: function(response) {
							jQuery(".loading_bar").remove();
							let addr = response.result;
							jQuery("#woocommerce_easyparcel_easyparcel_email").val(addr.Email);
							jQuery("#woocommerce_easyparcel_sender_name").val(addr.Name);
							jQuery("#woocommerce_easyparcel_sender_company_name").val(addr.Company);
							jQuery("#woocommerce_easyparcel_sender_contact_number").val(addr.Contact);
							jQuery("#woocommerce_easyparcel_sender_alt_contact_number").val(addr.Phone);
							jQuery("#woocommerce_easyparcel_sender_address_1").val(addr.Address_1);
							jQuery("#woocommerce_easyparcel_sender_address_2").val(addr.Address_2);
							jQuery("#woocommerce_easyparcel_sender_city").val(addr.Town);
							jQuery("#woocommerce_easyparcel_sender_postcode").val(addr.Postcode);
							jQuery("#woocommerce_easyparcel_sender_state").val(addr.State);
							
						},
					});	
				}
			}
		}
	});

	jQuery("#woocommerce_easyparcel_sender_contact_number").change(function () {
		var a = jQuery("#woocommerce_easyparcel_sender_contact_number").val();
		var country = jQuery("select#woocommerce_easyparcel_sender_country").val();
		var filter = /^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/;
		var example = "";
		if (country == "MY") {
			filter = /^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/;
			example = "60164433221";
		} else if (country == "SG") {
			filter = /\65(6|8|9)[0-9]{7}$/;
			example = "6598765432";
		}

		if (filter.test(a)) {
		} else {
			alert("not an valid mobile format. Example: " + example);
		}
	});

	jQuery("#woocommerce_easyparcel_sender_alt_contact_number").change(function () {
		var a = jQuery("#woocommerce_easyparcel_sender_alt_contact_number").val();
		var country = jQuery("select#woocommerce_easyparcel_sender_country").val();
		var filter = /^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/;
		var example = "";
		if (country == "MY") {
			filter = /^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/;
			example = "60164433221";
		} else if (country == "SG") {
			filter = /\65(6|8|9)[0-9]{7}$/;
			example = "6598765432";
		}

		if (filter.test(a)) {
		} else {
			alert("not an valid mobile format. Example: " + example);
		}
	});
});

function init() {
	updateCurrencyField();
	var selected = jQuery("select#woocommerce_easyparcel_sender_country").find(":selected").val();
	if (selected != "NONE" && jQuery("#woocommerce_easyparcel_enabled").is(":checked")) {
		showdetails(selected);
		change_state(selected);
		if (obj.sender_state != null) {
			jQuery("select#woocommerce_easyparcel_sender_state").val(obj.sender_state);
		}
		if (obj.courier_service != null) {
			jQuery("select#woocommerce_easyparcel_courier_service").val(obj.courier_service);
		}
	} else {
		hidedetails(selected);
	}
}

   function updateCurrencyField() {
        var senderCountry = jQuery('#woocommerce_easyparcel_sender_country').val();
        
        // Handle case where sender_country might not be selected yet
        if (!senderCountry || senderCountry === 'NONE') {
            senderCountry = 'MY'; // Default to MY
        }
        
        var epCurrency = (senderCountry === 'SG') ? 'SGD' : 'MYR';
        var storeCurrency = epSettings.storeCurrency; // Access the PHP value here
        
        // Update currency text in description spans
        jQuery('#ep-currency-target, #ep-currency-target-2, #ep-currency-target-3').text(epCurrency);
        
        // Get the conversion rate field row
        var $conversionRateRow = jQuery('#woocommerce_easyparcel_conversion_rate').closest('tr');
        
        // Show/hide based on currency match
        if (storeCurrency === epCurrency) {
            $conversionRateRow.hide();
			jQuery('#woocommerce_easyparcel_currency_conversion_setting').hide();
        } else {
            $conversionRateRow.show();
			jQuery('#woocommerce_easyparcel_currency_conversion_setting').show();
        }
    }

function hidedetails(country) {
	jQuery("#woocommerce_easyparcel_sender_detail").hide();
	jQuery("#woocommerce_easyparcel_sender_name").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_contact_number").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_alt_contact_number").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_company_name").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_easyparcel_email").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_address_1").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_address_2").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_city").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_postcode").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_sender_state").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_integration_id").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_courier_service").closest("tr").hide();
	// jQuery("#woocommerce_easyparcel_enabled").closest("tr").hide();

	jQuery("#woocommerce_easyparcel_order_status_update_setting").hide();
	jQuery("#woocommerce_easyparcel_order_status_update_option").closest("tr").hide();

	jQuery("#woocommerce_easyparcel_addon_service_setting").hide();
	jQuery("#woocommerce_easyparcel_addon_email_option").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_addon_sms_option").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_addon_whatsapp_option").closest("tr").hide();
	jQuery("#woocommerce_easyparcel_credit_balance").hide();
}

function showdetails(country) {
	jQuery("#woocommerce_easyparcel_sender_detail").show();
	jQuery("#woocommerce_easyparcel_sender_name").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_contact_number").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_alt_contact_number").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_company_name").closest("tr").show();
	jQuery("#woocommerce_easyparcel_easyparcel_email").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_address_1").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_address_2").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_city").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_postcode").closest("tr").show();
	jQuery("#woocommerce_easyparcel_sender_state").closest("tr").show();
	jQuery("#woocommerce_easyparcel_integration_id").closest("tr").show();
	jQuery("#woocommerce_easyparcel_courier_service").closest("tr").show();
	// jQuery("#woocommerce_easyparcel_enabled").closest("tr").show();

	jQuery("#woocommerce_easyparcel_order_status_update_setting").show();
	jQuery("#woocommerce_easyparcel_order_status_update_option").closest("tr").show();
	jQuery('#woocommerce_easyparcel_credit_balance').show();
	jQuery("#woocommerce_easyparcel_addon_service_setting").show();
	jQuery("#woocommerce_easyparcel_addon_email_option").closest("tr").show();
	jQuery("#woocommerce_easyparcel_addon_sms_option").closest("tr").show();
	jQuery("#woocommerce_easyparcel_addon_whatsapp_option").closest("tr").show();
}

function clearField() {
	jQuery("#woocommerce_easyparcel_sender_contact_number").val("");
	jQuery("#woocommerce_easyparcel_sender_alt_contact_number").val("");
	jQuery("#woocommerce_easyparcel_easyparcel_email").val("");
	jQuery("#woocommerce_easyparcel_sender_address_1").val("");
	jQuery("#woocommerce_easyparcel_sender_address_2").val("");
	jQuery("#woocommerce_easyparcel_sender_city").val("");
	jQuery("#woocommerce_easyparcel_sender_state").val("");
	jQuery("#woocommerce_easyparcel_sender_postcode").val("");
	jQuery("#woocommerce_easyparcel_integration_id").val("");
	jQuery("select#woocommerce_easyparcel_courier_service").val("cheaper");
}

function change_courier($country) {
	var courier = [];
	var option = "";
	if ($country == "SG") {
		courier["EP-CS0GU"] = "Ninjavan (Collect)";
		courier["EP-CS0MU"] = "MRight";
		courier["EP-CS04G"] = "Janio";
		courier["EP-CS0Q6"] = "J&T Express"; // archived service_id 309
		courier["EP-CS0RY"] = "UrbanFox";
		courier["EP-CS0RG"] = "Qxpress";
		courier["EP-CS0RQ"] = "Mystery Saver";
		courier["EP-CS0EK"] = "Singpost";
		courier["EP-CS0NO"] = "Aramex";
		courier["EP-CS0GH"] = "Airpak Express";
		courier["EP-CS0WO"] = "XDel";
		courier["all"] = "All Couriers";
		courier["cheaper"] = "Cheapest Courier(s)";
	} else {
		courier["EP-CR0DP"] = "J&T Express";
		courier["EP-CR05"] = "Skynet";
		courier["EP-CR0AL"] = "Teleport (Support only EM)";
		courier["EP-CR0D"] = "Airpak";
		courier["EP-CR0J"] = "Ultimate Consolidators (Support only EM)";
		courier["EP-CR0W"] = "SnT Global";
		courier["EP-CR03"] = "Aramex";
		courier["EP-CR0C"] = "DHL eCommerce";
		courier["EP-CR0Z"] = "CJ Logistics";
		courier["EP-CR0O"] = "Pgeon Delivery";
		courier["EP-CR0M"] = "Nationwide Express Courier Service Berhad";
		courier["EP-CR0A"] = "Poslaju National Courier";
		courier["all"] = "All Couriers";
		courier["cheaper"] = "Cheapest Courier(s)";
	}

	for (const key in courier) {
		if (obj.sender_state == key) {
			option += `<option value="${key}" selected='selected'>${courier[key]}</option>`;
		} else {
			option += `<option value="${key}" >${courier[key]}</option>`;
		}
	}
	jQuery("select#woocommerce_easyparcel_courier_service").empty();
	jQuery("select#woocommerce_easyparcel_courier_service").append(option);
}

function change_state($country) {
	var state = "";
	if ($country == "SG") {
		jQuery("select#woocommerce_easyparcel_sender_state").closest("td").siblings("th").html('<font color="red">*</font>Zone');
		state = '<option value="central">CENTRAL</option><option value="east">EAST</option><option value="north">NORTH</option><option value="northeast">NORTHEAST</option><option value="west">WEST</option>';
	} else {
		jQuery("select#woocommerce_easyparcel_sender_state").closest("td").siblings("th").html('<font color="red">*</font>State');
		state =
			'<option value="jhr">Johor</option><option value="kdh">Kedah</option><option value="ktn">Kelantan</option><option value="kul">Kuala Lumpur</option><option value="lbn">Labuan</option><option value="mlk">Malacca</option><option value="nsn">Negeri Sembilan</option><option value="phg">Pahang</option><option value="prk">Perak</option><option value="pls">Perlis</option><option value="png">Penang</option><option value="sbh">Sabah</option><option value="srw">Sarawak</option><option value="sgr">Selangor</option><option value="trg">Terengganu</option><option value="pjy">Putra Jaya</option>';
	}
	jQuery("select#woocommerce_easyparcel_sender_state").empty();
	jQuery("select#woocommerce_easyparcel_sender_state").append(state);
}
function addFeedbackBanner() {
    // Check if we're on an EasyParcel-related page
    if (window.location.href.indexOf('section=easyparcel') > -1 || 
        window.location.href.indexOf('section=easyparcel_shipping') > -1) {

        // Create the banner HTML
        var bannerHTML = '<div id="easyparcel-feedback-banner" style="background-color: #fff; border-left: 4px solid #00a0d2; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 0 0 15px; padding: 12px 15px; position: relative; font-size: 13px; line-height: 1.5;">' +
            '<div style="margin-bottom: 8px;">' +
            '<strong>Share Your Feedback!</strong> We\'re always improving the EasyParcel WooCommerce Plugin. Let us know how we can make it better: ' +
            '<a href="https://easyparcelmarketing.typeform.com/to/lDDSz4EA" target="_blank" style="color: #0073aa; text-decoration: none;">Click Here</a>' +
            '</div>' +
            '<div style="display: inline-block; margin-right: 20px;">' +
            '<strong>Need Help?</strong> Contact Us: ' +
            '<a href="https://app.easyparcel.com/my/en/contact-us" target="_blank" style="color: #0073aa; text-decoration: none;">Malaysia</a> | ' +
            '<a href="https://app.easyparcel.com/sg/en/contact-us" target="_blank" style="color: #0073aa; text-decoration: none;">Singapore</a>' +
            '</div>' +
            '<div style="display: inline-block;">' +
            '<strong>Privacy Policy:</strong> ' +
            '<a href="https://easyparcel.com/my/en/privacy/" target="_blank" style="color: #0073aa; text-decoration: none;">Malaysia</a> | ' +
            '<a href="https://www.easyparcel.sg/privacy" target="_blank" style="color: #0073aa; text-decoration: none;">Singapore</a>' +
            '</div>' +
            '</div>';

        // Specifically target the subsubsub navigation
        jQuery('.subsubsub').before(bannerHTML);
    }
}