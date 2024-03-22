let wcShippingZoneMethods = jQuery('.wc-shipping-zone-methods.widefat')
let wcShippingZoneAddMethod = jQuery('.wc-shipping-zone-add-method')
let wcShippingZoneAddMethodSave = jQuery('.wc-shipping-zone-method-save')
let wcAddShippingEvent = 'addCourier'
let wcDeleteShippingEvent = 'deleteCourier'
let wcShippingSetting = wcShippingZoneMethods.find('tbody').find('.wc-shipping-zone-method-title').find('.wc-shipping-zone-method-settings')
const body = jQuery('body')

function save_shipping_method_button() {
    const zone_name = jQuery('#zone_name')
    const zone_regions = jQuery('.select2-selection__rendered')
    const zone = zone_regions.find('.select2-selection__choice')
    if (zone_name.val() === '') {
        zone_name.focus()
        return false;
    }
    setTimeout(function () {
        let saveCourierModel = jQuery('.blockUI.blockOverlay')
        let saveCourierInterval = setInterval(function () {
            if (saveCourierModel.length === 0) {
                clearInterval(saveCourierInterval)
                wc_shipping_setting()
            } else {
                saveCourierModel = jQuery('.blockUI.blockOverlay')
            }
        }, 300)
        setTimeout(function () {
            clearInterval(saveCourierInterval)
        }, 5000)
    }, 500)
}

function add_shipping_method_button() {
    const zone_name = jQuery('#zone_name')
    const zone_regions = jQuery('.select2-selection__rendered')
    const zone = zone_regions.find('.select2-selection__choice')
    let shippingModelInterval = setInterval(function () {
        let wcBackboneModalMain = jQuery('#wc-backbone-modal-dialog')
        if (wcBackboneModalMain.length > 0) {
            wcBackboneModalMain.css('display', 'block')
            clearInterval(shippingModelInterval)
            let addMethodButton = wcBackboneModalMain.find('footer').find('button')
            let addMethodButtonInterval = setInterval(function () {
                if (addMethodButton.length > 0) {
                    clearInterval(addMethodButtonInterval)
                    if (addMethodButton.attr('id') === 'btn-ok') {
                        clearInterval(addMethodButtonInterval)
                        addMethodButton.attr('onclick', 'wc_shipping_setting()')
                    } else {
                        addMethodButton.on('click', function () {
                            let findSubmitButtonInterval = setInterval(function () {
                                let methodSubmitButton = jQuery('#wc-backbone-modal-dialog').find('#btn-ok')
                                if (methodSubmitButton.length > 0) {
                                    clearInterval(findSubmitButtonInterval)
                                    methodSubmitButton.attr('onclick', 'wc_shipping_setting()')
                                } else {
                                    methodSubmitButton = jQuery('#wc-backbone-modal-dialog').find('#btn-ok')
                                }
                            }, 300)
                            setTimeout(function () {
                                clearInterval(findSubmitButtonInterval)
                            }, 3000)
                        })
                    }
                } else {
                    addMethodButton = wcBackboneModalMain.find('footer').find('button')
                }
            }, 300)
            setTimeout(function () {
                clearInterval(addMethodButtonInterval)
            }, 5000)
        } else {
            wcBackboneModalMain = jQuery('#wc-backbone-modal-dialog')
        }
    }, 300)
    setTimeout(function () {
        clearInterval(shippingModelInterval)
    }, 20000)
}

let addShippingCourier = wcShippingZoneMethods.find('tbody.wc-shipping-zone-method-rows.ui-sortable').find('.wc-shipping-zone-method-title').find('.easyparcel-add-courier')

function add_shipping_courier_attributes() {
    let addShippingCourierInterval = setInterval(function () {
        addShippingCourier = wcShippingZoneMethods.find('tbody.wc-shipping-zone-method-rows.ui-sortable').find('.wc-shipping-zone-method-title').find('.easyparcel-add-courier')
        if (addShippingCourier.length > 0) {
            clearInterval(addShippingCourierInterval)
            addShippingCourier.attr('onclick', 'add_shipping_courier(this)')
        } else {
            addShippingCourier = wcShippingZoneMethods.find('tbody.wc-shipping-zone-method-rows.ui-sortable').find('.wc-shipping-zone-method-title').find('.easyparcel-add-courier')
        }
    }, 300)
    setTimeout(function () {
        clearInterval(addShippingCourierInterval)
    }, 20000)
}

