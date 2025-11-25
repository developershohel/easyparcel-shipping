
jQuery(document).ready(function() {
    console.log('load ready');

    // Initialize dropoff value from localized script data
    window.ep_courier_dropoff = obj.ep_courier_dropoff || '';
    
    jQuery('#ep_pickup_dropoff').change(ep_handlePickupDropoff);

    jQuery('#ep_courier').change(ep_loadDropoff);

    jQuery('#ep_courier_dropoff').change(ep_displayDropoffDetails);

    ep_handlePickupDropoff();
    ep_loadDropoff();
});

var ep_dropoff_main_list = []

const ep_handlePickupDropoff = () => {
    const pick_drop = jQuery('#ep_pickup_dropoff').val()

    if(pick_drop == "dropoff"){
        jQuery('.dropoff_field').removeClass('hide_field')
    }else{
        jQuery('.dropoff_field').addClass('hide_field')
    }
}

const ep_loadDropoff = () => {
    jQuery('#ep_dropoff_detail').html('');
    jQuery('#ep_courier_dropoff').html('<option value="">Loading...</option>')

    const courier = jQuery('#ep_courier').val()
    var data = {
        action: 'ep_get_courier_dropoff_list',
        cid: courier,
        // Add WordPress nonce for security
        nonce: obj.nonce // Make sure this is available in your localized script
    };

    jQuery.ajax({
        url: obj.ajax_url,		
        data: data,
        type: 'POST',				
        success: function(response) {

            // Check if the response was successful
            if (response.success === false) {
                console.error('AJAX Error:', response.data);
                jQuery('#ep_courier_dropoff').html('<option value="">Error: ' + response.data + '</option>');
                return;
            }
            
            // Make sure response.data is an array
            if (!Array.isArray(response.data)) {
                console.error('Expected array, got:', typeof response.data, response.data);
                jQuery('#ep_courier_dropoff').html('<option value="">Invalid response format</option>');
                return;
            }
            
            ep_dropoff_main_list = response.data;
            let content = '<option value="">Select a point</option>';
            if(response.data.length > 0){
                for(const item of ep_dropoff_main_list){
                    const isSelect = item.point_id == ep_courier_dropoff ? "selected" : ""; 
                    content += `<option value="${item.point_id}" ${isSelect}>${item.point_name}</option>`;
                }
            } else {
                content += '<option value="">No dropoff points available</option>';
            }
            jQuery('#ep_courier_dropoff').html(content);
            ep_displayDropoffDetails();
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            jQuery('#ep_courier_dropoff').html('<option value="">Connection error</option>');
        }
    });	
}

const ep_displayDropoffDetails = () => {
    if(jQuery('#ep_courier_dropoff').val() !== ''){
        ep_courier_dropoff = jQuery('#ep_courier_dropoff').val()
        const d = getDropoffDetails(jQuery('#ep_courier_dropoff').val())
        jQuery('#ep_dropoff_detail').html(`<small>
            ${d.point_name}<br>
            ${d.point_addr1}<br>
            ${d.point_addr2}<br>
            ${d.point_city} ${d.point_postcode}<br>
            tel: ${d.point_contact}<br>
        </small>`)
    }else{
        jQuery('#ep_dropoff_detail').html('');
    }
}

const getDropoffDetails = val => {
    return ep_dropoff_main_list.filter(i => i.point_id == val )[0]
}