jQuery(document).on("click", ".ep_fulfillment_notification_popupclose", function(){
	jQuery('#easyparcel_fulfillment_notification_popup').remove();	
});

jQuery(document).on("click", ".ep-fulfillment-notification-popup-close-icon", function(){
    // Use the clicked element as a reference point
    let titleElement = jQuery(this).closest(".popup_header").find(".popup_title");
    let titleText = titleElement.text();
    
    // For backup, also try the data attribute
    let popup = jQuery(this).closest("#easyparcel_fulfillment_notification_popup");
    let isSuccess = popup.attr("data-is-success") === "true";
    
    // Remove the popup
    popup.remove();
    
    // Check if it's a success message
    if(titleText.includes("Success") || isSuccess) {
        setTimeout(function() {
            window.location.reload();
        }, 100);
    }
});

jQuery( function( $ ) {
	var easyparcel_shipping_fulfillment = {
		init: function() {
			
			// Store original tracking number to detect edit mode
			this.originalTrackingNumber = jQuery("#tracking_number").val();
			this.originalTrackingUrl = jQuery("#tracking_url").val();

			$( '#easyparcel-shipping-integration-order-fulfillment').on( 'click', 'button.button-save-form', this.save_form );
			$( '#easyparcel-shipping-integration-order-fulfillment').on( 'change', 'select#shipping_provider', this.shipping_provider );
			jQuery(document).ready(this.shipping_provider); //pre-load get dropoff list
		},

		save_form: function () {	
			var error;	
			var tracking_number = jQuery("#tracking_number");
			var tracking_url = jQuery("#tracking_url");
			var shipping_provider = jQuery("#shipping_provider");
			var pick_up_date = jQuery("#pick_up_date");
			var easycover = jQuery("#easycover");
			var easyparcel_ddp = jQuery("#easyparcel_ddp");
			var easyparcel_parcel_category = jQuery("#easyparcel_parcel_category");

			// Detect edit mode based on original tracking number (when page loaded)
			var hasExistingTracking = this.originalTrackingNumber && 
									  this.originalTrackingNumber !== '' && 
									  this.originalTrackingNumber !== undefined &&
									  this.originalTrackingNumber !== null;

			// Validate tracking number: required in edit mode, optional in create mode
			if(hasExistingTracking && (!tracking_number.val() || tracking_number.val() === '' || tracking_number.val() === undefined)){
				showerror( tracking_number );error = true;
			} else if(hasExistingTracking) {
				hideerror(tracking_number);
			}

			if(hasExistingTracking && tracking_url.length > 0 && (!tracking_url.val() || tracking_url.val() === '' || tracking_url.val() === undefined)){
				showerror( tracking_url );error = true;
			} else if(hasExistingTracking && tracking_url.length > 0) {
				hideerror(tracking_url);
			} else {
				// Empty - just skip tracking URL validation
			}

			if( shipping_provider.val() === '' ){				
				jQuery("#shipping_provider").siblings('.select2-container').find('.select2-selection').css('border-color','red');
				// error = true;
			} else{
				jQuery("#shipping_provider").siblings('.select2-container').find('.select2-selection').css('border-color','#ddd');
				hideerror(shipping_provider);
			}

			if( pick_up_date.val() === '' ){				
				showerror( pick_up_date );error = true;
			} else{
				hideerror(pick_up_date);				
			}

			if(easycover.prop('checked') || easyparcel_ddp.prop('checked')){
				if(easyparcel_parcel_category.val() === ''){
					easyparcel_parcel_category.siblings('.select2-container').find('.select2-selection').css('border-color','red');
					error = true;
				}else{
					easyparcel_parcel_category.siblings('.select2-container').find('.select2-selection').css('border-color','#ddd');
				}
			}
			
			if(error == true){
				return false;
			}
			// if ( !$( 'input#tracking_number' ).val() ) { #EDIT CASE NEED CHECK
			// 	return false;
			// }

			$( '#easyparcel-fulfillment-form' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
						
			var product_data = [];
			jQuery(".ASTProduct_row").each(function(index){
				var ASTProduct_qty = jQuery(this).find('input[type="number"]').val();
				if(ASTProduct_qty > 0){
					product_data.push({
						product: jQuery(this).find('.product_id').val(),				
						qty: jQuery(this).find('input[type="number"]').val(),				
					});					
				}
			});	
			
			var jsonString = JSON.stringify(product_data);						
			var data = {
				action:                   'wc_shipment_tracking_save_form',
				order_id:                 woocommerce_admin_meta_boxes.post_id,
				shipping_provider:        $( '#shipping_provider' ).val(),
				courier_name:   		  $( '#shipping_provider' ).find('option:selected').text(),
				drop_off_point:			  $( '#drop_off' ).val(),
				pick_up_date:			  $( '#pick_up_date' ).val(),
				easycover:			  	  $( '#easycover' ).prop('checked'),
				easyparcel_ddp:			  $( '#easyparcel_ddp' ).prop('checked'),
				easyparcel_parcel_category:	$( '#easyparcel_parcel_category' ).val(),
				tracking_number:          $( 'input#tracking_number' ).val(),
				tracking_url:          	  $( 'input#tracking_url' ).val(),
				date_shipped:             $( 'input#date_shipped' ).val(),
				productlist: 	          jsonString, 
				security:                 $( '#easyparcel_fulfillment_create_nonce' ).val()
			};

			jQuery.ajax({
				url: woocommerce_admin_meta_boxes.ajax_url,		
				data: data,
				type: 'POST',				
				success: function(response) {
					$( '#easyparcel-fulfillment-form' ).unblock();
					if ( response.startsWith('success')	) {
						$( '#easyparcel-shipping-integration-order-fulfillment #tracking-items' ).append( response );
						$( '#easyparcel-shipping-integration-order-fulfillment button.button-show-tracking-form' ).show();
						// $( '#shipping_provider' ).selectedIndex = 0;
						// $( 'input#tracking_number' ).val( '' );
						// $( 'input#tracking_url' ).val( '' );
						// $( 'input#date_shipped' ).val( '' );
						jQuery('#order_status').val('wc-completed');
						jQuery('#order_status').select2().trigger('change');	
						jQuery('#post').before('<div id="order_updated_message" class="updated notice notice-success is-dismissible"><p>Order updated.</p><button type="button" class="notice-dismiss update-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>');

						if(response == "successEdit")
							easyparcel_fulfillment_notification_popup('green_text','Edit Success', "success")
						else
							easyparcel_fulfillment_notification_popup('green_text','Fulfillment Success', response)

						return false;
					}else{
						easyparcel_fulfillment_notification_popup('red_text','Fulfillment Failed',response)
					}

				},
				error: function(response) {
					console.log('Error response:', response);			
				}
			});			
			return false;
		},

		shipping_provider: function () {

			var shipping_provider = $( '#shipping_provider' ).val();
			var easyparcel_dropoff = $( '#easyparcel_dropoff' ).val();
			var selected_easyparcel_dropoff = $( '#selected_easyparcel_dropoff' ).val();
			$('.drop_off_field').html('');
			var easyparcel_dropoff_list = JSON.parse(easyparcel_dropoff);

			for(let i = 0; i < easyparcel_dropoff_list.length; i++){
				if(easyparcel_dropoff_list[i][shipping_provider]){ // if dropoff exist
					if(easyparcel_dropoff_list[i][shipping_provider].length > 0){ // check records
						var label = '<label for="drop_off">Drop Off Point:</label><br/>';
						var dropoff_select = '<select id="drop_off" name="drop_off" class="chosen_select drop_off_dropdown" style="width:100%;">';
						dropoff_select += '<option value="">[Optional] Select Drop Off Point</option>';
						for(let j = 0; j < easyparcel_dropoff_list[i][shipping_provider].length; j++){
							var selected = ( easyparcel_dropoff_list[i][shipping_provider][j]['point_id'] == selected_easyparcel_dropoff ) ? 'selected' : '';
							dropoff_select += '<option value="'+easyparcel_dropoff_list[i][shipping_provider][j]['point_id']+'" '+selected+'>'+easyparcel_dropoff_list[i][shipping_provider][j]['point_name']+'</option>';
						}
						dropoff_select += '</select>';

						if(!$('#tracking_number').length){
							$('.drop_off_field').html(label+dropoff_select);
							jQuery('#drop_off').select2({
								matcher: modelMatcher
							});
						}
						
					}
				}
				
			}

			let has_easycover = easyparcel_easycover.includes(shipping_provider);
			let has_ddp = easyparcel_coureierDDP.includes(shipping_provider);
			
			(has_easycover || has_ddp) ? $('.easyparcel-add-on').show() : $('.easyparcel-add-on').hide()
			$('#easycover,#easyparcel_ddp').prop('checked') ? $('.parcel_category_field').show() : $('.parcel_category_field').hide()
			has_easycover ? $('#easycover_field').show() : $('#easycover_field').hide()
			has_ddp ? $('#easyparcel_ddp_field').show() : $('#easyparcel_ddp_field').hide()

		}
	}

	easyparcel_shipping_fulfillment.init();
} );
jQuery(document).on("click", ".update-dismiss", function(){	
	jQuery('#order_updated_message').fadeOut();
});
function showerror(element){
	element.css("border-color","red");
}
function hideerror(element){
	element.css("border-color","");
}

jQuery(document).ready(function() {
	jQuery('#shipping_provider').select2({
		matcher: modelMatcher
	});

	jQuery("#easycover").click((o) => {
		const order_total = jQuery(".total bdi").html().replace(/[^0-9.]/g, '');
		const insurance_basic_coverage = easyparcel_insurance_basic_coverage[jQuery("#shipping_provider").val()];

		if(insurance_basic_coverage.basic_coverage < Number(order_total)){
			let message_title = 'Add-On Services: EasyCover (Additional charges may be applied)';
			let message_content = 
			`<p>
				This shipment is fully protected under EasyCover from lost (based on your parcel value or up to RM10000, whichever is lower).
				Highly recommend if you’re sending expensive items. <a href="https://blog.easyparcel.com/my/easycover/" target="_blank">View More Details</a>
			</p>
			<p>
				By clicking Agree, I have read, understood and agree to EasyCover’s <a href="https://easyparcel.com/my/en/easycover-tnc/" target="_blank">Terms & Conditions</a>. 
				If I wrongly declare the parcel content/value/item category or my parcel content/item category falls on an 
				<a href="https://helpcentre-my.easyparcel.com/support/solutions/articles/9000196016-what-is-easycover-insurance-on-easyparcel-#items" target="_blank">exclusion list</a>, 
				EasyCover protection will be void.
			</p>`;
			if(easyparcel_account_country == 'SG'){
				message_content = 
				`<p>
					This shipment is fully protected under EasyCover from lost (based on your parcel value or up to S$5000, whichever is lower). 
					Highly recommend if you’re sending expensive items. <a href="https://blog.easyparcel.com/sg/easycover/" target="_blank">View More Details</a>
				</p>
				<p>
					By clicking Agree, I have read, understood and agree to EasyCover’s <a href="https://easyparcel.com/sg/en/easycover-tnc" target="_blank">Terms & Conditions</a>. 
					If I wrongly declare the parcel content/value/item category or my parcel content/item category falls on an 
					<a href="https://helpcentre-sg.easyparcel.com/support/solutions/articles/9000224715-what-is-easycover-insurance-on-easyparcel-#items" target="_blank">exclusion list</a>, 
					EasyCover protection will be void.
				</p>`;
			}
			let footer = `<button class="button-secondary" onclick="easycover_popup_disagree()">Disagree</button>
			<button class="button-primary" onclick="easycover_popup_agree()">Agree</button>`;
			
			easyparcel_fulfillment_notification_popup('orange_text',message_title, message_content, footer, false)
		}else{
			let message_title = 'Add-On Services: EasyCover';
			let message_content = 
			`<p>
				Your order total still under the basic coverage of EasyCover.<br>
				You do not required to purchase EasyCover.
			</p>`;
			let footer = `<button class="button-primary" onclick="easycover_popup_disagree()">Acknowledged</button>`;
			
			easyparcel_fulfillment_notification_popup('orange_text',message_title, message_content, footer, false)
		}

	})

	jQuery("#easyparcel_ddp").click((o) => {
		let message_title = 'Add-On Services: Delivered Duty Paid (DDP) (Additional charges may be applied)';
		let message_content = 
		`<p>
			Enable DDP to speed and smooth out the shipment process and enhance your customer's experience. 
			<a href="https://helpcentre-my.easyparcel.com/support/solutions/articles/9000224000-what-is-delivery-duty-paid-ddp-and-delivery-duty-unpaid-ddu-" target="_blank">View More Details</a>
		</p>`;
		if(easyparcel_account_country == 'SG'){
			message_content = 
			`<p>
				Enable DDP to speed and smooth out the shipment process and enhance your customer's experience. 
				<a href="https://helpcentre-sg.easyparcel.com/support/solutions/articles/9000224730-which-courier-is-supporting-delivery-duty-unpaid-ddu-or-delivery-duty-paid-ddp-" target="_blank">View More Details</a>
			</p>`;
		}
		let footer = `<button class="button-secondary" onclick="ep_ddp_popup_disagree()">Disable</button>
		<button class="button-primary" onclick="ep_ddp_popup_agree()">Enable</button>`;
		
		easyparcel_fulfillment_notification_popup('orange_text',message_title, message_content, footer, false)
	})

	
	jQuery('#easyparcel_parcel_category').change((o) => {
		if(easyparcel_easycover_exclusion.includes(Number(jQuery('#easyparcel_parcel_category').val()))){
			let message_title = 'Warning!!';
			let message_content = 
			`<p>
				Oops! Your chosen item category falls under EasyCover exclusion list and is not covered by EasyCover. 
				Refer to the <a href="https://helpcentre-my.easyparcel.com/support/solutions/articles/9000196016-what-is-easycover-insurance-on-easyparcel-" target="_blank">exclusion list</a>
				for more info. No refund on EasyCover charges will be given if you would love to proceed.
			</p>`;
			if(easyparcel_account_country == 'SG'){
				message_content = 
				`<p>
				Oops! Your chosen item category falls under EasyCover exclusion list and is not covered by EasyCover. 
				Refer to the <a href="https://helpcentre-sg.easyparcel.com/support/solutions/articles/9000224715-what-is-easycover-insurance-on-easyparcel-" target="_blank">exclusion list</a>
				for more info. No refund on EasyCover charges will be given if you would love to proceed.
			</p>`;
			}
			let footer = `<button class="button-primary" onclick="ep_parcel_category_continue()">Acknowledged</button>`;
			easyparcel_fulfillment_notification_popup('orange_text',message_title, message_content, footer, false)
		}
	})

});


function ep_parcel_category_continue(){
	jQuery('#easyparcel_fulfillment_notification_popup').remove();
	jQuery('#easyparcel_parcel_category').siblings('.select2-container').find('.select2-selection').css('border-color','#ddd');
}

function easycover_popup_disagree(){
	jQuery("#easycover").prop("checked", false);
	jQuery('#easyparcel_fulfillment_notification_popup').remove();
	(jQuery('#easycover').prop('checked') || jQuery('#easyparcel_ddp').prop('checked')) ? jQuery('.parcel_category_field').show() : jQuery('.parcel_category_field').hide()
}

function easycover_popup_agree(){
	jQuery("#easycover").prop("checked", true);
	jQuery('#easyparcel_fulfillment_notification_popup').remove();
	jQuery('.parcel_category_field').show()
	jQuery('#easyparcel_parcel_category').siblings('.select2-container').find('.select2-selection').css('border-color','#ddd');
}

function ep_ddp_popup_disagree(){
	jQuery("#easyparcel_ddp").prop("checked", false);
	jQuery('#easyparcel_fulfillment_notification_popup').remove();
	(jQuery('#easycover').prop('checked') || jQuery('#easyparcel_ddp').prop('checked')) ? jQuery('.parcel_category_field').show() : jQuery('.parcel_category_field').hide()
}

function ep_ddp_popup_agree(){
	jQuery("#easyparcel_ddp").prop("checked", true);
	jQuery('#easyparcel_fulfillment_notification_popup').remove();
	jQuery('.parcel_category_field').show()
	jQuery('#easyparcel_parcel_category').siblings('.select2-container').find('.select2-selection').css('border-color','#ddd');
}

function modelMatcher (params, data) {				
	data.parentText = data.parentText || "";
	
	// Always return the object if there is nothing to compare
	if (jQuery.trim(params.term) === '') {
		return data;
	}
	
	// Do a recursive check for options with children
	if (data.children && data.children.length > 0) {
		// Clone the data object if there are children
		// This is required as we modify the object to remove any non-matches
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

function easyparcel_fulfillment_notification_popup(message_title_color = '', message_title = '', message_content ='', footer = '', popup_close_icon = true){
	let html = '';
	html += `<div id="easyparcel_fulfillment_notification_popup" class="add_fulfillment_popup" >`;
		html += `<div class="fulfillment_popup_row">`;
			html += `<div class="popup_header">`
				html += `<h3 class="popup_title ${message_title_color}">${message_title}</h3>`;				
				popup_close_icon ? html += `<span class="dashicons dashicons-no-alt ep-fulfillment-notification-popup-close-icon"></span>` : '';
			html += `</div>`;
			html += `<div class="popup_body">${message_content}</div>`;

			if(footer !== ''){
			html += `<div class="popup_footer">${footer}</div>`;
			}
			
		html += `</div>`;
		html += `<div class="ep_fulfillment_notification_popupclose"></div>`;
	html += `</div>`;
	jQuery("body").append(html);
	
}

function showerror(element){
	element.css("border","1px solid red");
}
function hideerror(element){
	element.css("border","1px solid #ddd");
}