let deleteShippingMethod = wcShippingZoneMethods.find('tbody.wc-shipping-zone-method-rows.ui-sortable').find('.wc-shipping-zone-method-title').find('.wc-shipping-zone-method-delete')

function delete_shipping_method() {
    let deleteShippingMethodInterval = setInterval(function () {
        deleteShippingMethod = wcShippingZoneMethods.find('tbody.wc-shipping-zone-method-rows.ui-sortable').find('.wc-shipping-zone-method-delete.wc-shipping-zone-actions')
        if (deleteShippingMethod.length > 0) {
            clearInterval(deleteShippingMethodInterval)
            setTimeout(function () {
                let deleteOverlay = jQuery('.blockUI.blockOverlay')
                let deleteOverlayInterval = setInterval(function () {
                    if (deleteOverlay.length === 0) {
                        clearInterval(deleteOverlayInterval)
                        wc_shipping_setting()
                    } else {
                        deleteOverlay = jQuery('.blockUI.blockOverlay')
                    }
                }, 300)
                setTimeout(function () {
                    clearInterval(deleteOverlayInterval)
                }, 20000)
            }, 1000)
        } else {
            deleteShippingMethod = wcShippingZoneMethods.find('tbody.wc-shipping-zone-method-rows.ui-sortable').find('.wc-shipping-zone-method-delete.wc-shipping-zone-actions')
        }
    }, 300)
    setTimeout(function () {
        clearInterval(deleteShippingMethodInterval)
    }, 20000)
}

function wc_shipping_setting() {
    setTimeout(function () {
        let wcShippingSettingInterval = setInterval(function () {
            let deleteOverlay = jQuery('.blockUI.blockOverlay')
            if (deleteOverlay.length === 0) {
                wcShippingZoneMethods = jQuery('.wc-shipping-zone-methods.widefat')
                let wcShippingList = wcShippingZoneMethods.find('tbody.wc-shipping-zone-method-rows.ui-sortable')
                if (wcShippingList.length > 0) {
                    const wcShippingSetting = wcShippingList.find('.wc-shipping-zone-method-title')
                    clearInterval(wcShippingSettingInterval)
                    wcShippingSetting.each(function () {
                        const setting = jQuery(this);
                        if (setting.text().trim() == 'EasyParcel Shipping') {
                            const button = setting.parent().find('.wc-shipping-zone-actions').find('a');
                            const editButton = jQuery(button[0])
                            const deleteButton = jQuery(button[1])
                            editButton.text('Add/Edit Courier')
                            if (!editButton.hasClass('easyparcel-add-courier')) {
                                editButton.addClass('easyparcel-add-courier')
                            }
                            editButton.attr('onclick', 'add_shipping_courier(this)')
                            deleteButton.text('Delete Courier')
                            deleteButton.attr('onclick', 'delete_shipping_method()')
                        } else {
                            const button = setting.parent().find('.wc-shipping-zone-actions').find('a');
                            const editButton = jQuery(button[0])
                            const deleteButton = jQuery(button[1])
                            editButton.attr('onclick', 'shipping_edit_button()')
                            deleteButton.attr('onclick', 'delete_shipping_method()')
                        }
                    })
                    wcShippingZoneAddMethod.attr('onclick', 'add_shipping_method_button()')
                    wcShippingZoneAddMethodSave.attr('onclick', 'save_shipping_method_button()')
                } else {
                    wcShippingList = wcShippingZoneMethods.find('tbody.wc-shipping-zone-method-rows.ui-sortable')
                }
            } else {
                deleteOverlay = jQuery('.blockUI.blockOverlay')
            }
        }, 300)
    }, 300)
}

