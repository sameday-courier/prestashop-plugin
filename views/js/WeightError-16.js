function showWeightError(){
    $('.delivery_options').append('<div id="weightAlert" class="alert alert-danger">The products exceed the maximum allowed weight. Please contact vendor for tailored solution.</div>');
    $('button[name="processCarrier"]').prop('disabled', true);
}
function hideWeightError(){
    $('#weightAlert').remove();
    $('button[name="processCarrier"]').prop('disabled', false);
}