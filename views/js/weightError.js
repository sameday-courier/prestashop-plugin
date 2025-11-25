function showWeightError(){
    $('.notifications-container.container').html('<div class="alert alert-danger">The products exceed the maximum allowed weight. Please contact vendor for tailored solution.</div>');
    $('button[name="confirmDeliveryOption"]').prop('disabled', true);
}
function hideWeightError(){
    $('.notifications-container.container').html("");
    $('button[name="confirmDeliveryOption"]').prop('disabled', false);
}