function add_shipping_courier(element) {
    const parentElement = jQuery(element)
    const parentInstanceID = instance_id(parentElement.attr('href'))
    parentElement.closest('td').siblings('.wc-shipping-zone-method-description').attr('data-instance_id', parentInstanceID)
    const zone_name = jQuery('#zone_name')
    const nonce = obj.easyparcel_nonce
    const zone_regions = jQuery('.select2-selection__rendered')
    const zone = zone_regions.find('.select2-selection__choice')
    let courierModel = jQuery('#wc-backbone-modal-dialog')
    let courierModelInterval = setInterval(function () {
        if (courierModel.length > 0) {
            jQuery('.easyparcel-nonce').closest('fieldset').css({display: 'none'})
            courierModel.css({display: 'block'})
            clearInterval(courierModelInterval)
            let courierModelForm = courierModel.find('form')
            const instance_id = courierModelForm.find('[name=instance_id]').val()
            const cloneForm = courierModelForm.clone()
            courierModelForm.css({display: 'none'})
            courierModelForm.empty()
            courierModelForm.css({display: 'block'})
            const courierModelHeader = jQuery('.wc-backbone-modal-header')
            if (courierModelHeader.length > 0) {
                courierModelHeader.children('h1').html('EasyParcel Courier Settings')
            }
            courierModelForm.css({'min-height': '200px'})
            const spinner = courierModelForm.find('#spinner')
            if (spinner.length === 0) {
                courierModelForm.append('<span class="dashicons dashicons-update-alt" id="spinner"></span>')
            }
            const saveCourierButton = jQuery('#wc-backbone-modal-dialog').find('footer').find('#btn-ok')
            const zoneId = zone_id()
            jQuery.ajax({
                url: ajax_object.ajax_url, method: 'post', data: {
                    action: 'easyparcel_check_setting', zone_id: zoneId, nonce: nonce
                }, beforeSend: function () {
                    jQuery('#spinner').css('display', 'block');
                    saveCourierButton.attr('disabled', 'disabled');
                }, success: function (data) {
                    console.log(data)
                    const jsonData = JSON.parse(data)
                    if (jsonData.status === false) {
                        courierModelForm.parent().html(cloneForm)
                        courierModelForm.css('display', 'block')
                        saveCourierButton.removeAttr('disabled')
                        saveCourierButton.on('click', function () {
                            wc_shipping_setting()
                        })
                    } else {
                        jQuery.ajax({
                            url: ajax_object.ajax_url,
                            method: 'post',
                            data: {
                                action: 'easyparcel_courier_list',
                                zone_id: zoneId,
                                instance_id: instance_id,
                                nonce: nonce
                            }, beforeSend: function () {
                                jQuery('#spinner').css('display', 'block');
                                saveCourierButton.attr('disabled', 'disabled');
                            }, success: function (data) {
                                console.log(data)
                                courierModelForm.html(`<table class="form-table">${data}</table><input type="hidden" name="instance_id" value="${instance_id}">`);
                            }, complete: function () {
                                console.log('completed');
                                jQuery('#spinner').css('display', 'none');
                                saveCourierButton.removeAttr('disabled');
                                save_courier();
                            }, error: function (xhr, status, error) {
                                console.log(error);
                            }
                        })
                    }
                }, error: function (xhr, status, error) {
                    console.log(error);
                    courierModelForm.parent().html(cloneForm)
                    return false;
                }
            })
        } else {
            courierModel = jQuery('#wc-backbone-modal-dialog')
        }
    }, 300)
    setTimeout(function () {
        clearInterval(courierModelInterval)
    }, 10000)
}

