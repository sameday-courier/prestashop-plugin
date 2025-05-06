$(document).ready(function(){
    const ajaxUrl = document.getElementById('nomenclatorAJaxUrl').value;
    $('#nomenclatorOptionsImport').on('click', function(){
        $.ajax({
            type: "GET",
            url: ajaxUrl,
            data: {
                action: 'nomenclatorImportCities',
            },
            success: function (response) {
                console.log(response);
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error);
            }
        });
    });

    $('#nomenclatorOptionsDrop').on('click', function(){
        $.ajax({
            type: "GET",
            url: ajaxUrl,
            data: {
                action: 'nomenclatorDropCities'
            },
            success: function(response){
                console.log(response);
            },
            error: function(xhr, status, error){
                console.error('AJAX Error:', error);
            }
        })
    });
});