function ep_validate_courier_data() {
    const modalDialog = jQuery('#wc-backbone-modal-dialog, #mainform ')
    const courier_services = modalDialog.find('#courier_services')
    const delivery_day = jQuery('#courier_delivery_days')
    const courier_value = modalDialog.find('#courier_data')
    const charges = modalDialog.find('#charges')
    const shipping_rate_option = modalDialog.find('#shipping_rate_option')
    const charges_value = modalDialog.find('#charges_value')
    const free_shipping = modalDialog.find('#free_shipping')
    const free_shipping_by = modalDialog.find('#free_shipping_by')
    const free_shipping_text = modalDialog.find('#free_shipping_text')
    const free_shipping_value = modalDialog.find('#free_shipping_value')
    const courier_service_img = modalDialog.find('#courier_service_img')
    const dropoff_point = modalDialog.find('#dropoff_point').children('.selected')
    const courier_dropoff_panel = modalDialog.find('#courier_dropoff_panel')
    const courier_display_name = modalDialog.find('#courier_display_name')
    const courier_delivery_days = modalDialog.find('#courier_delivery_days')
    const instance_id = modalDialog.find('[name="instance_id"]').val() !== '' ? modalDialog.find('[name="instance_id"]').val() : jQuery('#instance_id').val()
    const courier_data = {}
    courier_data['zone_id'] = zone_id() !== null ? zone_id() : modalDialog.find('#zone_id').val()
    courier_data['instance_id'] = instance_id
    const servicesData = courier_services.children('.selected').length > 0 ? courier_services.children('.selected') : jQuery(courier_services.children()[0])
    const service_name = servicesData.data('service_name')
    const service_id = servicesData.data('service_id')
    const services_type = servicesData.data('services_type')
    const courier_id = servicesData.data('courier_id')
    const courier_name = servicesData.data('courier_name')
    const courier_logo = servicesData.data('courier_logo')
    const courier_info = servicesData.data('courier_info')
    const sample_cost = servicesData.data('sample_cost')
    const dropoff = servicesData.data('dropoff')
    const servicesVal = courier_services.children('.selected')
    if (dropoff == 'yes') {
        courier_data['courier_dropoff_point'] = dropoff_point.val()
        courier_data['courier_dropoff_name'] = dropoff_point.data('dropoff_name')
    }
    courier_data['courier_display_name'] = courier_display_name.val()
    courier_data['service_id'] = service_id
    courier_data['service_name'] = service_name
    courier_data['service_type'] = services_type
    courier_data['courier_id'] = courier_id
    courier_data['courier_name'] = courier_name
    courier_data['courier_logo'] = courier_logo
    courier_data['courier_info'] = courier_info
    courier_data['sample_cost'] = sample_cost
    courier_data['courier_info'] = delivery_day.val()

    const chargesVal = charges.val()
    courier_data['charges'] = chargesVal
    if (chargesVal == 4) {
        courier_data['charges_value'] = `${shipping_rate_option.val()}:${charges_value.val()}`
    } else if (chargesVal == 1) {
        courier_data['charges_value'] = `${shipping_rate_option.val()}:${charges_value.val()}`
    } else {
        courier_data['charges_value'] = 0
    }
    if (free_shipping.prop('checked')) {
        courier_data['free_shipping'] = 1
        courier_data['free_shipping_by'] = free_shipping_by.val()
        courier_data['free_shipping_value'] = free_shipping_value.val()
    } else {
        courier_data['free_shipping'] = 0
    }
    courier_data['status'] = 1

    return courier_data
}

function save_courier() {
    const modalDialog = jQuery('#wc-backbone-modal-dialog, #courier-setting-table')
    //modalDialog.off()
    const article = jQuery('article.wc-modal-shipping-method-settings');
    const saveCourierButton = modalDialog.find('footer').find('#btn-ok')
    const modalClose = modalDialog.find('.modal-close')
    const instance_id = modalDialog.find('[name="instance_id"]').val()
    const courier_services = modalDialog.find('#courier_services')
    const courier_value = modalDialog.find('#courier_data')
    const charges = modalDialog.find('#charges')
    const shipping_rate_option = modalDialog.find('#shipping_rate_option')
    const shipping_rate_option_panel = modalDialog.find('#shipping_rate_option_panel')
    const charges_value_panel = modalDialog.find('#charges_shipping_rate_panel')
    const charges_value = modalDialog.find('#charges_value')
    const free_shipping = modalDialog.find('#free_shipping')
    const free_shipping_tab = modalDialog.find('#free_shipping_tab')
    const free_shipping_by = modalDialog.find('#free_shipping_by')
    const free_shipping_text = modalDialog.find('#free_shipping_text')
    const free_shipping_value_panel = modalDialog.find('#free_shipping_tab_value_panel')
    const free_shipping_value = modalDialog.find('#free_shipping_value')
    const courier_service_img = modalDialog.find('#courier_service_img')
    const dropoff_point = modalDialog.find('#dropoff_point')
    const dropoff_point_panel = modalDialog.find('#courier_dropoff_panel')
    const courier_display_name = modalDialog.find('#courier_display_name')
    const courier_display_panel = modalDialog.find('#courier_display_name_panel')
    const courier_delivery_days = modalDialog.find('#courier_delivery_days')
    const courier_delivery_panel = modalDialog.find('#courier_delivery_panel')
    courier_services.on('change', function (e) {
        const servicesVal = jQuery(this).val()
        jQuery(this).children(`[value=${servicesVal}]`).addClass('selected').siblings().removeClass('selected')
        const servicesData = jQuery(this).children('.selected')
        const service_id = servicesData.data('service_id')
        const courier_name = servicesData.data('courier_name')
        const courier_logo = servicesData.data('courier_logo')
        const courier_info = servicesData.data('courier_info')
        const dropoff = servicesData.data('dropoff')
        if (service_id === 'all' || service_id === 'cheapest') {
            dropoff_point_panel.css({display: 'none'})
            courier_display_panel.css({display: 'revert'})
            courier_display_name.val(courier_name)
            courier_delivery_panel.css({display: 'none'})
        } else {
            if (dropoff == 'yes') {
                const dropoffParent = modalDialog.find(`#${service_id}`)
                const dropoffElements = dropoffParent.children().clone()
                dropoff_point.html(dropoffElements)
                dropoff_point_panel.css({display: 'revert'})
                if (jQuery('#dropoff_point').children('.selected').length === 0) {
                    jQuery(dropoff_point.children()[0]).addClass('selected').attr('selected', 'selected').siblings().removeClass('selected').removeAttr('selected')
                }
            } else {
                dropoff_point_panel.css({display: 'none'})
                dropoff_point.empty()
            }
            courier_service_img.attr('src', courier_logo)
            courier_display_panel.css({display: 'revert'})
            courier_delivery_panel.css({display: 'revert'})
            courier_display_name.val(courier_name)
            courier_delivery_days.val(courier_info)
        }
    })
    const servicesElement = courier_services.children('.selected')
    if (servicesElement.length === 0) {
        jQuery(courier_services.children()[0]).addClass('selected').attr('selected', 'selected').siblings().removeClass('selected').removeAttr('selected')
    }
    if (courier_services.val() == 'all') {
        courier_display_panel.css({display: 'revert'})
        courier_display_name.val(courier_services.children('.selected').data('courier_name'))
    } else {
        const servicesData = courier_services.children('.selected')
        const service_id = servicesData.data('service_id')
        const courier_name = servicesData.data('courier_name')
        const courier_logo = servicesData.data('courier_logo')
        const courier_info = servicesData.data('courier_info')
        const dropoff = servicesData.data('dropoff')
        if (service_id === 'all' || service_id === 'cheapest') {
            dropoff_point_panel.css({display: 'none'})
            courier_display_panel.css({display: 'revert'})
            courier_display_name.val('All Couriers')
            courier_delivery_panel.css({display: 'none'})
        } else {
            if (dropoff == 'yes') {
                const dropoffParent = modalDialog.find(`#${service_id}`)
                const dropoffElements = dropoffParent.children().clone()
                dropoff_point.html(dropoffElements)
                dropoff_point_panel.css({display: 'revert'})
                if (jQuery('#dropoff_point').children('.selected').length === 0) {
                    jQuery(dropoff_point.children()[0]).addClass('selected').attr('selected', 'selected').siblings().removeClass('selected').removeAttr('selected')
                }
            }
            courier_service_img.attr('src', courier_logo)
            courier_display_panel.css({display: 'revert'})
            courier_delivery_panel.css({display: 'revert'})
            courier_display_name.val(courier_name)
            courier_delivery_days.val(courier_info)
        }
    }
    dropoff_point.on('change', function () {
        const dropoff_point_val = dropoff_point.val()
        jQuery(this).children(`[value="${dropoff_point_val}"]`).attr('selected', 'selected').siblings().removeAttr('selected')
        jQuery(this).children(`[value="${dropoff_point_val}"]`).addClass('selected').siblings().removeClass('selected')
    })
    charges.on('change', function () {
        const chargesVal = jQuery(this).val()
        jQuery(this).children(`[value="${chargesVal}"]`).addClass('selected').attr('selected', 'selected').siblings().removeAttr('selected').removeClass('selected')
        if (chargesVal == 4) {
            shipping_rate_option_panel.css({display: 'revert'})
            charges_value_panel.css({display: 'revert'})
        } else if (chargesVal == 1) {
            shipping_rate_option_panel.css({display: 'none'})
            charges_value_panel.css({display: 'revert'})
        } else {
            shipping_rate_option_panel.css({display: 'none'})
            charges_value_panel.css({display: 'none'})
        }
    })

    free_shipping.parent().on('click', function () {
        if (free_shipping.prop('checked')) {
            free_shipping_tab.css({display: 'revert'})
            free_shipping_value_panel.css({display: 'revert'})
            free_shipping_by.on('change', function () {
                jQuery(this).children(`[value="${free_shipping_by.val()}"]`).addClass('selected').siblings().removeClass('selected')
                free_shipping_text.html(free_shipping_by.children('.selected').text())
            })
        } else {
            free_shipping_tab.css({display: 'none'})
            free_shipping_value_panel.css({display: 'none'})
        }
    })
    modalClose.on('click', function () {
        modalDialog.fadeToggle()
        modalDialog.remove()
        jQuery('body').css({overflow: 'auto'})
    })
    saveCourierButton.on('click', function (e) {
        //e.preventDefault()
        const nonce = obj.easyparcel_nonce
        console.log(nonce)
        const zoneId = zone_id()
        if (zoneId === "") {
            return false;
        }
        const servicesVal = courier_services.children('.selected')
        if (servicesVal.data('dropoff') == 'yes') {
            if (courier_display_name.val() === '') {
                courier_display_name.focus()
                return false
            }
            if (courier_delivery_days.val() === '') {
                courier_delivery_days.focus()
                return false
            }
        }
        const chargesVal = charges.val()
        if (chargesVal == 4) {
            if (charges_value.val() === '') {
                charges_value.focus()
                return false
            }
        } else if (chargesVal == 1) {
            if (charges_value.val() === '') {
                charges_value.focus()
                return false
            }
        }
        if (free_shipping.prop('checked')) {
            if (free_shipping_value.val() === '') {
                free_shipping_value.focus()
                return false
            }
        }
        const ajaxCourierData = ep_validate_courier_data()
        jQuery.ajax({
            url: ajax_object.ajax_url,
            method: 'post',
            data: {
                action: 'easyparcel_ajax_save_courier_services',
                courier_data: ajaxCourierData,
                courier_setting: 'popup',
                nonce: nonce
            }, beforeSend: function () {
                saveCourierButton.attr('disabled', 'disabled')
                article.css({cursor: 'progress', opacity: .7})
            }, success: function (data) {
                console.log(data)
                const jsonData = JSON.parse(data)
                if (jsonData.status === true) {
                    saveCourierButton.css({backgroundColor: '#2271b1', borderColor: '#2271b1'})
                    //modalClose.click()
                    const successDiv = modalDialog.find('#save-courier-success-message')
                    if (successDiv.length > 0) {
                        successDiv.html(`${jsonData.message}`)
                    } else {
                        saveCourierButton.before(`<span id="save-courier-success-message" style="color: green; float: left; margin-right: 25px;">${jsonData.message}</span>`);
                    }
                    const courier_parent = jQuery('.wc-shipping-zone-methods.widefat').find(`[data-instance_id=${instance_id}]`)
                    const courier_title = courier_parent.find('.courier-name')
                    const addCourier = courier_parent.find('.add-courier')
                    if (courier_parent.length > 0) {
                        courier_parent.html(`<p class="courier-name" data-instance_id=${instance_id}>Courier Name: <strong>${jsonData.courier_name}</strong></p><p>Add/Edit Courier to <a class="courier-url" href="${ajax_object.admin_url}admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&courier_id=${jsonData.courier_id}">Click Here</a></p>`)
                    }
                } else {
                    saveCourierButton.css({backgroundColor: 'red', borderColor: 'red'})
                    saveCourierButton.before(`<span style="color: green; float: left">${jsonData.message}</span>`)
                    saveCourierButton.removeAttr('disabled')
                    article.css({cursor: 'progress', opacity: 1})
                    return false
                }
            }, complete: function () {
                console.log('completed')
                saveCourierButton.removeAttr('disabled')
                article.css({cursor: 'progress', opacity: 1})
                setTimeout(function () {
                    wc_shipping_setting()
                }, 500)
            }, error: function (xhr, status, error) {
                const successDiv = modalDialog.find('#save-courier-success-message')
                if (successDiv.length > 0) {
                    successDiv.html(`Courier didn't save for: ${error}`)
                } else {
                    saveCourierButton.before(`<span id="save-courier-success-message" style="color: red; float: left; margin-right: 25px;">Courier didn't save for: ${error}</span>`);
                }
                console.log(error)
                saveCourierButton.removeAttr('disabled')
                article.css({cursor: 'default', opacity: 1})
                return false
            }
        })
    })

    jQuery('.edit_courier').find('button').on('click', function (e) {
        //e.preventDefault()
        const button = jQuery(this)
        const courierTable = jQuery('#courier-setting-table')
        const formError = jQuery('.form-error')
        const zoneId = jQuery('#zone_id').val()
        const instance_id = jQuery('#instance_id').val()
        const courier_id = jQuery('#courier_id').val()
        const nonce = obj.easyparcel_nonce
        console.log(nonce)
        if (zoneId === "") {
            return false;
        }
        const servicesVal = courier_services.children('.selected')
        if (servicesVal.data('dropoff') == 'yes') {
            if (courier_display_name.val() === '') {
                courier_display_name.focus()
                return false
            }
            if (courier_delivery_days.val() === '') {
                courier_delivery_days.focus()
                return false
            }
        }
        const chargesVal = charges.val()
        if (chargesVal == 4) {
            if (charges_value.val() === '') {
                charges_value.focus()
                return false
            }
        } else if (chargesVal == 1) {
            if (charges_value.val() === '') {
                charges_value.focus()
                return false
            }
        }
        if (free_shipping.prop('checked')) {
            if (free_shipping_value.val() === '') {
                free_shipping_value.focus()
                return false
            }
        }
        const ajaxCourierData = ep_validate_courier_data()
        jQuery.ajax({
            url: ajax_object.ajax_url,
            method: 'post',
            data: {
                action: 'easyparcel_ajax_save_courier_services',
                courier_data: ajaxCourierData,
                courier_setting: 'edit_courier',
                id: courier_id,
                nonce: nonce
            }, beforeSend: () => {
                jQuery(this).attr('disabled', 'disabled')
                courierTable.css({cursor: 'progress', opacity: .7})
            }, success: (data) => {
                console.log(data)
                const jsonData = JSON.parse(data)
                if (jsonData.status === true) {
                    console.log('completed')
                    button.removeAttr('disabled')
                    courierTable.css({cursor: 'progress', opacity: 1})
                    button.css({backgroundColor: '#2271b1', borderColor: '#2271b1'})
                    window.location.href = jQuery('#redirect_url').val()
                } else {
                    button.css({backgroundColor: 'red', borderColor: 'red'})
                    formError.html(jsonData.message)
                    courierTable.css({cursor: 'progress', opacity: 1})
                }
            }, error: (xhr, status, error) => {
                formError.before(`Courier didn't save for: ${error}`)
                console.log(error)
                button.removeAttr('disabled')
                courierTable.css({cursor: 'default', opacity: 1})
                return false
            }
        })
    })
    jQuery('.setup_courier').find('button').on('click', function (e) {
        const button = jQuery(this)
        const courierTable = jQuery('#courier-setting-table')
        const formError = jQuery('.form-error')
        const zoneId = jQuery('#zone_id').val()
        const nonce = obj.easyparcel_nonce
        console.log(nonce)
        if (zoneId === "") {
            return false;
        }
        const servicesVal = courier_services.children('.selected')
        if (servicesVal.data('dropoff') == 'yes') {
            if (courier_display_name.val() === '') {
                courier_display_name.focus()
                return false
            }
            if (courier_delivery_days.val() === '') {
                courier_delivery_days.focus()
                return false
            }
        }
        const chargesVal = charges.val()
        if (chargesVal == 4) {
            if (charges_value.val() === '') {
                charges_value.focus()
                return false
            }
        } else if (chargesVal == 1) {
            if (charges_value.val() === '') {
                charges_value.focus()
                return false
            }
        }
        if (free_shipping.prop('checked')) {
            if (free_shipping_value.val() === '') {
                free_shipping_value.focus()
                return false
            }
        }
        const ajaxCourierData = ep_validate_courier_data()
        jQuery.ajax({
            url: ajax_object.ajax_url,
            method: 'post',
            data: {
                action: 'easyparcel_ajax_save_courier_services',
                courier_data: ajaxCourierData,
                courier_setting: 'setup_courier',
                nonce: nonce
            }, beforeSend: () => {
                jQuery(this).attr('disabled', 'disabled')
                courierTable.css({cursor: 'progress', opacity: .7})
            }, success: (data) => {
                console.log(data)
                const jsonData = JSON.parse(data)
                if (jsonData.status === true) {
                    console.log('completed')
                    button.removeAttr('disabled')
                    courierTable.css({cursor: 'progress', opacity: 1})
                    button.css({backgroundColor: '#2271b1', borderColor: '#2271b1'})
                    window.location.href = jQuery('#redirect_url').val()
                } else {
                    button.css({backgroundColor: 'red', borderColor: 'red'})
                    formError.html(jsonData.message)
                    courierTable.css({cursor: 'progress', opacity: 1})
                }
            }, error: (xhr, status, error) => {
                formError.before(`Courier didn't save for: ${error}`)
                console.log(error)
                button.removeAttr('disabled')
                courierTable.css({cursor: 'default', opacity: 1})
                return false
            }
        })
    })
}

function zone_id() {
    const currentUrl = window.location.href;
    const urlParams = new URLSearchParams(currentUrl);
    return urlParams.get('zone_id')
}

function instance_id(url) {
    const urlParams = new URLSearchParams(url);
    return urlParams.get('instance_id')
}

function shipping_page() {
    const currentUrl = window.location.href;
    const urlParams = new URLSearchParams(currentUrl);
    return !!(urlParams.get('zone_id') || urlParams.get('courier_id') && urlParams.get('tab') === 'shipping');
}

function shipping_form_change() {
    jQuery('#mainform').on('change', function () {
        setTimeout(function () {
            wc_shipping_setting()
        }, 500)
    })
}

function shipping_edit_button() {
    let saveButton = jQuery('.wc-shipping-zone-method-save')
    if (saveButton.prop('disabled')) {
        saveButton.css({backgroundColor: '#2271b1', borderColor: '#2271b1'})
        let editButtonInterval = setInterval(function () {
            let wcBackboneModalMain = jQuery('#wc-backbone-modal-dialog')
            if (wcBackboneModalMain.length > 0) {
                wcBackboneModalMain.css({display: 'block'})
                clearInterval(editButtonInterval)
                setTimeout(function () {
                    wcBackboneModalMain.find('footer').find('button').attr('onclick', 'wc_shipping_setting()')
                }, 300)
            } else {
                wcBackboneModalMain = jQuery('#wc-backbone-modal-dialog')
            }
        }, 200)
        setTimeout(function () {
            clearInterval(editButtonInterval)
        }, 10000)
    } else {
        jQuery('body').css({overflow: 'auto'})
        setTimeout(function () {
            let wcBackboneModalMain = jQuery('#wc-backbone-modal-dialog')
            wcBackboneModalMain.css({display: 'none'})
            wcBackboneModalMain.remove()
        }, 100)
        saveButton.focus()
        saveButton.css({backgroundColor: 'red', borderColor: 'red'})
        return false
    }
}

const woo_page = shipping_page()
if (woo_page === true) {
    let wcFormShippingSettingInterval = setInterval(function () {
        let courierTable = jQuery('#courier-setting-table')
        wcShippingZoneMethods = jQuery('.wc-shipping-zone-methods.widefat')
        wcShippingSetting = wcShippingZoneMethods.find('tbody.wc-shipping-zone-method-rows.ui-sortable')
        if (wcShippingSetting.length > 0) {
            jQuery('body').addClass('ep-shipping-method')
            wc_shipping_setting()
            shipping_form_change()
            save_courier()
            jQuery('#zone_name').on('change', function () {
                wc_shipping_setting()
            })
            wcShippingZoneMethods = jQuery('.wc-shipping-zone-methods.widefat')
            wcShippingZoneAddMethod = jQuery('.wc-shipping-zone-add-method')
            wcShippingZoneAddMethodSave = jQuery('.wc-shipping-zone-method-save')
            clearInterval(wcFormShippingSettingInterval)
            wcShippingZoneAddMethod.attr('onclick', 'add_shipping_method_button()').removeAttr('disabled')
        }
        if (courierTable.length > 0) {
            clearInterval(wcFormShippingSettingInterval)
            save_courier()
        }
    }, 300)
    setTimeout(function () {
        clearInterval(wcFormShippingSettingInterval)
    }, 20000)
}